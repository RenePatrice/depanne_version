import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_mode.dart';
import '../../core/providers.dart';
import '../auth/application/session_controller.dart';
import '../client/presentation/catalogue_screen.dart';
import '../client/presentation/historique_screen.dart';
import '../client/presentation/profil_client_screen.dart';
import '../technicien/presentation/demandes_screen.dart';
import '../technicien/presentation/mes_interventions_screen.dart';
import '../technicien/presentation/portefeuille_screen.dart';
import '../technicien/presentation/profil_technicien_screen.dart';
import 'mode/selecteur_mode.dart';

/// Coquille d'accueil, commune aux deux modes.
///
/// Elle porte la navigation, l'identité visuelle du mode et la bascule ; les
/// deux jeux d'onglets sont livrés — client en D2, technicien en D3.
///
/// Le changement de mode remet l'onglet à zéro et change la couleur d'accent :
/// c'est ce qui permet de savoir dans quel mode on se trouve sans lire. Un
/// technicien qui se croit en mode client cherche un bouton qui n'existe pas.
class AccueilScreen extends ConsumerStatefulWidget {
  const AccueilScreen({super.key});

  @override
  ConsumerState<AccueilScreen> createState() => _AccueilScreenState();
}

class _AccueilScreenState extends ConsumerState<AccueilScreen> {
  int _onglet = 0;

  @override
  Widget build(BuildContext context) {
    final AppMode mode = ref.watch(modeProvider);
    final Session session = ref.watch(sessionProvider);

    // Le changement de mode remet l'onglet à zéro : garder l'index d'un mode
    // à l'autre pointerait vers un onglet qui ne veut pas dire la même chose.
    final List<_Onglet> onglets = mode == AppMode.client
        ? _ongletsClient
        : _ongletsTechnicien;

    final int index = _onglet.clamp(0, onglets.length - 1);
    final bool peutBasculer =
        (session.utilisateur?.modesDisponibles.length ?? 1) > 1;

    return Scaffold(
      appBar: AppBar(
        title: Text('Mode ${mode.libelle}'),
        actions: <Widget>[
          if (peutBasculer)
            IconButton(
              tooltip: 'Changer de mode',
              icon: const Icon(Icons.swap_horiz),
              onPressed: () async {
                final AppMode? choisi = await SelecteurMode.ouvrir(context);
                if (choisi != null && mounted) setState(() => _onglet = 0);
              },
            ),
          IconButton(
            tooltip: 'Se déconnecter',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(sessionProvider.notifier).deconnecter(),
          ),
        ],
      ),
      // Les onglets sont conservés vivants d'un passage à l'autre : revenir
      // sur « Mes demandes » ne doit pas relancer un chargement que l'on vient
      // de payer, ni perdre la position dans la liste. Côté technicien, cela
      // garde aussi vivants le compte à rebours d'une sollicitation et le
      // suivi GPS d'une intervention en cours.
      body: mode == AppMode.client
          ? IndexedStack(
              index: index,
              children: const <Widget>[
                CatalogueVue(),
                HistoriqueScreen(),
                ProfilClientScreen(),
              ],
            )
          : IndexedStack(
              index: index,
              children: const <Widget>[
                DemandesScreen(),
                MesInterventionsScreen(),
                PortefeuilleScreen(),
                ProfilTechnicienScreen(),
              ],
            ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (int i) => setState(() => _onglet = i),
        destinations: <Widget>[
          for (final _Onglet o in onglets)
            NavigationDestination(icon: Icon(o.icone), label: o.libelle),
        ],
      ),
    );
  }

  static const List<_Onglet> _ongletsClient = <_Onglet>[
    _Onglet(Icons.add_circle_outline, 'Demander'),
    _Onglet(Icons.receipt_long_outlined, 'Mes demandes'),
    _Onglet(Icons.person_outline, 'Profil'),
  ];

  static const List<_Onglet> _ongletsTechnicien = <_Onglet>[
    _Onglet(Icons.notifications_active_outlined, 'Demandes'),
    _Onglet(Icons.build_outlined, 'Intervention'),
    _Onglet(Icons.account_balance_wallet_outlined, 'Portefeuille'),
    _Onglet(Icons.person_outline, 'Profil'),
  ];
}

class _Onglet {
  const _Onglet(this.icone, this.libelle);

  final IconData icone;
  final String libelle;
}
