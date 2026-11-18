<?php

declare(strict_types=1);

namespace App\Http\DataTables;

use App\Domain\Payments\Data\PaymentChannel;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Payments\Data\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Support\Money;
use App\Support\OpaqueId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Tous les paiements, directs ou via l'application (ADR-0047).
 *
 * Les deux confirmations — client et technicien — sont visibles sur chaque
 * ligne : c'est ce que le support regarde d'abord quand un paiement direct
 * traîne. La même requête filtrée sert l'écran et l'export (ADR-0016).
 */
final class PaiementsTable
{
    /** Au-delà, un paiement direct sans réponse est « en souffrance ». */
    public const SOUFFRANCE_HEURES = 24;

    public const COLONNES_EXPORT = [
        'reference' => 'Référence',
        'cree_le' => 'Créé le',
        'ticket' => 'Intervention',
        'client' => 'Client',
        'technicien' => 'Technicien',
        'canal' => 'Canal',
        'moyen' => 'Moyen',
        'montant_gnf' => 'Montant (GNF)',
        'statut' => 'Statut',
        'declare_par' => 'Déclaré par',
        'confirme_client_le' => 'Confirmé par le client le',
        'confirme_technicien_le' => 'Confirmé par le technicien le',
        'reference_transaction' => 'Référence opérateur',
        'conteste_le' => 'Contesté le',
        'motif_contestation' => 'Motif de contestation',
    ];

