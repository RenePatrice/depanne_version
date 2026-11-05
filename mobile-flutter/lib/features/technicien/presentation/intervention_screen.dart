import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../client/application/client_providers.dart';
import '../../shared/data/modeles.dart';
import '../application/technicien_providers.dart';
import 'diagnostic_feuille.dart';

/// L'intervention en cours, vue du technicien (§7.3).
///
/// Il nomme un geste — « je pars », « je suis arrivé » — et jamais un état :
/// c'est la machine à états du serveur qui décide si le geste est possible.
/// Une liste d'états recopiée ici finirait par diverger de la sienne, et
/// l'écran proposerait des boutons qui échouent.
class InterventionScreen extends ConsumerStatefulWidget {
  const InterventionScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<InterventionScreen> createState() => _InterventionScreenState();
}

class _InterventionScreenState extends ConsumerState<InterventionScreen>
    with InterrogationDeRepli<InterventionScreen> {
  bool _enCours = false;

  @override
  void initState() {
    super.initState();
    demarrerInterrogation(_rafraichir);
  }

  /// Le client peut accepter ou refuser un supplément pendant que le
  /// technicien regarde l'écran : c'est ce changement-là qu'on guette. Il
  /// arrive par Reverb ; cette boucle n'est que le repli.
  bool _rafraichir() {
    final Ticket? ticket = ref.read(ticketProvider(widget.ticketId)).value;

    if (ticket != null && ticket.etatFinal) return false;

    ref.invalidate(ticketProvider(widget.ticketId));
    return true;
  }

  Future<void> _avancer(String etape) async {
    if (_enCours) return;

    setState(() => _enCours = true);

    try {
      await ref
          .read(technicienRepositoryProvider)
          .avancer(widget.ticketId, etape);

      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(interventionsProvider);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
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
        title: Text(ticket.value?.reference ?? 'Intervention'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () =>
              context.canPop() ? context.pop() : context.go('/accueil'),
        ),
      ),
      body: ticket.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, StackTrace _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: Center(
            child: DmErreur(
              message: messageErreur(e),
              onReessayer: () =>
                  ref.invalidate(ticketProvider(widget.ticketId)),
            ),
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
              _Destination(ticket: t),
              const SizedBox(height: DmSpace.x6),
              if (t.client != null) ...<Widget>[
                _Client(client: t.client!, ticket: t),
                const SizedBox(height: DmSpace.x6),
              ],
              _Remuneration(ticket: t),
              const SizedBox(height: DmSpace.x6),
              _Gestes(
                ticket: t,
                enCours: _enCours,
                onAvancer: _avancer,
                ticketId: widget.ticketId,
              ),
              const SizedBox(height: DmSpace.x8),
            ],
          ),
        ),
      ),
    );
  }
}

class _Etat extends StatelessWidget {
  const _Etat({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(ticket.etatLibelle, style: theme.textTheme.titleLarge),
          const SizedBox(height: DmSpace.x2),
          Text(_explication(ticket.etat), style: theme.textTheme.bodyMedium),
          if (ticket.prestation != null) ...<Widget>[
            const SizedBox(height: DmSpace.x4),
            Text(ticket.prestation!.nom, style: theme.textTheme.titleMedium),
          ],
          if (ticket.description != null) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            Text(ticket.description!, style: theme.textTheme.bodyMedium),
          ],
        ],
      ),
    );
  }

  /// Ce que le technicien doit faire maintenant, en une phrase.
  ///
  /// L'état seul ne le dit pas : « Diagnostic en attente » n'apprend rien à
  /// qui se demande s'il doit continuer à travailler ou patienter.
  static String _explication(String etat) => switch (etat) {
    'ACCEPTEE' => 'Le client t\'attend. Préviens-le quand tu pars.',
    'EN_ROUTE' => 'Tu es en route. Signale ton arrivée sur place.',
    'SUR_PLACE' => 'Tu es arrivé. Démarre quand tu commences le travail.',
    'EN_COURS' => 'Intervention en cours. Termine quand le travail est fait.',
    'DIAGNOSTIC_EN_ATTENTE' =>
      'Ta proposition de supplément est partie. Le client doit répondre avant '
          'que tu continues.',
    'DIAGNOSTIC_REFUSE' =>
      'Le client a refusé le supplément. Fais ce qui était prévu au départ, au '
          'prix annoncé.',
    'TERMINEE' => 'Travail terminé. Le client doit régler.',
    'PAYEE' =>
      'Le client a payé. Les fonds te reviennent dès qu\'il valide, ou '
          'automatiquement sous 24 heures.',
    'CLOTUREE' => 'Intervention close. Le montant est dans ton portefeuille.',
    'LITIGE_OUVERT' =>
      'Une réclamation est ouverte. Le support va revenir vers toi.',
    _ => 'Suis les étapes ci-dessous.',
  };
}

