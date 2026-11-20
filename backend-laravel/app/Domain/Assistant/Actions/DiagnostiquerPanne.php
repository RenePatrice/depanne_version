<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Contracts\ModeleDiagnostic;
use App\Domain\Catalog\Models\Service;
use Illuminate\Support\Facades\Cache;

/**
 * Propose les prestations qui correspondent à une panne décrite (lot 4).
 *
 * Renvoie `null` quand aucun modèle n'est disponible ou qu'il n'a rien
 * trouvé d'exploitable : l'application garde alors son moteur local. Les
 * identifiants rendus par le modèle sont relus contre le catalogue actif —
 * jamais pris pour argent comptant.
 */
final class DiagnostiquerPanne
{
    public function __construct(private readonly ModeleDiagnostic $modele) {}

    /**
     * @return array{prestations: array<int, int>, explication: string, conseil: ?string}|null
     */
    public function execute(string $description): ?array
    {
        $catalogue = $this->catalogue();

        if ($catalogue === []) {
            return null;
        }

        $diagnostic = $this->modele->diagnostiquer($description, $catalogue);

        if ($diagnostic === null) {
            return null;
        }

        $connus = array_column($catalogue, 'id');
        $retenus = array_slice(array_unique(array_filter(
            $diagnostic->prestations,
            static fn (int $id): bool => in_array($id, $connus, true),
        )), 0, 2);

        if ($retenus === []) {
            return null;
        }

        return [
            'prestations' => $retenus,
            'explication' => $diagnostic->explication,
            'conseil' => $diagnostic->conseil,
        ];
    }

    /** @return array<int, array{id: int, nom: string, categorie: string, type: string}> */
    private function catalogue(): array
    {
        return Cache::remember('assistant:catalogue', now()->addMinutes(10), static fn (): array => Service::query()
            ->active()
            ->with('category')
            ->orderBy('id')
            ->get()
            ->map(static fn (Service $s): array => [
                'id' => (int) $s->id,
                'nom' => (string) $s->name,
                'categorie' => (string) $s->category->name,
                'type' => $s->type->label(),
            ])
            ->all());
    }
}
