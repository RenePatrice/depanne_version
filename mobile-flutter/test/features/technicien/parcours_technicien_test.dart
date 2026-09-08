import 'dart:convert';

import 'package:depanne_moi/core/auth/token_pair.dart';
import 'package:depanne_moi/core/auth/token_store.dart';
import 'package:depanne_moi/core/config/app_config.dart';
import 'package:depanne_moi/core/format/montant.dart';
import 'package:depanne_moi/core/network/api_client.dart';
import 'package:depanne_moi/features/shared/data/modeles.dart';
import 'package:depanne_moi/features/technicien/data/modeles.dart';
import 'package:depanne_moi/features/technicien/data/technicien_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

class _Adaptateur implements HttpClientAdapter {
  _Adaptateur(this.reponse);

  final Map<String, dynamic> reponse;
  final List<RequestOptions> appels = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    appels.add(options);

    return ResponseBody.fromString(
      jsonEncode(reponse),
      200,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}

  Map<String, dynamic> corpsDe(RequestOptions o) {
    final Object? data = o.data;

    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }
}

const Map<String, dynamic> _reponseFourreTout = <String, dynamic>{
  'en_ligne': true,
  'ok': true,
  'sollicitations': <dynamic>[],
  'data': <dynamic>[],
  'retraits': <dynamic>[],
  'solde_gnf': 0,
  'ticket': <String, dynamic>{
    'id': 7,
    'reference': 'DM-2026-000007',
    'etat': 'ACCEPTEE',
    'etat_libelle': 'Acceptée',
  },
  'retrait': <String, dynamic>{
    'reference': 'RET-1',
    'montant_gnf': 100000,
    'montant_formate': '100 000 GNF',
    'numero': '+224620000000',
    'operateur': 'ORANGE_MONEY',
    'statut': 'PENDING',
    'statut_libelle': 'En attente',
  },
};

(TechnicienRepository, _Adaptateur) _construire() {
  final _Adaptateur adaptateur = _Adaptateur(_reponseFourreTout);

  final ApiClient api = ApiClient.creer(
    config: const AppConfig(baseUrl: 'https://exemple.test/api/v1'),
    store: MemoryTokenStore()
      ..ecrire(
        TokenPair(
          accessToken: 'acces',
          refreshToken: 'refresh',
          expireLe: DateTime.now().add(const Duration(minutes: 10)),
        ),
      ),
    surDeconnexion: () async {},
    dio: Dio()..httpClientAdapter = adaptateur,
    dioRafraichissement: Dio()..httpClientAdapter = adaptateur,
  );

  return (TechnicienRepository(api), adaptateur);
}

