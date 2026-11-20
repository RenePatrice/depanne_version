import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/pc.dart';
import '../../client/application/client_providers.dart';
import '../../client/presentation/paiement_feuille.dart';
import '../../shared/data/modeles.dart';
import '../application/technicien_providers.dart';
import 'code_arrivee_feuille.dart';
import 'composants_technicien.dart';
import 'diagnostic_feuille.dart';

/// L'intervention en cours, vue du technicien (§7.3).
///
/// Il nomme un geste — « je pars », « je suis arrivé » — et jamais un état :
/// c'est la machine à états du serveur qui décide si le geste est possible.
/// Une liste d'états recopiée ici finirait par diverger de la sienne, et
/// l'écran proposerait des boutons qui échouent.
///
/// Le geste principal se **glisse** : un effleurement accidentel dans une
/// poche ne doit pas annoncer « je suis arrivé » au client.
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

  /// Le client peut accepter ou refuser un supplément, ou confirmer un
  /// paiement, pendant que le technicien regarde l'écran. Ça arrive par
  /// Reverb ; cette boucle n'est que le repli.
  bool _rafraichir() {
    final Ticket? ticket = ref.read(ticketProvider(widget.ticketId)).value;

    if (ticket != null && ticket.etatFinal) return false;

    ref.invalidate(ticketProvider(widget.ticketId));
    return true;
  }

  Future<void> _avancer(String etape) async {
    if (_enCours) return;

    // Code d'arrivée (lot 2) : « je suis arrivé » se prouve par les quatre
    // chiffres que le client lit dans son application.
    String? code;
    final ArriveeTicket? arrivee = ref
        .read(ticketProvider(widget.ticketId))
        .value
        ?.arrivee;

    if (etape == 'sur-place' && (arrivee?.codeRequis ?? false)) {
      code = await CodeArriveeFeuille.demander(
        context,
        essaisRestants: arrivee?.essaisRestants,
      );
      if (code == null || !mounted) return;
    }

    setState(() => _enCours = true);

    try {
      await ref
          .read(technicienRepositoryProvider)
          .avancer(widget.ticketId, etape, code: code);
      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(interventionsProvider);
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
      // Un code refusé change le nombre d'essais restants : on relit.
      ref.invalidate(ticketProvider(widget.ticketId));
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
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
              titre: ticket.value?.prestation?.nom ?? 'Intervention',
              sousTitre: ticket.value?.reference,
              onRetour: () =>
                  context.canPop() ? context.pop() : context.go('/accueil'),
            ),
            Expanded(
              child: ticket.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (Object e, StackTrace _) => Padding(
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
                      const SizedBox(height: 12),
                      _Progression(etat: t.etat),
                      const SizedBox(height: 14),
                      _Gestes(
                        ticket: t,
                        enCours: _enCours,
                        onAvancer: _avancer,
                      ),
                      if (_versLeClient(t)) ...<Widget>[
                        const SizedBox(height: 14),
                        _CarteTrajet(ticket: t),
                      ],
                      const PcSection('Destination'),
                      _Destination(ticket: t),
                      if (t.client != null) ...<Widget>[
                        const SizedBox(height: 12),
                        _Client(client: t.client!, ticket: t),
                      ],
                      if (t.description != null || t.photos.isNotEmpty) ...[
                        const PcSection('La panne'),
                        _Panne(ticket: t),
                      ],
                      const PcSection('Rémunération'),
                      _Remuneration(ticket: t),
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

  static bool _versLeClient(Ticket t) =>
      t.etat == 'ACCEPTEE' || t.etat == 'EN_ROUTE';
}

class _Etat extends StatelessWidget {
  const _Etat({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: PcRadius.lgAll,
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[PcColors.tech, PcColors.techDeep],
        ),
        boxShadow: PcShadow.glow(PcColors.tech),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            ticket.etatLibelle,
            style: const TextStyle(
              fontFamily: PcFonts.display,
              fontWeight: FontWeight.w700,
              fontSize: 18,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            _explication(ticket.etat),
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.88),
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  /// Ce que le technicien doit faire maintenant, en une phrase : « Diagnostic
  /// en attente » n'apprend rien à qui se demande s'il doit continuer.
  static String _explication(String etat) => switch (etat) {
    'ACCEPTEE' => 'Le client vous attend. Glissez « Je pars » en partant.',
    'EN_ROUTE' =>
      'Le client suit votre trajet. Signalez votre arrivée sur place.',
    'SUR_PLACE' => 'Vous êtes arrivé. Démarrez quand vous commencez.',
    'EN_COURS' => 'Travail en cours. Terminez quand c’est fait.',
    'DIAGNOSTIC_EN_ATTENTE' =>
      'Votre supplément est proposé. Le client doit répondre avant que vous '
          'continuiez.',
    'DIAGNOSTIC_VALIDE' => 'Supplément accepté. Continuez le travail.',
    'DIAGNOSTIC_REFUSE' =>
      'Supplément refusé. Faites ce qui était prévu, au prix annoncé.',
    'TERMINEE' => 'Travail terminé. Il reste à enregistrer le paiement.',
    'PAYEE' =>
      'Payé dans l’application. Les fonds arrivent dès que le client '
          'valide, ou sous 24 heures.',
    'CLOTUREE' => 'Intervention close. Merci !',
    'LITIGE_OUVERT' => 'Une réclamation est ouverte. Le support vous contacte.',
    _ => 'Suivez les étapes ci-dessous.',
  };
}

class _Progression extends StatelessWidget {
  const _Progression({required this.etat});

  final String etat;

  static const List<(String, IconData)> _etapes = <(String, IconData)>[
    ('Départ', Icons.flag_rounded),
    ('Trajet', Icons.two_wheeler_rounded),
    ('Sur place', Icons.location_on_rounded),
    ('Travaux', Icons.handyman_rounded),
    ('Paiement', Icons.payments_rounded),
  ];

  int get _index => switch (etat) {
    'ACCEPTEE' => 0,
    'EN_ROUTE' => 1,
    'SUR_PLACE' => 2,
    'EN_COURS' ||
    'DIAGNOSTIC_EN_ATTENTE' ||
    'DIAGNOSTIC_VALIDE' ||
    'DIAGNOSTIC_REFUSE' => 3,
    'TERMINEE' || 'PAYEE' || 'LITIGE_OUVERT' => 4,
    'CLOTUREE' => 5,
    _ => -1,
  };

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int courant = _index;
    if (courant < 0) return const SizedBox.shrink();

    return PcCard(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
      child: Row(
        children: <Widget>[
          for (int i = 0; i < _etapes.length; i++)
            Expanded(
              child: Column(
                children: <Widget>[
                  Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: i <= courant ? PcColors.tech : pc.soft,
                    ),
                    child: Icon(
                      i < courant ? Icons.check_rounded : _etapes[i].$2,
                      size: 16,
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
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Les gestes possibles, et eux seuls. Le bouton affiché dépend de l'état
/// renvoyé par le serveur : aucun geste n'est proposé « au cas où ».
class _Gestes extends StatelessWidget {
  const _Gestes({
    required this.ticket,
    required this.enCours,
    required this.onAvancer,
  });

  final Ticket ticket;
  final bool enCours;
  final Future<void> Function(String etape) onAvancer;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final (String etape, String libelle)? geste = switch (ticket.etat) {
      'ACCEPTEE' => ('en-route', 'Glisser : je pars'),
      'EN_ROUTE' => ('sur-place', 'Glisser : je suis arrivé'),
      'SUR_PLACE' => ('demarrer', 'Glisser : je commence'),
      'EN_COURS' ||
      'DIAGNOSTIC_VALIDE' ||
      'DIAGNOSTIC_REFUSE' => ('terminer', 'Glisser : j’ai terminé'),
      _ => null,
    };

    // Un supplément ne se propose qu'une fois sur place et avant la fin.
    final bool peutProposer =
        ticket.etat == 'SUR_PLACE' || ticket.etat == 'EN_COURS';

    // Paiement tracé (ADR-0047) : une fois terminé, le technicien déclare ce
    // qu'il a reçu en espèces, ou confirme ce que le client a déclaré. Un
    // retour sous garantie est gratuit — rien à encaisser.
    final bool paiement =
        ticket.etat == 'TERMINEE' && !ticket.estUnRetourGarantie;

    if (geste == null && !peutProposer && !paiement) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        // Code d'arrivée bloqué (lot 2) : plus de geste possible, seul le
        // client peut confirmer l'arrivée depuis son application.
        if (geste != null && geste.$1 == 'sur-place' && ticket.arrivee.bloque)
          PcCard(
            couleur: PcColors.danger.withValues(alpha: 0.12),
            bordure: PcColors.danger.withValues(alpha: 0.4),
            child: Row(
              children: <Widget>[
                const Icon(Icons.lock_rounded, color: PcColors.danger),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Trop d’essais avec un code incorrect. Le client a été '
                    'prévenu : il peut confirmer votre arrivée depuis son '
                    'application.',
                    style: TextStyle(color: pc.ink, fontSize: 13),
                  ),
                ),
              ],
            ),
          )
        else if (geste != null) ...<Widget>[
          GlisserPourConfirmer(
            libelle: geste.$2,
            chargement: enCours,
            onConfirme: () => onAvancer(geste.$1),
          ),
          if (geste.$1 == 'sur-place' && ticket.arrivee.codeRequis) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              'Le client vous donnera son code d’arrivée à 4 chiffres.',
              textAlign: TextAlign.center,
              style: TextStyle(color: pc.muted, fontSize: 12),
            ),
          ],
        ],
        if (paiement) ...<Widget>[
          PcButton(
            libelle: 'Enregistrer le paiement',
            icone: Icons.payments_rounded,
            variante: PcVariante.tech,
            onPressed: () => PaiementFeuille.ouvrir(context, ticket.id),
          ),
          const SizedBox(height: 8),
          Text(
            'Le client choisit son moyen. Chacun confirme de son côté ; la '
            'commission due est suivie dans Gains.',
            textAlign: TextAlign.center,
            style: TextStyle(color: pc.muted, fontSize: 12),
          ),
        ],
        if (peutProposer) ...<Widget>[
          const SizedBox(height: 10),
          PcButton(
            libelle: 'Proposer un supplément',
            icone: Icons.add_card_rounded,
            variante: PcVariante.doux,
            petit: true,
            onPressed: () => ProposerDiagnosticFeuille.ouvrir(context, ticket),
          ),
          const SizedBox(height: 6),
          Text(
            'Le client doit accepter avant que le total change. Un diagnostic '
            'écrit est obligatoire.',
            textAlign: TextAlign.center,
            style: TextStyle(color: pc.muted, fontSize: 12),
          ),
        ],
      ],
    );
  }
}

/// Le chemin jusqu'au client, sur OpenStreetMap : sa porte, et la position du
/// téléphone. La navigation pas à pas reste confiée à l'application de
/// cartographie du téléphone (ADR-0037) : elle connaît le trafic, et ne coûte
/// aucune clé.
class _CarteTrajet extends StatefulWidget {
  const _CarteTrajet({required this.ticket});

  final Ticket ticket;

  @override
  State<_CarteTrajet> createState() => _CarteTrajetState();
}

class _CarteTrajetState extends State<_CarteTrajet> {
  static const LatLng _conakry = LatLng(9.6412, -13.5784);

  final MapController _carte = MapController();
  LatLng? _moi;
  Timer? _minuteur;

  @override
  void initState() {
    super.initState();
    _localiser();
    _minuteur = Timer.periodic(
      const Duration(seconds: 15),
      (_) => _localiser(),
    );
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    _carte.dispose();
    super.dispose();
  }

  /// Position du téléphone, sans jamais redemander la permission : c'est le
  /// suivi de l'intervention qui l'a demandée au moment où elle servait.
  Future<void> _localiser() async {
    try {
      final LocationPermission p = await Geolocator.checkPermission();
      if (p != LocationPermission.always &&
          p != LocationPermission.whileInUse) {
        return;
      }
      final Position pos =
          await Geolocator.getLastKnownPosition() ??
          await Geolocator.getCurrentPosition();
      if (!mounted) return;
      setState(() => _moi = LatLng(pos.latitude, pos.longitude));
      _cadrer();
    } on Object {
      // Le GPS met parfois du temps : la prochaine lecture réessaiera.
    }
  }

  LatLng? get _destination {
    final Object? lat = widget.ticket.adresse?['latitude'];
    final Object? lng = widget.ticket.adresse?['longitude'];
    return lat is num && lng is num
        ? LatLng(lat.toDouble(), lng.toDouble())
        : null;
  }

  void _cadrer() {
    final LatLng? d = _destination;
    final LatLng? m = _moi;
    try {
      if (d != null && m != null) {
        _carte.fitCamera(
          CameraFit.bounds(
            bounds: LatLngBounds(m, d),
            padding: const EdgeInsets.all(50),
            maxZoom: 16,
          ),
        );
      }
    } on Object {
      // Carte pas encore dessinée : `onMapReady` cadrera.
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final LatLng? d = _destination;
    final LatLng? m = _moi;
    final double? km = d == null || m == null
        ? null
        : const Distance().as(LengthUnit.Meter, m, d) / 1000;

    return ClipRRect(
      borderRadius: PcRadius.lgAll,
      child: SizedBox(
        height: 240,
        child: Stack(
          children: <Widget>[
            FlutterMap(
              mapController: _carte,
              options: MapOptions(
                initialCenter: d ?? m ?? _conakry,
                initialZoom: 14,
                minZoom: 10,
                maxZoom: 18,
                onMapReady: _cadrer,
              ),
              children: <Widget>[
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'gn.depannemoi.app',
                  maxZoom: 18,
                ),
                if (d != null && m != null)
                  PolylineLayer(
                    polylines: <Polyline>[
                      Polyline(
                        points: <LatLng>[m, d],
                        strokeWidth: 3,
                        color: PcColors.tech.withValues(alpha: 0.6),
                        pattern: StrokePattern.dashed(
                          segments: const <double>[8, 6],
                        ),
                      ),
                    ],
                  ),
                MarkerLayer(
                  markers: <Marker>[
                    if (d != null)
                      Marker(
                        point: d,
                        width: 40,
                        height: 40,
                        child: const _Repere(
                          icone: Icons.home_rounded,
                          couleur: PcColors.coral,
                        ),
                      ),
                    if (m != null)
                      Marker(
                        point: m,
                        width: 44,
                        height: 44,
                        child: const _Repere(
                          icone: Icons.two_wheeler_rounded,
                          couleur: PcColors.tech,
                        ),
                      ),
                  ],
                ),
                const RichAttributionWidget(
                  attributions: <SourceAttribution>[
                    TextSourceAttribution('© OpenStreetMap'),
                  ],
                ),
              ],
            ),
            Positioned(
              left: 12,
              top: 12,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: pc.card,
                  borderRadius: BorderRadius.circular(99),
                  boxShadow: pc.ombre,
                ),
                child: Text(
                  km == null
                      ? 'Localisation…'
                      : '${km.toStringAsFixed(1)} km à vol d’oiseau',
                  style: TextStyle(
                    color: pc.ink,
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                  ),
                ),
              ),
            ),
            if (d != null)
              Positioned(
                right: 12,
                bottom: 22,
                child: PcButton(
                  libelle: 'Itinéraire',
                  icone: Icons.navigation_rounded,
                  variante: PcVariante.tech,
                  petit: true,
                  largeurPleine: false,
                  onPressed: () => ouvrirItineraire(context, widget.ticket),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Repere extends StatelessWidget {
  const _Repere({required this.icone, required this.couleur});

  final IconData icone;
  final Color couleur;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: couleur,
      shape: BoxShape.circle,
      border: Border.all(color: Colors.white, width: 3),
      boxShadow: PcShadow.glow(couleur),
    ),
    child: Icon(icone, color: Colors.white, size: 19),
  );
}

/// Passe la main à l'application de cartographie du téléphone (schéma
/// `geo:`, sans clé). Si rien ne peut l'ouvrir, on le dit plutôt que de
/// laisser un bouton silencieux.
Future<void> ouvrirItineraire(BuildContext context, Ticket ticket) async {
  final Object? lat = ticket.adresse?['latitude'];
  final Object? lng = ticket.adresse?['longitude'];
  if (lat is! num || lng is! num) return;

  final Uri lien = Uri.parse(
    'geo:$lat,$lng?q=$lat,$lng(${Uri.encodeComponent(ticket.reference)})',
  );

  final bool ouvert = await launchUrl(
    lien,
    mode: LaunchMode.externalApplication,
  ).catchError((_) => false);

  if (!ouvert && context.mounted) {
    showPcToast(
      context,
      'Aucune application de navigation sur ce téléphone.',
      icone: Icons.error_outline_rounded,
    );
  }
}

/// Où aller. Le repère écrit est affiché en grand : à Conakry, c'est lui qui
/// amène devant la porte, pas la coordonnée.
class _Destination extends StatelessWidget {
  const _Destination({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final String? repere = ticket.adresse?['landmark'] as String?;

    return PcCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const PcIco(
                Icons.place_rounded,
                couleur: PcColors.coral,
                taille: 40,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  ticket.adresseLisible,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
            ],
          ),
          if (repere != null && repere.isNotEmpty) ...<Widget>[
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: pc.teinte(PcColors.coral),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Point de repère',
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w700,
                      fontSize: 11.5,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    repere,
                    style: TextStyle(
                      color: pc.ink,
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
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

class _Client extends StatelessWidget {
  const _Client({required this.client, required this.ticket});

  final PartieTicket client;
  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final String? tel = ticket.etatActif ? client.telephone : null;

    return PcCard(
      child: Row(
        children: <Widget>[
          PcAvatar(nom: client.nomComplet, image: client.avatarUrl, taille: 46),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  client.nomComplet,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                Text(
                  // Le numéro n'est en clair que pendant une intervention
                  // active ; masqué, on le dit.
                  tel ?? 'Numéro visible pendant l’intervention',
                  style: TextStyle(color: pc.muted, fontSize: 12.5),
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
            fond: PcColors.tech,
            couleur: Colors.white,
            onTap: () => context.push('/technicien/chat/${ticket.id}'),
          ),
        ],
      ),
    );
  }
}

class _Panne extends StatelessWidget {
  const _Panne({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (ticket.description != null)
            Text(
              '« ${ticket.description!} »',
              style: TextStyle(color: pc.ink, fontSize: 14),
            ),
          if (ticket.photos.isNotEmpty) ...<Widget>[
            if (ticket.description != null) const SizedBox(height: 12),
            BandePhotos(photos: ticket.photos),
          ],
          if (ticket.diagnostic != null) ...<Widget>[
            const SizedBox(height: 12),
            Text(
              'Votre diagnostic',
              style: TextStyle(
                color: pc.muted,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
            Text(ticket.diagnostic!, style: TextStyle(color: pc.ink2)),
          ],
        ],
      ),
    );
  }
}

/// Le net, pas le total : c'est ce qui arrive au portefeuille. Le total est
/// rappelé pour pouvoir le dire au client sans chercher ailleurs.
class _Remuneration extends StatelessWidget {
  const _Remuneration({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int? net = ticket.prix.netTechnicienGnf;

    return PcCard(
      child: Row(
        children: <Widget>[
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Vous touchez',
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
                net == null
                    ? Text('—', style: Theme.of(context).textTheme.titleLarge)
                    : PcMontant(net, taille: 22, couleur: PcColors.tech),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text(
                'Le client paie',
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
              PcMontant(ticket.prix.totalGnf, taille: 16),
            ],
          ),
        ],
      ),
    );
  }
}
