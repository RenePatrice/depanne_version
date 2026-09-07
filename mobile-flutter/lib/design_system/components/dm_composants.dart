/// Composants réutilisables.
///
/// Ils sont réunis dans un seul fichier tant qu'ils tiennent en quelques
/// dizaines de lignes chacun : une arborescence d'un fichier par widget de
/// vingt lignes coûte plus à parcourir qu'elle ne rapporte. Le jour où l'un
/// d'eux grossit, il sort.
library;

import 'package:flutter/material.dart';

import '../design_tokens.dart';

/// Bandeau d'erreur.
///
/// Toujours accompagné d'une action quand l'erreur est réseau : à Conakry, la
/// bonne réponse à « pas de connexion » est un bouton « Réessayer », pas une
/// constatation.
class DmErreur extends StatelessWidget {
  const DmErreur({required this.message, this.onReessayer, super.key});

  final String message;
  final VoidCallback? onReessayer;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(DmSpace.x4),
      decoration: BoxDecoration(
        color: DmColors.danger.withValues(alpha: 0.08),
        borderRadius: DmRadius.smAll,
        border: Border.all(color: DmColors.danger.withValues(alpha: 0.35)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(Icons.error_outline, color: DmColors.danger, size: 20),
          const SizedBox(width: DmSpace.x3),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(message, style: theme.textTheme.bodyMedium),
                if (onReessayer != null) ...<Widget>[
                  const SizedBox(height: DmSpace.x2),
                  TextButton(
                    onPressed: onReessayer,
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      minimumSize: const Size(0, 32),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: const Text('Réessayer'),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Champ de saisie du design system.
///
/// Il porte l'erreur de champ renvoyée par l'API — celles que Laravel place
/// dans `errors` — sous le champ concerné plutôt que dans un bandeau général :
/// l'utilisateur voit tout de suite *quoi* corriger.
class DmChamp extends StatelessWidget {
  const DmChamp({
    required this.libelle,
    required this.controleur,
    this.indication,
    this.erreur,
    this.clavier,
    this.masque = false,
    this.autoRemplissage,
    this.prefixe,
    this.action,
    this.onSoumis,
    super.key,
  });

  final String libelle;
  final TextEditingController controleur;
  final String? indication;
  final String? erreur;
  final TextInputType? clavier;
  final bool masque;
  final Iterable<String>? autoRemplissage;
  final Widget? prefixe;
  final TextInputAction? action;
  final ValueChanged<String>? onSoumis;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controleur,
      obscureText: masque,
      keyboardType: clavier,
      autofillHints: autoRemplissage,
      textInputAction: action,
      onSubmitted: onSoumis,
      decoration: InputDecoration(
        labelText: libelle,
        hintText: indication,
        errorText: erreur,
        prefixIcon: prefixe,
      ),
    );
  }
}

/// Bouton principal, avec son état de chargement.
///
/// Pendant l'attente, le bouton reste à la même taille et devient inactif :
/// le remplacer par un indicateur ferait sauter la mise en page, et laisser
/// l'action cliquable enverrait deux requêtes — sur un réseau lent, c'est le
/// réflexe de tout le monde.
class DmBouton extends StatelessWidget {
  const DmBouton({
    required this.libelle,
    required this.onPresse,
    this.enCours = false,
    this.secondaire = false,
    super.key,
  });

  final String libelle;
  final VoidCallback? onPresse;
  final bool enCours;
  final bool secondaire;

  @override
  Widget build(BuildContext context) {
    if (secondaire) {
      return OutlinedButton(
        onPressed: enCours ? null : onPresse,
        child: enCours ? const _PetitIndicateur() : Text(libelle),
      );
    }

    return FilledButton(
      onPressed: enCours ? null : onPresse,
      child: enCours
          ? const SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: Colors.white,
              ),
            )
          : Text(libelle),
    );
  }
}

class _PetitIndicateur extends StatelessWidget {
  const _PetitIndicateur();

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 20,
    width: 20,
    child: CircularProgressIndicator(
      strokeWidth: 2.5,
      color: Theme.of(context).colorScheme.primary,
    ),
  );
}

/// Écran vide, expliqué.
///
/// « Aucune demande » ne suffit pas : on dit aussi ce qu'il faut faire pour
/// qu'il y en ait une. Un écran vide sans issue est un cul-de-sac.
class DmVide extends StatelessWidget {
  const DmVide({
    required this.icone,
    required this.titre,
    required this.texte,
    this.action,
    super.key,
  });

  final IconData icone;
  final String titre;
  final String texte;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(DmSpace.x8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(
              icone,
              size: 56,
              color: theme.colorScheme.primary.withValues(alpha: 0.4),
            ),
            const SizedBox(height: DmSpace.x4),
            Text(
              titre,
              style: theme.textTheme.titleLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: DmSpace.x2),
            Text(
              texte,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: DmColors.neutral500,
              ),
              textAlign: TextAlign.center,
            ),
            if (action != null) ...<Widget>[
              const SizedBox(height: DmSpace.x6),
              action!,
            ],
          ],
        ),
      ),
    );
  }
}

/// Carte du design system : la surface de base de toutes les listes.
class DmCarte extends StatelessWidget {
  const DmCarte({required this.enfant, this.onTape, this.padding, super.key});

  final Widget enfant;
  final VoidCallback? onTape;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTape,
        child: Padding(
          padding: padding ?? const EdgeInsets.all(DmSpace.x4),
          child: enfant,
        ),
      ),
    );
  }
}