void main() {
  group('sollicitation', () {
    Sollicitation sollicitation({
      required int expireDansS,
      String reponse = 'EN_ATTENTE',
    }) => Sollicitation.depuisJson(<String, dynamic>{
      'id': 1,
      'reference': 'DM-2026-000001',
      'prestation': 'Réparation de fuite',
      'quartier': 'Kipé',
      'distance_km': 2.4,
      'total_formate': '86 000 GNF',
      'net_formate': '69 000 GNF',
      'net_technicien_gnf': 69000,
      'expire_dans_s': expireDansS,
      'reponse': reponse,
      'cycle': 1,
    });

    test('n’est vivante que tant que la fenêtre est ouverte', () {
      expect(sollicitation(expireDansS: 30).estVivante, isTrue);
      expect(sollicitation(expireDansS: 0).estVivante, isFalse);
      expect(
        sollicitation(expireDansS: 30, reponse: 'REFUSE').estVivante,
        isFalse,
      );
    });

    test('ne porte pas l’adresse exacte avant acceptation', () {
      // Le §7.3 réserve l'adresse à celui qui s'est engagé : sans cette
      // réserve, refuser systématiquement les demandes deviendrait un moyen de
      // collecter des adresses.
      final Sollicitation s = sollicitation(expireDansS: 30);

      expect(s.quartier, 'Kipé');
      expect(s.distanceKm, 2.4);
    });

    test('annonce ce que le technicien touche, pas ce que le client paie', () {
      final Sollicitation s = sollicitation(expireDansS: 30);

      expect(s.netFormate, '69 000 GNF');
      expect(s.totalFormate, '86 000 GNF');
    });
  });

  group('portefeuille', () {
    test('distingue le solde du retirable', () {
      // Le retirable retranche ce qui est engagé dans une demande non versée :
      // les confondre ferait déposer trois demandes du même argent.
      final Portefeuille p = Portefeuille.depuisJson(<String, dynamic>{
        'solde_gnf': 300000,
        'solde_formate': '300 000 GNF',
        'engage_gnf': 100000,
        'retirable_gnf': 200000,
        'retrait_minimum_gnf': 50000,
        'mouvements': <dynamic>[],
      });

      expect(p.soldeGnf, 300000);
      expect(p.engageGnf, 100000);
      expect(p.retirableGnf, 200000);
    });

    test('garde le signe des mouvements', () {
      // Le grand livre écrit deux mouvements par intervention : le gain, puis
      // la commission en négatif. Un mouvement net unique rendrait le chiffre
      // d'affaires irrécupérable.
      final Mouvement gain = Mouvement.depuisJson(<String, dynamic>{
        'id': 1,
        'type': 'EARNING',
        'type_libelle': 'Gain',
        'montant_gnf': 86000,
        'montant_formate': '86 000 GNF',
      });

      final Mouvement commission = Mouvement.depuisJson(<String, dynamic>{
        'id': 2,
        'type': 'COMMISSION',
        'type_libelle': 'Commission',
        'montant_gnf': -17000,
        'montant_formate': '-17 000 GNF',
      });

      expect(gain.estUneEntree, isTrue);
      expect(commission.estUneEntree, isFalse);
    });
  });

  group('montants', () {
    test('s’écrivent comme ceux du serveur', () {
      const String e = separateurMillier;

      expect(formaterGnf(86000), '86${e}000${e}GNF');
      expect(formaterGnf(0), '0${e}GNF');
      expect(formaterGnf(-17000), '-17${e}000${e}GNF');
      expect(formaterGnf(1234567), '1${e}234${e}567${e}GNF');
    });

    test('utilisent le même séparateur que le serveur', () {
      // `Money::SEPARATEUR` vaut U+202F, une espace fine insécable. Une espace
      // ordinaire passerait inaperçue à l'œil et couperait « 86 000 » en fin
      // de ligne ; ce test est là pour que la dérive se voie avant.
      expect(separateurMillier, '\u202F');
      expect(separateurMillier.codeUnitAt(0), 0x202F);
    });
  });

  group('appels au serveur', () {
    test('nomme un geste, jamais un état', () async {
      // C'est la machine à états du serveur qui décide si le geste est
      // possible. Envoyer un état depuis l'application ferait diverger les
      // deux listes, et l'écran proposerait des boutons qui échouent.
      final (TechnicienRepository depot, _Adaptateur adaptateur) =
          _construire();

      await depot.avancer(7, 'en-route');

      final RequestOptions appel = adaptateur.appels.single;

      expect(appel.path, '/tickets/7/avancer');
      expect(adaptateur.corpsDe(appel)['etape'], 'en-route');
      expect(adaptateur.corpsDe(appel).containsKey('etat'), isFalse);
    });

    test('ne fait voyager aucun montant, sauf sur le diagnostic', () async {
      // Le pendant technicien du garde-fou : le prix d'une intervention est
      // calculé côté serveur, à partir de la grille de la zone et de la
      // position réelle. Une seule route fait exception, et elle **propose** —
      // le total ne bouge qu'après accord du client (§8.1).
      final (TechnicienRepository depot, _Adaptateur adaptateur) =
          _construire();

      await depot.definirDisponibilite(true);
      await depot.remonterPosition(latitude: 9.596, longitude: -13.642);
      await depot.accepter(3);
      await depot.refuser(4, motif: 'Trop loin');
      await depot.avancer(7, 'terminer');

      const List<String> interdits = <String>[
        'montant',
        'total',
        'prix',
        'amount',
        'price',
        'gnf',
        'km',
      ];

      for (final RequestOptions appel in adaptateur.appels) {
        for (final String cle in adaptateur.corpsDe(appel).keys) {
          expect(
            interdits.any(cle.toLowerCase().contains),
            isFalse,
            reason: '${appel.path} envoie « $cle » au serveur',
          );
        }
      }
    });

    test('le diagnostic est la seule route qui porte un montant', () async {
      final (TechnicienRepository depot, _Adaptateur adaptateur) =
          _construire();

      await depot.proposerSupplement(
        7,
        diagnostic: 'Le siphon est fendu, il faut le remplacer entièrement.',
        supplementGnf: 25000,
      );

      final RequestOptions appel = adaptateur.appels.single;

      // L'exception est nommée, pas glissée : elle porte un diagnostic écrit,
      // et son adresse dit ce qu'elle fait.
      expect(appel.path, '/tickets/7/diagnostic');
      expect(adaptateur.corpsDe(appel)['supplement_gnf'], 25000);
      expect(
        (adaptateur.corpsDe(appel)['diagnostic'] as String).length,
        greaterThanOrEqualTo(20),
      );
    });

    test('le retrait porte le montant que le technicien a choisi', () async {
      // Ce n'est pas un prix d'intervention : c'est son argent, il décide
      // combien il en sort. Le serveur revérifie le retirable dans la
      // transaction qui écrit le mouvement.
      final (TechnicienRepository depot, _Adaptateur adaptateur) =
          _construire();

      await depot.demanderRetrait(
        montantGnf: 100000,
        numero: '+224620000000',
        operateur: 'ORANGE_MONEY',
      );

      final RequestOptions appel = adaptateur.appels.single;

      expect(appel.path, '/retraits');
      expect(adaptateur.corpsDe(appel), <String, dynamic>{
        'montant_gnf': 100000,
        'numero': '+224620000000',
        'operateur': 'ORANGE_MONEY',
      });
    });

    test('omet le motif de refus quand il n’est pas donné', () async {
      final (TechnicienRepository depot, _Adaptateur adaptateur) =
          _construire();

      await depot.refuser(4);

      expect(adaptateur.corpsDe(adaptateur.appels.single), isEmpty);
    });
  });

  group('ticket vu du technicien', () {
    test('lit le client et la part nette', () {
      // Le serveur envoie `client` au technicien et `technicien` au client,
      // jamais les deux ; et le net n'apparaît que du côté technicien.
      final Ticket t = Ticket.depuisJson(<String, dynamic>{
        'id': 7,
        'reference': 'DM-2026-000007',
        'etat': 'EN_COURS',
        'etat_libelle': 'En cours',
        'etat_actif': true,
        'client': <String, dynamic>{
          'id': 2,
          'nom_complet': 'Aminata Diallo',
          'telephone': '+224620000001',
        },
        'prix': <String, dynamic>{
          'total_gnf': 86000,
          'total_formate': '86 000 GNF',
          'net_technicien_gnf': 69000,
        },
      });

      expect(t.client?.nomComplet, 'Aminata Diallo');
      expect(t.technicien, isNull);
      expect(t.prix.netTechnicienGnf, 69000);
    });

    test('laisse la part nette vide quand c’est le client qui regarde', () {
      final Ticket t = Ticket.depuisJson(<String, dynamic>{
        'id': 7,
        'reference': 'DM-2026-000007',
        'etat': 'EN_COURS',
        'etat_libelle': 'En cours',
        'prix': <String, dynamic>{
          'total_gnf': 86000,
          'total_formate': '86 000 GNF',
        },
      });

      expect(t.prix.netTechnicienGnf, isNull);
    });
  });
}
