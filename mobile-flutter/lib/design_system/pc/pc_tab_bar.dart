import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../pc_skin.dart';
import '../pc_tokens.dart';

/// Un onglet de la barre flottante.
@immutable
class PcOnglet {
  const PcOnglet(this.icone, this.libelle, {this.badge = 0});

  final IconData icone;
  final String libelle;

  /// Compteur affiché sur l'onglet (messages non lus…), 0 pour aucun.
  final int badge;
}

/// Barre d'onglets flottante, en verre dépoli, avec une pastille d'accent qui
/// glisse vers l'onglet choisi.
///
/// Elle flotte au-dessus du contenu plutôt que de le pousser : les écrans
/// défilent dessous, ce qui laisse plus de place sur les petits téléphones.
class PcTabBar extends StatelessWidget {
  const PcTabBar({
    super.key,
    required this.onglets,
    required this.index,
    required this.onChanged,
  });

  final List<PcOnglet> onglets;
  final int index;
  final ValueChanged<int> onChanged;

  /// Hauteur réservée en bas des écrans pour ne rien cacher sous la barre.
  static const double espaceReserve = 112;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final double bas = MediaQuery.of(context).padding.bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(12, 0, 12, 14 + bas),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
          child: Container(
            height: 72,
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              color: pc.card.withValues(alpha: pc.estSombre ? 0.82 : 0.8),
              borderRadius: BorderRadius.circular(28),
              border: Border.all(
                color: pc.estSombre
                    ? const Color(0x14FFFFFF)
                    : const Color(0x0F0D1220),
              ),
            ),
            child: LayoutBuilder(
              builder: (BuildContext context, BoxConstraints contraintes) {
                final double largeur = contraintes.maxWidth / onglets.length;

                return Stack(
                  children: <Widget>[
                    AnimatedPositioned(
                      duration: PcMotion.normal,
                      curve: PcMotion.ease,
                      left: largeur * index,
                      top: 0,
                      bottom: 0,
                      width: largeur,
                      child: Container(
                        decoration: BoxDecoration(
                          color: pc.accent,
                          borderRadius: BorderRadius.circular(21),
                          boxShadow: PcShadow.glow(pc.accent),
                        ),
                      ),
                    ),
                    Row(
                      children: <Widget>[
                        for (int i = 0; i < onglets.length; i++)
                          Expanded(child: _onglet(context, i)),
                      ],
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }

  Widget _onglet(BuildContext context, int i) {
    final PcSkin pc = context.pc;
    final PcOnglet onglet = onglets[i];
    final bool actif = i == index;
    final Color couleur = actif ? Colors.white : pc.muted;

    return Semantics(
      selected: actif,
      button: true,
      label: onglet.libelle,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () {
          if (!actif) {
            HapticFeedback.selectionClick();
            onChanged(i);
          }
        },
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: <Widget>[
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                AnimatedSwitcher(
                  duration: PcMotion.fast,
                  child: Icon(
                    onglet.icone,
                    key: ValueKey<bool>(actif),
                    size: 22,
                    color: couleur,
                  ),
                ),
                const SizedBox(height: 4),
                AnimatedDefaultTextStyle(
                  duration: PcMotion.fast,
                  style: TextStyle(
                    fontFamily: PcFonts.body,
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: couleur,
                  ),
                  child: Text(onglet.libelle, maxLines: 1),
                ),
              ],
            ),
            if (onglet.badge > 0)
              Positioned(
                top: 2,
                right: 14,
                child: Container(
                  constraints: const BoxConstraints(minWidth: 18),
                  height: 18,
                  padding: const EdgeInsets.symmetric(horizontal: 5),
                  decoration: BoxDecoration(
                    color: PcColors.coral,
                    borderRadius: BorderRadius.circular(9),
                    border: Border.all(color: pc.card, width: 2),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    onglet.badge > 9 ? '9+' : '${onglet.badge}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 9.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
