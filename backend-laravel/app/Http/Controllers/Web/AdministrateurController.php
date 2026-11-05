<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Actions\ManageAdminUsers;
use App\Domain\Accounts\Models\AdminUser;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Gestion des comptes du back-office (#9).
 *
 * Tout le module est derrière `permission:administrateurs.gerer`, que seul le
 * rôle ADMIN porte : SUPPORT et FINANCE ne voient ni le lien ni les routes. Le
 * contrôleur valide et présente ; la règle métier — dernier admin, auto-blocage
 * — vit dans l'action du domaine.
 */
final class AdministrateurController extends Controller
{
    public function index(): View
    {
        return view('administrateurs.index', [
            'administrateurs' => AdminUser::query()
                ->with('roles:id,name')
                ->orderBy('full_name')
                ->get(),
            'roles' => ManageAdminUsers::ROLES,
        ]);
    }

    public function store(Request $request, ManageAdminUsers $comptes): RedirectResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(ManageAdminUsers::ROLES)],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], attributes: ['full_name' => 'nom complet', 'password' => 'mot de passe']);

        try {
            $comptes->creer($donnees['email'], $donnees['full_name'], $donnees['role'], $donnees['password']);
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('administrateurs')->with('statut', 'Compte créé.');
    }

    public function update(Request $request, AdminUser $administrateur, ManageAdminUsers $comptes): RedirectResponse
    {
        $donnees = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(ManageAdminUsers::ROLES)],
        ], attributes: ['full_name' => 'nom complet']);

        try {
            $comptes->modifier($administrateur, $donnees['full_name'], $donnees['role'], $this->acteur($request));
        } catch (DomainException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('administrateurs')->with('statut', 'Compte mis à jour.');
    }

    public function motDePasse(Request $request, AdminUser $administrateur, ManageAdminUsers $comptes): RedirectResponse
    {
        $donnees = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)],
        ], attributes: ['password' => 'mot de passe']);

        $comptes->reinitialiserMotDePasse($administrateur, $donnees['password']);

        return redirect()->route('administrateurs')
            ->with('statut', 'Mot de passe réinitialisé. Les sessions du compte devront se reconnecter.');
    }

    public function statut(Request $request, AdminUser $administrateur, ManageAdminUsers $comptes): RedirectResponse
    {
        $donnees = $request->validate(['actif' => ['required', 'boolean']]);

        try {
            $comptes->definirStatut($administrateur, (bool) $donnees['actif'], $this->acteur($request));
        } catch (DomainException $e) {
            return back()->withErrors(['actif' => $e->getMessage()]);
        }

        return redirect()->route('administrateurs')->with('statut', 'Statut mis à jour.');
    }

    private function acteur(Request $request): AdminUser
    {
        /** @var AdminUser $acteur */
        $acteur = $request->user('admin');

        return $acteur;
    }
}
