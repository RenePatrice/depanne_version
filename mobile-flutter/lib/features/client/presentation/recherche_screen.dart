import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import '../application/recherche_prestations.dart';
import 'composants_client.dart';

/// Recherche instantanée dans tout le catalogue, au fil de la frappe.
class RechercheScreen extends ConsumerStatefulWidget {
  const RechercheScreen({super.key});

  @override
  ConsumerState<RechercheScreen> createState() => _RechercheScreenState();
}

class _RechercheScreenState extends ConsumerState<RechercheScreen> {
  static const List<String> _idees = <String>[
    'fuite',
    'disjoncteur',
    'prise',
    'peinture',
    'carrelage',
    'porte',
  ];

  final TextEditingController _requete = TextEditingController();

  @override
  void dispose() {
    _requete.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );
    final String texte = _requete.text.trim();

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 10),
              child: Row(
                children: <Widget>[
                  const PcBackButton(),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _requete,
                      autofocus: true,
                      textInputAction: TextInputAction.search,
                      onChanged: (_) => setState(() {}),
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 15,
                        color: pc.ink,
                      ),
                      decoration: InputDecoration(
                        hintText: 'Rechercher une prestation…',
                        prefixIcon: Icon(Icons.search_rounded, color: pc.muted),
                        suffixIcon: texte.isEmpty
                            ? null
                            : IconButton(
                                tooltip: 'Effacer',
                                icon: Icon(
                                  Icons.close_rounded,
                                  color: pc.muted,
                                ),
                                onPressed: () => setState(_requete.clear),
                              ),
                        contentPadding: const EdgeInsets.symmetric(
                          vertical: 14,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: catalogue.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (Object e, _) => Padding(
                  padding: const EdgeInsets.all(18),
                  child: PcErreur(
                    message: 'Le catalogue n’a pas pu être chargé.',
                    onReessayer: () => ref.invalidate(catalogueProvider),
                  ),
                ),
                data: (List<CategoriePrestations> categories) => texte.isEmpty
                    ? _suggestions(context, categories)
                    : _resultats(context, categories, texte),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _suggestions(
    BuildContext context,
    List<CategoriePrestations> categories,
  ) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 4, 18, 28),
      children: <Widget>[
        const PcSection('Idées de recherche'),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: <Widget>[
            for (final String idee in _idees)
              PcChip(
                libelle: idee,
                icone: Icons.north_west_rounded,
                onTap: () => setState(() => _requete.text = idee),
              ),
          ],
        ),
        PcSection(
          'Vous ne savez pas comment l’appeler ?',
          lien: 'Assistant',
          onLien: () => context.push('/client/assistant'),
        ),
        PcCard(
          onTap: () => context.push('/client/assistant'),
          child: Row(
            children: <Widget>[
              const PcIco(Icons.auto_awesome_rounded, couleur: PcColors.coral),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Décrivez la panne avec vos mots : l’assistant trouve la '
                  'bonne prestation.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _resultats(
    BuildContext context,
    List<CategoriePrestations> categories,
    String texte,
  ) {
    final PcSkin pc = context.pc;
    final List<ResultatRecherche> resultats = rechercherPrestations(
      categories,
      texte,
    );

    if (resultats.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(18),
        children: <Widget>[
          PcCard(
            padding: const EdgeInsets.all(22),
            child: Column(
              children: <Widget>[
                const PcIco(
                  Icons.auto_awesome_rounded,
                  couleur: PcColors.coral,
                ),
                const SizedBox(height: 12),
                Text(
                  'Aucune prestation trouvée',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                Text(
                  'Décrivez la panne avec vos mots, l’assistant s’en charge.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 14),
                PcButton(
                  libelle: 'Demander à l’assistant',
                  variante: PcVariante.corail,
                  petit: true,
                  onPressed: () => context.push('/client/assistant'),
                ),
              ],
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(18, 4, 18, 28),
      itemCount: resultats.length + 1,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (BuildContext context, int i) {
        if (i == 0) {
          return Text(
            '${resultats.length} résultat${resultats.length > 1 ? 's' : ''} pour « $texte »',
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          );
        }
        final ResultatRecherche r = resultats[i - 1];

        return LignePrestation(
          prestation: r.prestation,
          metier: PcMetier.de(r.categorie.code),
          categorie: r.categorie.nom,
          onTap: () => ouvrirFichePrestation(
            context,
            ref,
            prestation: r.prestation,
            categorie: r.categorie,
          ),
        );
      },
    );
  }
}
