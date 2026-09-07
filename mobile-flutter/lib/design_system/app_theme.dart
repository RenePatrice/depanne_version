import 'package:flutter/material.dart';

import '../core/app_mode.dart';
import 'design_tokens.dart';

/// Thèmes clair et sombre, déclinés par mode.
///
/// La couleur d'accentuation vient du mode : bleu pour le client, orange pour
/// le technicien. Tout le reste — neutres, rayons, espacements, typographie —
/// est commun, si bien que la bascule se voit sans que l'application change
/// d'identité.
///
/// Le thème sombre n'est pas une inversion mécanique du clair. Les fonds
/// gardent une dominante bleutée et les surfaces s'éclaircissent avec
/// l'élévation, comme dans le back-office : un gris neutre à côté du bleu de
/// marque paraît sale, et une carte plus sombre que son fond paraît creuse.
abstract final class AppTheme {
  static ThemeData clair(AppMode mode) => _construire(mode, Brightness.light);

  static ThemeData sombre(AppMode mode) => _construire(mode, Brightness.dark);

  static Color accent(AppMode mode) => switch (mode) {
    AppMode.client => DmColors.primary,
    AppMode.technicien => DmColors.accentTech,
  };

  static ThemeData _construire(AppMode mode, Brightness luminosite) {
    final bool estSombre = luminosite == Brightness.dark;
    final Color accentuation = accent(mode);

    final Color fond = estSombre
        ? DmColors.darkBackground
        : DmColors.neutral100;
    final Color surface = estSombre ? DmColors.darkSurface : DmColors.surface;
    final Color texte = estSombre
        ? DmColors.darkTextPrimary
        : DmColors.neutral900;
    final Color texteSecondaire = estSombre
        ? DmColors.darkTextSecondary
        : DmColors.neutral500;
    final Color bordure = estSombre ? DmColors.darkBorder : DmColors.neutral300;

    final ColorScheme couleurs =
        ColorScheme.fromSeed(
          seedColor: accentuation,
          brightness: luminosite,
        ).copyWith(
          // `fromSeed` produit une palette harmonieuse mais déplace la teinte de
          // marque. On la remet : le bleu #1B6FF3 et l'orange #FF7A1A sont
          // l'identité, pas une suggestion.
          primary: accentuation,
          surface: surface,
          error: DmColors.danger,
          onPrimary: Colors.white,
          onSurface: texte,
        );

    return ThemeData(
      useMaterial3: true,
      brightness: luminosite,
      colorScheme: couleurs,
      scaffoldBackgroundColor: fond,
      fontFamily: DmType.body,
      textTheme: _typographie(texte, texteSecondaire),
      appBarTheme: AppBarTheme(
        backgroundColor: fond,
        foregroundColor: texte,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: DmType.heading,
          fontSize: DmType.fs3,
          fontWeight: FontWeight.w600,
          color: texte,
        ),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: DmRadius.mdAll,
          side: BorderSide(color: bordure),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: estSombre ? DmColors.darkSurfaceRaised : DmColors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: DmSpace.x4,
          vertical: DmSpace.x4,
        ),
        border: OutlineInputBorder(
          borderRadius: DmRadius.smAll,
          borderSide: BorderSide(color: bordure),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: DmRadius.smAll,
          borderSide: BorderSide(color: bordure),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: DmRadius.smAll,
          borderSide: BorderSide(color: accentuation, width: 2),
        ),
        errorBorder: const OutlineInputBorder(
          borderRadius: DmRadius.smAll,
          borderSide: BorderSide(color: DmColors.danger),
        ),
        focusedErrorBorder: const OutlineInputBorder(
          borderRadius: DmRadius.smAll,
          borderSide: BorderSide(color: DmColors.danger, width: 2),
        ),
        labelStyle: TextStyle(color: texteSecondaire),
        hintStyle: TextStyle(color: texteSecondaire),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: accentuation,
          foregroundColor: Colors.white,
          // 52 px : au-dessus des 48 px recommandés, parce que l'application
          // s'utilise debout, souvent d'une main, parfois avec des gants.
          minimumSize: const Size.fromHeight(52),
          shape: const RoundedRectangleBorder(borderRadius: DmRadius.smAll),
          textStyle: const TextStyle(
            fontFamily: DmType.heading,
            fontSize: DmType.fs4,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: accentuation,
          minimumSize: const Size.fromHeight(52),
          side: BorderSide(color: accentuation),
          shape: const RoundedRectangleBorder(borderRadius: DmRadius.smAll),
          textStyle: const TextStyle(
            fontFamily: DmType.heading,
            fontSize: DmType.fs4,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: accentuation),
      ),
      dividerTheme: DividerThemeData(color: bordure, space: 1, thickness: 1),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: estSombre
            ? DmColors.darkSurfaceRaised
            : DmColors.neutral900,
        contentTextStyle: const TextStyle(color: Colors.white),
        behavior: SnackBarBehavior.floating,
        shape: const RoundedRectangleBorder(borderRadius: DmRadius.smAll),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: accentuation.withValues(alpha: 0.14),
        elevation: 0,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: DmType.fs6,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w600
                : FontWeight.w400,
            color: states.contains(WidgetState.selected)
                ? accentuation
                : texteSecondaire,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? accentuation
                : texteSecondaire,
          ),
        ),
      ),
    );
  }

  static TextTheme _typographie(Color texte, Color secondaire) {
    TextStyle titre(double taille, FontWeight graisse) => TextStyle(
      fontFamily: DmType.heading,
      fontSize: taille,
      fontWeight: graisse,
      color: texte,
      height: 1.25,
    );

    TextStyle corps(double taille, Color couleur, [FontWeight? graisse]) =>
        TextStyle(
          fontFamily: DmType.body,
          fontSize: taille,
          fontWeight: graisse ?? FontWeight.w400,
          color: couleur,
          height: 1.5,
        );

    return TextTheme(
      displaySmall: titre(DmType.fs1, FontWeight.w700),
      headlineMedium: titre(DmType.fs2, FontWeight.w600),
      titleLarge: titre(DmType.fs3, FontWeight.w600),
      titleMedium: corps(DmType.fs4, texte, FontWeight.w600),
      bodyLarge: corps(DmType.fs4, texte),
      bodyMedium: corps(DmType.fs5, texte),
      bodySmall: corps(DmType.fs6, secondaire),
      labelLarge: corps(DmType.fs5, texte, FontWeight.w600),
      labelSmall: corps(DmType.fs6, secondaire, FontWeight.w500),
    );
  }
}
