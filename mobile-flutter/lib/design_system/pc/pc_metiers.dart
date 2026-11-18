import 'package:flutter/material.dart';

/// Identité visuelle d'un métier : une couleur, une teinte profonde pour les
/// couvertures en dégradé, une icône.
///
/// Chaque métier a sa couleur plutôt qu'un bleu uniforme : la grille des
/// métiers se lit d'un coup d'œil, et la couleur suit la prestation jusqu'au
/// suivi de l'intervention.
@immutable
class PcMetier {
  const PcMetier(this.couleur, this.profonde, this.icone);

  final Color couleur;
  final Color profonde;
  final IconData icone;

  static const PcMetier _defaut = PcMetier(
    Color(0xFF2F6BED),
    Color(0xFF1636B8),
    Icons.handyman_rounded,
  );

  static const Map<String, PcMetier> _parCode = <String, PcMetier>{
    'PLOMBERIE': PcMetier(
      Color(0xFF2F6BED),
      Color(0xFF1636B8),
      Icons.water_drop_rounded,
    ),
    'ELECTRICITE': PcMetier(
      Color(0xFFF59E0B),
      Color(0xFFC2410C),
      Icons.bolt_rounded,
    ),
    'MACONNERIE': PcMetier(
      Color(0xFF64748B),
      Color(0xFF1E293B),
      Icons.foundation_rounded,
    ),
    'PEINTURE': PcMetier(
      Color(0xFF8B5CF6),
      Color(0xFF4C1D95),
      Icons.format_paint_rounded,
    ),
    'MENUISERIE': PcMetier(
      Color(0xFFD9631E),
      Color(0xFF7C2D12),
      Icons.carpenter_rounded,
    ),
    'CARRELAGE': PcMetier(
      Color(0xFF0D9488),
      Color(0xFF134E4A),
      Icons.grid_view_rounded,
    ),
  };

  /// Le métier d'un code de catégorie (`PLOMBERIE`…), ou un repli neutre.
  static PcMetier de(String? code) =>
      _parCode[(code ?? '').toUpperCase()] ?? _defaut;

  /// Dégradé des couvertures de métier.
  LinearGradient get degrade => LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[couleur, profonde],
  );
}
