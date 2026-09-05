<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Settings\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Valeurs par défaut des paramètres pilotables depuis le back-office.
 * Tout ce qui est ici serait, sinon, codé en dur quelque part — ce que le
 * cahier des charges interdit explicitement (§8.2, §8.3).
 */
final class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::settings() as $setting) {
            AppSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => ['v' => $setting['value']],
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'description' => $setting['description'] ?? null,
                    'type' => $setting['type'],
                ],
            );
        }

        AppSetting::flushCache();
    }

    /** @return array<int, array<string, mixed>> */
    private static function settings(): array
    {
        return [
            [
                'key' => AppSetting::COMMISSION_RATE,
                'value' => 0.10,
                'group' => 'finances',
                'type' => 'decimal',
                'label' => 'Taux de commission de la plateforme',
                'description' => '0,10 = 10 %. Sur 100 000 GNF : 10 000 pour la plateforme, 90 000 pour le technicien.',
            ],
            [
                'key' => AppSetting::WITHDRAWAL_MIN_GNF,
                'value' => 50_000,
                'group' => 'finances',
                'type' => 'integer',
                'label' => 'Montant minimum de retrait',
                'description' => 'En dessous de ce solde, le technicien ne peut pas demander de versement.',
            ],
            [
                'key' => AppSetting::ESCROW_AUTO_RELEASE_HOURS,
                'value' => 24,
                'group' => 'finances',
                'type' => 'integer',
                'label' => 'Libération automatique du séquestre',
                'description' => "Délai après la fin de l'intervention au terme duquel les fonds sont libérés sans validation du client.",
            ],
            [
                'key' => AppSetting::CANCELLATION_FEE_GNF,
                'value' => 20_000,
                'group' => 'finances',
                'type' => 'integer',
                'label' => "Frais d'annulation tardive",
                'description' => 'Facturés au client seulement si le technicien est déjà en route.',
            ],
            [
                'key' => AppSetting::TRAVEL_FEE_ROUNDING_GNF,
                'value' => 1_000,
                'group' => 'tarifs',
                'type' => 'integer',
                'label' => 'Arrondi des frais de déplacement',
                'description' => 'Les frais sont arrondis au multiple supérieur de cette valeur.',
            ],
            [
                'key' => AppSetting::SHORT_TRIP_UPLIFT_RATE,
                'value' => 0.01,
                'group' => 'tarifs',
                'type' => 'decimal',
                'label' => 'Majoration des interventions de proximité',
                'description' => '0,01 = 1 %. Appliquée quand le technicien est sous le seuil de kilomètres '
                    ."inclus de la zone : le déplacement n'est alors pas facturé, et cette majoration "
                    .'revient en totalité au technicien.',
            ],
            [
                'key' => AppSetting::HAVERSINE_ROAD_FACTOR,
                'value' => 1.3,
                'group' => 'tarifs',
                'type' => 'decimal',
                'label' => 'Facteur de repli pour la distance routière',
                'description' => "Appliqué à la distance à vol d'oiseau quand l'API de cartographie ne répond pas.",
            ],
            [
                'key' => AppSetting::MATCH_INITIAL_RADIUS_KM,
                'value' => 5,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Rayon de recherche initial',
            ],
            [
                'key' => AppSetting::MATCH_RADIUS_STEP_KM,
                'value' => 5,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Élargissement du rayon à chaque cycle',
            ],
            [
                'key' => AppSetting::MATCH_MAX_RADIUS_KM,
                'value' => 15,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Rayon de recherche maximum',
            ],
            [
                'key' => AppSetting::MATCH_RESPONSE_SECONDS,
                'value' => 45,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Délai de réponse du technicien',
                'description' => 'Passé ce délai sans réponse, la demande part au technicien suivant.',
            ],
            [
                'key' => AppSetting::MATCH_MAX_CYCLES,
                'value' => 3,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Nombre de cycles avant abandon',
                'description' => 'Au terme du dernier cycle, le ticket passe en SANS_REPONSE et le support est alerté.',
            ],
            [
                'key' => AppSetting::MATCH_CANDIDATES_PER_CYCLE,
                'value' => 10,
                'group' => 'matching',
                'type' => 'integer',
                'label' => 'Candidats évalués par cycle',
                'description' => 'Les dix meilleurs de la présélection géographique sont notés, puis sollicités un par un.',
            ],
            [
                'key' => AppSetting::SCORE_WEIGHTS,
                'value' => [
                    'proximity' => 0.40,
                    'rating' => 0.30,
                    'acceptance' => 0.20,
                    'cancellation' => -0.10,
                ],
                'group' => 'matching',
                'type' => 'json',
                'label' => 'Pondérations du score de matching',
                'description' => "score = 0,40 × proximité + 0,30 × note + 0,20 × taux d'acceptation − 0,10 × taux d'annulation.",
            ],
            [
                'key' => AppSetting::NEWCOMER_RATING,
                'value' => 4.0,
                'group' => 'matching',
                'type' => 'decimal',
                'label' => 'Note neutre des nouveaux techniciens',
                'description' => 'Sans elle, un technicien sans avis partirait à zéro et ne serait jamais sollicité.',
            ],
            [
                'key' => AppSetting::NEWCOMER_JOBS_THRESHOLD,
                'value' => 5,
                'group' => 'matching',
                'type' => 'integer',
                'label' => "Nombre d'interventions avant la note réelle",
            ],
            [
                'key' => AppSetting::NOTIF_TICKET_PUBLIE,
                'value' => 'Ta demande {reference} est publiée. On cherche un technicien près de chez toi.',
                'group' => 'notifications',
                'type' => 'string',
                'label' => 'Demande publiée',
                'description' => 'Variables disponibles : {reference}, {prestation}, {total}.',
            ],
            [
                'key' => AppSetting::NOTIF_TICKET_ACCEPTE,
                'value' => '{technicien} a accepté ta demande {reference}. Il arrive.',
                'group' => 'notifications',
                'type' => 'string',
                'label' => 'Demande acceptée',
                'description' => 'Variables disponibles : {reference}, {technicien}, {eta}.',
            ],
            [
                'key' => AppSetting::NOTIF_TECHNICIEN_ARRIVE,
                'value' => "{technicien} est arrivé à l'adresse indiquée.",
                'group' => 'notifications',
                'type' => 'string',
                'label' => 'Technicien sur place',
                'description' => 'Variables disponibles : {reference}, {technicien}.',
            ],
            [
                'key' => AppSetting::NOTIF_PAIEMENT_RECU,
                'value' => 'Paiement de {total} reçu pour {reference}. Merci !',
                'group' => 'notifications',
                'type' => 'string',
                'label' => 'Paiement reçu',
                'description' => 'Variables disponibles : {reference}, {total}, {moyen}.',
            ],
            [
                'key' => AppSetting::CGU,
                'value' => 'Dépanne-Moi met en relation des clients et des techniciens vérifiés à Conakry. '
                    .'Les prix affichés avant publication sont fermes. La plateforme prélève une commission '
                    ."sur chaque intervention réglée dans l'application. Tout échange doit rester dans "
                    ."l'application : c'est ce qui permet la garantie, le support et l'historique.",
                'group' => 'contenus',
                'type' => 'string',
                'label' => "Conditions générales d'utilisation",
                'description' => "Affichées à l'inscription et dans le profil de l'application mobile.",
            ],
            [
                'key' => AppSetting::POLITIQUE_CONFIDENTIALITE,
                'value' => "Les pièces d'identité des techniciens sont stockées dans un espace privé et ne sont "
                    ."consultables que par l'équipe de validation, via des liens à durée limitée. Les positions "
                    .'GPS ne sont collectées que pendant une intervention active.',
                'group' => 'contenus',
                'type' => 'string',
                'label' => 'Politique de confidentialité',
                'description' => "Affichée dans le profil de l'application mobile.",
            ],
            [
                'key' => AppSetting::DISPUTE_WINDOW_HOURS,
                'value' => 72,
                'group' => 'litiges',
                'type' => 'integer',
                'label' => 'Fenêtre de réclamation',
                'description' => "Délai après la fin de l'intervention pendant lequel le client peut ouvrir un litige.",
            ],
        ];
    }
}
