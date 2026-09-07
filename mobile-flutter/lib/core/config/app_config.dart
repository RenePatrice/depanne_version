/// Configuration d'exécution, fournie au lancement.
///
/// Tout passe par `--dart-define` : rien de sensible ni d'environnemental n'est
/// écrit dans le code. Le cahier des charges est formel là-dessus — et en
/// particulier, **aucune clé de service ne doit se trouver dans le binaire
/// mobile**, qui est distribuable et décompilable.
///
/// Exemple de lancement :
///
/// ```
/// flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
/// ```
///
/// `10.0.2.2` est l'adresse de la machine hôte vue depuis l'émulateur Android :
/// `localhost` y désignerait l'émulateur lui-même.
class AppConfig {
  const AppConfig({
    required this.baseUrl,
    this.connectTimeout = const Duration(seconds: 10),
    this.receiveTimeout = const Duration(seconds: 20),
  });

  final String baseUrl;

  /// Délais volontairement larges. Le cahier des charges vise des réponses
  /// sous 300 ms, mais c'est une cible de serveur : sur le réseau de Conakry,
  /// une requête qui met huit secondes a encore toutes ses chances d'aboutir,
  /// et couper trop tôt transformerait une lenteur en échec.
  final Duration connectTimeout;
  final Duration receiveTimeout;

  factory AppConfig.depuisEnvironnement() => const AppConfig(
    baseUrl: String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://10.0.2.2:8000/api/v1',
    ),
  );
}
