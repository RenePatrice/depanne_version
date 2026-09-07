/// Les deux casquettes de l'application.
///
/// Un seul binaire, deux modes : c'est une décision du cahier des charges, pas
/// une commodité technique. Un technicien est souvent aussi un client, et lui
/// demander d'installer deux applications reviendrait à en perdre un sur deux.
///
/// Le mode n'est pas un simple filtre d'écrans : il change la couleur
/// d'accentuation et la navigation, pour qu'on sache **au premier coup d'œil**
/// dans lequel on se trouve. Un technicien qui croit être en mode client
/// cherchera un bouton qui n'existe pas.
enum AppMode {
  client,
  technicien;

  String get libelle => switch (this) {
    AppMode.client => 'Client',
    AppMode.technicien => 'Technicien',
  };

  /// Ce que le mode permet de faire, en une phrase — affiché sur le sélecteur.
  String get description => switch (this) {
    AppMode.client => 'Demander une intervention et suivre son technicien.',
    AppMode.technicien => 'Recevoir des demandes et gérer tes interventions.',
  };

  AppMode get autre => switch (this) {
    AppMode.client => AppMode.technicien,
    AppMode.technicien => AppMode.client,
  };

  /// Valeur stockée. Nommée explicitement plutôt que reprise de `name` : le
  /// jour où l'on renomme une valeur de l'énumération, les préférences déjà
  /// enregistrées sur les téléphones ne doivent pas devenir illisibles.
  String get cle => switch (this) {
    AppMode.client => 'client',
    AppMode.technicien => 'technicien',
  };

  static AppMode? depuisCle(String? cle) => switch (cle) {
    'client' => AppMode.client,
    'technicien' => AppMode.technicien,
    _ => null,
  };
}
