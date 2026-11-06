<?php

declare(strict_types=1);

namespace App\Domain\Site\Services;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Site\Models\SiteMembre;
use App\Domain\Tickets\Data\TicketState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Modèle de vue de la page d'accueil publique : textes (réglés en back-office),
 * configuration de téléchargement, équipe, métiers du catalogue et chiffres
 * réels de la plateforme. Le contrôleur ne fait que présenter.
 */
final class SiteVitrine
{
    /** @return array<string, mixed> */
    public function donnees(): array
    {
        return [
            'hero' => [
                'titre' => (string) AppSetting::get(
                    AppSetting::SITE_HERO_TITRE,
                    'Un technicien de confiance, à votre porte en quelques minutes.',
                ),
                'sousTitre' => (string) AppSetting::get(
                    AppSetting::SITE_HERO_SOUS_TITRE,
                    'Plomberie, électricité, maçonnerie et plus encore à Conakry. '
                    .'Comparez les techniciens vérifiés, choisissez votre prix, payez en toute sécurité.',
                ),
                'annonce' => (string) AppSetting::get(AppSetting::SITE_ANNONCE, ''),
            ],
            'telechargement' => $this->telechargement(),
            'contact' => [
                'email' => (string) AppSetting::get(AppSetting::SITE_CONTACT_EMAIL, 'contact@parema.gn'),
                'whatsapp' => (string) AppSetting::get(
                    AppSetting::SITE_CONTACT_WHATSAPP,
                    (string) config('depanne.whatsapp.support_number', ''),
                ),
            ],
            'statistiques' => $this->statistiques(),
            'metiers' => $this->metiers(),
            'equipe' => $this->equipe(),
        ];
    }

    /** @return array{actif: bool, apk_disponible: bool, playstore_disponible: bool, playstore_url: string} */
    public function telechargement(): array
    {
        $actif = (bool) AppSetting::get(AppSetting::SITE_TELECHARGEMENT_ACTIF, true);
        $apk = trim((string) AppSetting::get(AppSetting::SITE_APK_URL, ''));
        $playstore = trim((string) AppSetting::get(AppSetting::SITE_PLAYSTORE_URL, ''));

        return [
            'actif' => $actif,
            'apk_disponible' => $actif && $apk !== '',
            'playstore_disponible' => $actif && $playstore !== '',
            'playstore_url' => $playstore,
        ];
    }

    /** L'URL de l'APK configurée, ou `null`. Consommée par la route de téléchargement. */
    public function urlApk(): ?string
    {
        $apk = trim((string) AppSetting::get(AppSetting::SITE_APK_URL, ''));

        return $apk === '' ? null : $apk;
    }

    public function urlPlaystore(): ?string
    {
        $url = trim((string) AppSetting::get(AppSetting::SITE_PLAYSTORE_URL, ''));

        return $url === '' ? null : $url;
    }

    /**
     * Chiffres réels de la plateforme — ce que le site « relié au dashboard »
     * donne à voir au public. Jamais inventés : comptés en base.
     *
     * @return array<int, array{cle: string, valeur: int|float, suffixe: string, libelle: string, icone: string}>
     */
    public function statistiques(): array
    {
        $techniciens = (int) DB::table('technician_profiles')
            ->where('verification_status', VerificationStatus::VALIDE->value)
            ->count();

        $interventions = (int) DB::table('tickets')
            ->where('state', TicketState::CLOTUREE->value)
            ->count();

        $quartiers = (int) DB::table('zones')->where('is_active', true)->count();

        $note = (float) DB::table('reviews')->avg('rating');

        return [
            [
                'cle' => 'techniciens',
                'valeur' => $techniciens,
                'suffixe' => '',
                'libelle' => 'Techniciens vérifiés',
                'icone' => 'bi-patch-check',
            ],
            [
                'cle' => 'interventions',
                'valeur' => $interventions,
                'suffixe' => '',
                'libelle' => 'Interventions réalisées',
                'icone' => 'bi-tools',
            ],
            [
                'cle' => 'quartiers',
                'valeur' => $quartiers,
                'suffixe' => '',
                'libelle' => 'Quartiers couverts',
                'icone' => 'bi-geo-alt',
            ],
            [
                'cle' => 'note',
                'valeur' => $note > 0 ? round($note, 1) : 5.0,
                'suffixe' => '/5',
                'libelle' => 'Satisfaction client',
                'icone' => 'bi-star-fill',
            ],
        ];
    }

    /**
     * Métiers couverts, lus depuis le catalogue (donc configurables là-bas).
     *
     * @return array<int, array{nom: string, icone: string, couleur: string}>
     */
    public function metiers(): array
    {
        return DB::table('service_categories')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['name', 'icon', 'color'])
            ->map(static fn ($c): array => [
                'nom' => (string) $c->name,
                'icone' => (string) ($c->icon ?: 'bi-tools'),
                'couleur' => (string) ($c->color ?: '#1B6FF3'),
            ])
            ->all();
    }

    /** @return Collection<int, SiteMembre> */
    public function equipe(): Collection
    {
        return SiteMembre::query()
            ->where('actif', true)
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();
    }
}
