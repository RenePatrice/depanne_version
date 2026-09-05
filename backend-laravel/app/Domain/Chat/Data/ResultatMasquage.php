<?php

declare(strict_types=1);

namespace App\Domain\Chat\Data;

/**
 * Ce que le filtre a trouvé dans un message (§7.3).
 *
 * L'original est conservé à côté du texte masqué : le support doit pouvoir
 * lire ce qui a réellement été écrit lors d'un litige, mais l'application
 * mobile ne reçoit jamais que la version masquée — c'est le modèle `Message`
 * qui garantit cette séparation, par `$hidden`.
 */
final readonly class ResultatMasquage
{
    /** @param array<int, string> $raisons */
    public function __construct(
        public string $texteOriginal,
        public string $texteMasque,
        public array $raisons,
    ) {}

    public function aEteMasque(): bool
    {
        return $this->raisons !== [];
    }

    /** Les raisons réunies, pour la colonne `flag_reason` (60 caractères). */
    public function raisonCourte(): ?string
    {
        return $this->raisons === [] ? null : mb_substr(implode('+', $this->raisons), 0, 60);
    }

    /**
     * Avertissement montré à l'expéditeur.
     *
     * Il explique **pourquoi** plutôt que de se contenter d'interdire : un
     * utilisateur qui comprend qu'il perd la garantie en sortant de
     * l'application recommence moins qu'un utilisateur qu'on rabroue.
     */
    public function avertissement(): ?string
    {
        if (! $this->aEteMasque()) {
            return null;
        }

        return 'Les coordonnées sont masquées dans le chat. '
            .'Tout doit rester dans l\'application : c\'est ce qui te donne la garantie, '
            .'le support en cas de problème et la trace de l\'intervention.';
    }
}
