import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

/// Un message reçu sur un canal.
///
/// Le nom de canal est celui du serveur, **sans** le préfixe `private-` : les
/// écrans pensent en `ticket.42`, pas en conventions du protocole Pusher.
@immutable
class EvenementTempsReel {
  const EvenementTempsReel({
    required this.canal,
    required this.nom,
    this.donnees = const <String, dynamic>{},
  });

  final String canal;
  final String nom;
  final Map<String, dynamic> donnees;
}

/// Ce dont le service a besoin d'un WebSocket, et rien de plus.
///
/// Une interface étroite plutôt que `WebSocketChannel` directement : c'est ce
/// qui permet de rejouer, en test, une connexion qui tombe et revient — le cas
/// courant sur le réseau de Conakry, et le seul qui compte vraiment ici.
abstract interface class ConnexionSocket {
  Stream<dynamic> get flux;

  void envoyer(String trame);

  void fermer();
}

class _ConnexionWebSocket implements ConnexionSocket {
  _ConnexionWebSocket(this._canal);

  final WebSocketChannel _canal;

  @override
  Stream<dynamic> get flux => _canal.stream;

  @override
  void envoyer(String trame) => _canal.sink.add(trame);

  @override
  void fermer() => _canal.sink.close();
}

ConnexionSocket ouvrirWebSocket(Uri adresse) =>
    _ConnexionWebSocket(WebSocketChannel.connect(adresse));

/// Signature d'un canal privé, délivrée par l'API jeton à l'appui.
typedef AutoriserCanal = Future<String> Function(
  String socketId,
  String nomCanal,
);

/// Client Reverb (protocole Pusher 7), écrit à la main.
///
/// Le protocole tient en six messages — connexion établie, abonnement, ping,
/// pong, erreur, événement — et une bibliothèque tierce aurait apporté un
/// cycle de vie de plus à suivre pour en économiser une centaine de lignes.
///
/// Trois règles le gouvernent :
///
/// - **il n'est jamais indispensable.** Tout ce qu'il annonce, l'application
///   l'aurait appris à la prochaine interrogation. Une panne ici dégrade la
///   fraîcheur, jamais le fonctionnement ; c'est pourquoi aucune erreur n'en
///   sort.
/// - **il ne transporte que des signaux.** Un événement dit « relis ce
///   ticket » ; c'est l'API qui dit ce qu'il contient. Deux sources de vérité
///   finiraient par se contredire, et l'écran ne saurait pas laquelle croire.
/// - **il se reconnecte seul**, avec un délai qui double à chaque échec jusqu'à
///   une minute. Réessayer en boucle serrée viderait la batterie d'un
///   téléphone qui n'a simplement pas de réseau.
class TempsReelService {
  TempsReelService({
    required String url,
    required String cle,
    required this._autoriser,
    this._ouvrir = ouvrirWebSocket,
    Duration Function(int essai)? attente,
  }) : _adresse = Uri.parse(
         '$url/app/$cle?protocol=7&client=depanne-moi&version=1.0',
       ),
       _attente = attente ?? _attenteParDefaut;

  final Uri _adresse;
  final AutoriserCanal _autoriser;
  final ConnexionSocket Function(Uri adresse) _ouvrir;
  final Duration Function(int essai) _attente;

  final StreamController<EvenementTempsReel> _evenements =
      StreamController<EvenementTempsReel>.broadcast();
  final StreamController<bool> _etats = StreamController<bool>.broadcast();

  /// Nombre d'écrans abonnés à chaque canal. Un canal n'est quitté que quand
  /// le dernier écran qui l'écoutait se ferme : le suivi et le chat d'un même
  /// ticket partagent `ticket.42`.
  final Map<String, int> _abonnes = <String, int>{};

  ConnexionSocket? _socket;
  StreamSubscription<dynamic>? _ecoute;
  Timer? _reconnexion;
  Timer? _battement;
  String? _socketId;
  int _echecs = 0;
  bool _arrete = false;

  Stream<EvenementTempsReel> get evenements => _evenements.stream;

  /// Vrai entre l'établissement de la connexion et sa perte. Les écrans s'en
  /// servent pour espacer leurs interrogations de repli.
  bool get connecte => _socketId != null;

  Stream<bool> get etats => _etats.stream;

  Stream<EvenementTempsReel> surCanal(String canal) =>
      evenements.where((EvenementTempsReel e) => e.canal == canal);

  void demarrer() {
    _arrete = false;
    _connecter();
  }

  void abonner(String canal) {
    final int avant = _abonnes[canal] ?? 0;
    _abonnes[canal] = avant + 1;

    if (avant == 0 && _socketId != null) unawaited(_souscrire(canal));
  }

