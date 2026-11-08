<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Site;

use App\Domain\Site\Actions\EnregistrerEvenement;
use App\Domain\Site\Data\TypeEvenement;
use App\Domain\Site\Services\SiteVitrine;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Site vitrine public (plateforme web). Aucune authentification : c'est la
 * porte d'entrée grand public de Parema Connect.
 *
 * Les clics qui comptent (téléchargement, Play Store, « devenir technicien »,
 * contact) passent par une route serveur qui **enregistre puis redirige** :
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

    public function contact(Request $request): RedirectResponse
    {
        $this->enregistrer->execute(TypeEvenement::CLIC_CONTACT, $request);

        $contact = $this->vitrine->donnees()['contact'];
        $whatsapp = trim((string) $contact['whatsapp']);

        if ($whatsapp !== '') {
            return redirect()->away('https://wa.me/'.$whatsapp);
        }

        return redirect()->away('mailto:'.$contact['email']);
    }
}
