import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_mode.dart';
import '../../core/providers.dart';
import '../../core/temps_reel/temps_reel_providers.dart';
import '../../core/temps_reel/temps_reel_service.dart';
import '../../design_system/pc.dart';
import '../auth/application/session_controller.dart';
import '../client/presentation/accueil_client_vue.dart';
import '../client/presentation/explorer_vue.dart';
import '../client/presentation/historique_screen.dart';
import '../client/presentation/messages_vue.dart';
import '../client/presentation/profil_client_screen.dart';
import '../notifications/application/notifications_providers.dart';
import '../technicien/presentation/demandes_screen.dart';
import '../technicien/presentation/mes_interventions_screen.dart';
import '../technicien/presentation/portefeuille_screen.dart';
import '../technicien/presentation/profil_technicien_screen.dart';

/// Coquille des deux espaces, avec la barre d'onglets flottante (prototype v4).
///
/// Client : Accueil, Explorer, Commandes, Messages, Profil — en clair, accent
/// bleu. Technicien : Tableau, Missions, Gains, Profil — en sombre, accent
/// vert. On sait dans quel espace on se trouve sans lire un mot.
class AccueilScreen extends ConsumerStatefulWidget {
  const AccueilScreen({super.key});

  @override
  ConsumerState<AccueilScreen> createState() => _AccueilScreenState();
}

class _AccueilScreenState extends ConsumerState<AccueilScreen> {
  int _onglet = 0;
  AppMode? _modePrecedent;

  @override
  Widget build(BuildContext context) {
    final AppMode mode = ref.watch(modeProvider);
    final Session session = ref.watch(sessionProvider);
    final PcSkin pc = context.pc;

    // Changer d'espace remet l'onglet à zéro : le même index ne veut pas dire
    // la même chose d'un espace à l'autre.
    if (_modePrecedent != null && _modePrecedent != mode) _onglet = 0;
    _modePrecedent = mode;

    // Toute notification poussée sur le canal personnel rallume la cloche.
    ref.listen(signauxProvider('utilisateur.${session.utilisateur?.id}'), (
      _,
      AsyncValue<EvenementTempsReel> suivant,
    ) {
      if (suivant.value?.nom == 'notification') {
        ref.invalidate(notificationsProvider);
      }
    });

    final bool client = mode == AppMode.client;
    final List<PcOnglet> onglets = client ? _ongletsClient : _ongletsTechnicien;
    final int index = _onglet.clamp(0, onglets.length - 1);

    final List<Widget> vues = client
        ? <Widget>[
            AccueilClientVue(onExplorer: () => setState(() => _onglet = 1)),
            const ExplorerVue(),
            const HistoriqueScreen(),
            const MessagesVue(),
            const ProfilClientScreen(),
          ]
        : const <Widget>[
            DemandesScreen(),
            MesInterventionsScreen(),
            PortefeuilleScreen(),
            ProfilTechnicienScreen(),
          ];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: pc.estSombre
          ? SystemUiOverlayStyle.light
          : SystemUiOverlayStyle.dark,
      child: Scaffold(
        extendBody: true,
        body: Stack(
          children: <Widget>[
            // Les onglets restent vivants d'un passage à l'autre : revenir sur
            // une liste ne relance pas un chargement déjà payé, et le compte à
            // rebours d'une sollicitation continue de tourner.
            Positioned.fill(
              child: IndexedStack(index: index, children: vues),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: PcTabBar(
                onglets: onglets,
                index: index,
                onChanged: (int i) => setState(() => _onglet = i),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static const List<PcOnglet> _ongletsClient = <PcOnglet>[
    PcOnglet(Icons.home_rounded, 'Accueil'),
    PcOnglet(Icons.explore_outlined, 'Explorer'),
    PcOnglet(Icons.receipt_long_outlined, 'Commandes'),
    PcOnglet(Icons.chat_bubble_outline_rounded, 'Messages'),
    PcOnglet(Icons.person_outline_rounded, 'Profil'),
  ];

  static const List<PcOnglet> _ongletsTechnicien = <PcOnglet>[
    PcOnglet(Icons.space_dashboard_outlined, 'Tableau'),
    PcOnglet(Icons.work_outline_rounded, 'Missions'),
    PcOnglet(Icons.account_balance_wallet_outlined, 'Gains'),
    PcOnglet(Icons.person_outline_rounded, 'Profil'),
  ];
}
