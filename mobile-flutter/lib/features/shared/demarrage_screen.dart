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
/// **Fond blanc, logo centré, animation de chargement** : exactement le même
/// visuel que l'écran de lancement natif (`flutter_native_splash`, fond blanc +
/// logo). L'un enchaîne sur l'autre sans rupture — une seule marque sur blanc,
/// du système jusqu'à l'application, plus aucun « logo bleu puis second logo ».
///
/// Quand le réseau manque, il ne renvoie pas vers la connexion : il propose de
/// réessayer. Les jetons sont toujours valables, c'est la couverture qui
/// manque, et forcer une ressaisie de mot de passe à chaque perte de réseau
/// serait une punition quotidienne.
class DemarrageScreen extends ConsumerStatefulWidget {
  const DemarrageScreen({super.key});

  @override
  ConsumerState<DemarrageScreen> createState() => _DemarrageScreenState();
}

class _DemarrageScreenState extends ConsumerState<DemarrageScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controleur;

  @override
  void initState() {
    super.initState();
    // Respiration lente du logo (pulsation d'échelle + opacité) : le « logo qui
    // charge en faisant des mouvements », sans jamais détourner l'attention.
    _controleur = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controleur.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final Session session = ref.watch(sessionProvider);
    final bool horsLigne = session.erreur?.reseau ?? false;

    // Fond blanc imposé (indépendant du thème) pour coller à l'écran natif.
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(DmSpace.x8),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                _LogoPulsant(controleur: _controleur),
                const SizedBox(height: DmSpace.x8),

                if (horsLigne)
                  DmErreur(
                    message: session.erreur!.message,
                    onReessayer: () =>
                        ref.read(sessionProvider.notifier).restaurer(),
                  )
                else
                  const SizedBox(
                    width: 30,
                    height: 30,
                    child: CircularProgressIndicator(
                      strokeWidth: 3,
                      valueColor: AlwaysStoppedAnimation<Color>(
                        DmColors.primary,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Le logo, animé d'une légère pulsation pendant le chargement.
class _LogoPulsant extends StatelessWidget {
  const _LogoPulsant({required this.controleur});

  final AnimationController controleur;

  @override
  Widget build(BuildContext context) {
    final Animation<double> echelle = Tween<double>(
      begin: 0.97,
      end: 1.03,
    ).animate(CurvedAnimation(parent: controleur, curve: Curves.easeInOut));
    final Animation<double> opacite = Tween<double>(
      begin: 0.82,
      end: 1,
    ).animate(CurvedAnimation(parent: controleur, curve: Curves.easeInOut));

    return FadeTransition(
      opacity: opacite,
      child: ScaleTransition(
        scale: echelle,
        child: Image.asset(
          'assets/images/logo.png',
          width: 220,
          fit: BoxFit.contain,
          semanticLabel: 'Parema Connect',
          // Sans le logo (asset manquant en test), on retombe sur le nom :
          // l'écran de démarrage ne doit jamais rester vide.
          errorBuilder: (_, _, _) => const Text(
            'Parema Connect',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: DmColors.primaryDark,
            ),
          ),
        ),
      ),
    );
  }
}
