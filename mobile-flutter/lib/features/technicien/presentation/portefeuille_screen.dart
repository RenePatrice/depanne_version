import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';
import 'retrait_feuille.dart';

/// Solde, mouvements et retraits (§7.3, §8.4).
///
/// Deux chiffres, pas un : le **solde** est ce que le technicien a gagné, le
/// **retirable** est ce qu'il peut sortir aujourd'hui. Les confondre ferait
/// déposer trois demandes de la totalité du solde, dont deux seraient rejetées
/// — et rien n'est plus corrosif qu'un retrait refusé sans qu'on comprenne.
class PortefeuilleScreen extends ConsumerWidget {
  const PortefeuilleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Portefeuille> portefeuille = ref.watch(
      portefeuilleProvider,
    );
    final AsyncValue<List<Retrait>> retraits = ref.watch(retraitsProvider);

    return portefeuille.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object e, StackTrace _) => Padding(
        padding: const EdgeInsets.all(DmSpace.x6),
        child: Center(
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(portefeuilleProvider),
          ),
        ),
      ),
      data: (Portefeuille p) => RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(portefeuilleProvider);
          ref.invalidate(retraitsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(DmSpace.x4),
          children: <Widget>[
            _Solde(portefeuille: p),

            const _Entete('Mes retraits'),
            retraits.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(DmSpace.x6),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (Object e, StackTrace _) =>
                  DmErreur(message: messageErreur(e)),
              data: (List<Retrait> liste) => liste.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: DmSpace.x2,
                      ),
                      child: Text(
                        'Aucun retrait demandé pour l\'instant.',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    )
                  : Column(
                      children: <Widget>[
                        for (final Retrait r in liste.take(5))
                          _LigneRetrait(retrait: r),
                      ],
                    ),
            ),

            const _Entete('Mes mouvements'),
            if (p.mouvements.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: DmSpace.x2),
                child: Text(
                  'Tes gains apparaîtront ici après ta première intervention '
                  'clôturée.',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              )
            else
              for (final Mouvement m in p.mouvements)
                _LigneMouvement(mouvement: m),

            const SizedBox(height: DmSpace.x8),
          ],
        ),
      ),
    );
  }
}

class _Entete extends StatelessWidget {
  const _Entete(this.titre);

  final String titre;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      DmSpace.x2,
      DmSpace.x6,
      DmSpace.x2,
      DmSpace.x3,
    ),
    child: Text(titre, style: Theme.of(context).textTheme.titleLarge),
  );
}

class _Solde extends ConsumerWidget {
  const _Solde({required this.portefeuille});

  final Portefeuille portefeuille;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ThemeData theme = Theme.of(context);
    final bool peutRetirer =
        portefeuille.retirableGnf >= portefeuille.retraitMinimumGnf &&
        portefeuille.retraitMinimumGnf > 0;

    return DmCarte(
      padding: const EdgeInsets.all(DmSpace.x6),
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Solde', style: theme.textTheme.labelSmall),
          const SizedBox(height: DmSpace.x1),
          Text(
            portefeuille.soldeFormate,
            style: theme.textTheme.displaySmall?.copyWith(
              color: theme.colorScheme.primary,
            ),
          ),

          const SizedBox(height: DmSpace.x4),
          const Divider(),
          const SizedBox(height: DmSpace.x3),

          _Ligne(
            libelle: 'Retirable maintenant',
            valeur: formaterGnf(portefeuille.retirableGnf),
          ),
          if (portefeuille.engageGnf > 0) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            _Ligne(
              libelle: 'Déjà demandé, pas encore versé',
              valeur: formaterGnf(portefeuille.engageGnf),
            ),
          ],

          const SizedBox(height: DmSpace.x6),
          DmBouton(
            libelle: 'Demander un retrait',
            onPresse: peutRetirer
                ? () => RetraitFeuille.ouvrir(context, portefeuille)
                : null,
          ),
          if (!peutRetirer) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            Text(
              // Dire le seuil plutôt que griser le bouton en silence : un
              // bouton inactif sans explication se lit comme une panne.
              'Retrait possible à partir de '
              '${formaterGnf(portefeuille.retraitMinimumGnf)}.',
              style: theme.textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne({required this.libelle, required this.valeur});

  final String libelle;
  final String valeur;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Row(
      children: <Widget>[
        Expanded(child: Text(libelle, style: theme.textTheme.bodyMedium)),
        Text(valeur, style: theme.textTheme.titleSmall),
      ],
    );
  }
}

class _LigneMouvement extends StatelessWidget {
  const _LigneMouvement({required this.mouvement});

  final Mouvement mouvement;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x2),
      child: DmCarte(
        padding: const EdgeInsets.symmetric(
          horizontal: DmSpace.x4,
          vertical: DmSpace.x3,
        ),
        enfant: Row(
          children: <Widget>[
            Icon(
              mouvement.estUneEntree
                  ? Icons.arrow_downward
                  : Icons.arrow_upward,
              size: 18,
              color: mouvement.estUneEntree
                  ? DmColors.success
                  : DmColors.neutral500,
            ),
            const SizedBox(width: DmSpace.x3),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    mouvement.libelle ?? mouvement.typeLibelle,
                    style: theme.textTheme.bodyMedium,
                  ),
                  if (mouvement.ticket != null)
                    Text(mouvement.ticket!, style: theme.textTheme.labelSmall),
                ],
              ),
            ),
            const SizedBox(width: DmSpace.x2),
            Text(
              mouvement.montantFormate,
              style: theme.textTheme.titleSmall?.copyWith(
                color: mouvement.estUneEntree ? DmColors.success : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LigneRetrait extends StatelessWidget {
  const _LigneRetrait({required this.retrait});

  final Retrait retrait;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    final Color couleur = retrait.estVerse
        ? DmColors.success
        : retrait.estRejete
        ? DmColors.danger
        : DmColors.warning;

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x2),
      child: DmCarte(
        enfant: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    retrait.montantFormate,
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                Text(
                  retrait.statutLibelle,
                  style: theme.textTheme.labelSmall?.copyWith(color: couleur),
                ),
              ],
            ),
            const SizedBox(height: DmSpace.x1),
            Text(
              '${retrait.operateur} · ${retrait.numero}',
              style: theme.textTheme.bodySmall,
            ),
            // Le motif d'un rejet est affiché, jamais avalé : un retrait refusé
            // sans explication est le meilleur moyen de perdre un technicien.
            if (retrait.note != null && retrait.note!.isNotEmpty) ...<Widget>[
              const SizedBox(height: DmSpace.x2),
              Text(
                retrait.note!,
                style: theme.textTheme.bodySmall?.copyWith(color: couleur),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
