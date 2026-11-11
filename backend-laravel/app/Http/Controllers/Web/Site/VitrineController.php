<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Site;

use App\Domain\Site\Actions\EnregistrerEvenement;
use App\Domain\Site\Actions\EnvoyerMessageContact;
use App\Domain\Site\Actions\InscrireNewsletter;
use App\Domain\Site\Data\TypeEvenement;
use App\Domain\Site\Models\SiteMessage;
use App\Domain\Site\Services\SiteVitrine;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Site vitrine public (plateforme web). Aucune authentification : c'est la
 * porte d'entrée grand public de Parema Connect.
 *
 * Les clics qui comptent (téléchargement, Play Store, « devenir technicien »,
 * WhatsApp) passent par une route serveur qui **enregistre puis redirige** :
 * la mesure ne dépend d'aucun JavaScript et reste fiable sur le réseau de
 * Conakry.
 */
final class VitrineController extends Controller
{
    public function __construct(
        private readonly SiteVitrine $vitrine,
        private readonly EnregistrerEvenement $enregistrer,
    ) {}

    public function accueil(Request $request): View
    {
        $this->enregistrer->execute(TypeEvenement::VISITE, $request);

        return view('site.accueil', $this->vitrine->donnees());
    }

    /**
     * Page d'explication d'un métier : description, prestations, ce qui est
     * inclus, prix indicatifs — le tout lu dans le catalogue du back-office.
     */
    public function metier(Request $request, string $code): View
    {
        $metier = $this->vitrine->metier($code);

        abort_if($metier === null, 404);

        $this->enregistrer->execute(TypeEvenement::VISITE, $request, ['metier' => $metier['code']]);

        return view('site.metier', [
            ...$this->vitrine->cadre(),
            'metier' => $metier,
            'metiers' => $this->vitrine->metiers(),
        ]);
    }

    public function contact(Request $request): View
    {
        $this->enregistrer->execute(TypeEvenement::VISITE, $request);

        return view('site.contact', [
            ...$this->vitrine->cadre(),
            'sujets' => SiteMessage::SUJETS,
        ]);
    }

    public function envoyerMessage(Request $request, EnvoyerMessageContact $action): RedirectResponse
    {
        // Pot de miel : un champ invisible qu'un humain ne remplit pas. Un
        // robot qui le remplit reçoit la même réponse de succès, sans que rien
        // ne soit enregistré — il n'apprend pas qu'il a été écarté.
        if ($request->filled('site_web')) {
            return redirect()->route('site.contact')->withFragment('formulaire')->with('contact_envoye', true);
        }

        $donnees = $request->validate([
            'nom' => ['required', 'string', 'min:2', 'max:120'],
            'telephone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\s-]{6,30}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'sujet' => ['required', Rule::in(array_keys(SiteMessage::SUJETS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'telephone.regex' => 'Indiquez un numéro de téléphone valide (chiffres, espaces, + autorisés).',
        ], [
            'nom' => 'nom',
            'telephone' => 'téléphone',
            'email' => 'adresse e-mail',
            'sujet' => 'sujet',
            'message' => 'message',
        ]);

        $action->execute($donnees);
        $this->enregistrer->execute(TypeEvenement::MESSAGE_CONTACT, $request, ['sujet' => $donnees['sujet']]);

        return redirect()->route('site.contact')->withFragment('formulaire')->with('contact_envoye', true);
    }

    public function newsletter(Request $request, InscrireNewsletter $action): RedirectResponse
    {
        $retour = fn (): RedirectResponse => redirect()->back()->withFragment('newsletter');

        if ($request->filled('site_web')) {
            return $retour()->with('newsletter_inscrit', true);
        }

        $donnees = $request->validateWithBag('newsletter', [
            'email' => ['required', 'email', 'max:160'],
        ], [], ['email' => 'adresse e-mail']);

        $action->execute($donnees['email'], $request->string('source')->toString() ?: null);
        $this->enregistrer->execute(TypeEvenement::INSCRIPTION_NEWSLETTER, $request);

        return $retour()->with('newsletter_inscrit', true);
    }

    public function telecharger(Request $request): RedirectResponse
    {
        $this->enregistrer->execute(TypeEvenement::CLIC_TELECHARGEMENT, $request);

        $url = $this->vitrine->urlApk();

        if ($url === null) {
            return redirect()->route('site.accueil')->withFragment('telecharger');
        }

        return redirect()->away($url);
    }

    public function playstore(Request $request): RedirectResponse
    {
        $this->enregistrer->execute(TypeEvenement::CLIC_PLAYSTORE, $request);

        $url = $this->vitrine->urlPlaystore();

        if ($url === null) {
            return redirect()->route('site.accueil')->withFragment('telecharger');
        }

        return redirect()->away($url);
    }

    public function devenirTechnicien(Request $request): RedirectResponse
    {
        $this->enregistrer->execute(TypeEvenement::CLIC_DEVENIR_TECHNICIEN, $request);

        // Le clic est mesuré en back-office, puis l'internaute arrive sur la
        // section qui explique concrètement comment rejoindre le réseau.
        return redirect()->route('site.accueil')->withFragment('devenir-technicien');
    }

    /** Contact direct : WhatsApp si un numéro est configuré, sinon e-mail. */
    public function whatsapp(Request $request): RedirectResponse
    {
        $this->enregistrer->execute(TypeEvenement::CLIC_CONTACT, $request);

        $contact = $this->vitrine->cadre()['contact'];
        $whatsapp = trim((string) $contact['whatsapp']);

        if ($whatsapp !== '') {
            return redirect()->away('https://wa.me/'.$whatsapp);
        }

        return redirect()->away('mailto:'.$contact['email']);
    }
}
