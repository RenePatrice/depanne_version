import 'package:dio/dio.dart';

import '../auth/token_store.dart';
import '../config/app_config.dart';
import '../hors_ligne/cache_lecture.dart';
import 'api_exception.dart';
import 'auth_interceptor.dart';

/// Point d'entrée unique vers l'API.
///
/// Aucun écran n'utilise Dio directement : tout passe par ici, ce qui garantit
/// que chaque appel porte le jeton, respecte les délais, et convertit ses
/// erreurs en `ApiException` — donc en français.
class ApiClient {
  ApiClient._(this._dio, this._cache, this._surReseau);

  final Dio _dio;
  final CacheLecture _cache;

  /// Prévenu à chaque réponse : `null` quand le serveur a répondu, l'heure des
  /// données affichées quand elles viennent du cache. C'est ce qui allume le
  /// bandeau « hors connexion ».
  final void Function(DateTime? donneesDu) _surReseau;

  /// Exposé pour les cas particuliers (téléversement, annulation). À utiliser
  /// avec parcimonie : passer par les méthodes typées garde la traduction des
  /// erreurs.
  Dio get dio => _dio;

  factory ApiClient.creer({
    required AppConfig config,
    required TokenStore store,
    required Future<void> Function() surDeconnexion,
    Dio? dio,
    Dio? dioRafraichissement,
    CacheLecture? cache,
    void Function(DateTime? donneesDu)? surReseau,
  }) {
    final BaseOptions options = BaseOptions(
      baseUrl: config.baseUrl,
      connectTimeout: config.connectTimeout,
      receiveTimeout: config.receiveTimeout,
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      // On laisse Dio traiter les 4xx comme des erreurs, à dessein : c'est ce
      // qui fait passer un 401 par `AuthInterceptor.onError`, donc par le
      // rafraîchissement et le rejeu. Élargir `validateStatus` aux 4xx — comme
      // on le fait souvent pour lire le corps plus commodément — rendrait tout
      // ce mécanisme silencieusement inopérant : l'utilisateur serait
      // déconnecté à la première expiration de jeton.
      validateStatus: (int? statut) => statut != null && statut < 400,
    );

    final Dio principal = dio ?? Dio();
    principal.options = options;

    // Un second client, **sans intercepteur**, pour le rafraîchissement.
    // Réutiliser le principal ferait rentrer l'appel de refresh dans son
    // propre intercepteur : un 401 sur le refresh déclencherait un refresh, à
    // l'infini.
    final Dio pourRafraichir = dioRafraichissement ?? Dio();
    pourRafraichir.options = options;

    principal.interceptors.add(
      AuthInterceptor(
        store: store,
        dioRafraichissement: pourRafraichir,
        surDeconnexion: surDeconnexion,
      ),
    );

    return ApiClient._(
      principal,
      cache ?? CacheEnMemoire(),
      surReseau ?? (_) {},
    );
  }

  /// Lecture.
  ///
  /// Avec `repliHorsLigne`, une réponse réussie est mémorisée, et une coupure
  /// réseau rend la dernière version connue au lieu d'une erreur. C'est une
  /// décision **par chemin**, prise à l'appel : un ticket ou un portefeuille
  /// d'il y a dix minutes reste utile, une sollicitation ou l'état d'un
  /// paiement d'il y a dix minutes serait un mensonge — la première a expiré,
  /// le second a peut-être abouti.
  Future<Map<String, dynamic>> get(
    String chemin, {
    Map<String, dynamic>? parametres,
    bool repliHorsLigne = false,
  }) async {
    if (!repliHorsLigne) {
      return _appeler(
        () => _dio.get<dynamic>(chemin, queryParameters: parametres),
      );
    }

    final String cle = Uri(
      path: chemin,
      queryParameters: parametres?.map(
        (String k, dynamic v) => MapEntry<String, String>(k, '$v'),
      ),
    ).toString();

    try {
      final Map<String, dynamic> reponse = await _appeler(
        () => _dio.get<dynamic>(chemin, queryParameters: parametres),
      );
      await _cache.ecrire(cle, reponse);

      return reponse;
    } on ApiException catch (e) {
      final LectureEnCache? connue = e.reseau ? await _cache.lire(cle) : null;
      if (connue == null) rethrow;

      _surReseau(connue.recueLe);

      return connue.donnees;
    }
  }

  Future<Map<String, dynamic>> post(String chemin, {Object? corps}) =>
      _appeler(() => _dio.post<dynamic>(chemin, data: corps));

  Future<Map<String, dynamic>> patch(String chemin, {Object? corps}) =>
      _appeler(() => _dio.patch<dynamic>(chemin, data: corps));

  Future<Map<String, dynamic>> delete(String chemin, {Object? corps}) =>
      _appeler(() => _dio.delete<dynamic>(chemin, data: corps));

  /// Enveloppe commune : exécute, et traduit toute erreur en français.
  Future<Map<String, dynamic>> _appeler(
    Future<Response<dynamic>> Function() requete,
  ) async {
    try {
      final Response<dynamic> reponse = await requete();
      final dynamic corps = reponse.data;
      _surReseau(null);

      // Une réponse vide est un succès valide — `204`, ou un corps qu'on
      // n'attend pas. On renvoie une carte vide plutôt que de lever.
      return corps is Map<String, dynamic> ? corps : <String, dynamic>{};
    } on DioException catch (e) {
      throw ApiException.depuisDio(e);
    }
  }
}
