<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\DataTables\JournalTable;
use App\Support\Exports\ExporteurTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journal d'audit (§6). En lecture seule : aucune route n'expose d'écriture ni
 * de suppression, et c'est volontaire — une trace que l'on peut effacer ne vaut
 * rien le jour où elle sert.
 */
final class JournalAuditController extends Controller
{
    public function __construct(private readonly JournalTable $table) {}

    public function index(): View
    {
        return view('journal.index', [
            'journaux' => JournalTable::JOURNAUX,
            'total' => Activity::query()->count(),
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
            JournalTable::COLONNES_EXPORT,
            fn (Activity $activite): array => $this->table->ligneExport($activite),
            'journal-audit',
            "Journal d'audit — Dépanne-Moi",
        );
    }
}
