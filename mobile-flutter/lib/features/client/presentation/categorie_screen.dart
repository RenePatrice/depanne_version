import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'composants_client.dart';

/// Un métier : sa couverture, puis ses prestations rangées par type
/// (Dépannage, Installation, Construction).
class CategorieScreen extends ConsumerStatefulWidget {
  const CategorieScreen({super.key, required this.code});

  final String code;

  @override
  ConsumerState<CategorieScreen> createState() => _CategorieScreenState();
}

class _CategorieScreenState extends ConsumerState<CategorieScreen> {
  static const List<(String, String, String)> _types =
      <(String, String, String)>[
        ('DEPANNAGE', 'Dépannage', 'Une panne à réparer, vite.'),
        ('INSTALLATION', 'Installation', 'Poser ou remplacer un équipement.'),
        (
          'CONSTRUCTION',
          'Construction',
          'Chantier BTP : le pro envoie un devis détaillé.',
        ),
      ];

  int _onglet = 0;

  @override
  Widget build(BuildContext context) {
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );

    return Scaffold(
      body: catalogue.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: PcErreur(
              message: 'Le catalogue n’a pas pu être chargé.',
              onReessayer: () => ref.invalidate(catalogueProvider),
            ),
          ),
        ),
        data: (List<CategoriePrestations> categories) {
          final CategoriePrestations? categorie = categories
              .where((CategoriePrestations c) => c.code == widget.code)
              .firstOrNull;

          if (categorie == null) {
            return const SafeArea(
              child: Padding(
                padding: EdgeInsets.all(18),
                child: PcErreur(message: 'Ce métier n’est plus proposé.'),
              ),
            );
          }

          return _contenu(context, categorie);
        },
      ),
    );
  }

  Widget _contenu(BuildContext context, CategoriePrestations categorie) {
    final PcSkin pc = context.pc;
    final PcMetier metier = PcMetier.de(categorie.code);
    final List<(String, String, String)> types = <(String, String, String)>[
      for (final (String, String, String) t in _types)
        if (categorie.prestations.any((Prestation p) => p.type == t.$1)) t,
    ];
    final int onglet = types.isEmpty ? 0 : _onglet.clamp(0, types.length - 1);
    final List<Prestation> liste = types.isEmpty
        ? categorie.prestations
        : categorie.prestations
              .where((Prestation p) => p.type == types[onglet].$1)
              .toList();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: CustomScrollView(
        slivers: <Widget>[
          SliverToBoxAdapter(
            child: Container(
              height: 250 + MediaQuery.of(context).padding.top,
              padding: EdgeInsets.fromLTRB(
                18,
                MediaQuery.of(context).padding.top + 8,
                18,
                20,
              ),
              decoration: BoxDecoration(gradient: metier.degrade),
              child: Stack(
                clipBehavior: Clip.none,
                children: <Widget>[
                  Positioned(
                    right: -40,
                    bottom: -60,
                    child: Icon(
                      metier.icone,
                      size: 210,
                      color: Colors.white.withValues(alpha: 0.18),
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      PcIconButton(
                        icone: Icons.arrow_back_ios_new_rounded,
                        libelle: 'Retour',
                        fond: Colors.white.withValues(alpha: 0.2),
                        couleur: Colors.white,
                        onTap: () => context.pop(),
                      ),
                      const Spacer(),
                      Text(
                        categorie.nom,
                        style: const TextStyle(
                          fontFamily: PcFonts.display,
                          fontWeight: FontWeight.w800,
                          fontSize: 32,
                          letterSpacing: -1,
                          color: Colors.white,
                        ),
                      ),
                      if (categorie.description != null) ...<Widget>[
                        const SizedBox(height: 4),
                        Text(
                          categorie.description!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.85),
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        children: <Widget>[
                          _Etiquette(
                            '${categorie.prestations.length} prestations',
                          ),
                          for (final (String, String, String) t in types)
                            _Etiquette(t.$2),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          if (types.length > 1)
            SliverPersistentHeader(
              pinned: true,
              delegate: _EnteteOnglets(
                fond: pc.bg,
                enfant: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 10, 18, 10),
                  child: PcSegmented(
                    options: <String>[
                      for (final (String, String, String) t in types) t.$2,
                    ],
                    index: onglet,
                    onChanged: (int i) => setState(() => _onglet = i),
                  ),
                ),
              ),
            ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(18, 6, 18, 30),
            sliver: SliverList.list(
              children: <Widget>[
                if (types.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(2, 4, 2, 12),
                    child: Text(
                      types[onglet].$3,
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                  ),
                for (final Prestation p in liste)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: LignePrestation(
                      prestation: p,
                      metier: metier,
                      onTap: () => ouvrirFichePrestation(
                        context,
                        ref,
                        prestation: p,
                        categorie: categorie,
                      ),
                    ),
                  ),
                const SizedBox(height: 4),
                PcCard(
                  onTap: () => context.push('/client/assistant'),
                  child: Row(
                    children: <Widget>[
                      const PcIco(
                        Icons.auto_awesome_rounded,
                        couleur: PcColors.coral,
                        taille: 40,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Vous ne trouvez pas ? Décrivez votre besoin à '
                          'l’assistant.',
                          style: TextStyle(
                            color: pc.ink2,
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      Icon(Icons.chevron_right_rounded, color: pc.muted),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Etiquette extends StatelessWidget {
  const _Etiquette(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.18),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      texte,
      style: const TextStyle(
        color: Colors.white,
        fontWeight: FontWeight.w700,
        fontSize: 12,
      ),
    ),
  );
}

class _EnteteOnglets extends SliverPersistentHeaderDelegate {
  _EnteteOnglets({required this.fond, required this.enfant});

  final Color fond;
  final Widget enfant;

  @override
  double get minExtent => 70;

  @override
  double get maxExtent => 70;

  @override
  Widget build(BuildContext context, double decalage, bool chevauche) =>
      ColoredBox(color: fond, child: enfant);

  @override
  bool shouldRebuild(covariant _EnteteOnglets ancien) =>
      ancien.fond != fond || ancien.enfant != enfant;
}
