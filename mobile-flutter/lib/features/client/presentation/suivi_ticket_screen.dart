import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'diagnostic_feuille.dart';
import 'paiement_feuille.dart';

/// Suivi d'une demande, de la recherche à la clôture.
///
/// Un seul écran pour toute la vie du ticket, piloté par son état. La
/// solution inverse — un écran par état — obligerait le client à comprendre où
/// il en est pour savoir où regarder, alors qu'il veut juste ouvrir
/// l'application et voir ce qui se passe.
///
/// Chaque changement d'état arrive par Reverb sur `ticket.{id}` et déclenche
/// une relecture. L'interrogation reste en repli — toutes les dix secondes sans
/// WebSocket, toutes les trente avec — parce que sur le réseau de Conakry le
/// WebSocket ne s'ouvrira pas toujours.
class SuiviTicketScreen extends ConsumerStatefulWidget {
  const SuiviTicketScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<SuiviTicketScreen> createState() => _SuiviTicketScreenState();
}

class _SuiviTicketScreenState extends ConsumerState<SuiviTicketScreen>
    with InterrogationDeRepli<SuiviTicketScreen> {
  @override
  void initState() {
    super.initState();
    demarrerInterrogation(_rafraichir);
  }

  bool _rafraichir() {
    final Ticket? ticket = ref.read(ticketProvider(widget.ticketId)).value;

    // On cesse d'interroger dès que le ticket est figé : continuer coûterait du
    // forfait au client pour un état qui ne changera plus.
    if (ticket != null && ticket.etatFinal) return false;

    ref.invalidate(ticketProvider(widget.ticketId));
    return true;
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(
      signauxProvider('ticket.${widget.ticketId}'),
      (_, _) => ref.invalidate(ticketProvider(widget.ticketId)),
    );

    final AsyncValue<Ticket> ticket = ref.watch(
      ticketProvider(widget.ticketId),
    );

    return Scaffold(
      appBar: AppBar(
        title: Text(ticket.value?.reference ?? 'Ma demande'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/accueil'),
        ),
      ),
      body: ticket.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(ticketProvider(widget.ticketId)),
          ),
        ),
        data: (Ticket t) => RefreshIndicator(
          onRefresh: () async =>
              ref.invalidate(ticketProvider(widget.ticketId)),
          child: ListView(
            padding: const EdgeInsets.all(DmSpace.x6),
            children: <Widget>[
              _Etat(ticket: t),
              const SizedBox(height: DmSpace.x6),
              if (t.technicien != null) ...<Widget>[
                _Technicien(technicien: t.technicien!, ticket: t),
                const SizedBox(height: DmSpace.x6),
              ],
              _Recapitulatif(ticket: t),
              const SizedBox(height: DmSpace.x6),
              _Actions(ticket: t),
            ],
          ),
        ),
      ),
    );
  }
}

