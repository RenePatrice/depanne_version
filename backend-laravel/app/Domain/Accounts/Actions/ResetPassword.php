<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\PasswordResetCode;
use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Contracts\SmsProvider;
use App\Support\Telephone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mot de passe oublié (§4) — **seul usage de l'OTP** dans le produit.
 *
 * Le code à six chiffres n'est jamais stocké en clair, expire en dix minutes et
 * tolère cinq essais. La demande ne dit jamais si le numéro est inscrit : le
 * message de confirmation est identique dans les deux cas, sans quoi
 * l'application deviendrait un outil pour savoir qui utilise Dépanne-Moi.
 */
final class ResetPassword
{
    public const MAX_DEMANDES = 3;

    public const FENETRE_DEMANDES = 900;

    public function __construct(private readonly SmsProvider $sms) {}

    /**
     * Envoie un code. Ne lève d'exception que si la demande est abusive —
     * jamais parce que le numéro est inconnu.
     *
     * @throws DomainException
     */
    public function demander(string $telephoneSaisi, string $ip): void
    {
        $telephone = Telephone::normaliser($telephoneSaisi);

        if ($telephone === null) {
            throw new DomainException('Ce numéro de téléphone n\'est pas valide.');
        }

        $cle = 'reinitialisation:'.$telephone.'|'.$ip;

        if (RateLimiter::tooManyAttempts($cle, self::MAX_DEMANDES)) {
            throw new DomainException(sprintf(
                'Trop de demandes. Réessaie dans %d minutes.',
                max(1, (int) ceil(RateLimiter::availableIn($cle) / 60)),
            ));
        }

        RateLimiter::hit($cle, self::FENETRE_DEMANDES);

        $utilisateur = User::query()->where('phone', $telephone)->first();

        if ($utilisateur === null) {
            // Silence volontaire : le contrôleur répondra la même chose.
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($telephone, $code): void {
            // Un nouveau code annule les précédents : deux codes valides en
            // même temps doubleraient la surface d'attaque.
            PasswordResetCode::query()
                ->where('phone', $telephone)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            PasswordResetCode::query()->create([
                'phone' => $telephone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(PasswordResetCode::LIFETIME_MINUTES),
                'created_at' => now(),
            ]);
        });

        $this->sms->envoyer(
            $telephone,
            sprintf(
                'Dépanne-Moi : ton code de réinitialisation est %s. Il expire dans %d minutes.',
                $code,
                PasswordResetCode::LIFETIME_MINUTES,
            ),
        );
    }

    /** @throws DomainException */
    public function reinitialiser(string $telephoneSaisi, string $code, string $nouveauMotDePasse): User
    {
        $telephone = Telephone::normaliser($telephoneSaisi);

        if ($telephone === null) {
            throw new DomainException('Ce numéro de téléphone n\'est pas valide.');
        }

        /** @var PasswordResetCode|null $demande */
        $demande = PasswordResetCode::query()
            ->where('phone', $telephone)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($demande === null || ! $demande->isUsable()) {
            throw new DomainException('Code expiré ou déjà utilisé. Demande un nouveau code.');
        }

        if (! Hash::check($code, $demande->code_hash)) {
            $demande->increment('attempts');

            $restants = PasswordResetCode::MAX_ATTEMPTS - $demande->attempts;

            throw new DomainException($restants > 0
                ? sprintf('Code incorrect. Il te reste %d essai%s.', $restants, $restants > 1 ? 's' : '')
                : 'Trop d\'essais. Demande un nouveau code.');
        }

        $utilisateur = User::query()->where('phone', $telephone)->first();

        if ($utilisateur === null) {
            throw new DomainException('Aucun compte ne correspond à ce numéro.');
        }

        return DB::transaction(function () use ($utilisateur, $demande, $nouveauMotDePasse): User {
            $utilisateur->forceFill(['password' => $nouveauMotDePasse])->save();

            $demande->forceFill(['consumed_at' => now()])->save();

            // Un mot de passe changé invalide toutes les sessions : si le
            // compte était compromis, l'intrus est éjecté.
            $utilisateur->tokens()->delete();
            $utilisateur->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            activity('authentification')
                ->performedOn($utilisateur)
                ->log('Mot de passe réinitialisé par code SMS');

            return $utilisateur;
        });
    }
}
