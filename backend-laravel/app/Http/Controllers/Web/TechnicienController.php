<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Accounts\Actions\ReviewTechnicianApplication;
use App\Domain\Accounts\Actions\SetUserStatus;
use App\Domain\Accounts\Data\UserStatus;
use App\Domain\Accounts\Data\VerificationStatus;
use App\Domain\Accounts\Models\TechnicianProfile;
use App\Domain\Accounts\Models\User;
use App\Domain\Catalog\Data\Specialty;
use App\Domain\Reviews\Models\Review;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\DataTables\TechniciensTable;
use App\Support\Exports\ExporteurTable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final class TechnicienController extends Controller
{
    /** Durée de vie des URL signées vers les pièces d'identité (§10). */
    private const MINUTES_URL_SIGNEE = 15;

    public function __construct(private readonly TechniciensTable $table) {}

    public function index(): View
    {
        return view('techniciens.index', [
            'verifications' => array_map(
                static fn (VerificationStatus $s): array => ['valeur' => $s->value, 'libelle' => $s->label()],
                VerificationStatus::cases(),
            ),
            'specialites' => array_map(
                static fn (Specialty $s): array => ['valeur' => $s->value, 'libelle' => $s->label()],
                Specialty::cases(),
            ),
            'enAttente' => TechnicianProfile::query()
                ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
                ->count(),
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
            TechniciensTable::COLONNES_EXPORT,
            fn (User $technicien): array => $this->table->ligneExport($technicien),
            'techniciens',
            'Techniciens — Dépanne-Moi',
        );
    }

    /** File de validation : l'écran que le support ouvre en premier le matin. */
    public function validation(): View
    {
        return view('techniciens.validation');
    }

    /** Dossiers en attente, consommés par l'îlot React de la file de validation. */
    public function dossiersEnAttente(): JsonResponse
    {
        $dossiers = TechnicianProfile::query()
            ->where('verification_status', VerificationStatus::EN_ATTENTE_VALIDATION)
            ->with('user:id,full_name,phone,email,created_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TechnicianProfile $profil): array => [
                'id' => $profil->user_id,
                'nom' => $profil->user?->full_name,
                'telephone' => $profil->user?->phone,
                'email' => $profil->user?->email,
                'inscritLe' => $profil->user?->created_at?->toIso8601String(),
                'specialites' => array_map(
                    static fn (string $code): string => Specialty::tryFrom($code)?->label() ?? $code,
                    $profil->specialties,
                ),
                'rayonKm' => $profil->service_radius_km,
                'documents' => [
                    'recto' => $this->urlSignee($profil, 'recto'),
                    'verso' => $this->urlSignee($profil, 'verso'),
                    'selfie' => $this->urlSignee($profil, 'selfie'),
                ],
            ])
            ->values()
            ->all();

        return response()->json(['dossiers' => $dossiers, 'total' => count($dossiers)]);
    }

    public function detail(User $technicien): View
    {
        abort_unless($technicien->is_technician, 404);

        $technicien->load('technicianProfile');

        return view('techniciens.detail', [
            'technicien' => $technicien,
            'profil' => $technicien->technicianProfile,
            'documents' => [
                'recto' => $this->urlSignee($technicien->technicianProfile, 'recto'),
                'verso' => $this->urlSignee($technicien->technicianProfile, 'verso'),
                'selfie' => $this->urlSignee($technicien->technicianProfile, 'selfie'),
            ],
            'tickets' => Ticket::query()
                ->where('technician_id', $technicien->id)
                ->with(['service:id,name', 'client:id,full_name'])
                ->latest('created_at')
                ->limit(20)
                ->get(),
            'solde' => Transaction::balanceFor($technicien->id),
            'mouvements' => Transaction::query()
                ->where('user_id', $technicien->id)
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'avis' => Review::query()
                ->where('technician_id', $technicien->id)
                ->with('client:id,full_name')
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function approuver(Request $request, User $technicien, ReviewTechnicianApplication $action): RedirectResponse
    {
        $profil = $this->profilOu404($technicien);

        try {
            $action->approuver($profil, (int) $request->user('admin')?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['dossier' => $e->getMessage()]);
        }

        return back()->with('statut', $technicien->full_name.' est validé : il entre dans le matching.');
    }

    public function rejeter(Request $request, User $technicien, ReviewTechnicianApplication $action): RedirectResponse
    {
        $profil = $this->profilOu404($technicien);

        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:10', 'max:500'],
        ], attributes: ['motif' => 'motif de rejet']);

        try {
            $action->rejeter($profil, $donnees['motif'], (int) $request->user('admin')?->id);
        } catch (DomainException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return back()->with('statut', 'Dossier de '.$technicien->full_name.' rejeté.');
    }

    public function changerStatut(Request $request, User $technicien, SetUserStatus $action): RedirectResponse
    {
        abort_unless($technicien->is_technician, 404);

        $donnees = $request->validate([
            'statut' => ['required', 'string', 'in:ACTIF,SUSPENDU'],
            'motif' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $action->execute(
                $technicien,
                UserStatus::from($donnees['statut']),
                $donnees['motif'] ?? null,
                (int) $request->user('admin')?->id,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['statut' => $e->getMessage()]);
        }

        return back()->with('statut', $donnees['statut'] === 'SUSPENDU' ? 'Compte suspendu.' : 'Compte réactivé.');
    }

    private function profilOu404(User $technicien): TechnicianProfile
    {
        abort_unless($technicien->is_technician, 404);

        $technicien->load('technicianProfile');

        return $technicien->technicianProfile ?? abort(404, 'Ce technicien n\'a pas de dossier professionnel.');
    }

    /**
     * Les pièces d'identité vivent dans un bucket privé : elles ne sont jamais
     * servies par une URL publique, seulement par une URL signée de courte
     * durée (§10). Tant que Supabase Storage n'est pas branché, le chemin est
     * renvoyé tel quel pour que l'interface reste démontrable.
     */
    /**
     * Lien temporaire vers une pièce justificative.
     *
     * Deux chemins pour un même résultat. Quand le disque sait signer — un
     * stockage objet — on lui laisse la main : le fichier ne transite pas par
     * l'application. Sinon, on passe par une route signée qui diffuse le
     * fichier, ce qui rend la file de validation démontrable en local. Dans les
     * deux cas le lien expire, et dans les deux cas rien n'est public.
     */
    private function urlSignee(?TechnicianProfile $profil, string $piece): ?string
    {
        if ($profil === null) {
            return null;
        }

        $chemin = match ($piece) {
            'recto' => $profil->id_doc_front_url,
            'verso' => $profil->id_doc_back_url,
            'selfie' => $profil->selfie_url,
            default => null,
        };

        if (! is_string($chemin) || $chemin === '') {
            return null;
        }

        if (config('filesystems.default') !== 'local') {
            return Storage::disk(config('filesystems.default'))
                ->temporaryUrl($chemin, now()->addMinutes(self::MINUTES_URL_SIGNEE));
        }

        return URL::temporarySignedRoute(
            'documents.piece',
            now()->addMinutes(self::MINUTES_URL_SIGNEE),
            ['profil' => $profil->user_id, 'piece' => $piece],
        );
    }
}
