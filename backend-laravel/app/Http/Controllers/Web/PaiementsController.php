<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Models\AdminUser;
use App\Domain\Payments\Actions\ArbitratePayment;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use App\Domain\Tickets\Data\ActorType;
use App\Domain\Wallet\Services\CommissionLedger;
use App\Http\Controllers\Controller;
use App\Http\DataTables\PaiementsTable;
use App\Support\Exports\ExporteurTable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suivi des paiements (ADR-0047) : chaque paiement, direct ou via
 * l'application, avec ses confirmations et son journal. L'arbitrage d'un
 * paiement direct contesté vit dans `ArbitratePayment`.
 */
final class PaiementsController extends Controller
{
    public function __construct(private readonly PaiementsTable $table) {}

    public function index(CommissionLedger $grandLivre): View
    {
        $depuis = now()->subDays(30);

        $repartition = Payment::query()
            ->whereIn('status', [PaymentStatus::CONFIRMEE->value, PaymentStatus::LIBEREE->value, PaymentStatus::CAPTUREE->value])
            ->where('created_at', '>=', $depuis)
            ->selectRaw('method, channel, COUNT(*) AS nombre, COALESCE(SUM(amount_gnf), 0) AS montant')
            ->groupBy('method', 'channel')
            ->orderByDesc('montant')
            ->get();

        $soldes = DB::table('transactions')
            ->select('user_id', DB::raw('SUM(amount_gnf) AS solde'))
            ->groupBy('user_id');

        $dettes = DB::query()
            ->fromSub($soldes, 's')
            ->join('users', 'users.id', '=', 's.user_id')
            ->where('s.solde', '<', 0)
            ->orderBy('s.solde')
            ->limit(6)
            ->get(['s.user_id', 's.solde', 'users.full_name']);

        return view('paiements.index', [
            'filtres' => PaiementsTable::filtres(),
            'aArbitrer' => Payment::query()->where('status', PaymentStatus::CONTESTEE->value)->count(),
            'enAttente' => Payment::query()->where('status', PaymentStatus::ATTENTE_CONFIRMATION->value)->count(),
            'enSouffrance' => Payment::query()
                ->where('status', PaymentStatus::ATTENTE_CONFIRMATION->value)
                ->where('created_at', '<', now()->subHours(PaiementsTable::SOUFFRANCE_HEURES))
                ->count(),
            'repartition' => $repartition,
            'dettes' => $dettes,
            'totalDettes' => (int) -DB::query()->fromSub($soldes, 's')->where('s.solde', '<', 0)->sum('s.solde'),
            'plafond' => $grandLivre->plafond(),
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
            PaiementsTable::COLONNES_EXPORT,
            fn (Payment $paiement): array => $this->table->ligneExport($paiement),
            'paiements',
            'Paiements — Parema Connect',
        );
    }

    public function detail(Payment $paiement): View
    {
        $paiement->load([
            'ticket:id,reference,client_id,technician_id,service_id,total_gnf,commission_gnf,state,completed_at',
            'ticket.client:id,full_name,phone',
            'ticket.technician:id,full_name,phone',
            'ticket.service:id,name',
            'events',
        ]);

        $admins = AdminUser::query()
            ->whereIn('id', $paiement->events
                ->filter(static fn (PaymentEvent $e): bool => $e->actor_type === ActorType::ADMIN)
                ->pluck('actor_id')
                ->filter()
                ->all())
            ->pluck('full_name', 'id');

        return view('paiements.detail', [
            'paiement' => $paiement,
            'admins' => $admins,
            'arbitrable' => $paiement->estDirect()
                && in_array($paiement->status, [PaymentStatus::ATTENTE_CONFIRMATION, PaymentStatus::CONTESTEE], true),
            'autres' => Payment::query()
                ->where('ticket_id', $paiement->ticket_id)
                ->whereKeyNot($paiement->getKey())
                ->latest('id')
                ->get(['id', 'provider_ref', 'method', 'channel', 'status', 'created_at']),
        ]);
    }

    public function arbitrer(Request $request, Payment $paiement, ArbitratePayment $action): RedirectResponse
    {
        $donnees = $request->validate([
            'decision' => ['required', 'string', 'in:confirmer,annuler'],
            'motif' => ['required', 'string', 'min:'.ArbitratePayment::MOTIF_MIN, 'max:500'],
        ], attributes: ['decision' => 'décision', 'motif' => 'motif']);

        /** @var AdminUser $admin */
        $admin = $request->user('admin');

        try {
            $action->execute($paiement, $admin, $donnees['decision'], $donnees['motif']);
        } catch (DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()])->withInput();
        }

        return redirect()->route('paiements.detail', $paiement)->with('statut', $donnees['decision'] === 'confirmer'
            ? 'Paiement confirmé : commission inscrite, intervention clôturée, les deux parties sont prévenues.'
            : 'Paiement annulé : les deux parties sont prévenues et une nouvelle déclaration est possible.');
    }
}
