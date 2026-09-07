<?php

declare(strict_types=1);

namespace App\Domain\Tickets\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Notifications\Actions\SendNotification;
use App\Domain\Notifications\Data\NotificationType;
use App\Domain\Pricing\Data\Devis;
use App\Domain\Pricing\Services\PricingService;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le technicien constate sur place qu'il faut davantage (§8.1).
 *
 * C'est le seul endroit où un montant peut augmenter après l'acceptation, et
 * c'est pour cela qu'il est encadré :
 *
 * - le technicien **propose**, il ne décide pas. Le ticket passe en
 *   DIAGNOSTIC_EN_ATTENTE et rien ne bouge tant que le client n'a pas répondu ;
 * - le supplément est **plafonné** en proportion de la prestation. Sans borne,
 *   un technicien pourrait doubler la facture une fois chez le client, qui
 *   accepterait par gêne plutôt que par accord ;
 * - le diagnostic écrit est **obligatoire**. Un supplément sans explication
 *   est un litige à venir, et le support n'aurait rien à examiner.
 *
 * Le déplacement et la majoration de proximité ne sont pas recalculés : ils ont
 * été figés à l'acceptation. Seul le supplément s'ajoute.
 */
final class ProposeDiagnostic
{
    /**
     * Un supplément ne peut pas dépasser le double de la prestation.
     *
     * La borne est volontairement large — une pièce à changer coûte parfois
     * plus cher que la main-d'œuvre — mais elle existe : elle transforme une
     * dérive en refus explicite plutôt qu'en négociation sur le pas de la
     * porte.
     */
    public const MULTIPLE_MAX = 2;

    public function __construct(
        private readonly TransitionTicket $transition,
        private readonly PricingService $tarification,
        private readonly SendNotification $notifier,
    ) {}

    /**
     * @param  array<int, string>  $photos
     *
     * @throws DomainException
     */
    public function execute(
        Ticket $ticket,
        User $technicien,
        string $diagnostic,
        int $supplementGnf,
        array $photos = [],
    ): Ticket {
        if (! $ticket->estLeTechnicien($technicien)) {
            throw new DomainException('Cette intervention ne t\'est pas attribuée.');
        }

        if (! $ticket->state->peutAllerVers(TicketState::DIAGNOSTIC_EN_ATTENTE)) {
            throw new DomainException(
                'Un diagnostic ne se propose qu\'une fois sur place, avant d\'avoir commencé.'
            );
        }

        if ($supplementGnf <= 0) {
            throw new DomainException(
                'Un diagnostic sans supplément n\'a pas à être soumis au client : démarre l\'intervention.'
            );
        }

        $plafond = $ticket->base_price_gnf * self::MULTIPLE_MAX;

        if ($supplementGnf > $plafond) {
            throw new DomainException(sprintf(
                'Le supplément ne peut pas dépasser %s. Au-delà, passe par le support.',
                Money::format($plafond),
            ));
        }

        return DB::transaction(function () use (
            $ticket,
            $technicien,
            $diagnostic,
            $supplementGnf,
            $photos,
        ): Ticket {
            // Le supplément est **proposé**, pas appliqué : les colonnes de prix
            // ne bougent pas tant que le client n'a pas accepté. Les écrire ici
            // ferait apparaître un total que personne n'a validé.
            $ticket->forceFill([
                'diagnosis' => $diagnostic,
                'diagnosis_photos' => $photos === [] ? null : array_slice($photos, 0, 3),
                'proposed_extra_fee_gnf' => $supplementGnf,
            ])->save();

            $ticket = $this->transition->execute(
                $ticket,
                TicketState::DIAGNOSTIC_EN_ATTENTE,
                ActorType::TECHNICIEN,
                (int) $technicien->getKey(),
                ['supplement_propose_gnf' => $supplementGnf],
            );

            $devis = $this->tarification->avecSupplement($ticket, $supplementGnf);

            DB::afterCommit(function () use ($ticket, $technicien, $supplementGnf, $devis): void {
                $this->notifier->execute(
                    $ticket->client,
                    NotificationType::DIAGNOSTIC_PROPOSE,
                    [
                        'reference' => $ticket->reference,
                        'technicien' => $technicien->full_name,
                        'supplement' => Money::format($supplementGnf),
                        'total' => Money::format($devis->totalGnf),
                    ],
                    [
                        'ticket_id' => $ticket->getKey(),
                        'supplement_gnf' => $supplementGnf,
                        'nouveau_total_gnf' => $devis->totalGnf,
                        'diagnostic' => $ticket->diagnosis,
                    ],
                );
            });

            return $ticket;
        });
    }

    /** Le devis qui résulterait d'un supplément, sans rien engager. */
    public function simuler(Ticket $ticket, int $supplementGnf): Devis
    {
        return $this->tarification->avecSupplement($ticket, $supplementGnf);
    }
}
