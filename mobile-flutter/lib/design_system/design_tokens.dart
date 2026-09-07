/// Jetons de design — contrepartie Dart de
/// `backend-laravel/resources/scss/_tokens.scss`.
///
/// Les deux fichiers décrivent **le même** système : toute modification ici
/// doit être portée là-bas, et réciproquement. Sans cette discipline, le
/// back-office et l'application dériveraient l'un de l'autre par petites
/// touches, jusqu'à ne plus se ressembler.
///
/// Rien ici n'est une couleur inventée à la volée : un composant qui a besoin
/// d'une teinte l'ajoute d'abord aux deux fichiers.
library;

import 'package:flutter/widgets.dart';

/// Couleurs de marque et couleurs sémantiques.
abstract final class DmColors {
  // --- Marque ---------------------------------------------------------------

  /// Bleu confiance — mode **Client**.
  static const Color primary = Color(0xFF1B6FF3);
  static const Color primaryDark = Color(0xFF0B4CB8);

  /// Orange chantier — mode **Technicien**.
  ///
  /// La bascule d'un mode à l'autre doit se voir immédiatement : c'est la
  /// couleur d'accentuation *et* la navigation qui changent, pas seulement un
  /// libellé.
  static const Color accentTech = Color(0xFFFF7A1A);

  // --- Sémantique -----------------------------------------------------------

  static const Color success = Color(0xFF16A34A);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFDC2626);
  static const Color info = Color(0xFF0EA5E9);

  // --- Neutres --------------------------------------------------------------

  static const Color neutral900 = Color(0xFF0F172A); // texte principal
  static const Color neutral700 = Color(0xFF334155);
  static const Color neutral500 = Color(0xFF64748B); // texte secondaire
  static const Color neutral300 = Color(0xFFCBD5E1);
  static const Color neutral100 = Color(0xFFF1F5F9); // fonds
  static const Color surface = Color(0xFFFFFFFF);

  // --- Neutres du thème sombre ---------------------------------------------
  //
  // Absents du fichier SCSS : le back-office construit son thème sombre par
  // surcharge de variables Bootstrap, l'application le déclare en clair. Les
  // teintes gardent la même dominante bleutée, jamais un gris pur — un gris
  // neutre à côté du bleu de marque paraît sale.

  static const Color darkBackground = Color(0xFF0A1120);
  static const Color darkSurface = Color(0xFF111B2E);
  static const Color darkSurfaceRaised = Color(0xFF1A2740);
  static const Color darkBorder = Color(0xFF20304A);
  static const Color darkTextPrimary = Color(0xFFE9EFF8);
  static const Color darkTextSecondary = Color(0xFF8FA1BA);
}

/// Familles et échelle typographiques.
abstract final class DmType {
  /// Titres. Les fichiers sont embarqués dans `assets/fonts/` plutôt que
  /// téléchargés au premier lancement : sur le réseau de Conakry, une police
  /// distante ferait démarrer l'application en typographie de repli, ou pas du
  /// tout.
  static const String heading = 'Poppins';
  static const String body = 'Inter';

  /// Échelle : 32 / 24 / 20 / 16 / 14 / 12.
  static const double fs1 = 32;
  static const double fs2 = 24;
  static const double fs3 = 20;
  static const double fs4 = 16;
  static const double fs5 = 14;
  static const double fs6 = 12;
}

/// Rayons de bordure : 8 / 16 / 24 / pilule.
abstract final class DmRadius {
  static const double sm = 8;
  static const double md = 16;
  static const double lg = 24;
  static const double pill = 999;

  static const BorderRadius smAll = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius mdAll = BorderRadius.all(Radius.circular(md));
  static const BorderRadius lgAll = BorderRadius.all(Radius.circular(lg));
  static const BorderRadius pillAll = BorderRadius.all(Radius.circular(pill));
}

/// Espacement, sur une grille de 4 px.
///
/// Toute marge de l'application sort de cette liste. Un `16.5` ou un `13`
/// glissé dans un widget se voit immédiatement à la relecture.
abstract final class DmSpace {
  static const double x1 = 4;
  static const double x2 = 8;
  static const double x3 = 12;
  static const double x4 = 16;
  static const double x6 = 24;
  static const double x8 = 32;
  static const double x12 = 48;
}

/// Ombres : trois niveaux diffus, teintés de la couleur primaire.
///
/// Jamais de noir pur — une ombre noire sur un fond bleuté paraît sale, et
/// c'est exactement la différence entre une interface soignée et une interface
/// par défaut.
abstract final class DmShadow {
  static const List<BoxShadow> level1 = [
    BoxShadow(color: Color(0x0F1B6FF3), blurRadius: 2, offset: Offset(0, 1)),
    BoxShadow(color: Color(0x0A0F172A), blurRadius: 3, offset: Offset(0, 1)),
  ];

  static const List<BoxShadow> level2 = [
    BoxShadow(
      color: Color(0x141B6FF3),
      blurRadius: 12,
      spreadRadius: -2,
      offset: Offset(0, 4),
    ),
    BoxShadow(
      color: Color(0x0D0F172A),
      blurRadius: 6,
      spreadRadius: -2,
      offset: Offset(0, 2),
    ),
  ];

  static const List<BoxShadow> level3 = [
    BoxShadow(
      color: Color(0x241B6FF3),
      blurRadius: 40,
      spreadRadius: -12,
      offset: Offset(0, 16),
    ),
    BoxShadow(
      color: Color(0x0F0F172A),
      blurRadius: 12,
      spreadRadius: -4,
      offset: Offset(0, 4),
    ),
  ];
}

/// Durées d'animation.
///
/// Volontairement courtes : l'application vise des téléphones d'entrée de
/// gamme, où une transition de 300 ms paraît lente parce qu'elle saccade.
abstract final class DmMotion {
  static const Duration fast = Duration(milliseconds: 120);
  static const Duration normal = Duration(milliseconds: 200);
  static const Duration slow = Duration(milliseconds: 320);
}
