<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Payments\Models\Payment;
use App\Domain\Reporting\Data\Periode;
use App\Domain\Reporting\Services\FinanceService;
use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Support\Exports\ExporteurTable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Commissions, montants dus et export comptable (§6).
 *
 * L'export porte sur les tickets **réglés** de la période : c'est la seule
 * assiette qui a un sens comptable. Un ticket annulé ou sans réponse n'a jamais
 * produit d'encaissement.
 */
final class FinanceController extends Controller
{
    public function __construct(private readonly FinanceService $finances) {}

    public function index(Request $request): View
    {
        $periode = Periode::depuisCle($request->string('periode')->toString());

        return view('finances.index', [
            'periode' => $periode,
            'choixPeriodes' => Periode::choix(),
            'synthese' => $this->finances->synthese($periode),
            'commissions' => $this->finances->commissionsParPeriode($periode),
            'montantsDus' => $this->finances->montantsDus(),
        ]);
    }

    public function export(Request $request, ExporteurTable $exporteur): Response
    {
        $format = (string) $request->string('format', 'xlsx');
        abort_unless(in_array($format, ExporteurTable::FORMATS, true), 422);

        $periode = Periode::depuisCle($request->string('periode')->toString());

        return $exporteur->repond(
            $format,
            $this->requeteComptable($periode),
            FinanceService::colonnesComptables(),
            fn (Ticket $ticket): array => $this->ligneComptable($ticket),
            'comptabilite_'.$periode->cle,
            'Export comptable — '.$periode->libelle,
        );
    }

    /** @return Builder<Ticket> */
    private function requeteComptable(Periode $periode): Builder
    {
        return Ticket::query()
            ->whereNotNull('commission_gnf')
            ->whereBetween('created_at', [$periode->debut, $periode->fin])
            ->with([
                'client:id,full_name',
                'technician:id,full_name',
                'service:id,name',
                'zone:id,name',
                'payments:id,ticket_id,method,provider_ref,status',
            ])
            ->orderBy('created_at');
    }

    /** @return array<string, string|int|null> */
    private function ligneComptable(Ticket $ticket): array
    {
        $paiement = $ticket->payments->first();

        return [
            'reference' => $ticket->reference,
            'date_cloture' => $ticket->closed_at?->format('Y-m-d H:i') ?? $ticket->paid_at?->format('Y-m-d H:i'),
            'client' => $ticket->client?->full_name,
            'technicien' => $ticket->technician?->full_name,
            'prestation' => $ticket->service?->name,
            'zone' => $ticket->zone?->name,
            'total_gnf' => $ticket->total_gnf,
            'commission_gnf' => $ticket->commission_gnf,
            'net_technicien_gnf' => $ticket->technician_net_gnf,
            'taux_commission' => $ticket->commission_rate !== null
                ? number_format($ticket->commission_rate * 100, 1, ',', ' ').' %'
                : null,
            'moyen_paiement' => $paiement instanceof Payment ? $paiement->method->label() : null,
            'reference_paiement' => $paiement instanceof Payment ? $paiement->provider_ref : null,
        ];
    }
}
