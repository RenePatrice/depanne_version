<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Wallet\Actions\ProcessWithdrawal;
use App\Domain\Wallet\Data\WithdrawalStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Http\Controllers\Controller;
use App\Http\DataTables\RetraitsTable;
use App\Support\Exports\ExporteurTable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * File des demandes de retrait (§6, §8.4). Le workflow complet vit dans
 * `ProcessWithdrawal` : le contrôleur ne fait que router la décision.
 */
final class RetraitController extends Controller
{
    public function __construct(private readonly RetraitsTable $table) {}

    public function index(): View
    {
        return view('retraits.index', [
            'statuts' => RetraitsTable::statutsFiltrables(),
            'enAttente' => Withdrawal::query()->where('status', WithdrawalStatus::EN_ATTENTE)->count(),
            'aVerser' => (int) Withdrawal::query()->where('status', WithdrawalStatus::APPROUVE)->sum('amount_gnf'),
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
            RetraitsTable::COLONNES_EXPORT,
            fn (Withdrawal $retrait): array => $this->table->ligneExport($retrait),
            'retraits',
            'Demandes de retrait — Dépanne-Moi',
        );
    }

    public function detail(Withdrawal $retrait): View
    {
        $retrait->load('technician:id,full_name,phone,status');

        return view('retraits.detail', [
            'retrait' => $retrait,
            'solde' => Transaction::balanceFor($retrait->technician_id),
            'mouvements' => Transaction::query()
                ->where('user_id', $retrait->technician_id)
                ->latest('created_at')
                ->limit(15)
                ->get(),
            'autresDemandes' => Withdrawal::query()
                ->where('technician_id', $retrait->technician_id)
                ->whereKeyNot($retrait->id)
                ->latest('requested_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function decider(Request $request, Withdrawal $retrait, ProcessWithdrawal $action): RedirectResponse
    {
        $donnees = $request->validate([
            'decision' => ['required', 'string', 'in:approuver,rejeter,payer'],
            'note' => ['nullable', 'string', 'max:300'],
            'reference_operateur' => ['nullable', 'string', 'max:120'],
        ], attributes: ['decision' => 'décision', 'reference_operateur' => 'référence opérateur']);

        $adminId = (int) $request->user('admin')?->id;

        try {
            $message = match ($donnees['decision']) {
                'approuver' => $this->approuver($action, $retrait, $adminId, $donnees['note'] ?? null),
                'rejeter' => $this->rejeter($action, $retrait, $adminId, $donnees['note'] ?? null),
                default => $this->payer($action, $retrait, $adminId, $donnees['reference_operateur'] ?? null),
            };
        } catch (DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('retraits.detail', $retrait)->with('statut', $message);
    }

    private function approuver(ProcessWithdrawal $action, Withdrawal $retrait, int $adminId, ?string $note): string
    {
        $action->approuver($retrait, $adminId, $note);

        return 'Retrait approuvé. Le solde du technicien ne bougera qu\'au versement effectif.';
    }

    private function rejeter(ProcessWithdrawal $action, Withdrawal $retrait, int $adminId, ?string $note): string
    {
        if ($note === null || mb_strlen(trim($note)) < 5) {
            throw new DomainException('Un rejet doit être motivé : le technicien doit savoir pourquoi.');
        }

        $action->rejeter($retrait, trim($note), $adminId);

        return 'Retrait rejeté, motif transmis au technicien.';
    }

    private function payer(ProcessWithdrawal $action, Withdrawal $retrait, int $adminId, ?string $reference): string
    {
        $action->marquerPaye($retrait, $adminId, $reference);

        return 'Versement enregistré : le mouvement est écrit au grand livre.';
    }
}
