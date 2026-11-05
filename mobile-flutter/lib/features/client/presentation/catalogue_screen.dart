import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Première étape : choisir ce dont on a besoin.
///
/// Les prestations sont à prix fixe et le prix est affiché ici, avant tout
/// engagement — c'est ce qui distingue Parema Connect d'un devis à négocier. Le
/// déplacement s'y ajoutera, et l'écran suivant le dira.
class CatalogueScreen extends StatelessWidget {
  const CatalogueScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('De quoi as-tu besoin ?')),
    body: const CatalogueVue(),
  );
}

/// Le catalogue sans sa coquille.
///
/// L'accueil le monte comme onglet, sous sa propre barre : deux `AppBar`
/// empilées annonceraient deux écrans là où il n'y en a qu'un.
class CatalogueVue extends ConsumerWidget {
  const CatalogueVue({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );

    return catalogue.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object e, StackTrace _) => Padding(
        padding: const EdgeInsets.all(DmSpace.x6),
        child: Center(
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(catalogueProvider),
          ),
        ),
      ),
      data: (List<CategoriePrestations> categories) => categories.isEmpty
          ? const DmVide(
              icone: Icons.inbox_outlined,
              titre: 'Catalogue vide',
              texte: 'Aucune prestation n\'est disponible pour le moment.',
            )
          : ListView(
              padding: const EdgeInsets.all(DmSpace.x4),
              children: <Widget>[
                for (final CategoriePrestations c in categories)
                  _Categorie(categorie: c),
              ],
            ),
    );
  }
}

class _Categorie extends StatelessWidget {
  const _Categorie({required this.categorie});

  final CategoriePrestations categorie;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(
            DmSpace.x2,
            DmSpace.x4,
            DmSpace.x2,
            DmSpace.x3,
          ),
          child: Text(categorie.nom, style: theme.textTheme.titleLarge),
        ),
        for (final Prestation p in categorie.prestations) ...<Widget>[
          _Prestation(prestation: p),
          const SizedBox(height: DmSpace.x3),
        ],
      ],
    );
  }
}

class _Prestation extends ConsumerWidget {
  const _Prestation({required this.prestation});

  final Prestation prestation;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      onTape: () {
        ref.read(brouillonProvider.notifier).choisirPrestation(prestation);
        context.push('/client/demande');
      },
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Text(prestation.nom, style: theme.textTheme.titleMedium),
              ),
              const SizedBox(width: DmSpace.x3),
              Text(
                prestation.prixFormate,
                style: theme.textTheme.titleMedium?.copyWith(
                  color: theme.colorScheme.primary,
                ),
              ),
            ],
          ),
          if (prestation.description != null) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            Text(prestation.description!, style: theme.textTheme.bodySmall),
          ],
          if (prestation.dureeMin != null) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            Row(
              children: <Widget>[
                const Icon(
                  Icons.schedule,
                  size: 14,
                  color: DmColors.neutral500,
                ),
                const SizedBox(width: DmSpace.x1),
                Text(
                  'environ ${prestation.dureeMin} min',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ],
          // Ce qui est inclus et ce qui ne l'est pas, avant l'engagement :
          // c'est ce qui évite qu'un client attende autre chose que ce qu'il a
          // commandé, et donc une réclamation.
          if (prestation.inclus.isNotEmpty) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            for (final String i in prestation.inclus)
              _Puce(texte: i, incluse: true),
          ],
          if (prestation.exclus.isNotEmpty)
            for (final String e in prestation.exclus)
              _Puce(texte: e, incluse: false),
        ],
      ),
    );
  }
}

class _Puce extends StatelessWidget {
  const _Puce({required this.texte, required this.incluse});

  final String texte;
  final bool incluse;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: DmSpace.x1),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(
          incluse ? Icons.check : Icons.close,
          size: 14,
          color: incluse ? DmColors.success : DmColors.neutral500,
        ),
        const SizedBox(width: DmSpace.x2),
        Expanded(
          child: Text(texte, style: Theme.of(context).textTheme.bodySmall),
        ),
      ],
    ),
  );
}
