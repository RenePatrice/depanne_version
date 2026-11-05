<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Actions\ChangeAdminPassword;
use App\Domain\Accounts\Models\AdminUser;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Profil de l'administrateur connecté (#9).
 *
 * Accessible à tout compte du back-office, pas seulement aux ADMIN : chacun
 * doit pouvoir changer son propre mot de passe depuis le dashboard. Le mot de
 * passe actuel est exigé (cf. `ChangeAdminPassword`).
 */
final class MonProfilController extends Controller
{
    public function show(Request $request): View
    {
        return view('profil.index', ['admin' => $request->user('admin')]);
    }

    public function motDePasse(Request $request, ChangeAdminPassword $changer): RedirectResponse
    {
        $donnees = $request->validate([
            'actuel' => ['required', 'string'],
            'nouveau' => ['required', 'confirmed', Password::min(12)],
        ], attributes: ['actuel' => 'mot de passe actuel', 'nouveau' => 'nouveau mot de passe']);

        /** @var AdminUser $admin */
        $admin = $request->user('admin');

        try {
            $changer->execute($admin, $donnees['actuel'], $donnees['nouveau']);
        } catch (DomainException $e) {
            return back()->withErrors(['actuel' => $e->getMessage()]);
        }

        // Le mot de passe a changé : on régénère la session courante pour écarter
        // toute fixation, et on garde l'administrateur connecté sur ce poste.
        $request->session()->regenerate();

        return redirect()->route('profil')->with('statut', 'Mot de passe mis à jour.');
    }
}
