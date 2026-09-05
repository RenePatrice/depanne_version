<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * État du ticket sous forme de classe (§8.1, ADR-0014).
 *
 * Le §8.1 impose « chaque statut est une classe, les transitions autorisées
 * sont déclarées, toute transition illégale lève une exception ». C'est ce que
 * ce paquet apporte : une transition interdite est refusée au niveau du
 * modèle, y compris si quelqu'un contourne un jour l'action du domaine.
 *
 * La table des transitions n'est **pas** recopiée ici : elle reste dans
 * TicketState, qui la porte depuis la phase A1, et `config()` la lit. Deux
 * copies de cette table finiraient par diverger, et la divergence se paierait
 * en tickets bloqués dans un état sans issue.
 *
 * Le nom de chaque classe (`$name`) est la valeur déjà stockée en base : le
 * passage aux classes d'état n'a demandé aucune migration.
 *
 * @extends State<Ticket>
 */
abstract class TicketStatus extends State
{
    public static string $name = '';

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): TicketStatusCaster
    {
        return new TicketStatusCaster;
    }

    public static function config(): StateConfig
    {
        $config = parent::config()->default(Brouillon::class);

        foreach (TicketState::cases() as $etat) {
            $config->registerState(self::classePour($etat));
        }

        foreach (TicketState::cases() as $depuis) {
            foreach ($depuis->transitionsPossibles() as $vers) {
                $config->allowTransition(self::classePour($depuis), self::classePour($vers));
            }
        }

        return $config;
    }

    /** L'énumération correspondante, qui porte les libellés et les couleurs. */
    public function etat(): TicketState
    {
        return TicketState::from(static::$name);
    }

    /** @return class-string<TicketStatus> */
    public static function classePour(TicketState $etat): string
    {
        return match ($etat) {
            TicketState::BROUILLON => Brouillon::class,
            TicketState::PUBLIEE => Publiee::class,
            TicketState::ACCEPTEE => Acceptee::class,
            TicketState::EN_ROUTE => EnRoute::class,
            TicketState::SUR_PLACE => SurPlace::class,
            TicketState::DIAGNOSTIC_EN_ATTENTE => DiagnosticEnAttente::class,
            TicketState::DIAGNOSTIC_VALIDE => DiagnosticValide::class,
            TicketState::DIAGNOSTIC_REFUSE => DiagnosticRefuse::class,
            TicketState::EN_COURS => EnCours::class,
            TicketState::TERMINEE => Terminee::class,
            TicketState::PAYEE => Payee::class,
            TicketState::CLOTUREE => Cloturee::class,
            TicketState::ANNULEE_CLIENT => AnnuleeClient::class,
            TicketState::ANNULEE_TECHNICIEN => AnnuleeTechnicien::class,
            TicketState::SANS_REPONSE => SansReponse::class,
            TicketState::LITIGE_OUVERT => LitigeOuvert::class,
        };
    }

    // ------------------------------------------------------------------------
    // Délégations : tout le back-office appelle déjà `$ticket->state->label()`
    // et consorts. Les garder ici évite de réécrire les vues et les tables.
    // ------------------------------------------------------------------------

    public function label(): string
    {
        return $this->etat()->label();
    }

    public function color(): string
    {
        return $this->etat()->color();
    }

    public function isActive(): bool
    {
        return $this->etat()->isActive();
    }

    public function isFinal(): bool
    {
        return $this->etat()->isFinal();
    }

    public function estAnnulable(): bool
    {
        return $this->etat()->estAnnulable();
    }

    public function peutAllerVers(TicketState $cible): bool
    {
        return $this->canTransitionTo(self::classePour($cible));
    }

    /**
     * Les états atteignables depuis celui-ci, sous forme d'énumération.
     *
     * @return array<int, TicketState>
     */
    public function suites(): array
    {
        return $this->etat()->transitionsPossibles();
    }

    public function __toString(): string
    {
        return static::$name;
    }
}
