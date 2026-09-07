import 'dart:convert';

import 'package:depanne_moi/core/auth/token_pair.dart';
import 'package:depanne_moi/core/auth/token_store.dart';
import 'package:depanne_moi/core/config/app_config.dart';
import 'package:depanne_moi/core/network/api_client.dart';
import 'package:depanne_moi/features/client/data/client_repository.dart';
import 'package:depanne_moi/features/client/data/modeles.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Adaptateur Dio piloté par le test : il répond toujours la même chose et
/// conserve ce qu'on lui a envoyé.
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

/// Réponse assez complète pour que toutes les fabriques y trouvent leur compte,
/// quelle que soit la route appelée.
const Map<String, dynamic> _reponseFourreTout = <String, dynamic>{
  'categories': <dynamic>[],
  'adresses': <dynamic>[],
  'data': <dynamic>[],
  'libere': true,
  'adresse': <String, dynamic>{
    'id': 1,
    'libelle': 'Maison',
    'adresse': 'Kipé',
    'par_defaut': false,
  },
  'devis': <String, dynamic>{
    'total_gnf': 90000,
    'total_formate': '90 000 GNF',
    'ferme': false,
    'lignes': <dynamic>[],
  },
  'ticket': <String, dynamic>{
    'id': 7,
    'reference': 'DM-2026-000007',
    'etat': 'PUBLIEE',
    'etat_libelle': 'Publiée',
  },
  'paiement': <String, dynamic>{
    'reference': 'PAY-1',
    'statut': 'EN_ATTENTE',
    'montant_gnf': 90000,
  },
  'reference': 'PAY-1',
  'statut': 'EN_ATTENTE',
  'montant_gnf': 90000,
  'diagnostic': 'Le siphon est fendu.',
  'supplement_gnf': 25000,
  'total_actuel_gnf': 90000,
};

(ClientRepository, _Adaptateur) _construire() {
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

  return (ClientRepository(api), adaptateur);
}

void main() {
  group('devis', () {
    test('n’est pas ferme tant qu’aucun technicien n’a accepté', () {
      final Devis devis = Devis.depuisJson(<String, dynamic>{
        'total_gnf': 86000,
        'total_formate': '86 000 GNF',
        'ferme': false,
        'lignes': <dynamic>[
          <String, dynamic>{
            'libelle': 'Déplacement',
            'montant_gnf': 300,
            'montant_formate': '300 GNF',
            'detail': '0,1 km au-delà des 3 km inclus',
          },
        ],
      });

      // C'est ce drapeau qui décide du mot affiché — « à partir de » plutôt que
      // « total » (ADR-0026). Le confondre produirait la première facture
      // surprise, donc la première réclamation.
      expect(devis.ferme, isFalse);
      expect(devis.lignes.single.detail, isNotNull);
    });

    test('reprend le total formaté du serveur, sans le recalculer', () {
      final Devis devis = Devis.depuisJson(<String, dynamic>{
        'total_gnf': 86000,
        'total_formate': '86 000 GNF',
        'ferme': true,
        'lignes': <dynamic>[],
      });

      // Le montant affiché est celui que le serveur a produit : reformater ici
      // ferait diverger l'écran de la caisse au premier changement de règle.
      expect(devis.totalFormate, '86 000 GNF');
      expect(devis.ferme, isTrue);
    });
  });

  group('ticket', () {
    Ticket ticket(String etat) => Ticket.depuisJson(<String, dynamic>{
      'id': 1,
      'reference': 'DM-2026-000001',
      'etat': etat,
      'etat_libelle': etat,
    });

    test('sait ce qu’il attend du client', () {
      expect(ticket('PUBLIEE').enRecherche, isTrue);
      expect(ticket('DIAGNOSTIC_EN_ATTENTE').attendUneDecision, isTrue);
      expect(ticket('TERMINEE').aPayer, isTrue);
      expect(ticket('PAYEE').aValider, isTrue);
    });

    test('n’ouvre la notation qu’une fois l’intervention réglée', () {
      // L'API l'accepterait dès TERMINEE ; l'application ne le propose qu'après
      // paiement, pour ne pas laisser croire que la note conditionne le
      // règlement.
      expect(ticket('TERMINEE').estNotable, isFalse);
      expect(ticket('PAYEE').estNotable, isTrue);
      expect(ticket('CLOTUREE').estNotable, isTrue);
    });

    test('reste lisible quand le serveur n’envoie ni prix ni adresse', () {
      final Ticket t = ticket('BROUILLON');

      expect(t.prix.totalGnf, 0);
      expect(t.adresseLisible, 'Adresse non renseignée');
      expect(t.jalons, isEmpty);
    });
  });

  group('adresse', () {
    test('est réputée desservie quand le serveur ne se prononce pas', () {
      // `couverte` n'est renseigné que sur les routes qui ont interrogé
      // PostGIS. Ailleurs, griser l'adresse laisserait croire à tort qu'elle
      // est hors zone.
      final Adresse a = Adresse.depuisJson(<String, dynamic>{
        'id': 1,
        'libelle': 'Maison',
        'adresse': 'Kipé',
        'par_defaut': true,
      });

      expect(a.couverte, isNull);
      expect(a.estDesservie, isTrue);
    });
  });

  group('appels au serveur', () {
    test('publie une demande avec les champs attendus par l’API', () async {
      final (ClientRepository depot, _Adaptateur adaptateur) = _construire();

      await depot.publier(
        prestationId: 3,
        adresseId: 9,
        description: 'Fuite sous l’évier',
      );

      final RequestOptions appel = adaptateur.appels.single;

      expect(appel.path, '/tickets');
      expect(adaptateur.corpsDe(appel), <String, dynamic>{
        'service_id': 3,
        'address_id': 9,
        'problem_description': 'Fuite sous l’évier',
      });
    });

    test('omet le numéro de paiement quand il n’est pas saisi', () async {
      final (ClientRepository depot, _Adaptateur adaptateur) = _construire();

      await depot.lancerPaiement(7);

      // Envoyer `telephone: null` ferait échouer la validation Laravel sur un
      // champ que le client n'a pas rempli. La clé doit disparaître, pas valoir
      // nul.
      expect(adaptateur.corpsDe(adaptateur.appels.single), isEmpty);
    });

    test('ne fait voyager aucun montant vers le serveur', () async {
      // Le pendant mobile du garde-fou de l'API : le prix est calculé côté
      // serveur, à partir de la grille de la zone et de la position du
      // technicien. Aucune route du parcours client ne doit pouvoir
      // l'influencer — c'est la porte d'entrée d'une facture négociée dans
      // l'application.
      final (ClientRepository depot, _Adaptateur adaptateur) = _construire();

      await depot.devis(prestationId: 3, adresseId: 9);
      await depot.publier(prestationId: 3, adresseId: 9);
      await depot.repondreAuSupplement(7, accepte: true);
      await depot.lancerPaiement(7, telephone: '+224620000000');
      await depot.validerIntervention(7);
      await depot.annuler(7, motif: 'Plus besoin');
      await depot.noter(7, note: 5);
      await depot.ajouterAdresse(
        libelle: 'Maison',
        adresse: 'Kipé',
        latitude: 9.596,
        longitude: -13.642,
      );

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
  });
}
