<?php

declare(strict_types=1);

use App\Domain\Accounts\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Compte du back-office portant un role donne. Declare ici plutot que dans
 * chaque fichier : PHP ne connait qu'un espace de fonctions global, et deux
 * fichiers de test qui declarent le meme helper se percutent.
 */
function adminAvecRole(string $role = 'ADMIN'): AdminUser
{
    return AdminUser::query()
        ->whereHas('roles', fn ($q) => $q->where('name', $role))
        ->firstOrFail();
}

/**
 * Parametres minimaux qu'attend DataTables en mode serveur.
 *
 * @param  array<int, string>|array<string, bool>  $colonnes  nom, ou nom => cherchable
 * @param  array<string, mixed>  $filtres
 * @return array<string, mixed>
 */
function parametresTable(array $colonnes, array $filtres = []): array
{
    $definition = [];
    $index = 0;

    foreach ($colonnes as $cle => $valeur) {
        $nom = is_int($cle) ? (string) $valeur : $cle;
        $cherchable = is_int($cle) ? true : (bool) $valeur;

        $definition[$index++] = [
            'data' => $nom,
            'name' => $nom,
            'searchable' => $cherchable ? 'true' : 'false',
            'orderable' => 'true',
            'search' => ['value' => '', 'regex' => 'false'],
        ];
    }

    return array_merge([
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'columns' => $definition,
        'search' => ['value' => '', 'regex' => 'false'],
    ], $filtres);
}

/**
 * Montant en GNF attendu : les montants sont toujours des entiers.
 */
expect()->extend('toBeGnf', function (int $expected) {
    return $this->toBeInt()->toBe($expected);
});
