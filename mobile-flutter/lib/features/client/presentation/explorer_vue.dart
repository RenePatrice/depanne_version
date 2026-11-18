import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Onglet « Explorer » : tous les métiers, filtrables par type d'intervention.
///
/// Le filtre ne masque pas les métiers qui ne proposent pas le type choisi :
/// il les estompe. Le client voit ainsi ce qui existe, et ce qui ne correspond
/// pas à sa recherche du moment.
class ExplorerVue extends ConsumerStatefulWidget {
  const ExplorerVue({super.key});

  @override
  ConsumerState<ExplorerVue> createState() => _ExplorerVueState();
}

class _ExplorerVueState extends ConsumerState<ExplorerVue> {
  static const List<(String, String?, String)> _filtres =
      <(String, String?, String)>[
        ('Tous', null, 'Tous les métiers disponibles à Conakry.'),
        (
          'Dépannage',
          'DEPANNAGE',
          'Une panne ? Ces métiers interviennent vite.',
        ),
        ('Installation', 'INSTALLATION', 'Poser ou remplacer un équipement.'),
        ('BTP', 'CONSTRUCTION', 'Chantiers de construction, sur devis.'),
      ];

  int _filtre = 0;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );
    final String? type = _filtres[_filtre].$2;

    return ListView(
      padding: EdgeInsets.fromLTRB(
        18,
        MediaQuery.of(context).padding.top + 10,
        18,
        PcTabBar.espaceReserve,
      ),
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Explorer',
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  catalogue.maybeWhen(
                    data: (List<CategoriePrestations> c) => Text(
                      '${c.length} métiers · ${c.fold<int>(0, (int n, CategoriePrestations x) => n + x.prestations.length)} prestations',
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 12.5,
                      ),
                    ),
                    orElse: () => const SizedBox.shrink(),
                  ),
                ],
              ),
            ),
            PcIconButton(
              icone: Icons.search_rounded,
              libelle: 'Rechercher',
              onTap: () => context.push('/client/recherche'),
            ),
          ],
        ),
        const SizedBox(height: 18),
        PcSegmented(
          options: <String>[for (final (String l, _, _) in _filtres) l],
          index: _filtre,
          onChanged: (int i) => setState(() => _filtre = i),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(2, 14, 2, 12),
          child: Text(
            _filtres[_filtre].$3,
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ),
        catalogue.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(40),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (Object e, _) => PcErreur(
            message: 'Le catalogue n’a pas pu être chargé.',
            onReessayer: () => ref.invalidate(catalogueProvider),
          ),
          data: (List<CategoriePrestations> categories) => GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: categories.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              mainAxisExtent: 196,
            ),
            itemBuilder: (BuildContext context, int i) {
              final CategoriePrestations c = categories[i];
              final bool correspond =
                  type == null ||
                  c.prestations.any((Prestation p) => p.type == type);

              return AnimatedOpacity(
                duration: PcMotion.normal,
                opacity: correspond ? 1 : 0.3,
                child: IgnorePointer(
                  ignoring: !correspond,
                  child: _CarteMetier(categorie: c),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _CarteMetier extends StatelessWidget {
  const _CarteMetier({required this.categorie});

  final CategoriePrestations categorie;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final PcMetier metier = PcMetier.de(categorie.code);
    final Set<String> types = <String>{
      for (final Prestation p in categorie.prestations) p.typeLibelle,
    };

    return PcPressable(
      onTap: () => context.push('/client/categorie/${categorie.code}'),
      child: Container(
        clipBehavior: Clip.antiAlias,
        decoration: BoxDecoration(
          color: pc.card,
          borderRadius: PcRadius.lgAll,
          boxShadow: pc.ombre,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Container(
              height: 96,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(gradient: metier.degrade),
              child: Stack(
                clipBehavior: Clip.none,
                children: <Widget>[
                  Positioned(
                    right: -22,
                    bottom: -34,
                    child: Icon(
                      metier.icone,
                      size: 110,
                      color: Colors.white.withValues(alpha: 0.22),
                    ),
                  ),
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(metier.icone, color: Colors.white, size: 22),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    categorie.nom,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${categorie.prestations.length} prestation${categorie.prestations.length > 1 ? 's' : ''}',
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 4,
                    runSpacing: 4,
                    children: <Widget>[
                      for (final String t in types.take(2))
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: pc.soft,
                            borderRadius: BorderRadius.circular(99),
                          ),
                          child: Text(
                            t,
                            style: TextStyle(
                              color: pc.ink2,
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
