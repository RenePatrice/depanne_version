<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Domain\Accounts\Models\User;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeReason;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Tickets\Actions\TransitionTicket;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ouverture d'une réclamation depuis l'application (§8.1).
 *
 * Le ticket passe en LITIGE_OUVERT, ce qui **suspend la libération des fonds**
 * : c'est tout l'intérêt du séquestre. Un litige déposé après le versement
 * n'aurait plus de levier.
 *
 * La fenêtre est de 72 heures après la fin de l'intervention, paramétrable.
 * Passé ce délai, le support reste joignable — mais hors du mécanisme
 * automatique, parce qu'un litige ouvert trois semaines plus tard sur une
 * intervention déjà payée demande un arbitrage humain, pas un blocage
 * mécanique.
 *
 * Le back-office porte déjà la file de traitement et la résolution depuis B5 ;
 * cette action ne fait qu'ouvrir la porte côté mobile, en réutilisant les
 * mêmes modèles.
 */
final class OpenDispute
{
    public function __construct(private readonly TransitionTicket $transition) {}

    /**
     * @param  array<int, string>  $preuves  URL de photos ou de captures
     *
     * @throws DomainException
     */
    public function execute(
        Ticket $ticket,
        User $auteur,
        DisputeReason $motif,
        string $description,
        array $preuves = [],
    ): Dispute {
        $this->verifierParticipation($ticket, $auteur);
        $this->verifierFenetre($ticket);

        if ($ticket->disputes()->whereIn('status', [
            DisputeStatus::OUVERT->value,
            DisputeStatus::EN_COURS->value,
        ])->exists()) {
            throw new DomainException('Une réclamation est déjà en cours sur cette intervention.');
        }

        $priorite = $this->priorite($motif, $ticket);

        return DB::transaction(function () use ($ticket, $auteur, $motif, $description, $preuves, $priorite): Dispute {
            /** @var Dispute $litige */
            $litige = Dispute::query()->create([
                'reference' => $this->reference(),
                'ticket_id' => $ticket->getKey(),
                'opened_by' => $auteur->getKey(),
                'reason' => $motif->value,
                'description' => $description,
                'evidence' => $preuves === [] ? null : array_slice($preuves, 0, 5),
                'status' => DisputeStatus::OUVERT->value,
                'priority' => $priorite->value,
                'sla_due_at' => now()->addHours($this->delaiSlaHeures($priorite)),
            ]);

            // Le passage en LITIGE_OUVERT n'est possible que depuis TERMINEE ou
            // PAYEE. Depuis CLOTUREE, les fonds sont déjà partis : la
            // réclamation existe, mais le ticket ne change plus d'état.
            if ($ticket->state->peutAllerVers(TicketState::LITIGE_OUVERT)) {
                $this->transition->execute(
                    $ticket,
                    TicketState::LITIGE_OUVERT,
                    $auteur->getKey() === $ticket->client_id ? ActorType::CLIENT : ActorType::TECHNICIEN,
                    (int) $auteur->getKey(),
                    ['litige' => $litige->reference, 'motif' => $motif->value],
                );
            }

            activity('litiges')
                ->performedOn($litige)
                ->withProperties(['ticket' => $ticket->reference, 'motif' => $motif->value])
                ->log('Réclamation ouverte : '.$litige->reference);

            return $litige;
        });
    }

    /**
     * La priorité décide de la place dans la file du support (B5), triée par
     * échéance. Elle est déduite du motif et de l'enjeu, pas laissée au
     * déclarant : chacun estimerait son propre litige urgent.
     */
    private function priorite(DisputeReason $motif, Ticket $ticket): DisputePriority
    {
        return match (true) {
            $motif === DisputeReason::DEGAT_MATERIEL => DisputePriority::URGENTE,
            $motif === DisputeReason::COMPORTEMENT => DisputePriority::HAUTE,
            $motif === DisputeReason::SURFACTURATION && $ticket->total_gnf >= 500_000 => DisputePriority::HAUTE,
            $motif === DisputeReason::RETARD => DisputePriority::BASSE,
            default => DisputePriority::NORMALE,
        };
    }

    private function delaiSlaHeures(DisputePriority $priorite): int
    {
        return match ($priorite) {
            DisputePriority::URGENTE => 4,
            DisputePriority::HAUTE => 12,
            DisputePriority::NORMALE => 24,
            DisputePriority::BASSE => 48,
        };
    }

    /** @throws DomainException */
    private function verifierParticipation(Ticket $ticket, User $auteur): void
    {
        $moi = (int) $auteur->getKey();

        if ((int) $ticket->client_id !== $moi && (int) $ticket->technician_id !== $moi) {
            throw new DomainException('Cette intervention ne te concerne pas.');
        }
    }

    /** @throws DomainException */
    private function verifierFenetre(Ticket $ticket): void
    {
        if ($ticket->completed_at === null) {
            throw new DomainException(
                'Une réclamation ne peut être ouverte qu\'après la fin de l\'intervention.'
            );
        }

        $fenetre = (int) AppSetting::get(AppSetting::DISPUTE_WINDOW_HOURS, 72);

        if ($ticket->completed_at->addHours($fenetre)->isPast()) {
            throw new DomainException(sprintf(
                'Le délai de réclamation de %d heures est dépassé. Contacte le support depuis ton profil.',
                $fenetre,
            ));
        }
    }

    private function reference(): string
    {
        $numero = DB::selectOne("SELECT nextval('dispute_reference_seq') AS n");

        return sprintf('LIT-%s-%s', now()->format('Y'), str_pad((string) ($numero->n ?? 1), 4, '0', STR_PAD_LEFT));
    }
}
