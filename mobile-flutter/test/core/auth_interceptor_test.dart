import 'package:depanne_moi/core/auth/token_pair.dart';
import 'package:depanne_moi/core/auth/token_store.dart';
import 'package:depanne_moi/core/config/app_config.dart';
import 'package:depanne_moi/core/network/api_client.dart';
import 'package:depanne_moi/core/network/api_exception.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Adaptateur Dio piloté par le test : il répond ce qu'on lui dit, et compte
/// ce qu'on lui a demandé.
///
/// Un vrai serveur rendrait ces tests lents et dépendants du réseau — or
/// c'est précisément la concurrence des requêtes qu'on veut observer, pas le
/// transport.
class _AdaptateurFactice implements HttpClientAdapter {
  _AdaptateurFactice(this.repondre);

  final Future<ResponseBody> Function(RequestOptions options) repondre;

  final List<RequestOptions> appels = <RequestOptions>[];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) {
    appels.add(options);
    return repondre(options);
  }

  @override
  void close({bool force = false}) {}

  int compter(String chemin) =>
      appels.where((RequestOptions o) => o.path.endsWith(chemin)).length;
}

ResponseBody _json(Map<String, dynamic> corps, int statut) =>
    ResponseBody.fromString(
      _encoder(corps),
      statut,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );

String _encoder(Map<String, dynamic> corps) {
  final StringBuffer b = StringBuffer('{');
  bool premier = true;
  corps.forEach((String k, dynamic v) {
    if (!premier) b.write(',');
    premier = false;
    b.write('"$k":');
    if (v is Map<String, dynamic>) {
      b.write(_encoder(v));
    } else if (v is num || v is bool) {
      b.write('$v');
    } else {
      b.write('"$v"');
    }
  });
  b.write('}');
  return b.toString();
}

/// Couple de jetons, expiré ou non selon le besoin du test.
TokenPair _jetons({required bool expire, String acces = 'acces-1'}) =>
    TokenPair(
      accessToken: acces,
      refreshToken: 'refresh-1',
      expireLe: expire
          ? DateTime.now().subtract(const Duration(minutes: 1))
          : DateTime.now().add(const Duration(minutes: 14)),
    );

Map<String, dynamic> _reponseJetons(String acces) => <String, dynamic>{
  'jetons': <String, dynamic>{
    'access_token': acces,
    'refresh_token': 'refresh-2',
    'expire_dans': 900,
  },
};

