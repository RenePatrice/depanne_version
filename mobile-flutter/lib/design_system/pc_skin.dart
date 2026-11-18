import 'package:flutter/material.dart';

import 'pc_tokens.dart';

/// Palette résolue de l'écran courant, portée par le thème.
///
/// Les composants lisent `context.pc` plutôt qu'une couleur fixe : le même
/// widget s'affiche juste dans l'espace client (clair, accent bleu) et dans
/// l'espace technicien (sombre, accent vert).
@immutable
class PcSkin extends ThemeExtension<PcSkin> {
  const PcSkin({
    required this.bg,
    required this.card,
    required this.soft,
    required this.ink,
    required this.ink2,
    required this.muted,
    required this.line,
    required this.accent,
    required this.accentLight,
    required this.accentDeep,
    required this.estSombre,
  });

  static const PcSkin client = PcSkin(
    bg: PcColors.bg,
    card: PcColors.card,
    soft: PcColors.soft,
    ink: PcColors.ink,
    ink2: PcColors.ink2,
    muted: PcColors.muted,
    line: PcColors.line,
    accent: PcColors.brand,
    accentLight: PcColors.brandLight,
    accentDeep: PcColors.brandDark,
    estSombre: false,
  );

  static const PcSkin technicien = PcSkin(
    bg: PcColors.nightBg,
    card: PcColors.nightCard,
    soft: PcColors.nightSoft,
    ink: PcColors.nightInk,
    ink2: PcColors.nightInk2,
    muted: PcColors.nightMuted,
    line: PcColors.nightLine,
    accent: PcColors.tech,
    accentLight: PcColors.techLight,
    accentDeep: PcColors.techDeep,
    estSombre: true,
  );

  /// L'espace client en thème sombre (réglage du téléphone).
  static const PcSkin clientSombre = PcSkin(
    bg: PcColors.nightBg,
    card: PcColors.nightCard,
    soft: PcColors.nightSoft,
    ink: PcColors.nightInk,
    ink2: PcColors.nightInk2,
    muted: PcColors.nightMuted,
    line: PcColors.nightLine,
    accent: PcColors.brandLight,
    accentLight: Color(0xFF7EA0FF),
    accentDeep: PcColors.brand,
    estSombre: true,
  );

  final Color bg;
  final Color card;
  final Color soft;
  final Color ink;
  final Color ink2;
  final Color muted;
  final Color line;
  final Color accent;
  final Color accentLight;
  final Color accentDeep;
  final bool estSombre;

  /// Dégradé des boutons et pastilles d'accent.
  LinearGradient get gradientAccent => LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[accentLight, accent, accentDeep],
    stops: const <double>[0, 0.55, 1],
  );

  List<BoxShadow> get ombre => estSombre ? PcShadow.night : PcShadow.soft;

  /// Fond teinté d'une couleur, lisible sur clair comme sur sombre.
  Color teinte(Color couleur, [double force = 0.13]) =>
      Color.alphaBlend(couleur.withValues(alpha: force), card);

  @override
  PcSkin copyWith({Color? accent}) => PcSkin(
    bg: bg,
    card: card,
    soft: soft,
    ink: ink,
    ink2: ink2,
    muted: muted,
    line: line,
    accent: accent ?? this.accent,
    accentLight: accentLight,
    accentDeep: accentDeep,
    estSombre: estSombre,
  );

  @override
  PcSkin lerp(covariant ThemeExtension<PcSkin>? autre, double t) {
    if (autre is! PcSkin) return this;

    Color m(Color a, Color b) => Color.lerp(a, b, t)!;

    return PcSkin(
      bg: m(bg, autre.bg),
      card: m(card, autre.card),
      soft: m(soft, autre.soft),
      ink: m(ink, autre.ink),
      ink2: m(ink2, autre.ink2),
      muted: m(muted, autre.muted),
      line: m(line, autre.line),
      accent: m(accent, autre.accent),
      accentLight: m(accentLight, autre.accentLight),
      accentDeep: m(accentDeep, autre.accentDeep),
      estSombre: t < 0.5 ? estSombre : autre.estSombre,
    );
  }
}

extension PcContexte on BuildContext {
  /// Palette de l'écran courant.
  PcSkin get pc => Theme.of(this).extension<PcSkin>() ?? PcSkin.client;
}
