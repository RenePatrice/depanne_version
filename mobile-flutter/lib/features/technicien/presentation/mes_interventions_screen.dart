import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/position_service.dart';
import '../application/technicien_providers.dart';
import 'composants_technicien.dart';

/// Onglet « Missions » : en cours, puis terminées.
///
/// C'est aussi l'écran qui décide du suivi GPS — il reste monté dans la
/// coquille, quel que soit l'onglet affiché. Tant qu'une intervention est
/// vivante, la position remonte toutes les huit secondes ; dès qu'il n'y en a
/// plus, elle s'arrête (ADR-0038). Lier le suivi à l'existence d'une
/// intervention plutôt qu'à un interrupteur évite qu'un technicien oublie de
/// le couper et se retrouve pisté toute la nuit.
class MesInterventionsScreen extends ConsumerStatefulWidget {
  const MesInterventionsScreen({super.key});

  @override
  ConsumerState<MesInterventionsScreen> createState() =>
      _MesInterventionsScreenState();
}

class _MesInterventionsScreenState
    extends ConsumerState<MesInterventionsScreen> {
  bool _suitDejaUneIntervention = false;
  int _filtre = 0;

  /// Aligne le suivi GPS sur ce que l'écran vient d'apprendre du serveur.
  ///
  /// Idempotent à dessein : la liste se recharge souvent, et redémarrer le
  /// flux à chaque rendu rallumerait le GPS en boucle.
  void _accorderLeSuivi(List<Ticket> tickets) {
    final bool enIntervention = tickets.any((Ticket t) => t.etatActif);

    if (enIntervention == _suitDejaUneIntervention) return;

    _suitDejaUneIntervention = enIntervention;

    final PositionService service = ref.read(positionServiceProvider);

    unawaited(
      enIntervention
          ? service.demarrer(enIntervention: true)
          : service.arreter(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<Ticket>> interventions = ref.watch(
      interventionsProvider(false),
    );

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async => ref.invalidate(interventionsProvider),
      child: ListView(
        padding: margesOnglet(context),
        children: <Widget>[
          const EnteteTechnicien(titre: 'Missions'),
          const SizedBox(height: 16),
          interventions.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => PcErreur(
              message: 'Vos missions n’ont pas pu être chargées.',
              onReessayer: () => ref.invalidate(interventionsProvider),
            ),
            data: (List<Ticket> liste) {
              WidgetsBinding.instance.addPostFrameCallback(
                (_) => _accorderLeSuivi(liste),
              );
              return _contenu(liste);
            },
          ),
        ],
      ),
    );
  }

  Widget _contenu(List<Ticket> liste) {
    final PcSkin pc = context.pc;

    if (liste.isEmpty) {
      return PcCard(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: <Widget>[
            PcIco(Icons.work_outline_rounded, couleur: pc.accent),
            const SizedBox(height: 12),
            Text(
              'Aucune mission pour l’instant',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            Text(
              'Mettez-vous en ligne depuis le tableau de bord : les clients '
              'proches pourront vous choisir.',
              textAlign: TextAlign.center,
              style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      );
    }

    final List<Ticket> enCours = liste
        .where((Ticket t) => !t.etatFinal)
        .toList();
    final List<Ticket> passees = liste
        .where((Ticket t) => t.etatFinal)
        .toList();
    final List<Ticket> affichees = _filtre == 0 ? enCours : passees;

    // Gagné sur les missions closes : la somme des nets réellement servis par
    // le serveur, rien d'estimé.
    final int gagne = passees
        .where((Ticket t) => t.etat == 'CLOTUREE')
        .fold<int>(0, (int n, Ticket t) => n + (t.prix.netTechnicienGnf ?? 0));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            ChiffreTech(
              valeur: '${enCours.length}',
              libelle: 'En cours',
              icone: Icons.timelapse_rounded,
            ),
            const SizedBox(width: 10),
            ChiffreTech(
              valeur:
                  '${passees.where((Ticket t) => t.etat == 'CLOTUREE').length}',
              libelle: 'Réussies',
              icone: Icons.done_all_rounded,
            ),
            const SizedBox(width: 10),
            ChiffreTech(
              valeur: formaterCourt(gagne),
              libelle: 'Net gagné',
              icone: Icons.payments_rounded,
            ),
          ],
        ),
        const SizedBox(height: 16),
        PcSegmented(
          options: <String>[
            'En cours (${enCours.length})',
            'Terminées (${passees.length})',
          ],
          index: _filtre,
          onChanged: (int i) => setState(() => _filtre = i),
        ),
        const SizedBox(height: 14),
        if (affichees.isEmpty)
          Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              _filtre == 0
                  ? 'Aucune mission en cours.'
                  : 'Aucune mission terminée pour l’instant.',
              textAlign: TextAlign.center,
              style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
            ),
          )
        else
          for (final Ticket t in affichees) ...<Widget>[
            _Ligne(ticket: t),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

/// Montant abrégé pour une tuile étroite : « 1,2 M », « 450 k ».
String formaterCourt(int gnf) {
  if (gnf >= 1000000) {
    return '${(gnf / 1000000).toStringAsFixed(1).replaceAll('.', ',')} M';
  }
  if (gnf >= 1000) return '${(gnf / 1000).round()} k';
  return '$gnf';
}

class _Ligne extends StatelessWidget {
  const _Ligne({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int? net = ticket.prix.netTechnicienGnf;
    final Color couleur = ticket.etatFinal
        ? (ticket.etat == 'CLOTUREE' ? PcColors.tech : pc.muted)
        : pc.accent;

    return PcCard(
      onTap: () => context.push('/technicien/intervention/${ticket.id}'),
      bordure: ticket.etatActif ? pc.accent.withValues(alpha: 0.5) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              PcIco(
                ticket.etatActif
                    ? Icons.handyman_rounded
                    : ticket.etat == 'CLOTUREE'
                    ? Icons.done_all_rounded
                    : Icons.history_rounded,
                couleur: couleur,
                taille: 42,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      ticket.prestation?.nom ?? ticket.reference,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    Text(
                      '${ticket.reference} · ${ticket.adresseLisible}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: pc.muted, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              // Le net, pas le total : c'est ce qui arrive au portefeuille,
              // donc le chiffre que le technicien cherche.
              PcMontant(net ?? ticket.prix.totalGnf, taille: 15),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Flexible(child: PcPill(ticket.etatLibelle, couleur: couleur)),
              if (ticket.estUnRetourGarantie) ...<Widget>[
                const SizedBox(width: 6),
                const PcPill(
                  'Garantie',
                  icone: Icons.verified_user_rounded,
                  couleur: PcColors.coral,
                ),
              ],
              const Spacer(),
              Icon(Icons.chevron_right_rounded, color: pc.muted, size: 20),
            ],
          ),
        ],
      ),
    );
  }
}
