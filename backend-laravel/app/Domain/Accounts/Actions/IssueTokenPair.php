<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\RefreshToken;
use App\Domain\Accounts\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Délivrance et rotation du couple de jetons (§4, ADR-0003).
 *
 * L'access token Sanctum vit 15 minutes ; le refresh token vit 30 jours, n'est
 * stocké que hashé, et **tourne à chaque usage**. Un jeton déjà échangé qui se
 * représente est un vol : toute la lignée est alors révoquée, ce qui déconnecte
 * l'attaquant en même temps que le porteur légitime — mieux vaut une
 * reconnexion qu'une session volée qui perdure.
 */
final class IssueTokenPair
{
    public const DUREE_REFRESH_JOURS = 30;

    /**
     * @return array{access_token: string, refresh_token: string, expire_dans: int, expire_le: string}
     */
    public function delivrer(User $utilisateur, ?string $appareil = null, ?string $ip = null): array
    {
        $minutes = (int) config('sanctum.expiration', 15);

        $acces = $utilisateur->createToken(
            $appareil ?? 'mobile',
            ['*'],
            now()->addMinutes($minutes),
        );

        $rafraichissement = $this->creerRefreshToken($utilisateur, $appareil, $ip);

        return [
            'access_token' => $acces->plainTextToken,
            'refresh_token' => $rafraichissement,
            'expire_dans' => $minutes * 60,
            'expire_le' => now()->addMinutes($minutes)->toIso8601String(),
        ];
    }

    /**
     * Échange un refresh token contre un couple neuf.
     *
     * @return array{access_token: string, refresh_token: string, expire_dans: int, expire_le: string}
     *
     * @throws DomainException si le jeton est inconnu, expiré, révoqué ou rejoué
     */
    public function rafraichir(string $jetonPresente, ?string $appareil = null, ?string $ip = null): array
    {
        $hash = RefreshToken::hash($jetonPresente);

        /** @var RefreshToken|null $jeton */
        $jeton = RefreshToken::query()->where('token_hash', $hash)->with('user')->first();

        if ($jeton === null) {
            throw new DomainException('Session expirée. Reconnecte-toi.');
        }

        // Rejeu : ce jeton a déjà servi à en obtenir un autre.
        if ($jeton->replaced_by_id !== null) {
            $this->revoquerLignee($jeton);

            throw new DomainException(
                'Session invalidée pour raison de sécurité. Reconnecte-toi.'
            );
        }

        if (! $jeton->isUsable()) {
            throw new DomainException('Session expirée. Reconnecte-toi.');
        }

        $utilisateur = $jeton->user;

        if ($utilisateur === null || ! $utilisateur->isActive()) {
            throw new DomainException('Ce compte n\'est plus actif.');
        }

        return DB::transaction(function () use ($jeton, $utilisateur, $appareil, $ip): array {
            // L'ancien access token n'a plus lieu d'être : une rotation, c'est
            // une session neuve.
            $utilisateur->tokens()->delete();

            $nouveau = $this->creerRefreshToken($utilisateur, $appareil ?? $jeton->device_name, $ip);

            $jeton->forceFill([
                'last_used_at' => now(),
                'revoked_at' => now(),
                'replaced_by_id' => RefreshToken::query()
                    ->where('token_hash', RefreshToken::hash($nouveau))
                    ->value('id'),
            ])->save();

            $minutes = (int) config('sanctum.expiration', 15);

            $acces = $utilisateur->createToken(
                $appareil ?? $jeton->device_name ?? 'mobile',
                ['*'],
                now()->addMinutes($minutes),
            );

            return [
                'access_token' => $acces->plainTextToken,
                'refresh_token' => $nouveau,
                'expire_dans' => $minutes * 60,
                'expire_le' => now()->addMinutes($minutes)->toIso8601String(),
            ];
        });
    }

    /** Déconnexion : l'access token courant et le refresh token présenté tombent. */
    public function revoquer(User $utilisateur, ?string $jetonPresente = null): void
    {
        $utilisateur->tokens()->delete();

        if ($jetonPresente !== null) {
            RefreshToken::query()
                ->where('token_hash', RefreshToken::hash($jetonPresente))
                ->update(['revoked_at' => now()]);

            return;
        }

        RefreshToken::query()
            ->where('user_id', $utilisateur->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Le jeton en clair n'existe qu'ici et dans la réponse HTTP. */
    private function creerRefreshToken(User $utilisateur, ?string $appareil, ?string $ip): string
    {
        $clair = Str::random(64);

        RefreshToken::query()->create([
            'user_id' => $utilisateur->id,
            'token_hash' => RefreshToken::hash($clair),
            'device_name' => $appareil,
            'ip_address' => $ip,
            'expires_at' => now()->addDays(self::DUREE_REFRESH_JOURS),
        ]);

        return $clair;
    }

    /**
     * Révoque toute la chaîne de rotation à laquelle appartient ce jeton.
     * Un rejeu signifie qu'une copie circule : on coupe tout.
     */
    private function revoquerLignee(RefreshToken $jeton): void
    {
        RefreshToken::query()
            ->where('user_id', $jeton->user_id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $jeton->user?->tokens()->delete();

        activity('authentification')
            ->withProperties(['user_id' => $jeton->user_id, 'refresh_token_id' => $jeton->id])
            ->log('Rejeu de jeton de rafraîchissement : toutes les sessions ont été révoquées');
    }
}
