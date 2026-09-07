import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../design_system/components/dm_composants.dart';
import '../../design_system/design_tokens.dart';
import '../auth/application/session_controller.dart';

/// Écran de démarrage, le temps de savoir si une session existe.
///
/// Il n'existe que pour éviter un clignotement : sans lui, l'application
/// afficherait l'écran de connexion pendant la seconde où elle interroge le
/// serveur, puis basculerait sur l'accueil — donnant l'impression d'une
/// déconnexion à chaque lancement.
///
/// Quand le réseau manque, il ne renvoie pas vers la connexion : il propose de
/// réessayer. Les jetons sont toujours valables, c'est la couverture qui
/// manque, et forcer une ressaisie de mot de passe à chaque perte de réseau
/// serait une punition quotidienne.
class DemarrageScreen extends ConsumerWidget {
  const DemarrageScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Session session = ref.watch(sessionProvider);
    final ThemeData theme = Theme.of(context);

    final bool horsLigne = session.erreur?.reseau ?? false;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(DmSpace.x8),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(
                  Icons.handyman,
                  size: 64,
                  color: theme.colorScheme.primary,
                ),
                const SizedBox(height: DmSpace.x4),
                Text('Dépanne-Moi', style: theme.textTheme.headlineMedium),
                const SizedBox(height: DmSpace.x8),

                if (horsLigne) ...<Widget>[
                  DmErreur(
                    message: session.erreur!.message,
                    onReessayer: () =>
                        ref.read(sessionProvider.notifier).restaurer(),
                  ),
                ] else
                  const CircularProgressIndicator(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
