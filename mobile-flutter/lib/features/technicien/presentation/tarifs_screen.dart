import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';

/// Mes tarifs — place de marché (#2).
///
/// Le catalogue n'est qu'un **défaut** : le technicien fixe son prix par
/// prestation, dans les bornes que le serveur impose (le prix hors bornes est
/// refusé côté serveur, pas seulement ici). Un champ vide reprend le prix
/// catalogue.
///
/// Aucun calcul de prix ici : le déplacement s'ajoute côté serveur au moment où
/// un client choisit le technicien. On ne saisit que le tarif de la prestation.
class TarifsScreen extends ConsumerWidget {
  const TarifsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<TarifTechnicien>> tarifs = ref.watch(tarifsProvider);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            const PcHeader(
              titre: 'Mes tarifs',
              sousTitre: 'Votre prix, dans les bornes autorisées',
            ),
            Expanded(
              child: tarifs.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (Object e, StackTrace _) => Padding(
                  padding: const EdgeInsets.all(18),
                  child: PcErreur(
                    message: messageErreur(e),
                    onReessayer: () => ref.invalidate(tarifsProvider),
                  ),
                ),
                data: (List<TarifTechnicien> liste) => liste.isEmpty
                    ? Padding(
                        padding: const EdgeInsets.all(28),
                        child: Text(
                          'Vos tarifs apparaîtront ici une fois vos métiers '
                          'validés.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: context.pc.muted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      )
                    : _Formulaire(tarifs: liste),
              ),
            ),
          ],
        ),
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
    final PcSkin pc = context.pc;
    final Map<String, List<TarifTechnicien>> parMetier =
        <String, List<TarifTechnicien>>{};
    for (final TarifTechnicien t in widget.tarifs) {
      parMetier.putIfAbsent(t.categorie, () => <TarifTechnicien>[]).add(t);
    }

    return Column(
      children: <Widget>[
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
            children: <Widget>[
              PcCard(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Icon(Icons.info_outline_rounded, color: pc.accent),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Laissez vide pour reprendre le prix catalogue. Le '
                        'déplacement s’ajoute automatiquement selon votre '
                        'position quand un client vous choisit.',
                        style: TextStyle(color: pc.ink2, fontSize: 12.5),
                      ),
                    ),
                  ],
                ),
              ),
              for (final MapEntry<String, List<TarifTechnicien>> m
                  in parMetier.entries) ...<Widget>[
                PcSection(m.key),
                for (final TarifTechnicien t in m.value) ...<Widget>[
                  _LigneTarif(
                    tarif: t,
                    controleur: _controleurs[t.serviceId]!,
                    onChange: () => setState(() {}),
                  ),
                  const SizedBox(height: 10),
                ],
              ],
            ],
          ),
        ),
        Container(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
          decoration: BoxDecoration(
            color: pc.card,
            border: Border(top: BorderSide(color: pc.line)),
          ),
          child: PcButton(
            libelle: 'Enregistrer mes tarifs',
            icone: Icons.save_rounded,
            variante: PcVariante.tech,
            chargement: _enCours,
            onPressed: _enregistrer,
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
        showPcToast(
          context,
          '${t.prestation} : entre ${formaterGnf(t.minGnf)} et '
          '${formaterGnf(t.maxGnf)}.',
          icone: Icons.error_outline_rounded,
        );
        return;
      }

      prix[t.serviceId] = valeur;
    }

    setState(() => _enCours = true);

    try {
      await ref.read(technicienRepositoryProvider).definirTarifs(prix);
      ref.invalidate(tarifsProvider);
      if (mounted) showPcToast(context, 'Tarifs enregistrés.');
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }
}

class _LigneTarif extends StatelessWidget {
  const _LigneTarif({
    required this.tarif,
    required this.controleur,
    required this.onChange,
  });

  final TarifTechnicien tarif;
  final TextEditingController controleur;
  final VoidCallback onChange;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int? saisi = int.tryParse(controleur.text.trim());
    final bool horsBornes =
        saisi != null && (saisi < tarif.minGnf || saisi > tarif.maxGnf);
    // Écart au catalogue, calculé sur ce que le technicien voit : un repère
    // pour se situer, pas une recommandation.
    final int? ecart = saisi == null || tarif.prixCatalogueGnf <= 0
        ? null
        : ((saisi - tarif.prixCatalogueGnf) * 100 / tarif.prixCatalogueGnf)
              .round();

    return PcCard(
      bordure: horsBornes ? PcColors.coral.withValues(alpha: 0.6) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  tarif.prestation,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              PcPill(
                tarif.type == 'DEPANNAGE' ? 'Dépannage' : 'Installation',
                couleur: tarif.type == 'DEPANNAGE' ? PcColors.coral : pc.accent,
              ),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            'Catalogue ${tarif.prixCatalogueFormate} · de '
            '${formaterGnf(tarif.minGnf)} à ${formaterGnf(tarif.maxGnf)}',
            style: TextStyle(color: pc.muted, fontSize: 12),
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Expanded(
                child: TextField(
                  controller: controleur,
                  keyboardType: TextInputType.number,
                  onChanged: (_) => onChange(),
                  inputFormatters: <TextInputFormatter>[
                    FilteringTextInputFormatter.digitsOnly,
                  ],
                  style: TextStyle(
                    color: pc.ink,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                  decoration: InputDecoration(
                    labelText: 'Mon prix',
                    hintText: '${tarif.prixCatalogueGnf}',
                    suffixText: 'GNF',
                    errorText: horsBornes ? 'Hors des bornes' : null,
                  ),
                ),
              ),
              if (ecart != null && !horsBornes) ...<Widget>[
                const SizedBox(width: 10),
                PcPill(
                  ecart == 0
                      ? 'Prix catalogue'
                      : '${ecart > 0 ? '+' : ''}$ecart %',
                  couleur: ecart > 0 ? PcColors.coral : PcColors.tech,
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
