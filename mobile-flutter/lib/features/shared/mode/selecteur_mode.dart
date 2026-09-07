import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/app_mode.dart';
import '../../../core/providers.dart';
import '../../../design_system/app_theme.dart';
import '../../../design_system/design_tokens.dart';
import '../../auth/application/session_controller.dart';

/// Bascule Client / Technicien, présentée en feuille modale.
///
/// Elle n'apparaît que pour les comptes à double casquette : proposer un
/// basculement à quelqu'un qui n'a qu'un mode ajouterait une question sans
/// réponse possible.
class SelecteurMode extends ConsumerWidget {
  const SelecteurMode({super.key});

  /// Ouvre la feuille. Renvoie le mode retenu, ou null si l'utilisateur ferme.
  static Future<AppMode?> ouvrir(BuildContext context) =>
      showModalBottomSheet<AppMode>(
        context: context,
        showDragHandle: true,
        builder: (BuildContext context) => const SelecteurMode(),
      );

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ThemeData theme = Theme.of(context);
    final AppMode actuel = ref.watch(modeProvider);
    final Session session = ref.watch(sessionProvider);

    final List<AppMode> disponibles =
        session.utilisateur?.modesDisponibles ??
        const <AppMode>[AppMode.client];

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          DmSpace.x6,
          0,
          DmSpace.x6,
          DmSpace.x6,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text('Changer de mode', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x4),

            for (final AppMode mode in disponibles) ...<Widget>[
              _LigneMode(
                mode: mode,
                actif: mode == actuel,
                onChoisir: () async {
                  await ref.read(modeProvider.notifier).basculer(mode);
                  if (context.mounted) Navigator.of(context).pop(mode);
                },
              ),
              const SizedBox(height: DmSpace.x3),
            ],
          ],
        ),
      ),
    );
  }
}

class _LigneMode extends StatelessWidget {
  const _LigneMode({
    required this.mode,
    required this.actif,
    required this.onChoisir,
  });

  final AppMode mode;
  final bool actif;
  final VoidCallback onChoisir;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color couleur = AppTheme.accent(mode);

    return InkWell(
      onTap: actif ? null : onChoisir,
      borderRadius: DmRadius.mdAll,
      child: Container(
        padding: const EdgeInsets.all(DmSpace.x4),
        decoration: BoxDecoration(
          color: actif ? couleur.withValues(alpha: 0.08) : Colors.transparent,
          borderRadius: DmRadius.mdAll,
          border: Border.all(
            color: actif ? couleur : theme.dividerColor,
            width: actif ? 2 : 1,
          ),
        ),
        child: Row(
          children: <Widget>[
            Icon(
              mode == AppMode.client
                  ? Icons.home_repair_service_outlined
                  : Icons.handyman_outlined,
              color: couleur,
            ),
            const SizedBox(width: DmSpace.x4),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Mode ${mode.libelle}',
                    style: theme.textTheme.titleMedium,
                  ),
                  const SizedBox(height: DmSpace.x1),
                  Text(mode.description, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
            if (actif)
              Text(
                'Actuel',
                style: theme.textTheme.labelSmall?.copyWith(color: couleur),
              ),
          ],
        ),
      ),
    );
  }
}
