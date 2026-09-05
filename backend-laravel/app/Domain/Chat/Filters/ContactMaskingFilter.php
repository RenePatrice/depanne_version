<?php

declare(strict_types=1);

namespace App\Domain\Chat\Filters;

use App\Domain\Chat\Data\ResultatMasquage;

/**
 * Masquage des coordonnées dans le chat (§7.3).
 *
 * Le contournement de la plateforme est le risque business n°1 du §11 : un
 * client et un technicien qui s'échangent leurs numéros au premier message
 * traitent hors application dès le deuxième, et la plateforme perd la
 * commission, la garantie, le support et l'historique.
 *
 * Trois choses sont cherchées : les **numéros**, les **adresses e-mail** et les
 * **liens**. La difficulté n'est pas la forme canonique — un `620123456` se
 * repère en une expression régulière — mais les contournements : chiffres
 * écrits en lettres, espacés un par un, ou e-mail écrit « nom arobase domaine
 * point com ». Le filtre normalise donc avant de chercher.
 *
 * ### Ce qu'il ne fait pas
 *
 * Il ne prétend pas être infaillible. Un numéro écrit à l'envers, en soussou,
 * ou glissé dans une photo passera. Le but n'est pas l'étanchéité — impossible
 * — mais de rendre le contournement **assez pénible pour ne pas être le
 * réflexe**, et de laisser une trace : chaque tentative détectée est signalée
 * au back-office, et une paire client/technicien qui revient dans cette liste
 * est un signal exploitable.
 *
 * ### Pourquoi ce seuil
 *
 * Un numéro guinéen fait neuf chiffres (`6XX XX XX XX`). Le seuil est à huit,
 * pour attraper un numéro amputé d'un chiffre, mais pas plus bas : à Conakry
 * les montants courants — 100 000 GNF — font six ou sept chiffres, et masquer
 * un prix dans une conversation sur un prix serait absurde. Les montants
 * suivis d'une mention monétaire sont explicitement épargnés.
 */
final class ContactMaskingFilter
{
    public const RAISON_TELEPHONE = 'TELEPHONE';

    public const RAISON_EMAIL = 'EMAIL';

    public const RAISON_LIEN = 'LIEN';

    /** Un numéro guinéen fait neuf chiffres ; on tolère un chiffre manquant. */
    private const CHIFFRES_MINIMUM = 8;

    private const REMPLACEMENT = '[masqué]';

    /** Chiffres écrits en toutes lettres, avec leurs variantes d'accentuation. */
    private const MOTS_CHIFFRES = [
        'zero' => '0', 'zéro' => '0', 'o' => '0',
        'un' => '1', 'une' => '1',
        'deux' => '2', 'trois' => '3', 'quatre' => '4', 'cinq' => '5',
        'six' => '6', 'sept' => '7', 'huit' => '8', 'neuf' => '9',
    ];

    /** Mentions monétaires : ce qui les précède est un prix, pas un numéro. */
    private const MONNAIE = ['gnf', 'fg', 'franc', 'francs', 'f'];

    /**
     * Préfixes de nos propres références. « DM-2026-000123 » porte dix
     * chiffres et serait pris pour un numéro — or c'est précisément ce que les
     * deux parties vont s'écrire pour parler de l'intervention.
     */
    private const PREFIXES_REFERENCE = ['dm', 'lit', 'ref', 'reference', 'ticket', 'commande'];

    public function appliquer(string $texte): ResultatMasquage
    {
        $raisons = [];
        $masque = $texte;

        $masque = $this->masquerEmails($masque, $raisons);
        $masque = $this->masquerLiens($masque, $raisons);
        $masque = $this->masquerNumeros($masque, $raisons);

        return new ResultatMasquage(
            texteOriginal: $texte,
            texteMasque: $masque,
            raisons: array_values(array_unique($raisons)),
        );
    }

    /**
     * Adresses e-mail, y compris écrites en toutes lettres.
     *
     * @param  array<int, string>  $raisons
     */
    private function masquerEmails(string $texte, array &$raisons): string
    {
        $motifs = [
            // Forme canonique.
            '/[\p{L}0-9._%+-]+@[\p{L}0-9.-]+\.[a-z]{2,}/iu',
            // « nom arobase domaine point com », et ses variantes ponctuées.
            '/[\p{L}0-9._%+-]+\s*(?:@|\(a\)|\[at\]|\s(?:arobase|at)\s)\s*[\p{L}0-9.-]+\s*(?:\.|\s(?:point|dot)\s)\s*[a-z]{2,}/iu',
        ];

        foreach ($motifs as $motif) {
            $texte = $this->remplacer($motif, $texte, self::RAISON_EMAIL, $raisons);
        }

        return $texte;
    }

