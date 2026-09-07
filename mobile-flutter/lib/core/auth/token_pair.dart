/// Le couple de jetons délivré par l'API (§4, ADR-0003).
///
/// L'access token vit quinze minutes, le refresh token trente jours. Ce
/// dernier **tourne à chaque usage** : l'échange en renvoie toujours un neuf et
/// invalide l'ancien. Présenter deux fois le même révoque toutes les sessions
/// du compte — c'est le signe qu'une copie circule.
///
/// Conséquence directe pour l'application : deux rafraîchissements concurrents
/// se déconnecteraient eux-mêmes. C'est `AuthInterceptor` qui les sérialise, et
/// c'est la raison d'être de sa complexité.
class TokenPair {
  const TokenPair({
    required this.accessToken,
    required this.refreshToken,
    required this.expireLe,
  });

  final String accessToken;
  final String refreshToken;
  final DateTime expireLe;

  factory TokenPair.depuisJson(Map<String, dynamic> json) => TokenPair(
    accessToken: json['access_token'] as String,
    refreshToken: json['refresh_token'] as String,
    expireLe: _expiration(json),
  );

  Map<String, dynamic> versJson() => {
    'access_token': accessToken,
    'refresh_token': refreshToken,
    'expire_le': expireLe.toIso8601String(),
  };

  /// Vrai un peu **avant** l'expiration réelle.
  ///
  /// La marge évite d'envoyer une requête avec un jeton qui expirera pendant
  /// son trajet — cas fréquent sur un réseau lent, et qui produirait un 401
  /// parfaitement évitable.
  bool expireBientot({Duration marge = const Duration(seconds: 30)}) =>
      DateTime.now().add(marge).isAfter(expireLe);

  /// L'API renvoie `expire_le` (ISO 8601) et `expire_dans` (secondes). On
  /// préfère la seconde : l'horloge du téléphone peut être décalée de
  /// plusieurs minutes, alors qu'une durée reste juste quelle que soit
  /// l'heure locale.
  static DateTime _expiration(Map<String, dynamic> json) {
    final dynamic secondes = json['expire_dans'];

    if (secondes != null) {
      final int? valeur = secondes is int
          ? secondes
          : int.tryParse('$secondes');
      if (valeur != null) {
        return DateTime.now().add(Duration(seconds: valeur));
      }
    }

    final String? iso = json['expire_le'] as String?;

    return DateTime.tryParse(iso ?? '') ??
        DateTime.now().add(const Duration(minutes: 15));
  }
}
