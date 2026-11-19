import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../../auth/data/utilisateur.dart';
import '../../shared/mode/selecteur_mode.dart';
import '../../shared/profil/profil_commun.dart';
import 'composants_technicien.dart';

/// Onglet « Profil » du technicien : identité, dossier, métiers, chiffres,
/// tarifs, compte.
///
/// Les chiffres ne sont pas décoratifs — ils décident de qui reçoit les
/// courses (§8.3). Les montrer, c'est donner au technicien les moyens de
/// comprendre pourquoi il en reçoit plus ou moins.
class ProfilTechnicienScreen extends ConsumerWidget {
  const ProfilTechnicienScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final Utilisateur? moi = ref.watch(sessionProvider).utilisateur;
    final ProfilTechnicien? profil = moi?.profilTechnicien;
    final bool peutBasculer = (moi?.modesDisponibles.length ?? 1) > 1;
    final String metiers = profil == null || profil.specialites.isEmpty
        ? 'Technicien'
        : profil.specialites.map(((String, String) s) => s.$2).join(' · ');

    return ListView(
      padding: EdgeInsets.fromLTRB(
        18,
        MediaQuery.of(context).padding.top + 18,
        18,
        PcTabBar.espaceReserve,
      ),
      children: <Widget>[
        EnteteProfil(sousTitre: metiers),
        const SizedBox(height: 16),
        _Dossier(utilisateur: moi),

        if (profil != null) ...<Widget>[
          const PcSection('Vos chiffres'),
          Row(
            children: <Widget>[
              ChiffreTech(
                valeur: profil.avis == 0
                    ? '—'
                    : profil.noteMoyenne.toStringAsFixed(1),
                libelle: '${profil.avis} avis',
                icone: Icons.star_rounded,
                couleur: const Color(0xFFF5B000),
              ),
              const SizedBox(width: 10),
              ChiffreTech(
                valeur: '${profil.interventions}',
                libelle: 'Terminées',
                icone: Icons.done_all_rounded,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              ChiffreTech(
                valeur: '${(profil.tauxAcceptation * 100).round()} %',
                libelle: 'Acceptation',
                icone: Icons.thumb_up_alt_rounded,
              ),
              const SizedBox(width: 10),
              ChiffreTech(
                valeur: '${(profil.tauxAnnulation * 100).round()} %',
                libelle: 'Annulation',
                icone: Icons.cancel_rounded,
                couleur: PcColors.coral,
              ),
            ],
          ),
          if (profil.debutant) ...<Widget>[
            const SizedBox(height: 10),
            PcCard(
              child: Row(
                children: <Widget>[
                  Icon(Icons.info_outline_rounded, size: 18, color: pc.accent),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      // La note appliquée n'est pas la note affichée : le
                      // scoring en substitue une neutre aux nouveaux venus,
                      // sinon un technicien sans avis ne serait jamais choisi.
                      'Vous débutez : en attendant vos premiers avis, vous '
                      'êtes classé avec une note neutre de '
                      '${profil.noteAppliquee.toStringAsFixed(1)}.',
                      style: TextStyle(color: pc.ink2, fontSize: 12.5),
                    ),
                  ),
                ],
              ),
            ),
          ],
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 10, 4, 0),
            child: Text(
              'La note, l’acceptation et la distance décident de votre place '
              'dans la liste que voit le client.',
              style: TextStyle(color: pc.muted, fontSize: 12),
            ),
          ),
        ],

        const PcSection('Activité'),
        GroupeProfil(
          lignes: <Widget>[
            // Place de marché (#2) : le technicien fixe ses prix.
            LigneProfil(
              icone: Icons.sell_outlined,
              libelle: 'Mes tarifs',
              detail: 'Votre prix par prestation, dans les bornes autorisées',
              onTap: () => context.push('/technicien/tarifs'),
            ),
            if (profil?.rayonKm != null)
              LigneProfil(
                icone: Icons.radar_rounded,
                libelle: 'Zone d’intervention',
                detail: '${profil!.rayonKm} km autour de votre point de départ',
              ),
            LigneProfil(
              icone: Icons.notifications_none_rounded,
              libelle: 'Notifications',
              onTap: () => context.push('/notifications'),
            ),
            if (peutBasculer)
              LigneProfil(
                icone: Icons.swap_horiz_rounded,
                libelle: 'Passer à mon espace client',
                couleur: const Color(0xFF2F6BED),
                onTap: () => SelecteurMode.ouvrir(context),
              ),
          ],
        ),

        const LignesCompte(),
      ],
    );
  }
}

/// L'état du dossier, en clair : un technicien en attente ne peut pas se
/// mettre en ligne, et doit le lire ici plutôt que chercher la panne du côté
/// de l'interrupteur.
class _Dossier extends StatelessWidget {
  const _Dossier({required this.utilisateur});

  final Utilisateur? utilisateur;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool valide = utilisateur?.peutTravailler ?? false;
    final bool rejete = utilisateur?.verificationTechnicien == 'REJETE';
    final Color couleur = valide
        ? PcColors.tech
        : rejete
        ? PcColors.coral
        : pc.accent;

    return PcCard(
      onTap: valide ? null : () => context.push('/dossier-technicien'),
      bordure: couleur.withValues(alpha: 0.45),
      child: Row(
        children: <Widget>[
          PcIco(
            valide
                ? Icons.verified_rounded
                : rejete
                ? Icons.error_outline_rounded
                : Icons.hourglass_top_rounded,
            couleur: couleur,
            taille: 42,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  valide
                      ? 'Profil vérifié'
                      : rejete
                      ? 'Dossier à reprendre'
                      : 'Dossier en cours',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                Text(
                  valide
                      ? 'Pièce d’identité contrôlée par l’équipe Parema.'
                      : 'Tant qu’il n’est pas validé, vous ne pouvez pas vous '
                            'mettre en ligne.',
                  style: TextStyle(color: pc.muted, fontSize: 12.5),
                ),
              ],
            ),
          ),
          if (!valide) Icon(Icons.chevron_right_rounded, color: pc.muted),
        ],
      ),
    );
  }
}
