import 'package:dio/dio.dart';

/// Erreur d'API traduite pour un être humain.
///
/// Le cahier des charges est explicite : « messages d'erreur clairs en
/// français, jamais de code technique brut ». Un `DioException` remonté tel
/// quel à l'écran afficherait « Connection closed before full header was
/// received » à un client de Ratoma.
///
/// L'API renvoie déjà des messages rédigés en français — ils sont écrits pour
/// être affichés directement. On les préfère donc toujours à un texte
/// fabriqué ici ; ce fichier ne parle que quand l'API n'a rien pu dire.
class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statut,
    this.erreursChamps = const {},
    this.reseau = false,
  });

  /// Message destiné à l'écran, en français.
  final String message;

  /// Code HTTP, quand la requête est arrivée jusqu'au serveur.
  final int? statut;

  /// Erreurs de validation, par nom de champ — celles que Laravel renvoie
  /// sous `errors`. Elles s'affichent sous le champ concerné plutôt que dans
  /// un bandeau générique.
  final Map<String, List<String>> erreursChamps;

  /// Vrai quand la requête n'a jamais atteint le serveur. L'écran peut alors
  /// proposer « Réessayer » plutôt qu'un message définitif : à Conakry, une
  /// coupure de trente secondes est le cas courant, pas l'exception.
  final bool reseau;

  bool get estValidation => statut == 422;

  bool get estAuthentification => statut == 401;

  /// Première erreur d'un champ donné, pour l'afficher sous ce champ.
  String? pourChamp(String champ) => erreursChamps[champ]?.firstOrNull;

  factory ApiException.depuisDio(DioException e) {
    final Response<dynamic>? reponse = e.response;

    if (reponse == null) {
      return ApiException(_messageReseau(e.type), reseau: true);
    }

    final dynamic corps = reponse.data;
    final Map<String, dynamic> donnees = corps is Map<String, dynamic>
        ? corps
        : const {};

    return ApiException(
      // Le message de l'API d'abord : il est déjà rédigé pour l'utilisateur,
      // et il en sait plus que nous sur ce qui s'est passé.
      (donnees['message'] as String?) ?? _messageStatut(reponse.statusCode),
      statut: reponse.statusCode,
      erreursChamps: _erreurs(donnees['errors']),
    );
  }

  static Map<String, List<String>> _erreurs(dynamic brut) {
    if (brut is! Map) return const {};

    return {
      for (final MapEntry<dynamic, dynamic> e in brut.entries)
        if (e.value is List)
          e.key.toString(): (e.value as List).map((v) => v.toString()).toList(),
    };
  }

  static String _messageReseau(DioExceptionType type) => switch (type) {
    DioExceptionType.connectionTimeout ||
    DioExceptionType.sendTimeout ||
    DioExceptionType.receiveTimeout =>
      'La connexion est trop lente. Vérifie ton réseau et réessaie.',
    DioExceptionType.cancel => 'Demande annulée.',
    _ => 'Pas de connexion. Vérifie ton réseau et réessaie.',
  };

  static String _messageStatut(int? statut) => switch (statut) {
    // Le cas nul d'abord : sans lui, la comparaison `>= 500` plus bas
    // porterait sur un entier qui peut ne pas exister.
    null => 'Une erreur est survenue.',
    401 => 'Ta session a expiré. Reconnecte-toi.',
    403 => 'Tu n\'as pas accès à cette action.',
    404 => 'Introuvable.',
    409 => 'Cette action vient d\'être faite par quelqu\'un d\'autre.',
    422 => 'Certaines informations sont incorrectes.',
    429 => 'Trop de tentatives. Patiente un instant.',
    >= 500 =>
      'Le service est momentanément indisponible. Réessaie dans un instant.',
    _ => 'Une erreur est survenue.',
  };

  @override
  String toString() => 'ApiException($statut): $message';
}