void main() {
  late MemoryTokenStore coffre;
  late int deconnexions;

  setUp(() {
    coffre = MemoryTokenStore();
    deconnexions = 0;
  });

  /// Construit un client dont l'adaptateur est partagé par le Dio principal et
  /// celui du rafraîchissement — comme un vrai serveur le serait.
  (ApiClient, _AdaptateurFactice) construire(
    Future<ResponseBody> Function(RequestOptions) repondre,
  ) {
    final _AdaptateurFactice adaptateur = _AdaptateurFactice(repondre);

    final Dio principal = Dio()..httpClientAdapter = adaptateur;
    final Dio rafraichissement = Dio()..httpClientAdapter = adaptateur;

    final ApiClient client = ApiClient.creer(
      config: const AppConfig(baseUrl: 'https://exemple.test/api/v1'),
      store: coffre,
      surDeconnexion: () async => deconnexions++,
      dio: principal,
      dioRafraichissement: rafraichissement,
    );

    return (client, adaptateur);
  }

  group('jeton porté sur la requête', () {
    test(
      'ajoute l’en-tête Authorization quand un jeton est en mémoire',
      () async {
        await coffre.ecrire(_jetons(expire: false));

        final (ApiClient client, _AdaptateurFactice adaptateur) = construire(
          (RequestOptions o) async => _json(<String, dynamic>{'ok': true}, 200),
        );

        await client.get('/moi');

        expect(
          adaptateur.appels.single.headers['Authorization'],
          'Bearer acces-1',
        );
      },
    );

    test('n’envoie pas de jeton sur les routes ouvertes', () async {
      await coffre.ecrire(_jetons(expire: false));

      final (ApiClient client, _AdaptateurFactice adaptateur) = construire(
        (RequestOptions o) async => _json(<String, dynamic>{'ok': true}, 200),
      );

      await client.get('/catalogue');

      expect(
        adaptateur.appels.single.headers.containsKey('Authorization'),
        isFalse,
      );
    });
  });

  group('renouvellement préventif', () {
    test('renouvelle avant d’envoyer quand le jeton est expiré', () async {
      await coffre.ecrire(_jetons(expire: true));

      final (ApiClient client, _AdaptateurFactice adaptateur) = construire((
        RequestOptions o,
      ) async {
        if (o.path.endsWith('/auth/refresh')) {
          return _json(_reponseJetons('acces-2'), 200);
        }
        return _json(<String, dynamic>{'ok': true}, 200);
      });

      await client.get('/moi');

      expect(adaptateur.compter('/auth/refresh'), 1);
      // La requête métier est partie avec le jeton neuf, pas l'expiré.
      expect(adaptateur.appels.last.headers['Authorization'], 'Bearer acces-2');
      expect((await coffre.lire())!.refreshToken, 'refresh-2');
    });

    test('ne lance qu’un seul rafraîchissement pour plusieurs requêtes simultanées', () async {
      // C'est le test qui compte. Le refresh token tourne à chaque usage : si
      // trois requêtes concurrentes en déclenchaient trois, le serveur y
      // verrait un rejeu et révoquerait toutes les sessions du compte.
      await coffre.ecrire(_jetons(expire: true));

      final (ApiClient client, _AdaptateurFactice adaptateur) = construire((
        RequestOptions o,
      ) async {
        if (o.path.endsWith('/auth/refresh')) {
          // Latence réaliste : sans elle, le premier rafraîchissement
          // s'achèverait avant que le deuxième ne démarre, et le test
          // passerait sans rien prouver.
          await Future<void>.delayed(const Duration(milliseconds: 30));
          return _json(_reponseJetons('acces-2'), 200);
        }
        return _json(<String, dynamic>{'ok': true}, 200);
      });

      await Future.wait<void>(<Future<void>>[
        client.get('/moi'),
        client.get('/tickets'),
        client.get('/notifications'),
      ]);

      expect(adaptateur.compter('/auth/refresh'), 1);
    });
  });

  group('rejeu après 401', () {
    test('renouvelle puis rejoue la requête refusée', () async {
      await coffre.ecrire(_jetons(expire: false));

      int appelsMoi = 0;

      final (ApiClient client, _AdaptateurFactice adaptateur) = construire((
        RequestOptions o,
      ) async {
        if (o.path.endsWith('/auth/refresh')) {
          return _json(_reponseJetons('acces-2'), 200);
        }
        appelsMoi++;
        // Le premier appel est refusé, le second — avec le jeton neuf — passe.
        return appelsMoi == 1
            ? _json(<String, dynamic>{'message': 'Non authentifié.'}, 401)
            : _json(<String, dynamic>{'ok': true}, 200);
      });

      final Map<String, dynamic> reponse = await client.get('/moi');

      expect(reponse['ok'], true);
      expect(adaptateur.compter('/auth/refresh'), 1);
      expect(appelsMoi, 2);
    });

    test(
      'ne rejoue qu’une fois, même si le nouveau jeton est refusé',
      () async {
        // Sans ce garde-fou, un compte révoqué ferait boucler l'application sur
        // le réseau du client jusqu'à épuiser sa batterie et son forfait.
        await coffre.ecrire(_jetons(expire: false));

        final (ApiClient client, _AdaptateurFactice adaptateur) = construire((
          RequestOptions o,
        ) async {
          if (o.path.endsWith('/auth/refresh')) {
            return _json(_reponseJetons('acces-2'), 200);
          }
          return _json(<String, dynamic>{'message': 'Non authentifié.'}, 401);
        });

        await expectLater(
          client.get('/moi'),
          throwsA(isA<ApiException>().having((e) => e.statut, 'statut', 401)),
        );

        expect(adaptateur.compter('/moi'), 2);
      },
    );

    test('déconnecte quand le rafraîchissement est lui-même refusé', () async {
      await coffre.ecrire(_jetons(expire: false));

      final (ApiClient client, _) = construire((RequestOptions o) async {
        if (o.path.endsWith('/auth/refresh')) {
          return _json(<String, dynamic>{'message': 'Session expirée.'}, 401);
        }
        return _json(<String, dynamic>{'message': 'Non authentifié.'}, 401);
      });

      await expectLater(client.get('/moi'), throwsA(isA<ApiException>()));

      expect(deconnexions, 1);
      expect(await coffre.lire(), isNull);
    });

    test('ne déconnecte pas sur une coupure réseau pendant le renouvellement', () async {
      // Un réseau coupé n'invalide rien : les jetons restent, la requête
      // échouera et sera réessayée. Les effacer déconnecterait l'utilisateur à
      // chaque perte de couverture — ce qui, à Conakry, est quotidien.
      await coffre.ecrire(_jetons(expire: false));

      final (ApiClient client, _) = construire((RequestOptions o) async {
        if (o.path.endsWith('/auth/refresh')) {
          throw DioException.connectionError(
            requestOptions: o,
            reason: 'reseau coupe',
          );
        }
        return _json(<String, dynamic>{'message': 'Non authentifié.'}, 401);
      });

      await expectLater(client.get('/moi'), throwsA(isA<ApiException>()));

      expect(deconnexions, 0);
      expect(await coffre.lire(), isNotNull);
    });

    test('ne tente aucun renouvellement sur un échec de connexion', () async {
      // Un 401 sur `/auth/connexion` veut dire « mauvais mot de passe », pas
      // « session expirée ». Le confondre effacerait les jetons d'une session
      // valide parce qu'un proche s'est trompé de mot de passe.
      await coffre.ecrire(_jetons(expire: false));

      final (ApiClient client, _AdaptateurFactice adaptateur) = construire(
        (RequestOptions o) async => _json(<String, dynamic>{
          'message': 'Numéro ou mot de passe incorrect.',
        }, 401),
      );

      await expectLater(
        client.post('/auth/connexion', corps: <String, dynamic>{}),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'message',
            'Numéro ou mot de passe incorrect.',
          ),
        ),
      );

      expect(adaptateur.compter('/auth/refresh'), 0);
      expect(deconnexions, 0);
    });
  });

  group('traduction des erreurs', () {
    test('reprend le message français de l’API', () async {
      final (ApiClient client, _) = construire(
        (RequestOptions o) async => _json(<String, dynamic>{
          'message': 'Cette adresse est hors de notre zone de couverture.',
        }, 422),
      );

      await expectLater(
        client.post('/devis'),
        throwsA(
          isA<ApiException>()
              .having(
                (e) => e.message,
                'message',
                contains('hors de notre zone'),
              )
              .having((e) => e.estValidation, 'estValidation', isTrue),
        ),
      );
    });

    test(
      'signale une coupure réseau pour que l’écran propose de réessayer',
      () async {
        final (ApiClient client, _) = construire(
          (RequestOptions o) async => throw DioException.connectionError(
            requestOptions: o,
            reason: 'coupe',
          ),
        );

        await expectLater(
          client.get('/catalogue'),
          throwsA(
            isA<ApiException>().having((e) => e.reseau, 'reseau', isTrue),
          ),
        );
      },
    );
  });
}
