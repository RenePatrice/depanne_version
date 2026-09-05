<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Accounts\Actions\AuthenticateAdmin;
use App\Domain\Accounts\Models\AdminUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Adaptateur : il valide, appelle l'action du domaine, et redirige.
 * Aucune règle d'authentification n'est écrite ici.
 */
final class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.connexion');
    }

    public function store(LoginRequest $request, AuthenticateAdmin $authenticate): RedirectResponse
    {
        $authenticate->execute(
            $request,
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request->boolean('remember'),
        );

        return redirect()->intended(route('tableau-de-bord'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof AdminUser) {
            activity('authentification')->causedBy($admin)->log('Déconnexion du back-office');
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion')->with('statut', 'Tu es déconnecté.');
    }
}
