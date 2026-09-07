import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/app_mode.dart';

import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/session_controller.dart';

/// Troisième étape de l'inscription : le dossier professionnel (§4).
///
/// L'écran de dépôt lui-même — spécialités, zone sur carte, photos de la pièce
/// d'identité — arrive en phase D3 : il demande l'appareil photo, la carte
/// Google Maps et le téléversement de fichiers, aucun des trois n'étant du
/// ressort de D1.
///
/// Ce qui est ici est ce qui manquerait le plus sans lui : un technicien qui
/// vient de créer son compte doit **savoir où il en est**. Le laisser devant un
/// tableau de bord vide, sans explication, est le meilleur moyen de le perdre
/// avant même qu'il ait commencé.
class DossierTechnicienScreen extends ConsumerWidget {
  const DossierTechnicienScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Session session = ref.watch(sessionProvider);
    final String? statut = session.utilisateur?.verificationTechnicien;

    final ({IconData icone, String titre, String texte})
    contenu = switch (statut) {
      'EN_ATTENTE_VALIDATION' => (
        icone: Icons.hourglass_top_outlined,
        titre: 'Dossier en cours d\'examen',
        texte:
            'Notre équipe vérifie tes pièces. Tu recevras une notification dès '
            'que ce sera fait — en général sous 48 heures ouvrées.',
      ),
      'REJETE' => (
        icone: Icons.error_outline,
        titre: 'Dossier à corriger',
        texte:
            'Une pièce n\'a pas pu être validée. Le motif t\'a été envoyé par '
            'notification ; tu peux redéposer ton dossier.',
      ),
      'SUSPENDU' => (
        icone: Icons.pause_circle_outline,
        titre: 'Compte suspendu',
        texte:
            'Ton compte technicien est suspendu. Contacte le support depuis le '
            'numéro affiché dans ton profil.',
      ),
      _ => (
        icone: Icons.badge_outlined,
        titre: 'Complète ton dossier',
        texte:
            'Pour recevoir des demandes, il nous faut tes spécialités, ta zone '
            'd\'intervention et une pièce d\'identité. Le dépôt arrive en '
            'phase D3.',
      ),
    };

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dossier technicien'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Se déconnecter',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(sessionProvider.notifier).deconnecter(),
          ),
        ],
      ),
      body: Column(
        children: <Widget>[
          Expanded(
            child: DmVide(
              icone: contenu.icone,
              titre: contenu.titre,
              texte: contenu.texte,
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(DmSpace.x6),
            child: Column(
              children: <Widget>[
                // La seule action réellement utile en attendant : redemander
                // au serveur où en est le dossier. Un technicien validé pendant
                // qu'il regarde l'écran ne doit pas avoir à redémarrer
                // l'application pour s'en apercevoir.
                DmBouton(
                  libelle: 'Actualiser',
                  onPresse: () =>
                      ref.read(sessionProvider.notifier).restaurer(),
                ),
                if (session.utilisateur?.estClient ?? false) ...<Widget>[
                  const SizedBox(height: DmSpace.x3),
                  DmBouton(
                    // Proposé uniquement aux comptes qui ont aussi la casquette
                    // client : à l'inscription en technicien, le serveur ne la
                    // donne pas, et offrir un bouton qui ne mène nulle part
                    // serait pire que ne rien offrir.
                    libelle: 'Continuer en mode Client',
                    secondaire: true,
                    onPresse: () async {
                      await ref
                          .read(modeProvider.notifier)
                          .basculer(AppMode.client);
                      if (context.mounted) context.go('/accueil');
                    },
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
