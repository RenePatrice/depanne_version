<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Events\TechnicianPositionUpdated;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Geo;
use DomainException;

/**
 * Disponibilité et position du technicien (§7.3, §10).
 *
 * Ces deux informations décident de qui reçoit les courses : elles sont donc
 * traitées comme du domaine, pas comme un champ de profil. Deux règles s'y
 * appliquent.
 *
 * Un technicien **non validé ne peut pas se mettre en ligne**. Le contrôle
 * existe déjà dans la présélection, mais le refuser ici donne un message clair
 * plutôt qu'un silence : sans lui, l'intéressé se croirait disponible et
 * s'étonnerait de ne jamais rien recevoir.
 *
 * Un technicien **en intervention ne peut pas se mettre hors ligne**. Se
 * déconnecter en cours de route, c'est laisser un client sans nouvelle et sans
 * position sur la carte du support.
 */
final class UpdateTechnicianPresence
{
    /** @throws DomainException */
    public function definirDisponibilite(User $technicien, bool $enLigne): TechnicianProfile
    {
        $profil = $this->profil($technicien);

        if ($enLigne && $profil->verification_status !== VerificationStatus::VALIDE) {
            throw new DomainException(
                'Ton dossier n\'est pas encore validé : tu ne peux pas encore recevoir de demandes.'
            );
        }

        if (! $enLigne && $this->aUneInterventionEnCours($technicien)) {
            throw new DomainException(
                'Tu as une intervention en cours. Termine-la avant de passer hors ligne.'
            );
        }

        $profil->forceFill(['is_online' => $enLigne])->save();

        return $profil;
    }

    /**
     * Position remontée par l'application (§10 : toutes les 8 secondes pendant
     * une intervention, plus espacé le reste du temps).
     *
     * L'écriture est faite sans événement Eloquent et la diffusion part
     * directement : à ce rythme, tout ce qui n'est pas strictement nécessaire
     * coûte cher.
     */
    public function definirPosition(User $technicien, float $latitude, float $longitude): TechnicianProfile
    {
        $profil = $this->profil($technicien);

        $profil->forceFill([
            'last_known_location' => Geo::point($latitude, $longitude),
            'last_position_at' => now(),
        ])->save();

        $enCours = Ticket::query()
            ->where('technician_id', $technicien->getKey())
            ->active()
            ->first();

        TechnicianPositionUpdated::dispatch(
            (int) $technicien->getKey(),
            $technicien->full_name,
            $latitude,
            $longitude,
            $enCours !== null,
            $enCours?->reference,
        );

        return $profil;
    }

    private function aUneInterventionEnCours(User $technicien): bool
    {
        return Ticket::query()
            ->where('technician_id', $technicien->getKey())
            ->whereIn('state', array_map(
                static fn (TicketState $e): string => $e->value,
                array_filter(TicketState::cases(), static fn (TicketState $e): bool => $e->isActive()),
            ))
            ->exists();
    }

    /** @throws DomainException */
    private function profil(User $technicien): TechnicianProfile
    {
        $profil = $technicien->technicianProfile;

        if ($profil === null) {
            throw new DomainException('Tu n\'as pas encore de dossier technicien.');
        }

        return $profil;
    }
}