/// Où aller, et comment y aller.
///
/// L'itinéraire part vers l'application de cartographie du téléphone plutôt
/// que vers une carte embarquée : c'est celle qui sait guider, qui connaît le
/// trafic, et qui fonctionne sans clé d'API. Le repère écrit est affiché en
/// grand — à Conakry, c'est lui qui amène devant la porte, pas la coordonnée.
class _Destination extends StatelessWidget {
  const _Destination({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Map<String, dynamic>? adresse = ticket.adresse;
    final String? repere = adresse?['landmark'] as String?;

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.place_outlined, size: 18),
              const SizedBox(width: DmSpace.x2),
              Expanded(
                child: Text(
                  ticket.adresseLisible,
                  style: theme.textTheme.titleMedium,
                ),
              ),
            ],
          ),
          if (repere != null && repere.isNotEmpty) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(DmSpace.x3),
              decoration: BoxDecoration(
                color: theme.colorScheme.primary.withValues(alpha: 0.08),
                borderRadius: DmRadius.smAll,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Point de repère', style: theme.textTheme.labelSmall),
                  const SizedBox(height: DmSpace.x1),
                  Text(repere, style: theme.textTheme.bodyLarge),
                ],
              ),
            ),
          ],
          if (_coordonnees(adresse) != null) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () => _ouvrirItineraire(context),
                icon: const Icon(Icons.directions_outlined),
                label: const Text('Ouvrir l\'itinéraire'),
              ),
            ),
          ],
        ],
      ),
    );
  }

  /// Le point de l'instantané, quand il y en a un.
  static (double, double)? _coordonnees(Map<String, dynamic>? adresse) {
    final double? lat = (adresse?['latitude'] as num?)?.toDouble();
    final double? lng = (adresse?['longitude'] as num?)?.toDouble();

    return lat == null || lng == null ? null : (lat, lng);
  }

  /// Passe la main à l'application de cartographie du téléphone.
  ///
  /// Le schéma `geo:` est celui d'Android et n'exige aucune clé : c'est
  /// l'application installée — Google Maps le plus souvent — qui guide, connaît
  /// le trafic et fonctionne hors ligne si ses cartes sont téléchargées. Une
  /// carte embarquée ferait moins bien, coûterait une clé, et ajouterait un
  /// écran de plus entre le technicien et la route.
  ///
  /// Si rien ne peut l'ouvrir, on le dit plutôt que de laisser un bouton
  /// silencieux : un bouton qui ne fait rien coûte plus cher en confiance
  /// qu'un message d'indisponibilité.
  Future<void> _ouvrirItineraire(BuildContext context) async {
    final (double, double)? point = _coordonnees(ticket.adresse);

    if (point == null) return;

    final Uri lien = Uri.parse(
      'geo:${point.$1},${point.$2}?q=${point.$1},${point.$2}'
      '(${Uri.encodeComponent(ticket.reference)})',
    );

    final bool ouvert = await launchUrl(
      lien,
      mode: LaunchMode.externalApplication,
    ).catchError((_) => false);

    if (!ouvert && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Aucune application de navigation trouvée sur ce téléphone.',
          ),
        ),
      );
    }
  }
}

class _Client extends StatelessWidget {
  const _Client({required this.client, required this.ticket});

  final PartieTicket client;
  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Row(
        children: <Widget>[
          CircleAvatar(
            radius: 22,
            backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.12),
            child: Icon(Icons.person_outline, color: theme.colorScheme.primary),
          ),
          const SizedBox(width: DmSpace.x4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(client.nomComplet, style: theme.textTheme.titleMedium),
                Text(
                  // Le serveur ne livre le numéro en clair que pendant une
                  // intervention active. Quand il est masqué, on le dit.
                  ticket.etatActif
                      ? (client.telephone ?? '')
                      : 'Numéro visible pendant l\'intervention',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Discuter',
            icon: const Icon(Icons.chat_bubble_outline),
            onPressed: () => context.push('/technicien/chat/${ticket.id}'),
          ),
        ],
      ),
    );
  }
}

