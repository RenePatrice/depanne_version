<?php

declare(strict_types=1);

namespace App\Domain\Tickets\States;

use App\Domain\Tickets\Data\TicketState;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\StateCaster;

/**
 * Cast des états de ticket, tolérant à l'énumération.
 *
 * Le cast fourni par le paquet accepte une classe d'état ou une chaîne, mais
 * pas un `TicketState`. Or l'énumération reste la façon naturelle de désigner
 * un état dans tout le projet : elle porte les libellés, la table des
 * transitions et les listes d'états. Écrire `$ticket->state = TicketState::PAYEE`
 * échouait sur une erreur de type opaque, à l'exécution seulement.
 *
 * Ce caster convertit l'énumération avant de laisser le paquet faire son
 * travail. Les trois écritures possibles — énumération, classe d'état, valeur
 * en base — donnent désormais le même résultat.
 */
final class TicketStatusCaster extends StateCaster
{
    public function __construct()
    {
        parent::__construct(TicketStatus::class);
    }

    /**
     * @param  Model  $model
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        return parent::set(
            $model,
            $key,
            $value instanceof TicketState ? $value->value : $value,
            $attributes,
        );
    }
}
