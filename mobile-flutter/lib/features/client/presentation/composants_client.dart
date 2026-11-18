import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// « 45 min », « 1 h », « 2 h 30 ».
String dureeTexte(int? minutes) {
  if (minutes == null || minutes <= 0) return 'Durée variable';
  if (minutes < 60) return '$minutes min';

  final int h = minutes ~/ 60;
  final int m = minutes % 60;

  return m == 0 ? '$h h' : '$h h ${m.toString().padLeft(2, '0')}';
}

/// Couleur associée au type de prestation.
Color couleurType(String type) => switch (type) {
  'INSTALLATION' => PcColors.brand,
  'CONSTRUCTION' => PcColors.coral,
  _ => PcColors.tech,
};

/// Une prestation dans une liste : nom, description, durée, prix.
class LignePrestation extends StatelessWidget {
  const LignePrestation({
    super.key,
    required this.prestation,
    required this.metier,
    required this.onTap,
    this.categorie,
  });

  final Prestation prestation;
  final PcMetier metier;
  final VoidCallback onTap;

  /// Nom du métier, affiché quand la liste mélange plusieurs métiers.
  final String? categorie;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      onTap: onTap,
      padding: const EdgeInsets.all(15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          PcIco(metier.icone, couleur: metier.couleur),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  prestation.nom,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 3),
                Text(
                  categorie != null
                      ? '$categorie · ${prestation.typeLibelle}'
                      : (prestation.description ?? prestation.typeLibelle),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w500,
                    fontSize: 12.5,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: <Widget>[
                    Icon(Icons.schedule_rounded, size: 14, color: pc.muted),
                    const SizedBox(width: 4),
                    Text(
                      dureeTexte(prestation.dureeMin),
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                    const Spacer(),
                    Flexible(
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: PcMontant(
                          prestation.prixGnf,
                          prefixe: prestation.surDevis ? 'dès' : null,
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
    );
  }
}

/// Fiche d'une prestation, en feuille montante, avant de la choisir.
Future<void> ouvrirFichePrestation(
  BuildContext context,
  WidgetRef ref, {
  required Prestation prestation,
  required CategoriePrestations categorie,
}) {
  final PcMetier metier = PcMetier.de(categorie.code);

  return showPcSheet<void>(
    context,
    builder: (BuildContext ctx) {
      final PcSkin pc = ctx.pc;

      Widget stat(String valeur, String libelle) => Expanded(
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
          decoration: BoxDecoration(
            color: pc.soft,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            children: <Widget>[
              Text(
                valeur,
                textAlign: TextAlign.center,
                style: Theme.of(ctx).textTheme.titleSmall,
              ),
              const SizedBox(height: 2),
              Text(
                libelle,
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w700,
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      );

      Widget liste(
        String titre,
        List<String> lignes,
        IconData icone,
        Color c,
      ) => Padding(
        padding: const EdgeInsets.only(top: 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(titre, style: Theme.of(ctx).textTheme.titleSmall),
            const SizedBox(height: 6),
            for (final String l in lignes)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Icon(icone, size: 16, color: c),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        l,
                        style: TextStyle(
                          color: pc.ink2,
                          fontSize: 13.5,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      );

      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Container(
            height: 130,
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: metier.degrade,
              borderRadius: PcRadius.lgAll,
            ),
            child: Stack(
              children: <Widget>[
                Positioned(
                  right: -14,
                  bottom: -22,
                  child: Icon(
                    metier.icone,
                    size: 120,
                    color: Colors.white.withValues(alpha: 0.2),
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    PcPill(
                      '${categorie.nom} · ${prestation.typeLibelle}',
                      couleur: Colors.white,
                    ),
                    const Spacer(),
                    Text(
                      prestation.nom,
                      style: const TextStyle(
                        fontFamily: PcFonts.display,
                        fontWeight: FontWeight.w700,
                        fontSize: 22,
                        letterSpacing: -0.6,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (prestation.description != null) ...<Widget>[
            const SizedBox(height: 16),
            Text(
              prestation.description!,
              style: TextStyle(
                color: pc.ink2,
                fontSize: 14,
                height: 1.6,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          const SizedBox(height: 16),
          Row(
            children: <Widget>[
              stat(dureeTexte(prestation.dureeMin), 'durée'),
              const SizedBox(width: 8),
              stat(prestation.surDevis ? 'Sur devis' : 'Indicatif', 'tarif'),
              const SizedBox(width: 8),
              stat(
                prestation.photosAutorisees > 0
                    ? '${prestation.photosAutorisees} max'
                    : 'Aucune',
                'photos',
              ),
            ],
          ),
          if (prestation.inclus.isNotEmpty)
            liste(
              'Ce qui est inclus',
              prestation.inclus,
              Icons.check_circle_rounded,
              PcColors.tech,
            ),
          if (prestation.exclus.isNotEmpty)
            liste(
              'Ce qui n’est pas inclus',
              prestation.exclus,
              Icons.remove_circle_outline_rounded,
              pc.muted,
            ),
          const SizedBox(height: 18),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Prix catalogue indicatif · chaque pro affiche son prix ferme',
                  style: TextStyle(
                    color: pc.muted,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              PcMontant(
                prestation.prixGnf,
                taille: 21,
                prefixe: prestation.surDevis ? 'dès' : null,
              ),
            ],
          ),
          const SizedBox(height: 16),
          PcButton(
            libelle: 'Choisir cette prestation',
            icone: Icons.arrow_forward_rounded,
            onPressed: () {
              ref
                  .read(brouillonProvider.notifier)
                  .choisirPrestation(prestation);
              Navigator.of(ctx).pop();
              context.push('/client/demande');
            },
          ),
        ],
      );
    },
  );
}
