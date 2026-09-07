import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/app_mode.dart';
import '../../core/providers.dart';
import '../../design_system/components/dm_composants.dart';
import '../auth/application/session_controller.dart';
import 'mode/selecteur_mode.dart';

/// Coquille d'accueil, commune aux deux modes.
///
/// Elle porte la navigation, l'identité visuelle du mode et la bascule ; les
/// onglets sont vides à dessein. Les remplir est le travail des phases D2
/// (parcours client) et D3 (parcours technicien) — les livrer à moitié ici
/// donnerait l'illusion d'une application terminée.
///
/// Les libellés d'onglets sont déjà les bons : le jour où le contenu arrive, la
/// navigation ne bouge pas, et personne n'a à réapprendre où sont les choses.
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
      body: DmVide(
        icone: onglets[index].icone,
        titre: onglets[index].libelle,
        texte: onglets[index].aVenir,
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
    _Onglet(
      Icons.add_circle_outline,
      'Demander',
      'Le catalogue des prestations, le choix de l\'adresse et le prix arrivent '
          'en phase D2.',
    ),
    _Onglet(
      Icons.receipt_long_outlined,
      'Mes demandes',
      'Le suivi de tes interventions, en cours et passées, arrive en phase D2.',
    ),
    _Onglet(
      Icons.person_outline,
      'Profil',
      'Adresses, moyens de paiement et paramètres arrivent en phase D2.',
    ),
  ];

  static const List<_Onglet> _ongletsTechnicien = <_Onglet>[
    _Onglet(
      Icons.notifications_active_outlined,
      'Demandes',
      'La réception des demandes et la fenêtre de réponse de 45 secondes '
          'arrivent en phase D3.',
    ),
    _Onglet(
      Icons.build_outlined,
      'Intervention',
      'Le suivi d\'intervention — en route, sur place, terminée — arrive en '
          'phase D3.',
    ),
    _Onglet(
      Icons.account_balance_wallet_outlined,
      'Portefeuille',
      'Ton solde, tes mouvements et tes demandes de retrait arrivent en '
          'phase D3.',
    ),
    _Onglet(
      Icons.person_outline,
      'Profil',
      'Spécialités, zone d\'intervention et statistiques arrivent en phase D3.',
    ),
  ];
}

class _Onglet {
  const _Onglet(this.icone, this.libelle, this.aVenir);

  final IconData icone;
  final String libelle;
  final String aVenir;
}
