import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/position_service.dart';
import '../application/technicien_providers.dart';

/// Mes interventions, en cours et passées.
///
/// C'est aussi l'écran qui décide du suivi GPS : tant qu'une intervention est
/// vivante, la position remonte toutes les huit secondes ; dès qu'il n'y en a
/// plus, elle s'arrête. Lier le suivi à l'existence d'une intervention plutôt
/// qu'à un interrupteur évite le cas où un technicien oublie de le couper et
/// se retrouve pisté toute la nuit.
class MesInterventionsScreen extends ConsumerStatefulWidget {
  const MesInterventionsScreen({super.key});

  @override
  ConsumerState<MesInterventionsScreen> createState() =>
      _MesInterventionsScreenState();
}

class _MesInterventionsScreenState
    extends ConsumerState<MesInterventionsScreen> {
  bool _suitDejaUneIntervention = false;

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
    final AsyncValue<List<Ticket>> interventions = ref.watch(
      interventionsProvider(false),
    );

    return interventions.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object e, StackTrace _) => Padding(
        padding: const EdgeInsets.all(DmSpace.x6),
        child: Center(
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(interventionsProvider),
          ),
        ),
      ),
      data: (List<Ticket> liste) {
        WidgetsBinding.instance.addPostFrameCallback(
          (_) => _accorderLeSuivi(liste),
        );

        if (liste.isEmpty) {
          return const DmVide(
            icone: Icons.build_outlined,
            titre: 'Aucune intervention',
            texte:
                'Mets-toi en ligne depuis l\'onglet Demandes : les demandes de '
                'ton quartier arriveront là-bas.',
          );
        }

        final List<Ticket> enCours = liste
            .where((Ticket t) => !t.etatFinal)
            .toList();
        final List<Ticket> passees = liste
            .where((Ticket t) => t.etatFinal)
            .toList();

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(interventionsProvider),
          child: ListView(
            padding: const EdgeInsets.all(DmSpace.x4),
            children: <Widget>[
              if (enCours.isNotEmpty) ...<Widget>[
                const _Entete('En cours'),
                for (final Ticket t in enCours) _Ligne(ticket: t),
              ],
              if (passees.isNotEmpty) ...<Widget>[
                const _Entete('Terminées'),
                for (final Ticket t in passees) _Ligne(ticket: t),
              ],
              const SizedBox(height: DmSpace.x8),
            ],
          ),
        );
      },
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
      DmSpace.x4,
      DmSpace.x2,
      DmSpace.x3,
    ),
    child: Text(titre, style: Theme.of(context).textTheme.titleLarge),
  );
}

class _Ligne extends StatelessWidget {
  const _Ligne({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int? net = ticket.prix.netTechnicienGnf;

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x3),
      child: DmCarte(
        onTape: () => context.push('/technicien/intervention/${ticket.id}'),
        enfant: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Text(
                    ticket.prestation?.nom ?? ticket.reference,
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                const SizedBox(width: DmSpace.x3),
                Text(
                  ticket.etatLibelle,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: ticket.etatFinal
                        ? DmColors.neutral500
                        : theme.colorScheme.primary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: DmSpace.x1),
            Text(ticket.adresseLisible, style: theme.textTheme.bodySmall),
            const SizedBox(height: DmSpace.x3),
            Row(
              children: <Widget>[
                Text(
                  // Le net, pas le total : c'est ce qui arrive sur le
                  // portefeuille, et donc le chiffre que le technicien cherche.
                  net == null ? ticket.prix.totalFormate : formaterGnf(net),
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: theme.colorScheme.primary,
                  ),
                ),
                const Spacer(),
                Text(ticket.reference, style: theme.textTheme.labelSmall),
                const SizedBox(width: DmSpace.x1),
                const Icon(
                  Icons.chevron_right,
                  size: 18,
                  color: DmColors.neutral500,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