    /**
     * Liens, y compris les domaines nus et les raccourcis de messagerie.
     *
     * @param  array<int, string>  $raisons
     */
    private function masquerLiens(string $texte, array &$raisons): string
    {
        $motifs = [
            '#\b(?:https?://|www\.)\S+#iu',
            // wa.me, t.me, chat.whatsapp.com : les passerelles les plus
            // évidentes vers une conversation hors application.
            '#\b(?:wa\.me|t\.me|m\.me|chat\.whatsapp\.com)/\S*#iu',
            // Domaine nu suivi d'une extension courante.
            '#\b[\p{L}0-9-]+\.(?:com|net|org|fr|gn|io|me|app)\b(?:/\S*)?#iu',
        ];

        foreach ($motifs as $motif) {
            $texte = $this->remplacer($motif, $texte, self::RAISON_LIEN, $raisons);
        }

        return $texte;
    }

    /**
     * Suites de chiffres, écrits en chiffres ou en lettres, éventuellement
     * espacés ou mélangés.
     *
     * L'analyse se fait par jetons plutôt que par expression régulière : c'est
     * la seule façon de traiter « six deux zéro 12 34 56 » comme une seule
     * suite, et de savoir ensuite quelle portion du texte d'origine masquer.
     *
     * @param  array<int, string>  $raisons
     */
    private function masquerNumeros(string $texte, array &$raisons): string
    {
        $jetons = $this->decouper($texte);

        $suites = [];
        $courante = null;
        $precedent = '';

        foreach ($jetons as $jeton) {
            $chiffres = $this->chiffresDe($jeton['valeur']);

            if ($chiffres !== null) {
                $courante ??= [
                    'debut' => $jeton['debut'],
                    'fin' => $jeton['fin'],
                    'chiffres' => '',
                    'precedent' => $precedent,
                ];
                $courante['fin'] = $jeton['fin'];
                $courante['chiffres'] .= $chiffres;

                continue;
            }

            // Un séparateur ne coupe pas la suite : « 620-12-34-56 » et
            // « 620 / 12 / 34 » sont un seul numéro.
            if ($this->estSeparateur($jeton['valeur'])) {
                continue;
            }

            if ($courante !== null) {
                $suites[] = $courante + ['suivant' => mb_strtolower($jeton['valeur'])];
                $courante = null;
            }

            $precedent = mb_strtolower($jeton['valeur']);
        }

        if ($courante !== null) {
            $suites[] = $courante + ['suivant' => ''];
        }

        // Le remplacement part de la fin : masquer par le début décalerait
        // toutes les positions suivantes.
        foreach (array_reverse($suites) as $suite) {
            if (strlen($suite['chiffres']) < self::CHIFFRES_MINIMUM) {
                continue;
            }

            if (in_array(rtrim($suite['suivant'], '.,!?'), self::MONNAIE, true)) {
                continue;   // c'est un montant, pas un numéro
            }

            if (in_array($suite['precedent'], self::PREFIXES_REFERENCE, true)) {
                continue;   // c'est une de nos références
            }

            $texte = mb_substr($texte, 0, $suite['debut'])
                .self::REMPLACEMENT
                .mb_substr($texte, $suite['fin']);

            $raisons[] = self::RAISON_TELEPHONE;
        }

        return $texte;
    }

    /**
     * Découpe le texte en jetons en conservant leurs positions.
     *
     * @return array<int, array{valeur: string, debut: int, fin: int}>
     */
    private function decouper(string $texte): array
    {
        $jetons = [];
        $longueur = mb_strlen($texte);
        $courant = '';
        $debut = 0;

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = mb_substr($texte, $i, 1);

            if (preg_match('/[\p{L}\p{N}]/u', $caractere) === 1) {
                if ($courant === '') {
                    $debut = $i;
                }
                $courant .= $caractere;

                continue;
            }

            if ($courant !== '') {
                $jetons[] = ['valeur' => $courant, 'debut' => $debut, 'fin' => $i];
                $courant = '';
            }

            $jetons[] = ['valeur' => $caractere, 'debut' => $i, 'fin' => $i + 1];
        }

        if ($courant !== '') {
            $jetons[] = ['valeur' => $courant, 'debut' => $debut, 'fin' => $longueur];
        }

        return $jetons;
    }

    /** Les chiffres que porte ce jeton, ou null s'il n'en porte pas. */
    private function chiffresDe(string $jeton): ?string
    {
        if (preg_match('/^\d+$/', $jeton) === 1) {
            return $jeton;
        }

        $normalise = $this->sansAccent(mb_strtolower($jeton));

        return self::MOTS_CHIFFRES[$normalise]
            ?? self::MOTS_CHIFFRES[mb_strtolower($jeton)]
            ?? null;
    }

    private function estSeparateur(string $jeton): bool
    {
        return in_array($jeton, [' ', '-', '.', '/', '_', ',', '(', ')', '+', "\t"], true);
    }

    private function sansAccent(string $texte): string
    {
        return strtr($texte, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    /** @param array<int, string> $raisons */
    private function remplacer(string $motif, string $texte, string $raison, array &$raisons): string
    {
        $remplace = preg_replace($motif, self::REMPLACEMENT, $texte);

        if ($remplace !== null && $remplace !== $texte) {
            $raisons[] = $raison;

            return $remplace;
        }

        return $texte;
    }
}
