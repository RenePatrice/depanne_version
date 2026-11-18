/// Jetons du design « Parema Connect » validé sur le prototype v4
/// (18 novembre 2026).
///
/// La marque est alignée sur le site vitrine et le back-office
/// (`backend-laravel/resources/scss/_tokens.scss`) : bleu #2F6BED, corail
/// #F2854C. Le vert est propre à l'application : c'est la couleur de l'espace
/// technicien, qui se distingue au premier coup d'œil de l'espace client.
library;

import 'package:flutter/animation.dart';
import 'package:flutter/painting.dart';

abstract final class PcColors {
  // --- Marque -----------------------------------------------------------
  static const Color brand = Color(0xFF2F6BED);
  static const Color brandLight = Color(0xFF5A86F2);
  static const Color brandDark = Color(0xFF1E51C9);
  static const Color brandNight = Color(0xFF0D1A5C);

  static const Color coral = Color(0xFFF2854C);
  static const Color coralLight = Color(0xFFFFA36B);
  static const Color coralDeep = Color(0xFFE2582A);

  static const Color tech = Color(0xFF12B76A);
  static const Color techLight = Color(0xFF34D399);
  static const Color techDeep = Color(0xFF0E9356);

  static const Color amber = Color(0xFFF5A524);
  static const Color danger = Color(0xFFEF4444);

  // --- Espace client (clair) ------------------------------------------
  static const Color bg = Color(0xFFF3F4F8);
  static const Color card = Color(0xFFFFFFFF);
  static const Color soft = Color(0xFFECEEF4);
  static const Color ink = Color(0xFF0D1220);
  static const Color ink2 = Color(0xFF3A425B);
  static const Color muted = Color(0xFF7B8299);
  static const Color line = Color(0xFFE7E9F0);

  // --- Espace technicien (sombre) -------------------------------------
  static const Color nightBg = Color(0xFF080B12);
  static const Color nightCard = Color(0xFF121826);
  static const Color nightSoft = Color(0xFF1A2132);
  static const Color nightInk = Color(0xFFF1F4FA);
  static const Color nightInk2 = Color(0xFFC3C9D8);
  static const Color nightMuted = Color(0xFF8890A6);
  static const Color nightLine = Color(0xFF212A3B);
}

abstract final class PcFonts {
  /// Titres, chiffres et boutons.
  static const String display = 'Sora';

  /// Texte courant.
  static const String body = 'PlusJakartaSans';
}

abstract final class PcRadius {
  static const double sm = 13;
  static const double md = 18;
  static const double lg = 24;
  static const double xl = 30;

  static const BorderRadius smAll = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius mdAll = BorderRadius.all(Radius.circular(md));
  static const BorderRadius lgAll = BorderRadius.all(Radius.circular(lg));
  static const BorderRadius xlAll = BorderRadius.all(Radius.circular(xl));
}

abstract final class PcMotion {
  /// La courbe du prototype : départ vif, arrivée douce.
  static const Curve ease = Cubic(0.22, 1, 0.36, 1);

  static const Duration fast = Duration(milliseconds: 220);
  static const Duration normal = Duration(milliseconds: 420);
  static const Duration slow = Duration(milliseconds: 600);
}

/// Ombres diffuses, jamais d'un noir pur.
abstract final class PcShadow {
  static const List<BoxShadow> soft = <BoxShadow>[
    BoxShadow(color: Color(0x0A0D1220), blurRadius: 2, offset: Offset(0, 1)),
    BoxShadow(
      color: Color(0x290D1220),
      blurRadius: 30,
      spreadRadius: -14,
      offset: Offset(0, 10),
    ),
  ];

  static const List<BoxShadow> night = <BoxShadow>[
    BoxShadow(
      color: Color(0xB3000000),
      blurRadius: 30,
      spreadRadius: -16,
      offset: Offset(0, 12),
    ),
  ];

  static List<BoxShadow> glow(Color couleur) => <BoxShadow>[
    BoxShadow(
      color: couleur.withValues(alpha: 0.55),
      blurRadius: 28,
      spreadRadius: -12,
      offset: const Offset(0, 14),
    ),
  ];
}
