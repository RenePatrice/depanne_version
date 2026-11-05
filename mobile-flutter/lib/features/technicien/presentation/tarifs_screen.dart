import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';

/// Mes tarifs — place de marché (#2).
///
/// Le catalogue n'est qu'un **défaut** : le technicien fixe son prix par
/// prestation, dans les bornes que le serveur impose (le prix hors bornes est
/// refusé côté serveur, pas seulement ici). Laisser un champ vide revient à
/// reprendre le prix catalogue.
///
/// Aucun calcul de prix ici : le déplacement s'ajoute côté serveur au moment où
/// un client choisit le technicien. On ne saisit que le tarif de la prestation.
class TarifsScreen extends ConsumerWidget {
  const TarifsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<TarifTechnicien>> tarifs = ref.watch(tarifsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Mes tarifs')),
      body: tarifs.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, StackTrace _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: Center(
            child: DmErreur(
              message: messageErreur(e),
              onReessayer: () => ref.invalidate(tarifsProvider),
            ),
          ),
        ),
        data: (List<TarifTechnicien> liste) => liste.isEmpty
            ? const DmVide(
                icone: Icons.sell_outlined,
                titre: 'Aucune prestation',
                texte: 'Tes tarifs apparaîtront ici une fois ta spécialité validée.',
              )
            : _Formulaire(tarifs: liste),
      ),
    );
  }
}

class _Formulaire extends ConsumerStatefulWidget {
  const _Formulaire({required this.tarifs});

  final List<TarifTechnicien> tarifs;

  @override
  ConsumerState<_Formulaire> createState() => _FormulaireState();
}

class _FormulaireState extends ConsumerState<_Formulaire> {
  final Map<int, TextEditingController> _controleurs =
      <int, TextEditingController>{};
  bool _enCours = false;

  @override
  void initState() {
    super.initState();
    for (final TarifTechnicien t in widget.tarifs) {
      _controleurs[t.serviceId] = TextEditingController(
        text: t.monPrixGnf?.toString() ?? '',
      );
    }
  }

  @override
  void dispose() {
    for (final TextEditingController c in _controleurs.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(DmSpace.x4),
            children: <Widget>[
              Text(
                'Fixe ton prix par prestation. Laisse vide pour reprendre le '
                'prix catalogue. Le déplacement s\'ajoute automatiquement selon '
                'ta position quand un client te choisit.',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: DmSpace.x3),
              for (final TarifTechnicien t in widget.tarifs)
                _LigneTarif(tarif: t, controleur: _controleurs[t.serviceId]!),
            ],
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(DmSpace.x4),
            child: DmBouton(
              libelle: 'Enregistrer mes tarifs',
              enCours: _enCours,
              onPresse: _enregistrer,
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _enregistrer() async {
    final Map<int, int?> prix = <int, int?>{};

    for (final TarifTechnicien t in widget.tarifs) {
      final String brut = _controleurs[t.serviceId]!.text.trim();
      if (brut.isEmpty) {
        prix[t.serviceId] = null;
        continue;
      }

      final int? valeur = int.tryParse(brut);
      if (valeur == null) continue;

      if (valeur < t.minGnf || valeur > t.maxGnf) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t.prestation} : le prix doit être entre '
              '${formaterGnf(t.minGnf)} et ${formaterGnf(t.maxGnf)}.',
            ),
          ),
        );
        return;
      }

      prix[t.serviceId] = valeur;
    }

    setState(() => _enCours = true);

    try {
      await ref.read(technicienRepositoryProvider).definirTarifs(prix);
      ref.invalidate(tarifsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Tarifs enregistrés.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }
}

class _LigneTarif extends StatelessWidget {
  const _LigneTarif({required this.tarif, required this.controleur});

  final TarifTechnicien tarif;
  final TextEditingController controleur;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x3),
      child: DmCarte(
        enfant: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    tarif.prestation,
                    style: theme.textTheme.titleSmall,
                  ),
                ),
                _TypeChip(type: tarif.type),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              'Catalogue : ${tarif.prixCatalogueFormate} · '
              'entre ${formaterGnf(tarif.minGnf)} et ${formaterGnf(tarif.maxGnf)}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: DmColors.neutral500,
              ),
            ),
            const SizedBox(height: DmSpace.x2),
            TextField(
              controller: controleur,
              keyboardType: TextInputType.number,
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.digitsOnly,
              ],
              decoration: InputDecoration(
                labelText: 'Mon prix (GNF)',
                hintText: tarif.prixCatalogueGnf.toString(),
                suffixText: 'GNF',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({required this.type});

  final String type;

  @override
  Widget build(BuildContext context) {
    final bool depannage = type == 'DEPANNAGE';
    final Color couleur = depannage ? DmColors.warning : DmColors.neutral500;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: couleur.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        depannage ? 'Dépannage' : 'Installation',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(color: couleur),
      ),
    );
  }
}
