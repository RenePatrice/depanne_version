<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Actions\SetUserStatus;
use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Models\User;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\DataTables\ClientsTable;
use App\Support\Exports\ExporteurTable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClientController extends Controller
{
    public function __construct(private readonly ClientsTable $table) {}

    public function index(): View
    {
        return view('clients.index', ['statuts' => ClientsTable::statutsFiltrables()]);
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
            ClientsTable::COLONNES_EXPORT,
            fn (User $client): array => $this->table->ligneExport($client),
            'clients',
            'Clients — Dépanne-Moi',
        );
    }

    public function detail(User $client): View
    {
        abort_unless($client->is_client, 404);

        $client->load(['clientProfile.defaultAddress', 'addresses']);

        return view('clients.detail', [
            'client' => $client,
            'tickets' => Ticket::query()
                ->where('client_id', $client->id)
                ->with(['service:id,name', 'technician:id,full_name'])
                ->latest('created_at')
                ->limit(20)
                ->get(),
            'depense' => (int) Ticket::query()
                ->where('client_id', $client->id)
                ->billable()
                ->sum('total_gnf'),
            'ticketsTotal' => Ticket::query()->where('client_id', $client->id)->count(),
            'remboursements' => Transaction::query()
                ->where('user_id', $client->id)
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function changerStatut(Request $request, User $client, SetUserStatus $action): RedirectResponse
    {
        abort_unless($client->is_client, 404);

        $donnees = $request->validate([
            'statut' => ['required', 'string', 'in:ACTIF,SUSPENDU'],
            'motif' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $action->execute(
                $client,
                UserStatus::from($donnees['statut']),
                $donnees['motif'] ?? null,
                (int) $request->user('admin')?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['statut' => $e->getMessage()]);
        }

        return redirect()
            ->route('clients.detail', $client)
            ->with('statut', $donnees['statut'] === 'SUSPENDU'
                ? 'Compte suspendu.'
                : 'Compte réactivé.');
    }
}
