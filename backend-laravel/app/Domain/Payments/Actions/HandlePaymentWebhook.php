<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Payments\Data\NotificationPaiement;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Wallet\Jobs\ReleaseEscrowJob;
use App\Domain\Wallet\Services\EscrowService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traitement d'une notification d'opérateur (§8.4, étape 2).
 *
 * C'est le point d'entrée le plus exposé de l'application : une adresse
 * publique qui, mal gardée, transformerait n'importe qui en caissier. Quatre
 * protections, dans cet ordre :
 *
 * 1. La **signature** est vérifiée par le contrôleur avant d'arriver ici. Sans
 *    elle, tout le reste est décoratif.
 * 2. La **référence doit exister** en base. Une notification portant une
 *    référence inconnue est ignorée, pas créée : accepter une référence
 *    inventée reviendrait à accepter un paiement inventé.
 * 3. Un **verrou** par référence sérialise les notifications simultanées. Les
 *    opérateurs renvoient volontiers la même notification deux fois quand la
 *    première réponse tarde.
 * 4. L'**état du paiement** est vérifié dans la transaction : un paiement déjà
 *    capturé est laissé tel quel et la réponse reste un 200. Répondre en
 *    erreur ferait rejouer l'opérateur indéfiniment.
 *
 * Le montant est **recomparé** à celui attendu. Un écart n'est pas forcément
 * une fraude — un opérateur peut prélever ses frais — mais il ne doit jamais
 * passer inaperçu.
 */
final class HandlePaymentWebhook
{
    private const VERROU_SECONDES = 15;

    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly SendNotification $notifier,
        private readonly EscrowService $sequestre,
    ) {}

    /** @return array{traite: bool, motif: string} */
    public function execute(NotificationPaiement $notification): array
    {
        if ($notification->reference === '') {
            return ['traite' => false, 'motif' => 'Référence absente.'];
        }

        $verrou = Cache::lock('paiement:'.$notification->reference, self::VERROU_SECONDES);

        if (! $verrou->get()) {
            // Une autre instance traite déjà cette référence. Ce n'est pas une
            // erreur : c'est précisément ce que le verrou doit produire.
            return ['traite' => false, 'motif' => 'Notification déjà en cours de traitement.'];
        }

        try {
            return DB::transaction(fn (): array => $this->traiter($notification));
        } finally {
            $verrou->release();
        }
    }

    /** @return array{traite: bool, motif: string} */
    private function traiter(NotificationPaiement $notification): array
    {
        /** @var Payment|null $paiement */
        $paiement = Payment::query()
            ->where('provider_ref', $notification->reference)
            ->lockForUpdate()
            ->first();

        if ($paiement === null) {
            Log::warning('Webhook de paiement : référence inconnue.', [
                'reference' => $notification->reference,
            ]);

            return ['traite' => false, 'motif' => 'Référence inconnue.'];
        }

        // Idempotence : un paiement déjà tranché ne se rejoue pas. La charge
        // utile est tout de même conservée, elle peut différer et servir au
        // rapprochement.
        if ($paiement->status !== PaymentStatus::EN_ATTENTE) {
            $this->archiver($paiement, $notification, 'rejeu');

            return ['traite' => false, 'motif' => 'Paiement déjà '.mb_strtolower($paiement->status->label()).'.'];
        }

        if (! $notification->reussi) {
            $paiement->forceFill([
                'status' => PaymentStatus::ECHOUEE,
                'failure_reason' => mb_substr($notification->motifEchec ?? 'Paiement refusé.', 0, 300),
            ])->save();

            $this->archiver($paiement, $notification, 'echec');

            return ['traite' => true, 'motif' => 'Échec enregistré.'];
        }

        $this->verifierLeMontant($paiement, $notification);

        $paiement->forceFill([
            'status' => PaymentStatus::CAPTUREE,
            'captured_at' => now(),
        ])->save();

        $this->archiver($paiement, $notification, 'capture');

        $this->capturer($paiement);

        return ['traite' => true, 'motif' => 'Paiement capturé.'];
    }

    /**
     * Le ticket passe en PAYEE, les fonds entrent en séquestre, et la
     * libération automatique est planifiée.
     */
    private function capturer(Payment $paiement): void
    {
        $ticket = $paiement->ticket;

        if ($ticket->state->peutAllerVers(TicketState::PAYEE)) {
            $this->transition->execute(
                $ticket,
                TicketState::PAYEE,
                ActorType::SYSTEME,
                null,
                ['paiement' => $paiement->provider_ref, 'montant_gnf' => $paiement->amount_gnf],
            );
        }

        $heures = $this->sequestre->delaiLiberationHeures();

        // La libération part après le commit : planifiée dedans, elle pourrait
        // s'exécuter avant que la capture ne soit visible en base.
        DB::afterCommit(function () use ($paiement, $heures, $ticket): void {
            ReleaseEscrowJob::dispatch((int) $paiement->getKey())->delay(now()->addHours($heures));

            $this->notifier->execute(
                $ticket->client,
                NotificationType::PAIEMENT_RECU,
                [
                    'reference' => $ticket->reference,
                    'total' => Money::format($paiement->amount_gnf),
                    'moyen' => $paiement->method->label(),
                ],
                ['ticket_id' => $ticket->getKey(), 'paiement_ref' => $paiement->provider_ref],
            );
        });
    }

    /**
     * Un montant différent de celui attendu est tracé et signalé au support,
     * mais n'annule pas la capture : l'argent est déjà parti de chez le client,
     * et refuser la notification le laisserait débité sans intervention payée.
     */
    private function verifierLeMontant(Payment $paiement, NotificationPaiement $notification): void
    {
        if ($notification->montantGnf === null || $notification->montantGnf === $paiement->amount_gnf) {
            return;
        }

        Log::error('Webhook de paiement : montant inattendu.', [
            'reference' => $paiement->provider_ref,
            'attendu_gnf' => $paiement->amount_gnf,
            'recu_gnf' => $notification->montantGnf,
        ]);

        activity('finances')
            ->performedOn($paiement)
            ->withProperties([
                'attendu_gnf' => $paiement->amount_gnf,
                'recu_gnf' => $notification->montantGnf,
            ])
            ->log('Écart de montant sur le paiement '.$paiement->provider_ref);
    }

    /** Conserve la charge utile brute : c'est la seule preuve en cas de litige. */
    private function archiver(Payment $paiement, NotificationPaiement $notification, string $cle): void
    {
        $historique = $paiement->webhook_payload ?? [];
        $historique[$cle.'_'.now()->format('YmdHis')] = $notification->brut;

        $paiement->forceFill(['webhook_payload' => $historique])->save();
    }
}
