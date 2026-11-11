<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Reporting\Data\Periode;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Site\Actions\EnregistrerContenuSite;
use App\Domain\Site\Actions\EnregistrerMembre;
use App\Domain\Site\Actions\EnvoyerMessageContact;
use App\Domain\Site\Models\SiteAbonne;
use App\Domain\Site\Models\SiteMembre;
use App\Domain\Site\Models\SiteMessage;
use App\Domain\Site\Services\SiteStatistiques;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pilotage du site vitrine depuis le back-office (#9, permission `site.gerer`,
 * réservée à l'ADMIN). Trois volets : fréquentation, contenu éditorial, équipe.
 */
final class SiteAdminController extends Controller
{
    public function index(Request $request, SiteStatistiques $stats): View
    {
        $periode = Periode::depuisCle($request->string('periode')->toString());

        return view('site-admin.index', [
            'periode' => $periode,
            'choixPeriodes' => Periode::choix(),
            'resume' => $stats->resume($periode),
            'serie' => $stats->serieParJour($periode),
            'contenu' => $this->contenuActuel(),
            'equipe' => SiteMembre::query()->orderBy('ordre')->orderBy('id')->get(),
            // Les messages à traiter d'abord, puis les plus récents.
            'messages' => SiteMessage::query()->orderByRaw('traite_at IS NOT NULL')->latest()->limit(30)->get(),
            'messagesATraiter' => SiteMessage::query()->whereNull('traite_at')->count(),
            'abonnes' => SiteAbonne::query()->latest()->limit(8)->get(),
            'totalAbonnes' => SiteAbonne::query()->count(),
        ]);
    }

    public function basculerMessage(SiteMessage $message, EnvoyerMessageContact $action): RedirectResponse
    {
        $action->basculerTraite($message);

        return redirect()->route('site-admin')->withFragment('messages')
            ->with('statut', $message->estTraite() ? 'Message marqué comme traité.' : 'Message remis à traiter.');
    }

    /**
     * Export CSV des abonnés, calculé côté serveur (ADR-0016) : la liste
     * complète, pas seulement les derniers affichés à l'écran.
     */
    public function exporterAbonnes(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $sortie = fopen('php://output', 'wb');

            if ($sortie === false) {
                return;
            }

            // BOM : Excel ouvre alors le fichier en UTF-8 sans casser les accents.
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['E-mail', 'Source', 'Inscrit le'], ';');

            SiteAbonne::query()->orderBy('id')->chunk(500, function ($lot) use ($sortie): void {
                foreach ($lot as $abonne) {
                    fputcsv($sortie, [
                        $abonne->email,
                        (string) $abonne->source,
                        $abonne->created_at?->format('d/m/Y H:i') ?? '',
                    ], ';');
                }
            });

            fclose($sortie);
        }, 'abonnes-newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function enregistrerContenu(Request $request, EnregistrerContenuSite $action): RedirectResponse
    {
        $valeurs = $request->validate([
            AppSetting::SITE_HERO_TITRE => ['required', 'string', 'max:160'],
            AppSetting::SITE_HERO_SOUS_TITRE => ['required', 'string', 'max:400'],
            AppSetting::SITE_ANNONCE => ['nullable', 'string', 'max:200'],
            AppSetting::SITE_TELECHARGEMENT_ACTIF => ['sometimes', 'boolean'],
            AppSetting::SITE_APK_URL => ['nullable', 'url', 'max:2048'],
            AppSetting::SITE_PLAYSTORE_URL => ['nullable', 'url', 'max:2048'],
            AppSetting::SITE_CONTACT_EMAIL => ['required', 'email', 'max:160'],
            AppSetting::SITE_CONTACT_WHATSAPP => ['nullable', 'string', 'regex:/^[0-9]{6,20}$/'],
        ], [
            AppSetting::SITE_CONTACT_WHATSAPP.'.regex' => 'Le numéro WhatsApp doit être au format international, chiffres seuls (ex. 224620000000).',
        ]);

        $valeurs[AppSetting::SITE_TELECHARGEMENT_ACTIF] = $request->boolean(AppSetting::SITE_TELECHARGEMENT_ACTIF);

        $action->execute($valeurs, auth('admin')->id());

        return redirect()->route('site-admin')->with('statut', 'Contenu du site mis à jour.');
    }

    public function stockerMembre(Request $request, EnregistrerMembre $action): RedirectResponse
    {
        $donnees = $this->validerMembre($request);

        $action->execute($donnees, $request->file('photo'));

        return redirect()->route('site-admin')->with('statut', 'Membre ajouté à l\'équipe.');
    }

    public function modifierMembre(Request $request, SiteMembre $membre, EnregistrerMembre $action): RedirectResponse
    {
        $donnees = $this->validerMembre($request);

        $action->execute($donnees, $request->file('photo'), $membre);

        return redirect()->route('site-admin')->with('statut', 'Membre mis à jour.');
    }

    public function basculerMembre(SiteMembre $membre): RedirectResponse
    {
        $membre->update(['actif' => ! $membre->actif]);

        return redirect()->route('site-admin')
            ->with('statut', $membre->actif ? 'Membre affiché sur le site.' : 'Membre masqué du site.');
    }

    public function supprimerMembre(SiteMembre $membre): RedirectResponse
    {
        if ($membre->photo_path !== null && $membre->photo_path !== '') {
            Storage::disk('public')->delete($membre->photo_path);
        }

        $membre->delete();

        return redirect()->route('site-admin')->with('statut', 'Membre retiré de l\'équipe.');
    }

    /** @return array<string, mixed> */
    private function validerMembre(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'role' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:400'],
            'lien_url' => ['nullable', 'url', 'max:2048'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
            'actif' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
        ]);
    }

    /** @return array<string, mixed> */
    private function contenuActuel(): array
    {
        return [
            AppSetting::SITE_HERO_TITRE => AppSetting::get(AppSetting::SITE_HERO_TITRE, ''),
            AppSetting::SITE_HERO_SOUS_TITRE => AppSetting::get(AppSetting::SITE_HERO_SOUS_TITRE, ''),
            AppSetting::SITE_ANNONCE => AppSetting::get(AppSetting::SITE_ANNONCE, ''),
            AppSetting::SITE_TELECHARGEMENT_ACTIF => (bool) AppSetting::get(AppSetting::SITE_TELECHARGEMENT_ACTIF, true),
            AppSetting::SITE_APK_URL => AppSetting::get(AppSetting::SITE_APK_URL, ''),
            AppSetting::SITE_PLAYSTORE_URL => AppSetting::get(AppSetting::SITE_PLAYSTORE_URL, ''),
            AppSetting::SITE_CONTACT_EMAIL => AppSetting::get(AppSetting::SITE_CONTACT_EMAIL, ''),
            AppSetting::SITE_CONTACT_WHATSAPP => AppSetting::get(AppSetting::SITE_CONTACT_WHATSAPP, ''),
        ];
    }
}
