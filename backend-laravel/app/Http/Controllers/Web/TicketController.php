<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Models\ServiceCategory;
use App\Domain\Tickets\Actions\CancelTicketByAdmin;
use App\Domain\Tickets\Actions\ForceCloseTicket;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Zones\Models\Zone;
use App\Http\Controllers\Controller;
use App\Http\DataTables\TicketsTable;
use App\Support\Exports\ExporteurTable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supervision des tickets (§6). Le contrôleur filtre, présente et délègue :
 * toute modification d'un ticket passe par une action du domaine, jamais par
 * une écriture directe.
 */
final class TicketController extends Controller
{
    public function __construct(private readonly TicketsTable $table) {}

    public function index(): View
    {
        return view('tickets.index', [
            'etats' => TicketsTable::etatsFiltrables(),
            'categories' => ServiceCategory::query()->orderBy('sort_order')->get(['id', 'name']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        return $this->table->json($request);
    }

    public function export(Request $request, ExporteurTable $exporteur): Response
    {
        $format = (string) $request->string('format', 'csv');
        abort_unless(in_array($format, ExporteurTable::FORMATS, true), 422);

        return $exporteur->repond(
            $format,
            $this->table->requete($request),
            TicketsTable::COLONNES_EXPORT,
            fn (Ticket $ticket): array => $this->table->ligneExport($ticket),
            'tickets',
            'Tickets — Dépanne-Moi',
        );
    }

    public function detail(Ticket $ticket): View
    {
        // Le chargement paresseux est interdit hors production : tout ce que la
        // vue affiche est déclaré ici, explicitement.
        $ticket->load([
            'client:id,full_name,phone,email,status',
            'technician:id,full_name,phone',
            'technician.technicianProfile:user_id,rating_avg,jobs_completed,verification_status',
            'service:id,name,category_id,estimated_duration_min',
            'service.category:id,name,color',
            'zone:id,name,base_travel_fee_gnf,price_per_km_gnf,included_km',
            'events',
            'messages.sender:id,full_name',
            'payments',
            'transactions',
            'review',
            'disputes',
            'matchAttempts.technician:id,full_name',
        ]);

        return view('tickets.detail', ['ticket' => $ticket]);
    }

    public function annuler(Request $request, Ticket $ticket, CancelTicketByAdmin $annuler): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:300'],
            'impute_au_technicien' => ['sometimes', 'boolean'],
        ], attributes: ['motif' => 'motif d\'annulation']);

        try {
            $annuler->execute(
                $ticket,
                $donnees['motif'],
                $request->boolean('impute_au_technicien'),
                (int) $request->user('admin')?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return redirect()
            ->route('tickets.detail', $ticket)
            ->with('statut', 'Ticket '.$ticket->reference.' annulé.');
    }

    public function cloturer(Request $request, Ticket $ticket, ForceCloseTicket $cloturer): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:300'],
        ], attributes: ['motif' => 'motif de clôture']);

        try {
            $cloturer->execute($ticket, $donnees['motif'], (int) $request->user('admin')?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return redirect()
            ->route('tickets.detail', $ticket)
            ->with('statut', 'Ticket '.$ticket->reference.' clôturé.');
    }
}
