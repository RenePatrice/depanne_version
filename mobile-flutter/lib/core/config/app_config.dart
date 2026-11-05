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
    this.reverbUrl = '',
    this.reverbCle = '',
  });

  final String baseUrl;

  /// Adresse du serveur Reverb, par exemple `ws://10.0.2.2:8080`.
  ///
  /// La clé d'application Reverb n'est **pas** un secret : c'est l'équivalent
  /// d'une clé publique Pusher, qui ne permet que de se connecter. Les canaux
  /// privés exigent en plus une signature délivrée par l'API, jeton à l'appui.
  /// Sans clé, le temps réel est simplement désactivé et l'application
  /// interroge le serveur, comme en D3.
  final String reverbUrl;
  final String reverbCle;

  bool get tempsReelConfigure => reverbUrl.isNotEmpty && reverbCle.isNotEmpty;

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
    reverbUrl: String.fromEnvironment(
      'REVERB_URL',
      defaultValue: 'ws://10.0.2.2:8080',
    ),
    reverbCle: String.fromEnvironment('REVERB_APP_KEY'),
  );
}
