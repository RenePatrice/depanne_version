import 'dart:convert';

import 'package:depanne_moi/core/auth/token_pair.dart';
import 'package:depanne_moi/core/auth/token_store.dart';
import 'package:depanne_moi/core/config/app_config.dart';
import 'package:depanne_moi/core/hors_ligne/cache_lecture.dart';
import 'package:depanne_moi/core/network/api_client.dart';
import 'package:depanne_moi/core/network/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Un réseau qu'on coupe et qu'on rétablit à volonté.
class _Reseau implements HttpClientAdapter {
  bool coupe = false;
  int statut = 200;
  Map<String, dynamic> reponse = <String, dynamic>{'etat': 'EN_ROUTE'};

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (coupe) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'réseau coupé',
      );
    }

    return ResponseBody.fromString(
      jsonEncode(reponse),
      statut,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

class _Banc {
  _Banc() {
    api = ApiClient.creer(
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
      dio: Dio()..httpClientAdapter = reseau,
      dioRafraichissement: Dio()..httpClientAdapter = reseau,
      cache: cache,
      surReseau: signaux.add,
    );
  }

  final _Reseau reseau = _Reseau();
  final CacheEnMemoire cache = CacheEnMemoire();
  final List<DateTime?> signaux = <DateTime?>[];
  late final ApiClient api;
}

void main() {
  test('rend la dernière version connue quand le réseau tombe', () async {
    final _Banc banc = _Banc();

    await banc.api.get('/tickets/7', repliHorsLigne: true);
    banc.reseau
      ..coupe = true
      ..reponse = <String, dynamic>{'etat': 'SUR_PLACE'};

    final Map<String, dynamic> lu = await banc.api.get(
      '/tickets/7',
      repliHorsLigne: true,
    );

    expect(lu['etat'], 'EN_ROUTE');
    // Le bandeau s'allume avec l'heure des données : afficher une information
    // ancienne comme si elle était fraîche serait pire qu'une erreur.
    expect(banc.signaux.last, isNotNull);
  });

  test('éteint le bandeau à la première réponse du serveur', () async {
    final _Banc banc = _Banc();

    await banc.api.get('/tickets/7', repliHorsLigne: true);
    banc.reseau.coupe = true;
    await banc.api.get('/tickets/7', repliHorsLigne: true);
    banc.reseau.coupe = false;
    await banc.api.get('/catalogue');

    expect(banc.signaux.last, isNull);
  });

  test('n’offre aucun repli aux lectures qui ne l’ont pas demandé', () async {
    final _Banc banc = _Banc();

    // Une sollicitation ou un état de paiement d'il y a dix minutes serait un
    // mensonge : la première a expiré, le second a peut-être abouti.
    await banc.api.get('/paiements/PAY-1');
    banc.reseau.coupe = true;

    await expectLater(
      banc.api.get('/paiements/PAY-1'),
      throwsA(isA<ApiException>().having((e) => e.reseau, 'reseau', isTrue)),
    );
  });

  test('ne masque pas une vraie réponse d’erreur par le cache', () async {
    final _Banc banc = _Banc();

    await banc.api.get('/tickets/7', repliHorsLigne: true);
    banc.reseau.statut = 404;

    // Le serveur a répondu : le ticket n'est plus le nôtre. Montrer l'ancienne
    // copie ferait croire le contraire.
    await expectLater(
      banc.api.get('/tickets/7', repliHorsLigne: true),
      throwsA(isA<ApiException>().having((e) => e.statut, 'statut', 404)),
    );
  });

  test('distingue les lectures par leurs paramètres', () async {
    final _Banc banc = _Banc();

    banc.reseau.reponse = <String, dynamic>{'liste': 'en cours'};
    await banc.api.get(
      '/tickets',
      parametres: <String, dynamic>{'filtre': 'en_cours'},
      repliHorsLigne: true,
    );
    banc.reseau.reponse = <String, dynamic>{'liste': 'toutes'};
    await banc.api.get('/tickets', repliHorsLigne: true);

    banc.reseau.coupe = true;

    expect(
      (await banc.api.get(
        '/tickets',
        parametres: <String, dynamic>{'filtre': 'en_cours'},
        repliHorsLigne: true,
      ))['liste'],
      'en cours',
    );
  });

  test('oublie tout au vidage', () async {
    final CacheEnMemoire cache = CacheEnMemoire();
    await cache.ecrire('/portefeuille', <String, dynamic>{'solde_gnf': 90000});

    await cache.vider();

    expect(await cache.lire('/portefeuille'), isNull);
  });
}
