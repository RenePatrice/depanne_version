import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/client_providers.dart';
import '../data/modeles.dart';

/// Mes demandes, en cours et passées.
///
/// L'écran est monté comme onglet de l'accueil : il ne porte donc pas de
/// `Scaffold`, la coquille est celle de l'accueil.
///
/// Les interventions vivantes sont annoncées en tête, avant l'historique :
/// c'est ce que le client vient chercher en ouvrant l'application. Lui faire
/// dérouler trois mois de dépannages pour retrouver celui d'aujourd'hui serait
/// classer par date d'archivage plutôt que par urgence.
class HistoriqueScreen extends ConsumerWidget {
  const HistoriqueScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<Ticket>> tickets = ref.watch(ticketsProvider(false));

    return tickets.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (Object e, StackTrace _) => Padding(
        padding: const EdgeInsets.all(DmSpace.x6),
        child: Center(
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(ticketsProvider),
          ),
        ),
      ),
      data: (List<Ticket> liste) {
        if (liste.isEmpty) {
          return DmVide(
            icone: Icons.receipt_long_outlined,
            titre: 'Aucune demande',
            texte:
                'Tes interventions apparaîtront ici, en cours comme passées.',
            action: DmBouton(
              libelle: 'Faire une demande',
              onPresse: () => context.push('/client/catalogue'),
            ),
          );
        }

        final List<Ticket> enCours = liste
            .where((Ticket t) => !t.etatFinal)
            .toList();
        final List<Ticket> passees = liste
            .where((Ticket t) => t.etatFinal)
            .toList();

        return RefreshIndicator(
          // Le geste que tout le monde connaît, et le seul recours quand le
          // réseau est revenu sans que l'application s'en aperçoive.
          onRefresh: () async => ref.invalidate(ticketsProvider),
          child: ListView(
            padding: const EdgeInsets.all(DmSpace.x4),
            children: <Widget>[
              if (enCours.isNotEmpty) ...<Widget>[
                const _Entete('En cours'),
                for (final Ticket t in enCours) _LigneTicket(ticket: t),
              ],
              if (passees.isNotEmpty) ...<Widget>[
                const _Entete('Terminées'),
                for (final Ticket t in passees) _LigneTicket(ticket: t),
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

class _LigneTicket extends StatelessWidget {
  const _LigneTicket({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x3),
      child: DmCarte(
        onTape: () => context.push('/client/suivi/${ticket.id}'),
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
                _Pastille(ticket: ticket),
              ],
            ),
            const SizedBox(height: DmSpace.x1),
            Text(ticket.adresseLisible, style: theme.textTheme.bodySmall),
            const SizedBox(height: DmSpace.x3),
            Row(
              children: <Widget>[
                Text(
                  ticket.prix.totalFormate,
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
            // Ce qui attend le client est dit sur la carte, pas seulement
            // derrière un clic : une demande qui attend un paiement ou une
            // décision doit se voir depuis la liste.
            if (ticket.attendUneDecision)
              const _Appel('Un supplément attend ta décision'),
            if (ticket.aPayer) const _Appel('Intervention à régler'),
            if (ticket.aValider) const _Appel('À valider pour clore'),
          ],
        ),
      ),
    );
  }
}

class _Appel extends StatelessWidget {
  const _Appel(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: DmSpace.x3),
    child: Row(
      children: <Widget>[
        const Icon(Icons.info_outline, size: 16, color: DmColors.warning),
        const SizedBox(width: DmSpace.x2),
        Expanded(
          child: Text(
            texte,
            style: Theme.of(context).textTheme.bodySmall
                ?.copyWith(color: DmColors.warning),
          ),
        ),
      ],
    ),
  );
}

/// Badge d'état.
///
/// Jamais la couleur seule : elle ne dit rien à qui ne la distingue pas, et le
/// libellé — celui du serveur, pas une traduction locale qui divergerait —
/// porte l'information.
class _Pastille extends StatelessWidget {
  const _Pastille({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final Color couleur = switch (ticket.etat) {
      'CLOTUREE' => DmColors.success,
      'SANS_REPONSE' || 'LITIGE_OUVERT' => DmColors.danger,
      'TERMINEE' || 'DIAGNOSTIC_EN_ATTENTE' => DmColors.warning,
      final String e when e.startsWith('ANNULEE') => DmColors.neutral500,
      _ => Theme.of(context).colorScheme.primary,
    };

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: DmSpace.x2,
        vertical: DmSpace.x1,
      ),
      decoration: BoxDecoration(
        color: couleur.withValues(alpha: 0.12),
        borderRadius: DmRadius.pillAll,
      ),
      child: Text(
        ticket.etatLibelle,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(color: couleur),
      ),
    );
  }
}
