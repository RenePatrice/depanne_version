<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use App\Domain\Tickets\Data\TicketState;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Table des tickets, en mode serveur (§10).
 *
 * La même requête filtrée sert l'affichage et l'export : un export qui ne
 * refléterait pas les filtres à l'écran serait pire qu'inutile pour la
 * comptabilité.
 */
final class TicketsTable
{
    /** Colonnes exportées, dans l'ordre. */
    public const COLONNES_EXPORT = [
        'reference' => 'Référence',
        'date' => 'Date',
        'statut' => 'Statut',
        'client' => 'Client',
        'technicien' => 'Technicien',
        'prestation' => 'Prestation',
        'categorie' => 'Catégorie',
        'zone' => 'Zone',
        'prix_fixe_gnf' => 'Prix fixe (GNF)',
        'deplacement_gnf' => 'Déplacement (GNF)',
        'supplement_gnf' => 'Supplément (GNF)',
        'total_gnf' => 'Total (GNF)',
        'commission_gnf' => 'Commission (GNF)',
        'net_technicien_gnf' => 'Net technicien (GNF)',
    ];

    /** @return Builder<Ticket> */
    public function requete(Request $request): Builder
    {
        return Ticket::query()
            ->with(['client:id,full_name,phone', 'technician:id,full_name', 'service:id,name,category_id', 'service.category:id,name,color', 'zone:id,name'])
            ->when($request->filled('etat'), fn (Builder $q) => $q->where('tickets.state', $request->string('etat')))
            ->when($request->filled('categorie'), fn (Builder $q) => $q->whereHas(
                'service.category',
                fn ($c) => $c->where('service_categories.id', $request->integer('categorie')),
            ))
            ->when($request->filled('zone'), fn (Builder $q) => $q->where('tickets.zone_id', $request->integer('zone')))
            ->when($request->filled('du'), fn (Builder $q) => $q->whereDate('tickets.created_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn (Builder $q) => $q->whereDate('tickets.created_at', '<=', $request->date('au')));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->addColumn('statut', static fn (Ticket $t): string => view('composants.badge-etat', ['etat' => $t->state])->render())
            ->addColumn('client', static function (Ticket $t): string {
                $nom = $t->client?->full_name;

                return e($nom ?? '—');
            })
            ->addColumn('technicien', static function (Ticket $t): string {
                $nom = $t->technician?->full_name;

                return e($nom ?? '—');
            })
            ->addColumn('prestation', static function (Ticket $t): string {
                $nom = $t->service?->name;

                return e($nom ?? '—');
            })
            ->addColumn('zone', static function (Ticket $t): string {
                $nom = $t->zone?->name;

                return e($nom ?? '—');
            })
            ->addColumn('total', static fn (Ticket $t): string => '<span class="dm-amount">'.Money::format($t->total_gnf).'</span>')
            ->addColumn('actions', static fn (Ticket $t): string => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-secondary" title="Ouvrir le ticket %s">'
                .'<i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>',
                route('tickets.detail', $t->id),
                e($t->reference),
            ))
            ->editColumn('reference', static fn (Ticket $t): string => '<span class="font-monospace">'.e($t->reference).'</span>')
            ->editColumn('created_at', static fn (Ticket $t): string => $t->created_at?->translatedFormat('d/m/Y H:i') ?? '—')
            // La recherche globale porte sur ce que le support tape réellement :
            // une référence, un nom, un téléphone.
            ->filterColumn('client', static function (Builder $query, string $terme): void {
                $query->whereHas('client', fn ($c) => $c
                    ->where('full_name', 'ilike', "%{$terme}%")
                    ->orWhere('phone', 'ilike', "%{$terme}%"));
            })
            ->filterColumn('technicien', static function (Builder $query, string $terme): void {
                $query->whereHas('technician', fn ($c) => $c->where('full_name', 'ilike', "%{$terme}%"));
            })
            ->filterColumn('prestation', static function (Builder $query, string $terme): void {
                $query->whereHas('service', fn ($s) => $s->where('name', 'ilike', "%{$terme}%"));
            })
            ->filterColumn('zone', static fn (): null => null)
            ->filterColumn('total', static fn (): null => null)
            ->filterColumn('actions', static fn (): null => null)
            ->orderColumn('total', 'tickets.total_gnf $1')
            ->rawColumns(['reference', 'statut', 'total', 'actions'])
            ->toJson();
    }

    /**
     * Une ligne d'export. Les montants restent des entiers : un tableur ne doit
     * pas recevoir « 100 000 GNF » mais 100000, sinon il ne sait pas additionner.
     *
     * @return array<string, string|int|null>
     */
    public function ligneExport(Ticket $ticket): array
    {
        return [
            'reference' => $ticket->reference,
            'date' => $ticket->created_at?->format('Y-m-d H:i'),
            'statut' => $ticket->state->label(),
            'client' => $ticket->client?->full_name,
            'technicien' => $ticket->technician?->full_name,
            'prestation' => $ticket->service?->name,
            'categorie' => $ticket->service?->category?->name,
            'zone' => $ticket->zone?->name,
            'prix_fixe_gnf' => $ticket->base_price_gnf,
            'deplacement_gnf' => $ticket->travel_fee_gnf,
            'supplement_gnf' => $ticket->extra_fee_gnf,
            'total_gnf' => $ticket->total_gnf,
            'commission_gnf' => $ticket->commission_gnf,
            'net_technicien_gnf' => $ticket->technician_net_gnf,
        ];
    }

    /** @return array<int, array{valeur: string, libelle: string}> */
    public static function etatsFiltrables(): array
    {
        return array_map(
            static fn (TicketState $etat): array => ['valeur' => $etat->value, 'libelle' => $etat->label()],
            TicketState::cases(),
        );
    }
}
