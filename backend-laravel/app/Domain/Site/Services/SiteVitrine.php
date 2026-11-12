<?php

declare(strict_types=1);

namespace App\Domain\Site\Services;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Catalog\Models\Service;
use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Reviews\Models\Review;
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
            ...$this->cadre(),
            'statistiques' => $this->statistiques(),
            'metiers' => $this->metiers(),
            'zones' => $this->zones(),
            'equipe' => $this->equipe(),
            'temoignages' => $this->temoignages(),
        ];
    }

    /**
     * Ce dont le gabarit du site a besoin sur TOUTES les pages (bandeau
     * d'annonce, bouton de téléchargement, coordonnées du pied de page), sans
     * les agrégations propres à l'accueil.
     *
     * @return array<string, mixed>
     */
    public function cadre(): array
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
        ];
    }

    /**
     * Fiche d'un métier pour sa page d'explication : tout vient du catalogue
     * du back-office (description, prestations, ce qui est inclus, durée, prix
     * indicatif) et du nombre réel de techniciens validés dans la spécialité.
     * `null` si le métier n'existe pas ou n'est plus actif.
     *
     * @return array{code: string, nom: string, description: string, icone: string, couleur: string, techniciens: int, prestations: array<int, array{nom: string, description: string, type: string, prix_gnf: int, duree_min: int, inclus: array<int, string>}>}|null
     */
    public function metier(string $code): ?array
    {
        $categorie = ServiceCategory::query()
            ->where('is_active', true)
            ->where('code', mb_strtoupper($code))
            ->first();

        if ($categorie === null) {
            return null;
        }

        $prestations = Service::query()
            ->where('category_id', $categorie->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (Service $service): array => [
                'nom' => (string) $service->name,
                'description' => (string) $service->description,
                'type' => $service->type->label(),
                'prix_gnf' => (int) $service->base_price_gnf,
                'duree_min' => (int) $service->estimated_duration_min,
                'inclus' => array_values(array_map(strval(...), (array) $service->included)),
            ])
            ->all();

        return [
            'code' => mb_strtolower($categorie->code->value),
            'nom' => (string) $categorie->name,
            'description' => (string) $categorie->description,
            'icone' => (string) ($categorie->icon ?: 'bi-tools'),
            'couleur' => (string) ($categorie->color ?: '#2F6BED'),
            'techniciens' => (int) DB::table('technician_profiles')
                ->where('verification_status', VerificationStatus::VALIDE->value)
                ->whereJsonContains('specialties', $categorie->code->value)
                ->count(),
            'prestations' => $prestations,
        ];
    }

    /**
     * Avis clients réels et récents pour la section « témoignages ». Jamais
     * inventés : lus en base, et seulement ceux qui portent un commentaire et
     * une bonne note. Seul le prénom du client apparaît (§7). La section
     * disparaît tant qu'aucun avis public n'existe.
     *
     * @return array<int, array{prenom: string, note: int, commentaire: string, metier: string}>
     */
    public function temoignages(): array
    {
        return Review::query()
            ->with(['client', 'ticket.service.category'])
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->where('rating', '>=', 4)
            ->latest()
            ->limit(6)
            ->get()
            ->map(static fn (Review $avis): array => [
                'prenom' => $avis->client?->firstName() ?? 'Client',
                'note' => (int) $avis->rating,
                'commentaire' => (string) $avis->comment,
                'metier' => (string) ($avis->ticket?->service?->category->name ?? ''),
            ])
            ->all();
    }

    /**
     * Communes de Conakry réellement couvertes (au moins un quartier actif).
     * Sert la section « couverture » du site : on travaille par quartier, mais
     * on annonce au public les communes où l'on est présent. Le nombre de
     * quartiers, lui, est donné par la carte des statistiques.
     *
     * @return array<int, string>
     */
    public function zones(): array
    {
        return DB::table('zones')
            ->where('is_active', true)
            ->whereNotNull('commune')
            ->distinct()
            ->orderBy('commune')
            ->pluck('commune')
            ->map(static fn ($c): string => (string) $c)
            ->all();
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
    /**
     * L'URL de l'APK, ou `null` si aucune n'est configurée OU si le
     * téléchargement est désactivé en back-office : l'interrupteur doit aussi
     * fermer l'adresse /telecharger tapée à la main, pas seulement griser les
     * boutons.
     */
    public function urlApk(): ?string
    {
        return $this->telechargement()['apk_disponible']
            ? trim((string) AppSetting::get(AppSetting::SITE_APK_URL, ''))
            : null;
    }

    public function urlPlaystore(): ?string
    {
        $telechargement = $this->telechargement();

        return $telechargement['playstore_disponible'] ? $telechargement['playstore_url'] : null;
    }

    /**
     * Chiffres réels de la plateforme — ce que le site « relié au dashboard »
     * donne à voir au public. Jamais inventés : comptés en base.
     *
     * @return array<int, array{cle: string, valeur: int|float, suffixe: string, libelle: string, icone: string, texte: string|null}>
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

        // La satisfaction n'est affichée que si de vrais avis existent. Sans
        // avis, on l'annonce comme telle — jamais un 5,0 inventé.
        $nombreAvis = (int) DB::table('reviews')->count();
        $note = $nombreAvis > 0 ? round((float) DB::table('reviews')->avg('rating'), 1) : 0.0;

        return [
            [
                'cle' => 'techniciens',
                'valeur' => $techniciens,
                'suffixe' => '',
                'libelle' => 'Techniciens vérifiés',
                'icone' => 'bi-patch-check',
                'texte' => null,
            ],
            [
                'cle' => 'interventions',
                'valeur' => $interventions,
                'suffixe' => '',
                'libelle' => 'Interventions réalisées',
                'icone' => 'bi-tools',
                'texte' => null,
            ],
            [
                'cle' => 'quartiers',
                'valeur' => $quartiers,
                'suffixe' => '',
                'libelle' => 'Quartiers couverts',
                'icone' => 'bi-geo-alt',
                'texte' => null,
            ],
            [
                'cle' => 'note',
                'valeur' => $note,
                'suffixe' => $nombreAvis > 0 ? '/5' : '',
                'libelle' => $nombreAvis > 0 ? 'Satisfaction client' : 'En attente d’avis',
                'icone' => 'bi-star-fill',
                'texte' => $nombreAvis > 0 ? null : '—',
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
            ->get(['code', 'name', 'description', 'icon', 'color'])
            ->map(static fn ($c): array => [
                'code' => mb_strtolower((string) $c->code),
                'description' => (string) $c->description,
                'nom' => (string) $c->name,
                'icone' => (string) ($c->icon ?: 'bi-tools'),
                'couleur' => (string) ($c->color ?: '#2F6BED'),
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
