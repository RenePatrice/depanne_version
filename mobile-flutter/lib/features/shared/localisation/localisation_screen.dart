import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';

import '../../../core/app_mode.dart';
import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import 'localisation_controller.dart';

/// Demande d'accès à la position, à l'entrée dans l'application.
///
/// L'écran explique **avant** de demander : quelqu'un à qui l'on demande sa
/// position sans savoir pourquoi refuse, et un refus définitif est difficile
/// à rattraper.
class LocalisationScreen extends ConsumerStatefulWidget {
  const LocalisationScreen({super.key});

  @override
  ConsumerState<LocalisationScreen> createState() => _LocalisationScreenState();
}

class _LocalisationScreenState extends ConsumerState<LocalisationScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ondes = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  )..repeat();
  bool _demande = false;

  @override
  void dispose() {
    _ondes.dispose();
    super.dispose();
  }

  Future<void> _autoriser() async {
    setState(() => _demande = true);
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
    } catch (_) {
      // Service indisponible (émulateur sans GPS…) : on poursuit, la position
      // sera redemandée au moment où elle sert.
    }
    await _terminer();
  }

  Future<void> _terminer() async {
    await ref.read(localisationDemandeeProvider.notifier).marquer();
    if (mounted) context.go('/accueil');
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool technicien = ref.watch(modeProvider) == AppMode.technicien;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
          child: Column(
            children: <Widget>[
              const Spacer(),
              SizedBox(
                width: 220,
                height: 220,
                child: Stack(
                  alignment: Alignment.center,
                  children: <Widget>[
                    for (final double d in <double>[0, 0.33, 0.66])
                      AnimatedBuilder(
                        animation: _ondes,
                        builder: (BuildContext context, Widget? _) {
                          final double t = (_ondes.value + d) % 1;

                          return Opacity(
                            opacity: 1 - t,
                            child: Transform.scale(
                              scale: 0.25 + t * 0.75,
                              child: Container(
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    color: pc.accent.withValues(alpha: 0.3),
                                    width: 1.5,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    Container(
                      width: 68,
                      height: 68,
                      decoration: BoxDecoration(
                        gradient: pc.gradientAccent,
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: PcShadow.glow(pc.accent),
                      ),
                      child: const Icon(
                        Icons.near_me_rounded,
                        color: Colors.white,
                        size: 30,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Activez votre position',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 12),
              Text(
                technicien
                    ? 'Pour recevoir les demandes proches de vous et calculer '
                          'le déplacement. Elle n’est partagée avec un client '
                          'que pendant une mission.'
                    : 'Pour trouver les techniciens les plus proches et '
                          'calculer précisément le déplacement. Votre position '
                          'reste privée.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: pc.muted,
                  fontSize: 14.5,
                  height: 1.6,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const Spacer(),
              PcButton(
                libelle: 'Autoriser la localisation',
                icone: Icons.place_rounded,
                variante: technicien ? PcVariante.tech : PcVariante.accent,
                chargement: _demande,
                onPressed: _autoriser,
              ),
              const SizedBox(height: 10),
              PcButton(
                libelle: 'Plus tard',
                variante: PcVariante.doux,
                onPressed: _demande ? null : _terminer,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
