import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../design_system/pc.dart';
import '../auth/application/session_controller.dart';

/// Écran de démarrage, le temps de savoir si une session existe.
///
/// Il évite un clignotement : sans lui, l'application afficherait la
/// connexion pendant la seconde où elle interroge le serveur, puis basculerait
/// sur l'accueil — l'impression d'une déconnexion à chaque lancement.
///
/// Quand le réseau manque, il ne renvoie pas vers la connexion : il propose de
/// réessayer. Les jetons sont toujours valables, c'est la couverture qui
/// manque.
class DemarrageScreen extends ConsumerStatefulWidget {
  const DemarrageScreen({super.key});

  @override
  ConsumerState<DemarrageScreen> createState() => _DemarrageScreenState();
}

class _DemarrageScreenState extends ConsumerState<DemarrageScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ondes = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2200),
  )..repeat();

  @override
  void dispose() {
    _ondes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final Session session = ref.watch(sessionProvider);
    final bool horsLigne = session.erreur?.reseau ?? false;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: RadialGradient(
              center: Alignment(0, -0.3),
              radius: 1.1,
              colors: <Color>[Color(0xFF2B4BD8), Color(0xFF0B1638)],
            ),
          ),
          child: SafeArea(
            child: Column(
              children: <Widget>[
                const Spacer(flex: 3),
                SizedBox(
                  width: 200,
                  height: 200,
                  child: Stack(
                    alignment: Alignment.center,
                    children: <Widget>[
                      for (final double decalage in <double>[0, 0.5])
                        _Onde(animation: _ondes, decalage: decalage),
                      const _LogoCarre(),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                const ExcludeSemantics(child: _Marque()),
                const SizedBox(height: 8),
                Text(
                  'Le bon pro, au bon prix, maintenant.',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.7),
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                  ),
                ),
                const Spacer(flex: 2),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 40),
                  child: horsLigne
                      ? _HorsLigne(
                          message: session.erreur!.message,
                          onReessayer: () =>
                              ref.read(sessionProvider.notifier).restaurer(),
                        )
                      : const SizedBox(
                          width: 26,
                          height: 26,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.6,
                            color: Colors.white,
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

class _LogoCarre extends StatelessWidget {
  const _LogoCarre();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 110,
      height: 110,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(36),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x80000000),
            blurRadius: 60,
            spreadRadius: -20,
            offset: Offset(0, 30),
          ),
        ],
      ),
      child: Image.asset(
        'assets/images/logo_mark.png',
        semanticLabel: 'Parema Connect',
        fit: BoxFit.contain,
        errorBuilder: (_, _, _) => Semantics(
          label: 'Parema Connect',
          child: const Icon(
            Icons.handyman_rounded,
            color: PcColors.brand,
            size: 48,
          ),
        ),
      ),
    );
  }
}

class _Marque extends StatelessWidget {
  const _Marque();

  @override
  Widget build(BuildContext context) {
    return const Text.rich(
      TextSpan(
        children: <InlineSpan>[
          TextSpan(text: 'Parema '),
          TextSpan(
            text: 'Connect',
            style: TextStyle(color: PcColors.coralLight),
          ),
        ],
      ),
      style: TextStyle(
        fontFamily: PcFonts.display,
        fontWeight: FontWeight.w800,
        fontSize: 30,
        letterSpacing: -0.9,
        color: Colors.white,
      ),
    );
  }
}

/// Onde qui part du logo et s'efface : l'application « respire » pendant
/// qu'elle vérifie la session.
class _Onde extends StatelessWidget {
  const _Onde({required this.animation, required this.decalage});

  final Animation<double> animation;
  final double decalage;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: animation,
      builder: (BuildContext context, Widget? _) {
        final double t = (animation.value + decalage) % 1;

        return Opacity(
          opacity: (1 - t) * 0.55,
          child: Transform.scale(
            scale: 0.75 + t * 0.9,
            child: Container(
              width: 130,
              height: 130,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(44),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.45),
                  width: 2,
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _HorsLigne extends StatelessWidget {
  const _HorsLigne({required this.message, required this.onReessayer});

  final String message;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: PcRadius.lgAll,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const Icon(Icons.wifi_off_rounded, color: Colors.white),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          TextButton(
            onPressed: onReessayer,
            style: TextButton.styleFrom(foregroundColor: Colors.white),
            child: const Text('Réessayer'),
          ),
        ],
      ),
    );
  }
}
