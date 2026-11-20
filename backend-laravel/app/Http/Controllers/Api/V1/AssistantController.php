<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assistant\Actions\DiagnostiquerPanne;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Assistant de diagnostic (lot 4).
 *
 * `source: "ia"` quand le modèle hébergé a répondu, `null` sinon : l'application
 * retombe alors sur son moteur local, sans message d'erreur.
 */
final class AssistantController extends Controller
{
    public function diagnostic(Request $request, DiagnostiquerPanne $diagnostic): JsonResponse
    {
        $valide = $request->validate(
            ['description' => ['required', 'string', 'min:3', 'max:500']],
            attributes: ['description' => 'description'],
        );

        $resultat = $diagnostic->execute(trim((string) $valide['description']));

        return response()->json($resultat === null
            ? ['source' => null]
            : ['source' => 'ia'] + $resultat);
    }
}
