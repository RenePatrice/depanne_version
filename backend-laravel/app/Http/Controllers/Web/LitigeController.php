<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Disputes\Actions\ResolveDispute;
use App\Domain\Disputes\Data\DisputePriority;
use App\Domain\Disputes\Data\DisputeStatus;
use App\Domain\Disputes\Models\Dispute;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Litiges et réclamations (§6).
 *
 * La file est triée par échéance de traitement : ce qui est en retard remonte,
 * puis ce qui va expirer. C'est l'ordre dans lequel le support doit travailler,
 * pas l'ordre d'arrivée.
 */
final class LitigeController extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->string('statut')->toString();

        $litiges = Dispute::query()
            ->with([
                'ticket:id,reference,total_gnf,client_id,technician_id,state',
                'ticket.client:id,full_name',
                'ticket.technician:id,full_name',
                'openedBy:id,full_name',
            ])
            ->when($statut !== '', fn ($q) => $q->where('status', $statut))
            ->when($statut === '', fn ($q) => $q->whereIn('status', [DisputeStatus::OUVERT, DisputeStatus::EN_COURS]))
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                DisputeStatus::OUVERT->value, DisputeStatus::EN_COURS->value,
            ])
            ->orderBy('sla_due_at')
            ->paginate(20)
            ->withQueryString();

        return view('litiges.index', [
            'litiges' => $litiges,
            'statutCourant' => $statut,
            'statuts' => DisputeStatus::cases(),
            'compteurs' => [
                'ouverts' => Dispute::query()->where('status', DisputeStatus::OUVERT)->count(),
                'en_cours' => Dispute::query()->where('status', DisputeStatus::EN_COURS)->count(),
                'en_retard' => Dispute::query()
                    ->whereIn('status', [DisputeStatus::OUVERT, DisputeStatus::EN_COURS])
                    ->whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<', now())
                    ->count(),
            ],
        ]);
    }

    public function detail(Dispute $litige): View
    {
        $litige->load([
            'ticket.client:id,full_name,phone',
            'ticket.technician:id,full_name,phone',
            'ticket.service:id,name',
            'ticket.payments',
            'ticket.messages.sender:id,full_name',
            'openedBy:id,full_name',
            'messages',
        ]);

        return view('litiges.detail', [
            'litige' => $litige,
            'resolutions' => ResolveDispute::RESOLUTIONS,
            'priorites' => DisputePriority::cases(),
        ]);
    }

    public function prendreEnCharge(Request $request, Dispute $litige, ResolveDispute $action): RedirectResponse
    {
        try {
            $action->prendreEnCharge($litige, (int) $request->user('admin')?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['litige' => $e->getMessage()]);
        }

        return back()->with('statut', 'Litige pris en charge.');
    }

    public function ecrire(Request $request, Dispute $litige, ResolveDispute $action): RedirectResponse
    {
        $donnees = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:2000'],
            'audience' => ['required', 'string', 'in:CLIENT,TECHNICIEN,LES_DEUX'],
            'is_internal' => ['sometimes', 'boolean'],
        ], attributes: ['content' => 'message', 'audience' => 'destinataire']);

        $action->ecrire(
            $litige,
            $donnees['content'],
            $donnees['audience'],
            $request->boolean('is_internal'),
            (int) $request->user('admin')?->id,
        );

        return back()->with('statut', $request->boolean('is_internal')
            ? 'Note interne enregistrée : elle n\'est visible ni du client ni du technicien.'
            : 'Message envoyé.');
    }

    public function resoudre(Request $request, Dispute $litige, ResolveDispute $action): RedirectResponse
    {
        $donnees = $request->validate([
            'resolution' => ['required', 'string'],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
            'refund_gnf' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'resolution' => 'décision',
            'resolution_note' => 'motivation',
            'refund_gnf' => 'montant du remboursement',
        ]);

        $adminId = (int) $request->user('admin')?->id;

        try {
            if ($donnees['resolution'] === 'REJETER') {
                $action->rejeter($litige, $donnees['resolution_note'], $adminId);
                $message = 'Réclamation rejetée, décision motivée transmise.';
            } else {
                $action->resoudre(
                    $litige,
                    $donnees['resolution'],
                    $donnees['resolution_note'],
                    (int) ($donnees['refund_gnf'] ?? 0),
                    $adminId,
                );

                $message = (int) ($donnees['refund_gnf'] ?? 0) > 0
                    ? 'Litige résolu. Le remboursement est écrit au grand livre ; le versement vers Mobile Money interviendra en phase C5.'
                    : 'Litige résolu.';
            }
        } catch (DomainException $e) {
            return back()->withErrors(['resolution' => $e->getMessage()])->withInput();
        }

        return redirect()->route('litiges.detail', $litige)->with('statut', $message);
    }
}