    /** @return Builder<Payment> */
    public function requete(Request $request): Builder
    {
        return Payment::query()
            ->join('tickets', 'tickets.id', '=', 'payments.ticket_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'tickets.client_id')
            ->leftJoin('users as techniciens', 'techniciens.id', '=', 'tickets.technician_id')
            ->select([
                'payments.id', 'payments.provider_ref', 'payments.ticket_id', 'payments.channel',
                'payments.method', 'payments.amount_gnf', 'payments.status', 'payments.declared_by',
                'payments.transaction_ref', 'payments.client_confirmed_at', 'payments.technician_confirmed_at',
                'payments.contested_at', 'payments.contest_reason', 'payments.created_at',
                'tickets.reference as ticket_reference',
                'clients.full_name as client_nom',
                'techniciens.full_name as technicien_nom',
            ])
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('payments.status', $request->string('statut')))
            ->when($request->filled('moyen'), fn (Builder $q) => $q->where('payments.method', $request->string('moyen')))
            ->when($request->filled('canal'), fn (Builder $q) => $q->where('payments.channel', $request->string('canal')))
            ->when($request->filled('du'), fn (Builder $q) => $q->whereDate('payments.created_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn (Builder $q) => $q->whereDate('payments.created_at', '<=', $request->date('au')))
            ->when($request->boolean('a_traiter'), fn (Builder $q) => $q->where(function (Builder $q): void {
                $q->where('payments.status', PaymentStatus::CONTESTEE->value)
                    ->orWhere(function (Builder $q): void {
                        $q->where('payments.status', PaymentStatus::ATTENTE_CONFIRMATION->value)
                            ->where('payments.created_at', '<', now()->subHours(self::SOUFFRANCE_HEURES));
                    });
            }));
    }

    public function json(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->requete($request))
            ->editColumn('provider_ref', static fn (Payment $p): string => '<span class="font-monospace small">'.e($p->provider_ref).'</span>')
            ->editColumn('created_at', static fn (Payment $p): string => (string) $p->created_at?->translatedFormat('d/m/Y H:i'))
            ->addColumn('ticket', static fn (Payment $p): string => sprintf(
                '<a href="%s" class="font-monospace small">%s</a>',
                route('tickets.detail', OpaqueId::encode($p->ticket_id)),
                e((string) $p->getAttribute('ticket_reference')),
            ))
            ->addColumn('client', static fn (Payment $p): string => e((string) $p->getAttribute('client_nom')))
            ->addColumn('technicien', static fn (Payment $p): string => e((string) $p->getAttribute('technicien_nom')))
            ->addColumn('moyen', static fn (Payment $p): string => sprintf(
                '%s<span class="d-block text-body-secondary" style="font-size:.7rem">%s</span>',
                e($p->method->label()),
                e($p->channel->label()),
            ))
            ->addColumn('montant', static fn (Payment $p): string => '<span class="dm-amount">'.Money::format($p->amount_gnf).'</span>')
            ->addColumn('confirmations', static fn (Payment $p): string => self::confirmations($p))
            ->addColumn('statut', static function (Payment $p): string {
                $badge = sprintf('<span class="dm-badge dm-badge--%s">%s</span>', $p->status->color(), e($p->status->label()));

                if ($p->status === PaymentStatus::ATTENTE_CONFIRMATION
                    && $p->created_at !== null
                    && $p->created_at->lt(now()->subHours(self::SOUFFRANCE_HEURES))) {
                    $badge .= '<span class="d-block small text-danger mt-1"><i class="bi bi-hourglass-split" aria-hidden="true"></i> en souffrance</span>';
                }

                return $badge;
            })
            ->addColumn('actions', static fn (Payment $p): string => sprintf(
                '<a href="%s" class="btn btn-sm btn-outline-secondary" title="Ouvrir %s">'
                .'<i class="bi bi-arrow-right-short" aria-hidden="true"></i></a>',
                route('paiements.detail', OpaqueId::encode((int) $p->id)),
                e($p->provider_ref),
            ))
            ->filterColumn('ticket', static function (Builder $query, string $terme): void {
                $query->where('tickets.reference', 'ilike', "%{$terme}%");
            })
            ->filterColumn('client', static function (Builder $query, string $terme): void {
                $query->where('clients.full_name', 'ilike', "%{$terme}%");
            })
            ->filterColumn('technicien', static function (Builder $query, string $terme): void {
                $query->where('techniciens.full_name', 'ilike', "%{$terme}%");
            })
            ->filterColumn('moyen', static fn (): null => null)
            ->filterColumn('montant', static fn (): null => null)
            ->filterColumn('confirmations', static fn (): null => null)
            ->filterColumn('statut', static fn (): null => null)
            ->filterColumn('actions', static fn (): null => null)
            ->orderColumn('montant', 'payments.amount_gnf $1')
            ->rawColumns(['provider_ref', 'ticket', 'moyen', 'montant', 'confirmations', 'statut', 'actions'])
            ->toJson();
    }

    /** @return array<string, string|int|null> */
    public function ligneExport(Payment $paiement): array
    {
        return [
            'reference' => $paiement->provider_ref,
            'cree_le' => $paiement->created_at?->format('Y-m-d H:i'),
            'ticket' => (string) $paiement->getAttribute('ticket_reference'),
            'client' => (string) $paiement->getAttribute('client_nom'),
            'technicien' => (string) $paiement->getAttribute('technicien_nom'),
            'canal' => $paiement->channel->label(),
            'moyen' => $paiement->method->label(),
            'montant_gnf' => $paiement->amount_gnf,
            'statut' => $paiement->status->label(),
            'declare_par' => $paiement->declared_by?->label(),
            'confirme_client_le' => $paiement->client_confirmed_at?->format('Y-m-d H:i'),
            'confirme_technicien_le' => $paiement->technician_confirmed_at?->format('Y-m-d H:i'),
            'reference_transaction' => $paiement->transaction_ref,
            'conteste_le' => $paiement->contested_at?->format('Y-m-d H:i'),
            'motif_contestation' => $paiement->contest_reason,
        ];
    }

    /** @return array<string, array<int, array{valeur: string, libelle: string}>> */
    public static function filtres(): array
    {
        return [
            'statuts' => array_map(static fn (PaymentStatus $s): array => ['valeur' => $s->value, 'libelle' => $s->label()], PaymentStatus::cases()),
            'moyens' => array_map(static fn (PaymentMethod $m): array => ['valeur' => $m->value, 'libelle' => $m->label()], PaymentMethod::cases()),
            'canaux' => array_map(static fn (PaymentChannel $c): array => ['valeur' => $c->value, 'libelle' => $c->label()], PaymentChannel::cases()),
        ];
    }

    private static function confirmations(Payment $p): string
    {
        $pastille = static fn (string $qui, bool $ok): string => sprintf(
            '<span class="d-inline-flex align-items-center gap-1 me-2 small %s"><i class="bi %s" aria-hidden="true"></i>%s</span>',
            $ok ? 'text-success' : 'text-body-secondary',
            $ok ? 'bi-check-circle-fill' : 'bi-circle',
            $qui,
        );

        if ($p->channel === PaymentChannel::APP) {
            return $pastille('Client (opérateur)', $p->client_confirmed_at !== null);
        }

        return $pastille('Client', $p->client_confirmed_at !== null)
            .$pastille('Technicien', $p->technician_confirmed_at !== null);
    }
}