/// Où en est l'intervention, en une phrase et une icône.
class _Etat extends StatelessWidget {
  const _Etat({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final (IconData icone, String texte, Color couleur) info = _info(theme);

    return DmCarte(
      enfant: Row(
        children: <Widget>[
          Icon(info.$1, color: info.$3, size: 32),
          const SizedBox(width: DmSpace.x4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(ticket.etatLibelle, style: theme.textTheme.titleMedium),
                const SizedBox(height: DmSpace.x1),
                Text(info.$2, style: theme.textTheme.bodySmall),
              ],
            ),
          ),
          // En sélection, le client doit agir : pas de spinner qui laisserait
          // croire qu'une recherche tourne toute seule. Le sablier ne sert que
          // quand on attend vraiment le serveur (confirmation du technicien).
          if (ticket.enRecherche && !ticket.enSelection)
            const SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
        ],
      ),
    );
  }

  /// Ce que l'état veut dire pour le client, pas pour la machine à états.
  (IconData, String, Color) _info(ThemeData theme) => switch (ticket.etat) {
    'PUBLIEE' => (
      Icons.groups_outlined,
      'Choisis un technicien parmi ceux disponibles près de chez toi — chacun avec son prix, sa note et sa distance.',
      theme.colorScheme.primary,
    ),
    'ACCEPTEE' => (
      Icons.check_circle_outline,
      'Technicien sollicité. Il a quelques secondes pour confirmer, puis se met en route.',
      DmColors.success,
    ),
    'EN_ROUTE' => (
      Icons.directions_car_outlined,
      'Le technicien est en route vers chez toi.',
      theme.colorScheme.primary,
    ),
    'SUR_PLACE' => (
      Icons.location_on_outlined,
      'Le technicien est arrivé à l\'adresse indiquée.',
      theme.colorScheme.primary,
    ),
    'DIAGNOSTIC_EN_ATTENTE' => (
      Icons.priority_high,
      'Le technicien propose un supplément. À toi de décider.',
      DmColors.warning,
    ),
    'DIAGNOSTIC_VALIDE' => (
      Icons.check_circle_outline,
      'Supplément accepté. Le technicien peut continuer.',
      DmColors.success,
    ),
    'DIAGNOSTIC_REFUSE' => (
      Icons.info_outline,
      'Supplément refusé. Le technicien fera ce qui était prévu au départ.',
      DmColors.neutral500,
    ),
    'EN_COURS' => (
      Icons.build_outlined,
      'Le travail est en cours.',
      theme.colorScheme.primary,
    ),
    'TERMINEE' => (
      Icons.task_alt,
      'L\'intervention est terminée. Il reste à régler.',
      DmColors.warning,
    ),
    'PAYEE' => (
      Icons.shield_outlined,
      'Payé. Ton argent est bloqué jusqu\'à ce que tu valides — ou automatiquement sous 24 h.',
      DmColors.success,
    ),
    'CLOTUREE' => (
      Icons.done_all,
      'Intervention terminée et réglée. Merci !',
      DmColors.success,
    ),
    'SANS_REPONSE' => (
      Icons.person_off_outlined,
      'Aucun technicien n\'était disponible. Tu peux republier ta demande.',
      DmColors.danger,
    ),
    'LITIGE_OUVERT' => (
      Icons.gavel_outlined,
      'Ta réclamation est en cours d\'examen. Le paiement reste bloqué.',
      DmColors.warning,
    ),
    _ when ticket.etat.startsWith('ANNULEE') => (
      Icons.cancel_outlined,
      'Cette demande a été annulée.',
      DmColors.neutral500,
    ),
    _ => (Icons.help_outline, ticket.etatLibelle, DmColors.neutral500),
  };
}

class _Technicien extends StatelessWidget {
  const _Technicien({required this.technicien, required this.ticket});

  final PartieTicket technicien;
  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Row(
        children: <Widget>[
          CircleAvatar(
            radius: 24,
            backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.12),
            child: Text(
              technicien.nomComplet.isEmpty ? '?' : technicien.nomComplet[0],
              style: theme.textTheme.titleLarge?.copyWith(
                color: theme.colorScheme.primary,
              ),
            ),
          ),
          const SizedBox(width: DmSpace.x4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(technicien.nomComplet, style: theme.textTheme.titleMedium),
                Text(
                  // Le serveur ne livre le numéro en clair que pendant une
                  // intervention active. Quand il est masqué, on le dit plutôt
                  // que d'afficher des points sans explication.
                  ticket.etatActif
                      ? (technicien.telephone ?? '')
                      : 'Numéro visible pendant l\'intervention',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Discuter',
            icon: const Icon(Icons.chat_bubble_outline),
            onPressed: () => context.push('/client/chat/${ticket.id}'),
          ),
        ],
      ),
    );
  }
}

