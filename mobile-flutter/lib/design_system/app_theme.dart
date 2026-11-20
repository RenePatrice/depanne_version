import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/app_mode.dart';
import 'pc_skin.dart';
import 'pc_tokens.dart';

/// Thèmes de l'application : clair par défaut, sombre au choix (Profil →
/// Apparence). L'accent dit l'espace — bleu client, vert technicien.
abstract final class AppTheme {
  static ThemeData clair(AppMode mode) =>
      _construire(mode == AppMode.client ? PcSkin.client : PcSkin.technicien);

  static ThemeData sombre(AppMode mode) => _construire(
    mode == AppMode.client ? PcSkin.clientSombre : PcSkin.technicienSombre,
  );

  static Color accent(AppMode mode) =>
      mode == AppMode.client ? PcColors.brand : PcColors.tech;

  static ThemeData _construire(PcSkin skin) {
    final Brightness luminosite = skin.estSombre
        ? Brightness.dark
        : Brightness.light;

    final ColorScheme couleurs =
        ColorScheme.fromSeed(
          seedColor: skin.accent,
          brightness: luminosite,
        ).copyWith(
          primary: skin.accent,
          onPrimary: Colors.white,
          surface: skin.card,
          onSurface: skin.ink,
          error: PcColors.danger,
        );

    final TextTheme texte = _typographie(skin);

    return ThemeData(
      useMaterial3: true,
      brightness: luminosite,
      colorScheme: couleurs,
      scaffoldBackgroundColor: skin.bg,
      fontFamily: PcFonts.body,
      textTheme: texte,
      extensions: <ThemeExtension<dynamic>>[skin],
      splashFactory: InkSparkle.splashFactory,
      appBarTheme: AppBarTheme(
        backgroundColor: skin.bg,
        foregroundColor: skin.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        systemOverlayStyle: skin.estSombre
            ? SystemUiOverlayStyle.light
            : SystemUiOverlayStyle.dark,
        titleTextStyle: texte.titleLarge,
      ),
      dividerTheme: DividerThemeData(color: skin.line, space: 1, thickness: 1),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: skin.accent),
      textSelectionTheme: TextSelectionThemeData(
        cursorColor: skin.accent,
        selectionHandleColor: skin.accent,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: skin.card,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 18,
        ),
        hintStyle: TextStyle(color: skin.muted, fontWeight: FontWeight.w600),
        border: OutlineInputBorder(
          borderRadius: PcRadius.mdAll,
          borderSide: BorderSide(color: skin.line, width: 1.5),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: PcRadius.mdAll,
          borderSide: BorderSide(color: skin.line, width: 1.5),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: PcRadius.mdAll,
          borderSide: BorderSide(color: skin.accent, width: 2),
        ),
        errorBorder: const OutlineInputBorder(
          borderRadius: PcRadius.mdAll,
          borderSide: BorderSide(color: PcColors.danger, width: 1.5),
        ),
        focusedErrorBorder: const OutlineInputBorder(
          borderRadius: PcRadius.mdAll,
          borderSide: BorderSide(color: PcColors.danger, width: 2),
        ),
      ),
      // Les écrans encore à l'ancienne mise en page héritent au moins des
      // bonnes couleurs et des bonnes formes.
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: skin.accent,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(56),
          shape: const RoundedRectangleBorder(borderRadius: PcRadius.mdAll),
          textStyle: const TextStyle(
            fontFamily: PcFonts.display,
            fontSize: 15.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: skin.ink,
          minimumSize: const Size.fromHeight(56),
          side: BorderSide(color: skin.line, width: 1.5),
          shape: const RoundedRectangleBorder(borderRadius: PcRadius.mdAll),
          textStyle: const TextStyle(
            fontFamily: PcFonts.display,
            fontSize: 15.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: skin.accent,
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      cardTheme: CardThemeData(
        color: skin.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: const RoundedRectangleBorder(borderRadius: PcRadius.lgAll),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: skin.card,
        showDragHandle: false,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: PcColors.ink,
        contentTextStyle: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
        ),
        behavior: SnackBarBehavior.floating,
        shape: const RoundedRectangleBorder(borderRadius: PcRadius.mdAll),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: skin.card,
        indicatorColor: skin.teinte(skin.accent, 0.16),
        elevation: 0,
      ),
    );
  }

  static TextTheme _typographie(PcSkin skin) {
    TextStyle titre(
      double taille,
      FontWeight graisse, [
      double hauteur = 1.15,
    ]) => TextStyle(
      fontFamily: PcFonts.display,
      fontSize: taille,
      fontWeight: graisse,
      color: skin.ink,
      height: hauteur,
      letterSpacing: -0.6,
    );

    TextStyle corps(double taille, Color couleur, [FontWeight? graisse]) =>
        TextStyle(
          fontFamily: PcFonts.body,
          fontSize: taille,
          fontWeight: graisse ?? FontWeight.w500,
          color: couleur,
          height: 1.45,
        );

    return TextTheme(
      displaySmall: titre(30, FontWeight.w800, 1.08),
      headlineMedium: titre(25, FontWeight.w800),
      headlineSmall: titre(21, FontWeight.w700),
      titleLarge: titre(19, FontWeight.w700),
      titleMedium: titre(15.5, FontWeight.w700, 1.3),
      titleSmall: corps(14, skin.ink, FontWeight.w700),
      bodyLarge: corps(15, skin.ink),
      bodyMedium: corps(14, skin.ink),
      bodySmall: corps(12.5, skin.muted, FontWeight.w600),
      labelLarge: corps(14, skin.ink, FontWeight.w700),
      labelMedium: corps(12.5, skin.muted, FontWeight.w700),
      labelSmall: corps(11, skin.muted, FontWeight.w700),
    );
  }
}
