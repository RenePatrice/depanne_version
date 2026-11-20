import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'carte_suivi.dart';
import 'diagnostic_feuille.dart';
import 'paiement_feuille.dart';

/// Suivi d'une demande, de la publication à la clôture.
///
/// Un seul écran pour toute la vie du ticket, piloté par son état. La
/// solution inverse — un écran par état — obligerait le client à comprendre où
/// il en est pour savoir où regarder, alors qu'il veut juste ouvrir
/// l'application et voir ce qui se passe.
///
/// Chaque changement d'état arrive par Reverb sur `ticket.{id}` et déclenche
/// une relecture. L'interrogation reste en repli — toutes les dix secondes sans
/// WebSocket, toutes les trente avec — parce que sur le réseau de Conakry le
/// WebSocket ne s'ouvrira pas toujours. Les positions du technicien passent
/// sur le même canal mais ne relisent pas le ticket : la carte s'en charge.
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
    final PcSkin pc = context.pc;

    ref.listen(signauxProvider('ticket.${widget.ticketId}'), (
      _,
      AsyncValue<EvenementTempsReel> signal,
    ) {
      if (signal.value?.estPosition ?? false) return;
      ref.invalidate(ticketProvider(widget.ticketId));
    });

    final AsyncValue<Ticket> ticket = ref.watch(
      ticketProvider(widget.ticketId),
    );

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(
              titre: ticket.value?.prestation?.nom ?? 'Ma demande',
              sousTitre: ticket.value?.reference,
              onRetour: () =>
                  context.canPop() ? context.pop() : context.go('/accueil'),
            ),
            Expanded(
              child: ticket.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (Object e, _) => Padding(
                  padding: const EdgeInsets.all(18),
                  child: PcErreur(
                    message: messageErreur(e),
                    onReessayer: () =>
                        ref.invalidate(ticketProvider(widget.ticketId)),
                  ),
                ),
                data: (Ticket t) => RefreshIndicator(
                  color: pc.accent,
                  onRefresh: () async =>
                      ref.invalidate(ticketProvider(widget.ticketId)),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(18, 0, 18, 32),
                    children: <Widget>[
                      _Etat(ticket: t),
                      const SizedBox(height: 14),
                      _Progression(etat: t.etat),
                      if (t.arrivee.codeRequis) ...<Widget>[
                        const SizedBox(height: 14),
                        _CodeArrivee(ticket: t),
                      ],
                      if (_trajet(t)) ...<Widget>[
                        const SizedBox(height: 14),
                        CarteSuivi(
                          ticketId: t.id,
                          destination: _destination(t),
                        ),
                      ],
                      if (t.technicien != null) ...<Widget>[
                        const SizedBox(height: 14),
                        _Technicien(technicien: t.technicien!, ticket: t),
                      ],
                      const SizedBox(height: 14),
                      _Actions(ticket: t),
                      const PcSection('Récapitulatif'),
                      _Recapitulatif(ticket: t),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static bool _trajet(Ticket t) =>
      t.technicien != null && (t.etat == 'ACCEPTEE' || t.etat == 'EN_ROUTE');

  static LatLng? _destination(Ticket t) {
    final Object? lat = t.adresse?['latitude'];
    final Object? lng = t.adresse?['longitude'];
    return lat is num && lng is num
        ? LatLng(lat.toDouble(), lng.toDouble())
        : null;
  }
}

/// Code d'arrivée (lot 2).
///
/// Quatre chiffres à donner au technicien **devant la porte**, pas avant : un
/// technicien qui n'est pas là ne peut pas se déclarer arrivé. Et pour le
/// client, celui qui demande le bon code est bien celui que l'application a
/// envoyé.
///
/// Si le technicien a épuisé ses essais, ou si le code ne passe pas, le client
/// confirme lui-même l'arrivée : il le voit, sa parole vaut le code.
class _CodeArrivee extends ConsumerWidget {
  const _CodeArrivee({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final ArriveeTicket arrivee = ticket.arrivee;
    final String code = arrivee.code ?? '----';

    return PcCard(
      bordure: arrivee.bloque ? PcColors.danger.withValues(alpha: 0.5) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(Icons.shield_rounded, color: pc.accent, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Code d’arrivée',
                  style: TextStyle(
                    fontFamily: PcFonts.display,
                    fontWeight: FontWeight.w700,
                    color: pc.ink,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Semantics(
            label: 'Code d’arrivée : ${code.split('').join(' ')}',
            excludeSemantics: true,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                for (final String c in code.split(''))
                  Container(
                    width: 52,
                    height: 60,
                    margin: const EdgeInsets.symmetric(horizontal: 5),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: pc.accentLight.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Text(
                      c,
                      style: TextStyle(
                        fontFamily: PcFonts.display,
                        fontSize: 30,
                        fontWeight: FontWeight.w800,
                        color: pc.accentDeep,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Text(
            arrivee.bloque
                ? 'Votre technicien n’a pas réussi à saisir ce code. S’il est '
                      'bien devant vous, confirmez son arrivée.'
                : 'Donnez ce code au technicien quand il est devant vous, '
                      'jamais avant ni par téléphone.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: arrivee.bloque ? PcColors.danger : pc.muted,
              fontSize: 12.5,
            ),
          ),
          if (arrivee.confirmable) ...<Widget>[
            const SizedBox(height: 12),
            PcButton(
              libelle: 'Le technicien est là : confirmer',
              icone: Icons.how_to_reg_rounded,
              variante: arrivee.bloque ? PcVariante.accent : PcVariante.doux,
              petit: !arrivee.bloque,
              onPressed: () => _confirmer(context, ref),
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _confirmer(BuildContext context, WidgetRef ref) async {
    final bool? ok = await showDialog<bool>(
      context: context,
      builder: (BuildContext ctx) => AlertDialog(
        title: const Text('Confirmer l’arrivée ?'),
        content: const Text(
          'Confirmez seulement si le technicien est devant vous. Il pourra '
          'alors commencer l’intervention.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Pas encore'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Il est là'),
          ),
        ],
      ),
    );

    if (ok != true) return;

    try {
      await ref.read(clientRepositoryProvider).confirmerArrivee(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      if (context.mounted) showPcToast(context, 'Arrivée confirmée.');
    } on ApiException catch (e) {
      if (context.mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    }
  }
}

/// Où en est l'intervention, en une phrase et une icône.
class _Etat extends StatelessWidget {
  const _Etat({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final (IconData icone, String texte, Color couleur) = _info(pc);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: PcRadius.lgAll,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[couleur, Color.lerp(couleur, Colors.black, 0.25)!],
        ),
        boxShadow: PcShadow.glow(couleur),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icone, color: Colors.white, size: 26),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  ticket.etatLibelle,
                  style: const TextStyle(
                    fontFamily: PcFonts.display,
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 17,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  texte,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.88),
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Ce que l'état veut dire pour le client, pas pour la machine à états.
  (IconData, String, Color) _info(PcSkin pc) => switch (ticket.etat) {
    'PUBLIEE' => (
      Icons.groups_rounded,
      'Choisissez un technicien parmi ceux disponibles : prix, note et '
          'distance affichés.',
      pc.accent,
    ),
    'ACCEPTEE' => (
      Icons.hourglass_top_rounded,
      'Technicien choisi. Il confirme, puis se met en route.',
      PcColors.tech,
    ),
    'EN_ROUTE' => (
      Icons.two_wheeler_rounded,
      'Votre technicien est en route : suivez-le sur la carte.',
      pc.accent,
    ),
    'SUR_PLACE' => (
      Icons.location_on_rounded,
      'Votre technicien est arrivé à l’adresse indiquée.',
      pc.accent,
    ),
    'DIAGNOSTIC_EN_ATTENTE' => (
      Icons.priority_high_rounded,
      'Le technicien propose un supplément. À vous de décider.',
      PcColors.coral,
    ),
    'DIAGNOSTIC_VALIDE' => (
      Icons.check_circle_rounded,
      'Supplément accepté. Le technicien continue.',
      PcColors.tech,
    ),
    'DIAGNOSTIC_REFUSE' => (
      Icons.info_rounded,
      'Supplément refusé. Le technicien fait ce qui était prévu.',
      pc.muted,
    ),
    'EN_COURS' => (
      Icons.handyman_rounded,
      'Le travail est en cours.',
      pc.accent,
    ),
    'TERMINEE' => (
      Icons.task_alt_rounded,
      ticket.estUnRetourGarantie
          ? 'Retour sous garantie terminé. Confirmez que tout est réglé.'
          : 'Intervention terminée. Il reste à régler le paiement.',
      PcColors.coral,
    ),
    'PAYEE' => (
      Icons.shield_rounded,
      'Payé. L’argent reste bloqué jusqu’à votre validation, ou 24 h.',
      PcColors.tech,
    ),
    'CLOTUREE' => (
      Icons.done_all_rounded,
      'Intervention terminée et réglée. Merci !',
      PcColors.tech,
    ),
    'SANS_REPONSE' => (
      Icons.person_off_rounded,
      'Aucun technicien n’était disponible. Vous pouvez republier.',
      PcColors.coral,
    ),
    'LITIGE_OUVERT' => (
      Icons.gavel_rounded,
      'Votre réclamation est en cours d’examen.',
      PcColors.coral,
    ),
    _ when ticket.etat.startsWith('ANNULEE') => (
      Icons.cancel_rounded,
      'Cette demande a été annulée.',
      pc.muted,
    ),
    _ => (Icons.help_rounded, ticket.etatLibelle, pc.muted),
  };
}

/// Les grandes étapes, pour savoir d'un coup d'œil ce qui reste.
class _Progression extends StatelessWidget {
  const _Progression({required this.etat});

  final String etat;

  static const List<(String, IconData)> _etapes = <(String, IconData)>[
    ('Choix', Icons.groups_rounded),
    ('Trajet', Icons.two_wheeler_rounded),
    ('Travaux', Icons.handyman_rounded),
    ('Paiement', Icons.payments_rounded),
    ('Terminé', Icons.done_all_rounded),
  ];

  int get _index => switch (etat) {
    'PUBLIEE' => 0,
    'ACCEPTEE' || 'EN_ROUTE' => 1,
    'SUR_PLACE' ||
    'DIAGNOSTIC_EN_ATTENTE' ||
    'DIAGNOSTIC_VALIDE' ||
    'DIAGNOSTIC_REFUSE' ||
    'EN_COURS' => 2,
    'TERMINEE' || 'PAYEE' || 'LITIGE_OUVERT' => 3,
    'CLOTUREE' => 4,
    _ => -1,
  };

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int courant = _index;

    // États hors parcours (annulée, sans réponse) : pas de frise trompeuse.
    if (courant < 0) return const SizedBox.shrink();

    return PcCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      child: Row(
        children: <Widget>[
          for (int i = 0; i < _etapes.length; i++) ...<Widget>[
            Expanded(
              child: Column(
                children: <Widget>[
                  AnimatedContainer(
                    duration: PcMotion.normal,
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: i <= courant ? pc.accent : pc.soft,
                    ),
                    child: Icon(
                      i < courant ? Icons.check_rounded : _etapes[i].$2,
                      size: 17,
                      color: i <= courant ? Colors.white : pc.muted,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _etapes[i].$1,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: i <= courant ? pc.ink : pc.muted,
                      fontWeight: i == courant
                          ? FontWeight.w800
                          : FontWeight.w600,
                      fontSize: 11,
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
}

class _Technicien extends StatelessWidget {
  const _Technicien({required this.technicien, required this.ticket});

  final PartieTicket technicien;
  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final String? tel = ticket.etatActif ? technicien.telephone : null;

    return PcCard(
      child: Row(
        children: <Widget>[
          PcAvatar(
            nom: technicien.nomComplet,
            image: technicien.avatarUrl,
            taille: 50,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  technicien.nomComplet,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text(
                  // Le serveur ne livre le numéro en clair que pendant une
                  // intervention active. Masqué, on le dit plutôt que
                  // d'afficher des points sans explication.
                  tel ?? 'Numéro visible pendant l’intervention',
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
          ),
          if (tel != null && tel.isNotEmpty) ...<Widget>[
            PcIconButton(
              icone: Icons.call_rounded,
              libelle: 'Appeler',
              onTap: () => launchUrl(Uri(scheme: 'tel', path: tel)),
            ),
            const SizedBox(width: 8),
          ],
          PcIconButton(
            icone: Icons.chat_bubble_rounded,
            libelle: 'Discuter',
            fond: pc.accent,
            couleur: Colors.white,
            onTap: () => context.push('/client/chat/${ticket.id}'),
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
    final PcSkin pc = context.pc;
    final TextStyle doux = TextStyle(
      color: pc.muted,
      fontWeight: FontWeight.w600,
      fontSize: 12.5,
    );

    return PcCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(Icons.place_outlined, size: 18, color: pc.muted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  ticket.adresseLisible,
                  style: TextStyle(color: pc.ink2, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          if (ticket.description != null) ...<Widget>[
            const SizedBox(height: 12),
            Text('Votre description', style: doux),
            const SizedBox(height: 2),
            Text(ticket.description!, style: TextStyle(color: pc.ink)),
          ],
          if (ticket.diagnostic != null) ...<Widget>[
            const SizedBox(height: 12),
            Text('Diagnostic du technicien', style: doux),
            const SizedBox(height: 2),
            Text(ticket.diagnostic!, style: TextStyle(color: pc.ink)),
          ],
          const SizedBox(height: 12),
          Divider(color: pc.line),
          const SizedBox(height: 6),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  ticket.prix.ferme ? 'Total' : 'À partir de',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              PcMontant(ticket.prix.totalGnf, taille: 20, couleur: pc.accent),
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
    final PcSkin pc = context.pc;
    final TextStyle note = TextStyle(
      color: pc.muted,
      fontWeight: FontWeight.w600,
      fontSize: 12,
    );

    final List<Widget> enfants = <Widget>[
      // Place de marché (#2) : publier n'engage personne, le client choisit.
      if (ticket.enSelection)
        PcButton(
          libelle: 'Choisir un technicien',
          icone: Icons.groups_rounded,
          onPressed: () => context.push('/client/choix/${ticket.id}'),
        ),

      if (ticket.attendUneDecision)
        PcButton(
          libelle: 'Voir le supplément proposé',
          variante: PcVariante.corail,
          icone: Icons.receipt_long_rounded,
          onPressed: () => DiagnosticFeuille.ouvrir(context, ticket.id),
        ),

      // Paiement tracé (ADR-0047) : que le technicien ait déclaré des espèces
      // ou que le client doive choisir son moyen, tout passe par la feuille.
      if (ticket.especesAConfirmer) ...<Widget>[
        PcButton(
          libelle: 'Confirmer le paiement déclaré',
          variante: PcVariante.corail,
          icone: Icons.verified_rounded,
          onPressed: () => _payer(context, ref),
        ),
        const SizedBox(height: 6),
        Text(
          'Le technicien déclare avoir été payé. Confirmez seulement si c’est '
          'exact.',
          style: note,
          textAlign: TextAlign.center,
        ),
      ] else if (ticket.aPayer)
        PcButton(
          libelle: 'Régler ${ticket.prix.totalFormate}',
          icone: Icons.payments_rounded,
          onPressed: () => _payer(context, ref),
        ),

      // Retour sous garantie (#4) : la panne est réglée, le client confirme.
      if (ticket.garantieAConfirmer)
        PcButton(
          libelle: 'Confirmer que la panne est réglée',
          icone: Icons.task_alt_rounded,
          onPressed: () => _confirmerGarantie(context, ref),
        ),

      if (ticket.aValider) ...<Widget>[
        PcButton(
          libelle: 'Valider l’intervention',
          icone: Icons.verified_rounded,
          onPressed: () => _valider(context, ref),
        ),
        const SizedBox(height: 6),
        Text(
          'Sans validation de votre part, les fonds sont libérés '
          'automatiquement sous 24 heures.',
          style: note,
          textAlign: TextAlign.center,
        ),
      ],

      if (ticket.estNotable) ...<Widget>[
        const SizedBox(height: 10),
        PcButton(
          libelle: 'Noter le technicien',
          variante: PcVariante.doux,
          icone: Icons.star_rounded,
          onPressed: () => context.push('/client/avis/${ticket.id}'),
        ),
      ],

      // Garantie (#4) : une intervention clôturée encore sous garantie peut
      // rappeler le même technicien gratuitement si la panne revient.
      if (ticket.garantieActive) ...<Widget>[
        const SizedBox(height: 10),
        PcCard(
          bordure: PcColors.tech.withValues(alpha: 0.4),
          onTap: () => _ouvrirGarantie(context, ref),
          child: Row(
            children: <Widget>[
              const PcIco(
                Icons.verified_user_rounded,
                couleur: PcColors.tech,
                taille: 42,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'La panne est revenue ?',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    Text(
                      'Le retour du même technicien est gratuit sous garantie.',
                      style: note,
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: pc.muted),
            ],
          ),
        ),
      ],

      if (ticket.etat == 'SANS_REPONSE')
        PcButton(
          libelle: 'Republier ma demande',
          icone: Icons.refresh_rounded,
          onPressed: () => context.go('/accueil'),
        ),
    ];

    final List<Widget> liens = <Widget>[
      // Le serveur tient la fenêtre et l'unicité : le bouton est offert sur
      // tout ticket terminé, et un refus arrive en clair.
      if (ticket.reclamable)
        TextButton.icon(
          onPressed: () => context.push('/client/reclamation/${ticket.id}'),
          icon: const Icon(Icons.report_problem_outlined, size: 18),
          label: const Text('Signaler un problème'),
        ),

      // Anti-contournement (#3) : un geste discret pour signaler qu'on a
      // demandé au client de régler « en dehors ».
      if (ticket.etatActif)
        TextButton.icon(
          onPressed: () => _signalerHorsPlateforme(context, ref),
          icon: const Icon(Icons.shield_outlined, size: 18),
          label: const Text('On m’a demandé de payer hors application'),
        ),

      if (ticket.annulable)
        TextButton(
          onPressed: () => _confirmerAnnulation(context, ref),
          style: TextButton.styleFrom(foregroundColor: PcColors.coral),
          child: const Text('Annuler ma demande'),
        ),
    ];

    if (enfants.isEmpty && liens.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[...enfants, ...liens],
    );
  }

  Future<void> _payer(BuildContext context, WidgetRef ref) async {
    await PaiementFeuille.ouvrir(context, ticket.id);
    ref.invalidate(ticketProvider(ticket.id));
  }

  Future<void> _valider(BuildContext context, WidgetRef ref) async {
    try {
      final bool libere = await ref
          .read(clientRepositoryProvider)
          .validerIntervention(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      if (context.mounted) {
        showPcToast(
          context,
          libere
              ? 'Merci. Le technicien a été payé.'
              : 'Libération suspendue : une réclamation est en cours.',
        );
      }
    } on ApiException catch (e) {
      if (context.mounted) _erreur(context, e);
    }
  }

  Future<void> _confirmerGarantie(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(clientRepositoryProvider).confirmerGarantie(ticket.id);
      ref.invalidate(ticketProvider(ticket.id));
      if (context.mounted) {
        showPcToast(context, 'Garantie clôturée. Merci d’avoir confirmé.');
      }
    } on ApiException catch (e) {
      if (context.mounted) _erreur(context, e);
    }
  }

  Future<void> _ouvrirGarantie(BuildContext context, WidgetRef ref) async {
    final String? description = await _demanderTexte(
      context,
      titre: 'La panne est revenue',
      indication: 'Décrivez ce qui se reproduit (10 caractères au moins).',
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
      if (context.mounted) _erreur(context, e);
    }
  }

  Future<void> _signalerHorsPlateforme(
    BuildContext context,
    WidgetRef ref,
  ) async {
    final String? description = await _demanderTexte(
      context,
      titre: 'Paiement hors application',
      indication:
          'Expliquez ce qui vous a été demandé. Rester dans l’application, '
          'c’est garder la garantie et le support.',
      bouton: 'Envoyer au support',
    );

    if (description == null) return;

    try {
      await ref
          .read(clientRepositoryProvider)
          .signalerHorsPlateforme(ticket.id, description);
      if (context.mounted) {
        showPcToast(context, 'Signalement transmis au support.');
      }
    } on ApiException catch (e) {
      if (context.mounted) _erreur(context, e);
    }
  }

  void _erreur(BuildContext context, ApiException e) =>
      showPcToast(context, e.message, icone: Icons.error_outline_rounded);

  /// Saisie d'un texte d'au moins dix caractères, réutilisée par la garantie
  /// et le signalement. Renvoie `null` si l'utilisateur renonce.
  Future<String?> _demanderTexte(
    BuildContext context, {
    required String titre,
    required String indication,
    required String bouton,
  }) {
    final TextEditingController controleur = TextEditingController();

    return showPcSheet<String>(
      context,
      builder: (BuildContext ctx) => StatefulBuilder(
        builder: (BuildContext ctx, StateSetter setState) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(titre, style: Theme.of(ctx).textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(
              indication,
              style: TextStyle(
                color: ctx.pc.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 14),
            PcField(
              libelle: 'Votre message',
              controleur: controleur,
              lignes: 4,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 14),
            PcButton(
              libelle: bouton,
              onPressed: controleur.text.trim().length >= 10
                  ? () => Navigator.of(ctx).pop(controleur.text.trim())
                  : null,
            ),
          ],
        ),
      ),
    ).whenComplete(controleur.dispose);
  }

  /// L'annulation demande confirmation, et **annonce les frais avant** de les
  /// appliquer. Les découvrir après serait exactement la surprise que le reste
  /// du système cherche à éviter.
  Future<void> _confirmerAnnulation(BuildContext context, WidgetRef ref) async {
    final bool payante = ticket.etat != 'PUBLIEE' && ticket.etat != 'ACCEPTEE';

    final bool? confirme = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Annuler votre demande ?',
            style: Theme.of(ctx).textTheme.titleLarge,
          ),
          const SizedBox(height: 8),
          Text(
            payante
                ? 'Le technicien est déjà en route : des frais d’annulation '
                      'tardive s’appliqueront.'
                : 'Personne n’est encore intervenu : l’annulation est '
                      'gratuite.',
            style: TextStyle(color: ctx.pc.ink2, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Oui, annuler',
            variante: PcVariante.corail,
            onPressed: () => Navigator.of(ctx).pop(true),
          ),
          const SizedBox(height: 8),
          PcButton(
            libelle: 'Non, garder ma demande',
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(false),
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
      if (context.mounted) _erreur(context, e);
    }
  }
}