class _Recapitulatif extends StatelessWidget {
  const _Recapitulatif({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (ticket.prestation != null)
            Text(ticket.prestation!.nom, style: theme.textTheme.titleMedium),
          const SizedBox(height: DmSpace.x2),
          Text(ticket.adresseLisible, style: theme.textTheme.bodySmall),
          if (ticket.description != null) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            Text(ticket.description!, style: theme.textTheme.bodyMedium),
          ],
          if (ticket.diagnostic != null) ...<Widget>[
            const SizedBox(height: DmSpace.x4),
            Text('Diagnostic du technicien', style: theme.textTheme.labelLarge),
            const SizedBox(height: DmSpace.x1),
            Text(ticket.diagnostic!, style: theme.textTheme.bodyMedium),
          ],
          const SizedBox(height: DmSpace.x4),
          const Divider(),
          const SizedBox(height: DmSpace.x3),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  ticket.prix.ferme ? 'Total' : 'À partir de',
                  style: theme.textTheme.titleMedium,
                ),
              ),
              Text(
                ticket.prix.totalFormate,
                style: theme.textTheme.titleLarge?.copyWith(
                  color: theme.colorScheme.primary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Ce que le client peut faire, à cet instant précis.
///
/// Une seule action principale à la fois : proposer « payer », « valider » et
/// « noter » ensemble laisserait chercher laquelle est attendue.
class _Actions extends ConsumerWidget {
  const _Actions({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Column(
      children: <Widget>[
        // Place de marché (#2) : publier n'engage personne, le client choisit.
        if (ticket.enSelection) ...<Widget>[
          DmBouton(
            libelle: 'Choisir un technicien',
            onPresse: () => context.push('/client/choix/${ticket.id}'),
          ),
          const SizedBox(height: DmSpace.x3),
        ],

        if (ticket.attendUneDecision)
          DmBouton(
            libelle: 'Voir le supplément proposé',
            onPresse: () => DiagnosticFeuille.ouvrir(context, ticket.id),
          ),

        // Paiement espèces déclaré par le technicien (#1) : le client confirme.
        // C'est sa confirmation seule qui engage.
        if (ticket.especesAConfirmer) ...<Widget>[
          DmBouton(
            libelle: 'J\'ai payé ${ticket.prix.totalFormate} en espèces',
            onPresse: () => _confirmerEspeces(context, ref),
          ),
          const SizedBox(height: DmSpace.x2),
          Text(
            'Confirme seulement si tu as bien remis l\'argent au technicien.',
            style: Theme.of(context).textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: DmSpace.x3),
        ],

        if (ticket.aPayer)
          DmBouton(
            libelle: ticket.especesAConfirmer
                ? 'Payer dans l\'application'
                : 'Payer ${ticket.prix.totalFormate}',
            secondaire: ticket.especesAConfirmer,
            onPresse: () => PaiementFeuille.ouvrir(context, ticket.id),
          ),

        // Retour sous garantie (#4) : la panne est réglée, le client confirme.
        if (ticket.garantieAConfirmer)
          DmBouton(
            libelle: 'Confirmer que la panne est réglée',
            onPresse: () => _confirmerGarantie(context, ref),
          ),

        if (ticket.aValider) ...<Widget>[
          DmBouton(
            libelle: 'Valider l\'intervention',
            onPresse: () async {
              final bool libere = await ref
                  .read(clientRepositoryProvider)
                  .validerIntervention(ticket.id);

              ref.invalidate(ticketProvider(ticket.id));

              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      libere ? 'Merci. Le technicien a été payé.' : 'La libération est suspendue : une réclamation est en cours.',
                    ),
                  ),
                );
              }
            },
          ),
          const SizedBox(height: DmSpace.x3),
          Text(
            'Sans validation de ta part, les fonds sont libérés automatiquement '
            'sous 24 heures.',
            style: Theme.of(context).textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],

        if (ticket.estNotable) ...<Widget>[
          const SizedBox(height: DmSpace.x3),
          DmBouton(
            libelle: 'Noter le technicien',
            secondaire: true,
            onPresse: () => context.push('/client/avis/${ticket.id}'),
          ),
        ],

        // Garantie (#4) : une intervention clôturée encore sous garantie peut
        // rappeler le même technicien gratuitement si la panne revient.
        if (ticket.garantieActive) ...<Widget>[
          const SizedBox(height: DmSpace.x3),
          DmBouton(
            libelle: 'La panne est revenue (garantie)',
            secondaire: true,
            onPresse: () => _ouvrirGarantie(context, ref),
          ),
          const SizedBox(height: DmSpace.x2),
          Text(
            'Le retour du même technicien est gratuit tant que la garantie court.',
            style: Theme.of(context).textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],

        // Le serveur tient la fenêtre de 72 heures et l'unicité : le bouton
        // est offert sur tout ticket terminé, et un refus arrive en clair.
        if (ticket.reclamable) ...<Widget>[
          const SizedBox(height: DmSpace.x3),
          TextButton.icon(
            onPressed: () => context.push('/client/reclamation/${ticket.id}'),
            icon: const Icon(Icons.report_problem_outlined),
            label: const Text('Signaler un problème'),
          ),
        ],

        // Anti-contournement (#3) : pendant une intervention active, un bouton
        // discret pour signaler qu'on a demandé au client de régler « dehors ».
        if (ticket.etatActif) ...<Widget>[
          TextButton.icon(
            onPressed: () => _signalerHorsPlateforme(context, ref),
            icon: const Icon(Icons.shield_outlined),
            label: const Text('Signaler un paiement hors application'),
          ),
        ],

        if (ticket.etat == 'SANS_REPONSE') ...<Widget>[
          DmBouton(
            libelle: 'Republier ma demande',
            onPresse: () => context.go('/client/catalogue'),
          ),
        ],

        if (ticket.annulable) ...<Widget>[
          const SizedBox(height: DmSpace.x3),
          TextButton(
            onPressed: () => _confirmerAnnulation(context, ref),
            child: const Text('Annuler ma demande'),
          ),
        ],
      ],
    );
  }

  Future<void> _confirmerEspeces(BuildContext context, WidgetRef ref) async {
    try {
      await ref
          .read(clientRepositoryProvider)
          .confirmerPaiementEspeces(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Paiement confirmé. L\'intervention est clôturée.'),
          ),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _confirmerGarantie(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(clientRepositoryProvider).confirmerGarantie(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Garantie clôturée. Merci d\'avoir confirmé.'),
          ),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _ouvrirGarantie(BuildContext context, WidgetRef ref) async {
    final String? description = await _demanderTexte(
      context,
      titre: 'La panne est revenue',
      indication: 'Décris ce qui se reproduit (au moins 10 caractères).',
      bouton: 'Ouvrir la garantie',
    );

    if (description == null) return;

    try {
      final Ticket retour = await ref
          .read(clientRepositoryProvider)
          .ouvrirGarantie(ticket.id, description);
      ref.invalidate(ticketsProvider);
      if (context.mounted) context.go('/client/suivi/${retour.id}');
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _signalerHorsPlateforme(
    BuildContext context,
    WidgetRef ref,
  ) async {
    final String? description = await _demanderTexte(
      context,
      titre: 'Signaler un paiement hors application',
      indication:
          'Explique ce qui t\'a été demandé. Rester dans l\'application, '
          'c\'est garder la garantie et le support.',
      bouton: 'Envoyer au support',
    );

    if (description == null) return;

    try {
      await ref
          .read(clientRepositoryProvider)
          .signalerHorsPlateforme(ticket.id, description);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Signalement transmis au support.')),
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  /// Petite saisie de texte en boîte de dialogue, réutilisée par la garantie
  /// et le signalement. Renvoie `null` si l'utilisateur annule ou laisse vide.
  Future<String?> _demanderTexte(
    BuildContext context, {
    required String titre,
    required String indication,
    required String bouton,
  }) async {
    final TextEditingController controleur = TextEditingController();

    final String? texte = await showDialog<String>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text(titre),
        content: TextField(
          controller: controleur,
          minLines: 3,
          maxLines: 5,
          maxLength: 2000,
          decoration: InputDecoration(hintText: indication),
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () {
              final String valeur = controleur.text.trim();
              Navigator.of(context).pop(valeur.length >= 10 ? valeur : null);
            },
            child: Text(bouton),
          ),
        ],
      ),
    );

    controleur.dispose();
    return texte;
  }

  /// L'annulation demande confirmation, et **annonce les frais avant** de les
  /// appliquer. Les découvrir après serait exactement la surprise que le reste
  /// du système cherche à éviter.
  Future<void> _confirmerAnnulation(BuildContext context, WidgetRef ref) async {
    final bool payante = ticket.etat != 'PUBLIEE' && ticket.etat != 'ACCEPTEE';

    final bool? confirme = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Annuler ta demande ?'),
        content: Text(
          payante
              ? 'Le technicien est déjà en route : des frais d\'annulation '
                    'tardive s\'appliqueront.'
              : 'Personne n\'est encore intervenu, l\'annulation est gratuite.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Non, garder'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Oui, annuler'),
          ),
        ],
      ),
    );

    if (confirme != true) return;

    try {
      await ref.read(clientRepositoryProvider).annuler(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      ref.invalidate(ticketsProvider);
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}
