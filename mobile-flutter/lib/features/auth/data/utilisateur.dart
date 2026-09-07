import '../../../core/app_mode.dart';

/// Compte connecté, tel que l'API le renvoie (`UtilisateurResource`).
///
/// Les noms de champs suivent le JSON du serveur plutôt qu'une convention
/// Dart : quand un champ change côté API, la correspondance saute aux yeux
/// dans ce fichier au lieu de se perdre dans une couche de traduction.
class Utilisateur {
  const Utilisateur({
    required this.id,
    required this.nomComplet,
    required this.prenom,
    required this.telephone,
    required this.telephoneAffichage,
    required this.estClient,
    required this.estTechnicien,
    required this.statut,
    this.email,
    this.avatarUrl,
    this.verificationTechnicien,
    this.peutTravailler = false,
  });

  final int id;
  final String nomComplet;
  final String prenom;

  /// Toujours en E.164 (`+224XXXXXXXXX`) : c'est l'identifiant du compte.
  final String telephone;

  /// La même chose, mise en forme pour l'affichage. Calculée par le serveur
  /// pour que l'application n'ait pas sa propre idée du formatage guinéen.
  final String telephoneAffichage;

  final String? email;
  final String? avatarUrl;
  final String statut;

  final bool estClient;
  final bool estTechnicien;

  /// État du dossier technicien, quand la casquette existe. Il décide de
  /// l'écran d'accueil : un dossier en attente ne mène pas au tableau de bord
  /// mais à l'écran qui explique où en est la validation.
  final String? verificationTechnicien;

  /// Le droit de recevoir des demandes, tel que le serveur le calcule.
  ///
  /// On ne le déduit pas d'une comparaison avec « VALIDE » : la règle
  /// appartient au domaine, et un état ajouté côté serveur — une suspension
  /// temporaire, par exemple — se propagerait ici sans qu'on y touche.
  final bool peutTravailler;

  bool get aLesDeuxCasquettes => estClient && estTechnicien;

  /// Les modes réellement accessibles à ce compte.
  List<AppMode> get modesDisponibles => <AppMode>[
    if (estClient) AppMode.client,
    if (estTechnicien) AppMode.technicien,
  ];

  factory Utilisateur.depuisJson(Map<String, dynamic> json) {
    final Map<String, dynamic> casquettes =
        (json['casquettes'] as Map<String, dynamic>?) ??
        const <String, dynamic>{};

    final Map<String, dynamic>? profilTechnicien =
        json['profil_technicien'] as Map<String, dynamic>?;

    final Map<String, dynamic>? verification =
        profilTechnicien?['verification'] as Map<String, dynamic>?;

    return Utilisateur(
      id: json['id'] as int,
      nomComplet: json['nom_complet'] as String? ?? '',
      prenom: json['prenom'] as String? ?? '',
      telephone: json['telephone'] as String? ?? '',
      telephoneAffichage:
          json['telephone_affichage'] as String? ??
          json['telephone'] as String? ??
          '',
      email: json['email'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      statut: json['statut'] as String? ?? 'ACTIF',
      estClient: casquettes['client'] as bool? ?? true,
      estTechnicien: casquettes['technicien'] as bool? ?? false,
      verificationTechnicien: verification?['statut'] as String?,
      peutTravailler: verification?['peut_travailler'] as bool? ?? false,
    );
  }
}
