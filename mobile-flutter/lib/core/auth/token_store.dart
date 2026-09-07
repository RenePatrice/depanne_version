import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'token_pair.dart';

/// Conservation des jetons.
///
/// Le stockage sécurisé de la plateforme — Keystore sur Android — plutôt que
/// des préférences en clair : un refresh token valable trente jours vaut le
/// mot de passe, et un téléphone se prête, se perd, se revend.
///
/// L'interface est volontairement étroite et abstraite : `AuthInterceptor` est
/// la pièce la plus délicate de l'application, et on doit pouvoir la tester
/// sans dépendre d'un Keystore qui n'existe pas dans un test unitaire.
abstract interface class TokenStore {
  Future<TokenPair?> lire();

  Future<void> ecrire(TokenPair jetons);

  Future<void> effacer();
}

/// Implémentation réelle, adossée au coffre de la plateforme.
class SecureTokenStore implements TokenStore {
  /// Les réglages par défaut d'Android suffisent depuis la version 11 du
  /// paquet : chiffrement AES-GCM des données et enveloppement de la clé par
  /// RSA-OAEP, adossés au Keystore. Les versions antérieures demandaient
  /// d'activer explicitement `encryptedSharedPreferences` ; ce n'est plus le
  /// cas, et le forcer échouerait aujourd'hui à la compilation.
  SecureTokenStore([FlutterSecureStorage? coffre])
    : _coffre = coffre ?? const FlutterSecureStorage();

  static const String _cle = 'depanne.jetons';

  final FlutterSecureStorage _coffre;

  @override
  Future<TokenPair?> lire() async {
    final String? brut = await _coffre.read(key: _cle);

    if (brut == null) return null;

    try {
      return TokenPair.depuisJson(jsonDecode(brut) as Map<String, dynamic>);
    } on FormatException {
      // Contenu illisible — version antérieure du format, ou écriture
      // interrompue. On repart de zéro plutôt que de bloquer l'application sur
      // un coffre corrompu : le pire est une reconnexion.
      await effacer();
      return null;
    }
  }

  @override
  Future<void> ecrire(TokenPair jetons) =>
      _coffre.write(key: _cle, value: jsonEncode(jetons.versJson()));

  @override
  Future<void> effacer() => _coffre.delete(key: _cle);
}

/// Stockage en mémoire, pour les tests.
class MemoryTokenStore implements TokenStore {
  TokenPair? _jetons;

  @override
  Future<TokenPair?> lire() async => _jetons;

  @override
  Future<void> ecrire(TokenPair jetons) async => _jetons = jetons;

  @override
  Future<void> effacer() async => _jetons = null;
}