/// Ce que l'intervention rapporte.
///
/// Le net, pas le total : c'est ce qui arrive sur le portefeuille. Le total
/// est rappelé en dessous pour que le technicien puisse le dire au client sans
/// aller chercher ailleurs.
class _Remuneration extends StatelessWidget {
  const _Remuneration({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int? net = ticket.prix.netTechnicienGnf;

    return DmCarte(
      enfant: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text('Tu touches', style: theme.textTheme.labelSmall),
                const SizedBox(height: DmSpace.x1),
                Text(
                  net == null ? '—' : formaterGnf(net),
                  style: theme.textTheme.headlineSmall?.copyWith(
                    color: theme.colorScheme.primary,
                  ),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text('Le client paie', style: theme.textTheme.labelSmall),
              const SizedBox(height: DmSpace.x1),
              Text(
                ticket.prix.totalFormate,
                style: theme.textTheme.titleMedium,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Les gestes possibles, et eux seuls.
///
/// Le bouton affiché dépend de l'état renvoyé par le serveur. Aucun geste
/// n'est proposé « au cas où » : un bouton qui échoue une fois sur deux apprend
/// au technicien à ne plus faire confiance à l'écran.
class _Gestes extends ConsumerWidget {
  const _Gestes({
    required this.ticket,
    required this.enCours,
    required this.onAvancer,
    required this.ticketId,
  });

  final Ticket ticket;
  final bool enCours;
  final Future<void> Function(String etape) onAvancer;
  final int ticketId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final (String etape, String libelle)? geste = switch (ticket.etat) {
      'ACCEPTEE' => ('en-route', 'Je pars maintenant'),
      'EN_ROUTE' => ('sur-place', 'Je suis arrivé'),
      'SUR_PLACE' => ('demarrer', 'Je commence le travail'),
      'EN_COURS' || 'DIAGNOSTIC_REFUSE' => ('terminer', 'J\'ai terminé'),
      _ => null,
    };

    // Un supplément ne se propose qu'une fois sur place et avant la fin : plus
    // tôt le technicien n'a rien constaté, plus tard le prix est arrêté.
    final bool peutProposer =
        ticket.etat == 'SUR_PLACE' || ticket.etat == 'EN_COURS';

    // Paiement direct en espèces (#1) : une fois l'intervention terminée, le
    // technicien déclare avoir été payé en liquide. Un retour sous garantie est
    // gratuit — rien à déclarer.
    final bool peutDeclarerEspeces =
        ticket.etat == 'TERMINEE' && !ticket.estUnRetourGarantie;

    if (geste == null && !peutProposer && !peutDeclarerEspeces) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        if (geste != null)
          DmBouton(
            libelle: geste.$2,
            enCours: enCours,
            onPresse: () => onAvancer(geste.$1),
          ),
        if (peutDeclarerEspeces) ...<Widget>[
          DmBouton(
            libelle: 'J\'ai été payé en espèces',
            onPresse: () => _declarerEspeces(context, ref),
          ),
          const SizedBox(height: DmSpace.x3),
          Text(
            'Le client devra confirmer. La commission de la plateforme te restera '
            'à régler (suivie dans ton portefeuille). S\'il préfère payer dans '
            'l\'application, il le fait de son côté — tu n\'as rien à faire.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
        if (peutProposer) ...<Widget>[
          const SizedBox(height: DmSpace.x3),
          DmBouton(
            libelle: 'Proposer un supplément',
            secondaire: true,
            onPresse: () => ProposerDiagnosticFeuille.ouvrir(context, ticket),
          ),
          const SizedBox(height: DmSpace.x3),
          Text(
            'Le client doit accepter avant que le total change. Explique ce que '
            'tu as constaté : sans diagnostic écrit, la proposition est refusée.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ],
    );
  }

  Future<void> _declarerEspeces(BuildContext context, WidgetRef ref) async {
    final bool? confirme = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: const Text('Confirmer le paiement en espèces ?'),
        content: Text(
          'Tu déclares avoir reçu ${ticket.prix.totalFormate} en espèces. Le '
          'client devra confirmer de son côté pour clôturer l\'intervention.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Oui, déclarer'),
          ),
        ],
      ),
    );

    if (confirme != true) return;

    try {
      await ref.read(technicienRepositoryProvider).declarerEspeces(ticketId);
      ref.invalidate(ticketProvider(ticketId));
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Déclaré. En attente de confirmation du client.'),
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
}
