<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Accounts\Models\User;
use App\Domain\Payments\Data\PaymentMethod;
use App\Domain\Settings\Models\AppSetting;
use App\Domain\Wallet\Actions\RequestWithdrawal;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Withdrawal;
use App\Http\Controllers\Controller;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Portefeuille du technicien (§7.3, §8.4).
 *
 * Le solde affiché est **la somme des mouvements**, jamais un compteur
 * (ADR-0004). Le montant retirable en retranche ce qui est déjà engagé dans
 * une demande non versée : afficher le solde brut inviterait à demander deux
 * fois le même argent.
 */
final class PortefeuilleController extends Controller
{
    public function __construct(private readonly RequestWithdrawal $demande) {}

    /** Solde, montant retirable et derniers mouvements. */
    public function index(Request $request): JsonResponse
    {
        $technicien = $this->technicien($request);

        $mouvements = Transaction::query()
            ->where('user_id', $technicien->getKey())
            ->with('ticket:id,reference')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $solde = Transaction::balanceFor((int) $technicien->getKey());
        $engage = $this->demande->engage($technicien);

        return response()->json([
            'solde_gnf' => $solde,
            'solde_formate' => Money::format($solde),
            'engage_gnf' => $engage,
            'retirable_gnf' => $this->demande->disponible($technicien),
            'retrait_minimum_gnf' => (int) AppSetting::get(AppSetting::WITHDRAWAL_MIN_GNF, 50_000),
            'mouvements' => $mouvements->map(static fn (Transaction $t): array => [
                'id' => $t->id,
                'type' => $t->type->value,
                'type_libelle' => $t->type->label(),
                'montant_gnf' => $t->amount_gnf,
                'montant_formate' => Money::format($t->amount_gnf),
                'solde_apres_gnf' => $t->balance_after_gnf,
                'libelle' => $t->description,
                'ticket' => $t->ticket?->reference,
                'date' => $t->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /** Demander un retrait. */
    public function retirer(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'montant_gnf' => ['required', 'integer', 'min:1'],
            'numero' => ['required', 'string', 'max:20'],
            'operateur' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        try {
            $retrait = $this->demande->execute(
                $this->technicien($request),
                (int) $valide['montant_gnf'],
                (string) $valide['numero'],
                PaymentMethod::from((string) $valide['operateur']),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Demande enregistrée. Le versement part après validation, sous 48 heures ouvrées.',
            'retrait' => $this->presenter($retrait),
        ], 201);
    }

    /** Mes demandes de retrait. */
    public function retraits(Request $request): JsonResponse
    {
        $retraits = Withdrawal::query()
            ->where('technician_id', $this->technicien($request)->getKey())
            ->orderByDesc('requested_at')
            ->limit(30)
            ->get();

        return response()->json([
            'retraits' => $retraits->map(fn (Withdrawal $r): array => $this->presenter($r))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function presenter(Withdrawal $retrait): array
    {
        return [
            'reference' => $retrait->reference,
            'montant_gnf' => $retrait->amount_gnf,
            'montant_formate' => Money::format($retrait->amount_gnf),
            'numero' => $retrait->mobile_money_number,
            'operateur' => $retrait->provider->value,
            'statut' => $retrait->status->value,
            'statut_libelle' => $retrait->status->label(),
            // Le motif d'un rejet est renvoyé : un retrait refusé sans
            // explication est le meilleur moyen de perdre un technicien.
            'note' => $retrait->note,
            'demande_le' => $retrait->requested_at->toIso8601String(),
            'verse_le' => $retrait->paid_at?->toIso8601String(),
        ];
    }

    private function technicien(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        abort_unless($utilisateur->is_technician, 403, 'Seuls les techniciens ont un portefeuille.');

        return $utilisateur;
    }
}
