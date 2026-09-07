import '../../../core/auth/token_pair.dart';
import '../../../core/auth/token_store.dart';
import '../../../core/network/api_client.dart';
import 'utilisateur.dart';

/// Résultat d'une authentification réussie.
class ResultatAuth {
  const ResultatAuth({required this.utilisateur, required this.etapeSuivante});

  final Utilisateur utilisateur;

  /// Ce que le serveur attend ensuite : `accueil`, ou `dossier_technicien`
  /// quand le compte vient d'être créé avec la casquette technicien.
  ///
  /// C'est le serveur qui décide de la suite du parcours, pas l'application :
  /// le jour où l'inscription gagne une étape, aucun écran ne change ici.
  final String etapeSuivante;
}

/// Appels d'authentification (§4).
///
/// Le dépôt est la seule couche qui connaisse la forme du JSON : au-dessus, on
/// ne manipule que des `Utilisateur` et des `Session`.
class AuthRepository {
  const AuthRepository({required ApiClient client, required TokenStore store})
    : _api = client,
      _coffre = store;

  final ApiClient _api;
  final TokenStore _coffre;

  Future<ResultatAuth> inscrire({
    required String nomComplet,
    required String telephone,
    required String motDePasse,
    required bool estTechnicien,
  }) => _authentifier('/auth/inscription', <String, dynamic>{
    'full_name': nomComplet,
    // Le numéro part tel que saisi : c'est le serveur qui normalise en E.164
    // (`App\Support\Telephone`). Le faire ici aussi créerait une seconde règle
    // de normalisation, et deux règles finissent par diverger.
    'phone': telephone,
    'password': motDePasse,
    'password_confirmation': motDePasse,
    'is_technician': estTechnicien,
  });

  Future<ResultatAuth> connecter({
    required String telephone,
    required String motDePasse,
  }) => _authentifier('/auth/connexion', <String, dynamic>{
    'phone': telephone,
    'password': motDePasse,
  });

  /// Vrai si un couple de jetons dort dans le coffre.
  ///
  /// Consulté au lancement pour éviter un aller-retour réseau inutile : sans
  /// jeton, inutile de demander au serveur qui nous sommes.
  Future<bool> aUneSessionEnCoffre() async => await _coffre.lire() != null;

  /// Le compte actuellement connecté, rechargé depuis le serveur.
  Future<Utilisateur> moi() async {
    final Map<String, dynamic> reponse = await _api.get('/moi');

    return Utilisateur.depuisJson(
      reponse['utilisateur'] as Map<String, dynamic>,
    );
  }

  Future<void> demanderCodeMotDePasse(String telephone) => _api.post(
    '/auth/mot-de-passe-oublie',
    corps: <String, dynamic>{'phone': telephone},
  );

  Future<void> reinitialiserMotDePasse({
    required String telephone,
    required String code,
    required String motDePasse,
  }) => _api.post(
    '/auth/mot-de-passe-reinitialiser',
    corps: <String, dynamic>{
      'phone': telephone,
      'code': code,
      'password': motDePasse,
      'password_confirmation': motDePasse,
    },
  );

  /// Déconnexion.
  ///
  /// Le coffre est vidé **quoi qu'il arrive** : si l'appel au serveur échoue —
  /// réseau coupé — l'utilisateur doit tout de même être déconnecté sur son
  /// téléphone. Lui refuser la déconnexion parce que le réseau est tombé
  /// serait le pire des deux mondes.
  Future<void> deconnecter() async {
    try {
      await _api.post('/auth/deconnexion');
    } finally {
      await _coffre.effacer();
    }
  }

  Future<ResultatAuth> _authentifier(
    String chemin,
    Map<String, dynamic> corps,
  ) async {
    final Map<String, dynamic> reponse = await _api.post(chemin, corps: corps);

    // Les jetons sont rangés avant tout le reste : si la construction du
    // modèle échouait sur un champ inattendu, la session serait tout de même
    // ouverte et l'application récupérable.
    await _coffre.ecrire(
      TokenPair.depuisJson(reponse['jetons'] as Map<String, dynamic>),
    );

    return ResultatAuth(
      utilisateur: Utilisateur.depuisJson(
        reponse['utilisateur'] as Map<String, dynamic>,
      ),
      etapeSuivante: reponse['etape_suivante'] as String? ?? 'accueil',
    );
  }
}
