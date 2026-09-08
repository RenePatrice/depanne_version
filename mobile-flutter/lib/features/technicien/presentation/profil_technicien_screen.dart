import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../auth/application/session_controller.dart';
import '../../auth/data/utilisateur.dart';

/// Profil technicien : dossier, spécialités et statistiques.
///
/// Les chiffres ne sont pas décoratifs — ils décident de qui reçoit les
/// courses (§8.3). Les montrer, c'est donner au technicien les moyens de
/// comprendre pourquoi il en reçoit plus ou moins, plutôt que de lui laisser
/// imaginer une raison qui n'existe pas.
class ProfilTechnicienScreen extends ConsumerWidget {
  const ProfilTechnicienScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Session session = ref.watch(sessionProvider);
    final Utilisateur? moi = session.utilisateur;
    final ProfilTechnicien? profil = moi?.profilTechnicien;
    final ThemeData theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.all(DmSpace.x4),
      children: <Widget>[
        DmCarte(
          enfant: Row(
            children: <Widget>[
              CircleAvatar(
                radius: 26,
                backgroundColor: theme.colorScheme.primary.withValues(
                  alpha: 0.12,
                ),
                child: Icon(
                  Icons.handyman_outlined,
                  color: theme.colorScheme.primary,
                ),
              ),
              const SizedBox(width: DmSpace.x4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      moi?.nomComplet ?? 'Mon compte',
                      style: theme.textTheme.titleMedium,
                    ),
                    Text(
                      moi?.telephoneAffichage ?? '',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: DmSpace.x4),
        _Dossier(session: session),

        if (profil != null) ...<Widget>[
          if (profil.specialites.isNotEmpty) ...<Widget>[
            const _Entete('Mes spécialités'),
            Wrap(
              spacing: DmSpace.x2,
              runSpacing: DmSpace.x2,
              children: <Widget>[
                for (final (String _, String libelle) in profil.specialites)
                  Chip(label: Text(libelle)),
              ],
            ),
          ],

          const _Entete('Mes chiffres'),
          _Statistiques(profil: profil),
        ],

        const SizedBox(height: DmSpace.x8),
        Center(
          child: TextButton.icon(
            onPressed: () => ref.read(sessionProvider.notifier).deconnecter(),
            icon: const Icon(Icons.logout),
            label: const Text('Se déconnecter'),
          ),
        ),
        const SizedBox(height: DmSpace.x8),
      ],
    );
  }
}

class _Entete extends StatelessWidget {
  const _Entete(this.titre);

  final String titre;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(
      DmSpace.x2,
      DmSpace.x6,
      DmSpace.x2,
      DmSpace.x3,
    ),
    child: Text(titre, style: Theme.of(context).textTheme.titleLarge),
  );
}

/// L'état du dossier, en clair.
///
/// Un technicien dont le dossier est en attente ne peut pas se mettre en
/// ligne : le lui dire ici, sur la carte qui porte son statut, évite qu'il
/// cherche la panne du côté de l'interrupteur.
class _Dossier extends StatelessWidget {
  const _Dossier({required this.session});

  final Session session;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool peutTravailler = session.utilisateur?.peutTravailler ?? false;
    final String statut =
        session.utilisateur?.verificationTechnicien ?? 'INCONNU';

    final Color couleur = peutTravailler
        ? DmColors.success
        : statut == 'REJETE'
        ? DmColors.danger
        : DmColors.warning;

    return DmCarte(
      onTape: peutTravailler ? null : () => context.push('/dossier-technicien'),
      enfant: Row(
        children: <Widget>[
          Icon(
            peutTravailler ? Icons.verified_outlined : Icons.pending_outlined,
            color: couleur,
          ),
          const SizedBox(width: DmSpace.x3),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  peutTravailler ? 'Dossier validé' : 'Dossier à compléter',
                  style: theme.textTheme.titleMedium?.copyWith(color: couleur),
                ),
                Text(
                  peutTravailler
                      ? 'Tu peux te mettre en ligne et recevoir des demandes.'
                      : 'Tant qu\'il n\'est pas validé, tu ne peux pas te '
                            'mettre en ligne.',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          if (!peutTravailler)
            const Icon(Icons.chevron_right, color: DmColors.neutral500),
        ],
      ),
    );
  }
}

class _Statistiques extends StatelessWidget {
  const _Statistiques({required this.profil});

  final ProfilTechnicien profil;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: _Chiffre(
                libelle: 'Note',
                valeur: profil.avis == 0
                    ? '—'
                    : profil.noteMoyenne.toStringAsFixed(1),
                detail: '${profil.avis} avis',
              ),
            ),
            Expanded(
              child: _Chiffre(
                libelle: 'Interventions',
                valeur: '${profil.interventions}',
                detail: 'terminées',
              ),
            ),
          ],
        ),
        const SizedBox(height: DmSpace.x3),
        Row(
          children: <Widget>[
            Expanded(
              child: _Chiffre(
                libelle: 'Acceptation',
                valeur: '${(profil.tauxAcceptation * 100).round()} %',
                detail: 'des demandes reçues',
              ),
            ),
            Expanded(
              child: _Chiffre(
                libelle: 'Annulation',
                valeur: '${(profil.tauxAnnulation * 100).round()} %',
                detail: 'après acceptation',
              ),
            ),
          ],
        ),

        if (profil.debutant) ...<Widget>[
          const SizedBox(height: DmSpace.x4),
          DmCarte(
            enfant: Row(
              children: <Widget>[
                const Icon(Icons.info_outline, size: 18),
                const SizedBox(width: DmSpace.x3),
                Expanded(
                  child: Text(
                    // La note appliquée n'est pas la note affichée : le
                    // scoring en substitue une neutre aux nouveaux venus,
                    // sinon un technicien sans avis ne serait jamais sollicité
                    // — donc n'aurait jamais d'avis.
                    'Tu débutes : en attendant tes premiers avis, tu es classé '
                    'avec une note neutre de '
                    '${profil.noteAppliquee.toStringAsFixed(1)}.',
                    style: theme.textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ),
        ],

        const SizedBox(height: DmSpace.x4),
        Text(
          'Ta note pèse 30 % dans le choix du technicien à qui une demande est '
          'proposée. Ton taux d\'acceptation et ta distance comptent aussi.',
          style: theme.textTheme.bodySmall,
        ),
      ],
    );
  }
}

class _Chiffre extends StatelessWidget {
  const _Chiffre({
    required this.libelle,
    required this.valeur,
    required this.detail,
  });

  final String libelle;
  final String valeur;
  final String detail;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(libelle, style: theme.textTheme.labelSmall),
          const SizedBox(height: DmSpace.x1),
          Text(
            valeur,
            style: theme.textTheme.headlineSmall?.copyWith(
              color: theme.colorScheme.primary,
            ),
          ),
          Text(detail, style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}
