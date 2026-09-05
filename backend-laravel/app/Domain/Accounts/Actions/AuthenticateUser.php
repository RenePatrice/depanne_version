<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\User;
use App\Support\Telephone;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Connexion par téléphone et mot de passe (§4, ADR-0003).
 *
 * Pas d'OTP au login : le numéro est l'identifiant, le mot de passe est le
 * secret. Le verrouillage porte sur le couple **numéro + IP** — verrouiller
 * seulement l'IP punirait tout un cybercafé, verrouiller seulement le numéro
 * permettrait à un attaquant de bloquer le compte de quelqu'un d'autre.
 */
final class AuthenticateUser
{
    public const MAX_TENTATIVES = 5;

    public const FENETRE_SECONDES = 900;

    /** @throws DomainException */
    public function execute(string $telephoneSaisi, string $motDePasse, string $ip): User
    {
        $telephone = Telephone::normaliser($telephoneSaisi);

        if ($telephone === null) {
            throw new DomainException('Ce numéro de téléphone n\'est pas valide.');
        }

        $cle = self::cleVerrouillage($telephone, $ip);

        if (RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES)) {
            $secondes = RateLimiter::availableIn($cle);

            throw new DomainException(sprintf(
                'Trop de tentatives. Réessaie dans %d minute%s.',
                $minutes = max(1, (int) ceil($secondes / 60)),
                $minutes > 1 ? 's' : '',
            ));
        }

        /** @var User|null $utilisateur */
        $utilisateur = User::query()->where('phone', $telephone)->first();

        // Le hachage est calculé même quand le compte n'existe pas : sans cela,
        // le temps de réponse dirait à un attaquant quels numéros sont inscrits.
        // Le hachage factice est calculé une fois par processus, puis réutilisé.
        $reference = $utilisateur !== null ? $utilisateur->password : self::hachageFactice();
        $motDePasseValide = Hash::check($motDePasse, $reference);

        if ($utilisateur === null || ! $motDePasseValide) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            throw new DomainException('Numéro ou mot de passe incorrect.');
        }

        if (! $utilisateur->isActive()) {
            RateLimiter::hit($cle, self::FENETRE_SECONDES);

            throw new DomainException(
                'Ce compte est suspendu. Contacte le support depuis le numéro affiché dans l\'application.'
            );
        }

        RateLimiter::clear($cle);

        $utilisateur->forceFill(['last_login_at' => now()])->save();

        return $utilisateur;
    }

    /** Hachage jetable, de même coût qu'un vrai, pour égaliser les temps de réponse. */
    private static function hachageFactice(): string
    {
        static $factice = null;

        return $factice ??= Hash::make(Str::random(32));
    }

    public static function cleVerrouillage(string $telephoneE164, string $ip): string
    {
        return 'connexion-mobile:'.$telephoneE164.'|'.$ip;
    }
}
