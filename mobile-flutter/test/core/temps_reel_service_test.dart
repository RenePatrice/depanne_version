import 'dart:async';
import 'dart:convert';

import 'package:depanne_moi/core/temps_reel/temps_reel_service.dart';
import 'package:flutter_test/flutter_test.dart';

/// Socket piloté par le test : il reçoit ce que le serveur enverrait et garde
/// ce que le client a envoyé.
class _SocketFactice implements ConnexionSocket {
  final StreamController<dynamic> _serveur = StreamController<dynamic>();
  final List<Map<String, dynamic>> envoyees = <Map<String, dynamic>>[];
  bool ferme = false;

  @override
  Stream<dynamic> get flux => _serveur.stream;

  @override
  void envoyer(String trame) =>
      envoyees.add(jsonDecode(trame) as Map<String, dynamic>);

  @override
  void fermer() => ferme = true;

  void recevoir(
    String evenement,
    Map<String, dynamic> donnees, {
    String? canal,
  }) => _serveur.add(
    jsonEncode(<String, dynamic>{
      'event': evenement,
      'channel': ?canal,
      // Pusher double-encode : `data` est une chaîne JSON.
      'data': jsonEncode(donnees),
    }),
  );

  void etablir([String socketId = '123.456']) => recevoir(
    'pusher:connection_established',
    <String, dynamic>{'socket_id': socketId, 'activity_timeout': 30},
  );

  void couper() => _serveur.close();

  List<String> evenementsEnvoyes() =>
      envoyees.map((Map<String, dynamic> t) => t['event'] as String).toList();
}

class _Banc {
  _Banc({this.refuser = false}) {
    service = TempsReelService(
      url: 'ws://reverb.test:8080',
      cle: 'cle-publique',
      autoriser: (String socketId, String canal) async {
        autorisations.add('$socketId|$canal');
        if (refuser) throw Exception('403');
        return 'cle-publique:signature';
      },
      ouvrir: (Uri adresse) {
        adresses.add(adresse);
        final _SocketFactice socket = _SocketFactice();
        sockets.add(socket);
        return socket;
      },
      attente: (_) => Duration.zero,
    );
  }

  final bool refuser;
  late final TempsReelService service;
  final List<_SocketFactice> sockets = <_SocketFactice>[];
  final List<Uri> adresses = <Uri>[];
  final List<String> autorisations = <String>[];

  _SocketFactice get socket => sockets.last;
}

Future<void> _laisserPasser() => Future<void>.delayed(Duration.zero);

void main() {
  test('se connecte avec la clé publique et le protocole 7', () {
    final _Banc banc = _Banc()..service.demarrer();

    expect(
      banc.adresses.single.toString(),
      startsWith('ws://reverb.test:8080/app/cle-publique?protocol=7'),
    );
  });

  test(
    'ne s’abonne qu’une fois la connexion établie, signature à l’appui',
    () async {
      final _Banc banc = _Banc()
        ..service.abonner('ticket.42')
        ..service.demarrer();

      await _laisserPasser();
      expect(banc.socket.envoyees, isEmpty);

      banc.socket.etablir();
      await _laisserPasser();

      expect(banc.autorisations, <String>['123.456|private-ticket.42']);
      expect(banc.socket.envoyees.single, <String, dynamic>{
        'event': 'pusher:subscribe',
        'data': <String, dynamic>{
          'channel': 'private-ticket.42',
          'auth': 'cle-publique:signature',
        },
      });
    },
  );

  test('livre les événements sous le nom de canal du serveur', () async {
    final _Banc banc = _Banc()..service.demarrer();
    final List<EvenementTempsReel> recus = <EvenementTempsReel>[];
    banc.service.surCanal('ticket.42').listen(recus.add);

    banc.socket
      ..etablir()
      ..recevoir('ticket.etat', <String, dynamic>{
        'id': 42,
        'etat': 'EN_ROUTE',
      }, canal: 'private-ticket.42')
      ..recevoir('ticket.etat', <String, dynamic>{
        'id': 7,
      }, canal: 'private-ticket.7');
    await _laisserPasser();

    expect(recus, hasLength(1));
    expect(recus.single.nom, 'ticket.etat');
    expect(recus.single.donnees['etat'], 'EN_ROUTE');
  });

  test('répond au ping du serveur', () async {
    final _Banc banc = _Banc()..service.demarrer();

    banc.socket
      ..etablir()
      ..recevoir('pusher:ping', const <String, dynamic>{});
    await _laisserPasser();

    expect(banc.socket.evenementsEnvoyes(), contains('pusher:pong'));
  });

  test('se reconnecte après une coupure et se réabonne', () async {
    final _Banc banc = _Banc()
      ..service.abonner('ticket.42')
      ..service.demarrer();
    final List<bool> etats = <bool>[];
    banc.service.etats.listen(etats.add);

    banc.socket.etablir('1.1');
    await _laisserPasser();
    banc.socket.couper();
    await _laisserPasser();
    await _laisserPasser();

    expect(banc.sockets, hasLength(2));

    banc.socket.etablir('2.2');
    await _laisserPasser();

    // Une signature est liée à un socket : la nouvelle connexion en
    // redemande une, l'ancienne ne vaudrait rien.
    expect(banc.autorisations, <String>[
      '1.1|private-ticket.42',
      '2.2|private-ticket.42',
    ]);
    expect(etats, <bool>[true, false, true]);
  });

  test('avale un refus d’autorisation sans couper la connexion', () async {
    final _Banc banc = _Banc(refuser: true)
      ..service.abonner('ticket.99')
      ..service.demarrer();

    banc.socket.etablir();
    await _laisserPasser();

    // Le ticket n'est plus le nôtre (réassignation) : l'écran garde son
    // interrogation de repli, rien ne remonte.
    expect(banc.socket.envoyees, isEmpty);
    expect(banc.socket.ferme, isFalse);
    expect(banc.service.connecte, isTrue);
  });

  test('ne quitte un canal partagé qu’au départ du dernier écran', () async {
    final _Banc banc = _Banc()..service.demarrer();
    banc.socket.etablir();
    await _laisserPasser();

    banc.service
      ..abonner('ticket.42')
      ..abonner('ticket.42');
    await _laisserPasser();
    banc.service.desabonner('ticket.42');

    expect(banc.socket.evenementsEnvoyes(), <String>['pusher:subscribe']);

    banc.service.desabonner('ticket.42');

    expect(banc.socket.evenementsEnvoyes(), <String>[
      'pusher:subscribe',
      'pusher:unsubscribe',
    ]);
  });

  test('ignore une trame illisible plutôt que de couper', () async {
    final _Banc banc = _Banc()..service.demarrer();
    banc.socket.etablir();
    await _laisserPasser();

    banc.socket._serveur.add('pas du JSON');
    await _laisserPasser();

    expect(banc.service.connecte, isTrue);
  });

  test('cesse de se reconnecter une fois fermé', () async {
    final _Banc banc = _Banc()..service.demarrer();
    banc.socket.etablir();
    await _laisserPasser();

    await banc.service.fermer();
    await _laisserPasser();

    expect(banc.sockets, hasLength(1));
    expect(banc.socket.ferme, isTrue);
  });
}