  void desabonner(String canal) {
    final int restant = (_abonnes[canal] ?? 0) - 1;

    if (restant > 0) {
      _abonnes[canal] = restant;
      return;
    }

    _abonnes.remove(canal);

    if (_socketId != null) {
      _envoyer('pusher:unsubscribe', <String, dynamic>{
        'channel': 'private-$canal',
      });
    }
  }

  Future<void> fermer() async {
    _arrete = true;
    _reconnexion?.cancel();
    _couper();
    await _evenements.close();
    await _etats.close();
  }

  // ------------------------------------------------------------- connexion --

  void _connecter() {
    if (_arrete) return;

    try {
      final ConnexionSocket socket = _ouvrir(_adresse);
      _socket = socket;
      _ecoute = socket.flux.listen(
        _recevoir,
        onError: (Object _) => _perdu(),
        onDone: _perdu,
        cancelOnError: true,
      );
    } on Object {
      _perdu();
    }
  }

  void _perdu() {
    final bool etaitConnecte = _socketId != null;
    _couper();

    if (etaitConnecte) _etats.add(false);
    if (_arrete) return;

    _reconnexion?.cancel();
    _reconnexion = Timer(_attente(_echecs), _connecter);
    _echecs++;
  }

  void _couper() {
    _battement?.cancel();
    _ecoute?.cancel();
    _ecoute = null;
    _socket?.fermer();
    _socket = null;
    _socketId = null;
  }

  static Duration _attenteParDefaut(int essai) =>
      Duration(seconds: (1 << essai.clamp(0, 6)).clamp(1, 60));

  // ------------------------------------------------------------- réception --

  void _recevoir(dynamic brut) {
    final Map<String, dynamic>? trame = _decoder(brut);
    if (trame == null) return;

    final String evenement = trame['event'] as String? ?? '';
    final Map<String, dynamic> donnees = _decoder(trame['data']) ?? const {};

    switch (evenement) {
      case 'pusher:connection_established':
        _socketId = donnees['socket_id'] as String?;
        _echecs = 0;
        _etats.add(true);
        _battre(donnees['activity_timeout']);
        for (final String canal in _abonnes.keys) {
          unawaited(_souscrire(canal));
        }

      case 'pusher:ping':
        _envoyer('pusher:pong', const <String, dynamic>{});

      case 'pusher:pong':
      case 'pusher:error':
      case 'pusher_internal:subscription_succeeded':
        // Rien à faire : une erreur d'abonnement laisse l'écran sur son
        // interrogation de repli, qui continue de toute façon.
        break;

      default:
        final String? canal = trame['channel'] as String?;
        if (canal == null) return;

        _evenements.add(
          EvenementTempsReel(
            canal: canal.replaceFirst('private-', ''),
            nom: evenement,
            donnees: donnees,
          ),
        );
    }
  }

  /// Pusher double-encode : le champ `data` est une chaîne JSON dans une trame
  /// JSON. Une trame illisible est ignorée plutôt que de couper la connexion.
  static Map<String, dynamic>? _decoder(dynamic brut) {
    if (brut is Map<String, dynamic>) return brut;
    if (brut is! String || brut.isEmpty) return null;

    try {
      final dynamic valeur = jsonDecode(brut);
      return valeur is Map<String, dynamic> ? valeur : null;
    } on FormatException {
      return null;
    }
  }

  // ---------------------------------------------------------------- envoi --

  Future<void> _souscrire(String canal) async {
    final String? socketId = _socketId;
    if (socketId == null) return;

    try {
      final String signature = await _autoriser(socketId, 'private-$canal');

      // La connexion a pu tomber pendant l'aller-retour : la signature est
      // liée à l'ancien socket et ne vaudrait rien sur le suivant.
      if (_socketId != socketId || !_abonnes.containsKey(canal)) return;

      _envoyer('pusher:subscribe', <String, dynamic>{
        'channel': 'private-$canal',
        'auth': signature,
      });
    } on Object {
      // Refus (le ticket n'est plus le nôtre) ou réseau : l'écran garde son
      // interrogation de repli. Rien à montrer à l'utilisateur.
    }
  }

  /// Le serveur coupe une connexion silencieuse au bout de son délai
  /// d'inactivité. Un ping régulier la garde ouverte entre deux événements.
  void _battre(dynamic delaiSecondes) {
    _battement?.cancel();
    final int secondes = delaiSecondes is int && delaiSecondes > 5
        ? delaiSecondes - 5
        : 25;

    _battement = Timer.periodic(
      Duration(seconds: secondes),
      (_) => _envoyer('pusher:ping', const <String, dynamic>{}),
    );
  }

  void _envoyer(String evenement, Map<String, dynamic> donnees) {
    try {
      _socket?.envoyer(
        jsonEncode(<String, dynamic>{'event': evenement, 'data': donnees}),
      );
    } on Object {
      _perdu();
    }
  }
}
