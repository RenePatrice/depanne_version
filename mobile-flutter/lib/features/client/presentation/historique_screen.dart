import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Onglet « Commandes » : les demandes en cours, puis l'historique.
///
/// Monté dans la coquille de l'accueil : pas de `Scaffold` ici. Les
/// interventions vivantes passent en tête — c'est ce que le client vient
/// chercher en ouvrant l'application. Lui faire dérouler trois mois de
/// dépannages pour retrouver celui d'aujourd'hui serait classer par date
/// d'archivage plutôt que par urgence.
class HistoriqueScreen extends ConsumerStatefulWidget {
  const HistoriqueScreen({super.key});

  @override
  ConsumerState<HistoriqueScreen> createState() => _HistoriqueScreenState();
}

class _HistoriqueScreenState extends ConsumerState<HistoriqueScreen> {
  int _filtre = 0;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<Ticket>> tickets = ref.watch(ticketsProvider(false));

    return RefreshIndicator(
      color: pc.accent,
      // Le geste que tout le monde connaît, et le seul recours quand le
      // réseau est revenu sans que l'application s'en aperçoive.
      onRefresh: () async => ref.invalidate(ticketsProvider),
      child: ListView(
        padding: EdgeInsets.fromLTRB(
          18,
          MediaQuery.of(context).padding.top + 10,
          18,
          PcTabBar.espaceReserve,
        ),
        children: <Widget>[
          Text('Commandes', style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 4),
          Text(
            'Vos demandes, leur suivi et vos factures.',
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 12.5,
            ),
          ),
          const SizedBox(height: 16),
          tickets.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => PcErreur(
              message: 'Vos commandes n’ont pas pu être chargées.',
              onReessayer: () => ref.invalidate(ticketsProvider),
            ),
            data: (List<Ticket> liste) => _contenu(liste),
          ),
        ],
      ),
    );
  }

  Widget _contenu(List<Ticket> liste) {
    final PcSkin pc = context.pc;
    final List<Ticket> enCours = liste
        .where((Ticket t) => !t.etatFinal)
        .toList();
    final List<Ticket> passees = liste
        .where((Ticket t) => t.etatFinal)
        .toList();

    if (liste.isEmpty) {
      return PcCard(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: <Widget>[
            PcIco(Icons.receipt_long_rounded, couleur: pc.accent),
            const SizedBox(height: 12),
            Text(
              'Aucune commande pour l’instant',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            Text(
              'Vos interventions apparaîtront ici, en cours comme passées.',
              textAlign: TextAlign.center,
              style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            PcButton(
              libelle: 'Trouver un technicien',
              petit: true,
              largeurPleine: false,
              onPressed: () => context.push('/client/recherche'),
            ),
          ],
        ),
      );
    }

    final List<Ticket> affichees = _filtre == 0 ? enCours : passees;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
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
                  ? 'Aucune demande en cours.'
                  : 'Aucune intervention terminée pour l’instant.',
              textAlign: TextAlign.center,
              style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
            ),
          )
        else
          for (final Ticket t in affichees) ...<Widget>[
            _LigneTicket(ticket: t),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

class _LigneTicket extends StatelessWidget {
  const _LigneTicket({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color couleur = couleurEtat(context, ticket.etat);

    // Ce qui attend le client est dit sur la carte, pas seulement derrière un
    // clic : une demande qui attend un geste doit se voir depuis la liste.
    final String? appel = ticket.enSelection
        ? 'Choisissez votre technicien'
        : ticket.attendUneDecision
        ? 'Un supplément attend votre décision'
        : ticket.especesAConfirmer
        ? 'Un paiement attend votre confirmation'
        : ticket.aPayer
        ? 'Intervention à régler'
        : ticket.aValider
        ? 'À valider pour clore'
        : null;

    return PcCard(
      onTap: () => context.push('/client/suivi/${ticket.id}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              PcIco(_icone(ticket.etat), couleur: couleur, taille: 42),
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
                      '${ticket.reference}'
                      '${ticket.technicien == null ? '' : ' · ${ticket.technicien!.nomComplet}'}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w500,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              PcMontant(ticket.prix.totalGnf, taille: 15),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              // Jamais la couleur seule : le libellé du serveur porte
              // l'information.
              Flexible(child: PcPill(ticket.etatLibelle, couleur: couleur)),
              if (ticket.estUnRetourGarantie) ...<Widget>[
                const SizedBox(width: 6),
                const PcPill(
                  'Garantie',
                  icone: Icons.verified_user_rounded,
                  couleur: PcColors.tech,
                ),
              ],
              const Spacer(),
              Icon(Icons.chevron_right_rounded, color: pc.muted, size: 20),
            ],
          ),
          if (appel != null) ...<Widget>[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
              decoration: BoxDecoration(
                color: pc.teinte(PcColors.coral),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: <Widget>[
                  const Icon(
                    Icons.touch_app_rounded,
                    size: 16,
                    color: PcColors.coral,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      appel,
                      style: TextStyle(
                        color: pc.ink,
                        fontWeight: FontWeight.w700,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  static IconData _icone(String etat) => switch (etat) {
    'PUBLIEE' => Icons.groups_rounded,
    'ACCEPTEE' || 'EN_ROUTE' => Icons.two_wheeler_rounded,
    'CLOTUREE' => Icons.done_all_rounded,
    'TERMINEE' || 'PAYEE' => Icons.payments_rounded,
    'LITIGE_OUVERT' => Icons.gavel_rounded,
    final String e when e.startsWith('ANNULEE') => Icons.cancel_rounded,
    _ => Icons.handyman_rounded,
  };
}

/// Couleur d'un état de ticket, partagée par les listes et les cartes.
Color couleurEtat(BuildContext context, String etat) => switch (etat) {
  'CLOTUREE' => PcColors.tech,
  'SANS_REPONSE' || 'LITIGE_OUVERT' => PcColors.coral,
  'TERMINEE' || 'DIAGNOSTIC_EN_ATTENTE' => PcColors.coral,
  final String e when e.startsWith('ANNULEE') => context.pc.muted,
  _ => context.pc.accent,
};
