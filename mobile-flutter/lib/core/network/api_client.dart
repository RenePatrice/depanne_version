import 'package:dio/dio.dart';

import '../auth/token_store.dart';
import '../config/app_config.dart';
import 'api_exception.dart';
import 'auth_interceptor.dart';

/// Point d'entrée unique vers l'API.
///
/// Aucun écran n'utilise Dio directement : tout passe par ici, ce qui garantit
/// que chaque appel porte le jeton, respecte les délais, et convertit ses
/// erreurs en `ApiException` — donc en français.
class ApiClient {
  ApiClient._(this._dio);

  final Dio _dio;

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

    return ApiClient._(principal);
  }

  Future<Map<String, dynamic>> get(
    String chemin, {
    Map<String, dynamic>? parametres,
  }) => _appeler(() => _dio.get<dynamic>(chemin, queryParameters: parametres));

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

      // Une réponse vide est un succès valide — `204`, ou un corps qu'on
      // n'attend pas. On renvoie une carte vide plutôt que de lever.
      return corps is Map<String, dynamic> ? corps : <String, dynamic>{};
    } on DioException catch (e) {
      throw ApiException.depuisDio(e);
    }
  }
}
