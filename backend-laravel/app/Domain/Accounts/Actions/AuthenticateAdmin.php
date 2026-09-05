<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\AdminUser;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Connexion d'un administrateur au back-office (§4).
 *
 * Toute la règle vit ici : verrouillage progressif, compte désactivé, trace
 * d'audit. Le contrôleur ne fait que valider la requête et présenter le résultat.
 */
final class AuthenticateAdmin
{
    /** Cinq tentatives par fenêtre de quinze minutes, par couple IP + email. */
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900;

    public function execute(Request $request, string $email, string $password, bool $remember = false): AdminUser
    {
        $key = self::throttleKey($request, $email);

        $this->guardAgainstBruteForce($key);

        if (! Auth::guard('admin')->attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            Event::dispatch(new Failed('admin', null, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        // Un compte désactivé ne doit pas garder de session ouverte.
        if (! $admin->is_active) {
            Auth::guard('admin')->logout();
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Ce compte a été désactivé. Contacte un administrateur.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        activity('authentification')
            ->causedBy($admin)
            ->withProperties(['ip' => $request->ip(), 'agent' => $request->userAgent()])
            ->log('Connexion au back-office');

        return $admin;
    }

    /**
     * Le verrouillage est progressif : au-delà du seuil, l'attente restante est
     * annoncée à la seconde près plutôt que de renvoyer un refus muet.
     */
    private function guardAgainstBruteForce(string $key): void
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans_choice(
                '{1}Trop de tentatives. Réessaie dans une seconde.|[2,*]Trop de tentatives. Réessaie dans :count secondes.',
                $seconds,
                ['count' => $seconds],
            ),
        ]);
    }

    public static function throttleKey(Request $request, string $email): string
    {
        return 'connexion:'.mb_strtolower($email).'|'.$request->ip();
    }
}
