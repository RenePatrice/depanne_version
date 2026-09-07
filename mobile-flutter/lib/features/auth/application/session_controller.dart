import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../data/utilisateur.dart';

/// Où en est la session, du point de vue de la navigation.
enum EtatSession {
  /// Au lancement : on ne sait pas encore s'il y a un jeton valable en coffre.
  /// Un état distinct de « déconnecté » évite de faire clignoter l'écran de
  /// connexion avant de rétablir une session parfaitement valide.
  inconnu,

  deconnecte,
  connecte,

  /// Connecté, mais le dossier technicien reste à compléter.
  dossierACompleter,
}

/// État de session observable par toute l'application.
class Session {
  const Session({required this.etat, this.utilisateur, this.erreur});

  final EtatSession etat;
  final Utilisateur? utilisateur;

  /// Dernière erreur, destinée à l'écran qui l'a provoquée.
  final ApiException? erreur;

  bool get estConnecte =>
      etat == EtatSession.connecte || etat == EtatSession.dossierACompleter;

  Session copie({
    EtatSession? etat,
    Utilisateur? utilisateur,
    ApiException? erreur,
    bool effacerErreur = false,
  }) => Session(
    etat: etat ?? this.etat,
    utilisateur: utilisateur ?? this.utilisateur,
    erreur: effacerErreur ? null : (erreur ?? this.erreur),
  );
}

/// Pilote la session : restauration au lancement, connexion, inscription,
/// déconnexion.
///
/// C'est le seul objet que le routeur observe pour décider où envoyer
/// l'utilisateur. Concentrer cette décision ici évite que chaque écran ait sa
/// propre idée de « suis-je connecté ? ».
class SessionController extends Notifier<Session> {
  @override
  Session build() => const Session(etat: EtatSession.inconnu);

  AuthRepository get _depot => ref.read(authRepositoryProvider);

  /// Tente de rétablir la session au démarrage.
  ///
  /// Un jeton en coffre ne suffit pas : il peut avoir été révoqué depuis le
  /// back-office. On demande donc le compte au serveur — et si l'appel échoue
  /// pour cause de réseau plutôt que d'authentification, on garde la session,
  /// parce qu'un utilisateur hors couverture ne doit pas être déconnecté.
  Future<void> restaurer() async {
    if (!await _depot.aUneSessionEnCoffre()) {
      state = const Session(etat: EtatSession.deconnecte);
      return;
    }

    try {
      final Utilisateur utilisateur = await _depot.moi();
      state = _pour(utilisateur);
    } on ApiException catch (e) {
      // Réseau coupé : on ne déconnecte pas. Les jetons sont toujours
      // valables, c'est la couverture qui manque. L'état reste indéterminé et
      // l'écran de démarrage propose de réessayer — sans quoi la moindre perte
      // de réseau au lancement coûterait une ressaisie du mot de passe, tous
      // les matins, à Conakry.
      state = e.reseau
          ? Session(etat: EtatSession.inconnu, erreur: e)
          : const Session(etat: EtatSession.deconnecte);
    }
  }

  Future<bool> connecter({
    required String telephone,
    required String motDePasse,
  }) => _tenter(
    () => _depot.connecter(telephone: telephone, motDePasse: motDePasse),
  );

  Future<bool> inscrire({
    required String nomComplet,
    required String telephone,
    required String motDePasse,
    required bool estTechnicien,
  }) => _tenter(
    () => _depot.inscrire(
      nomComplet: nomComplet,
      telephone: telephone,
      motDePasse: motDePasse,
      estTechnicien: estTechnicien,
    ),
  );

  Future<void> deconnecter() async {
    await _depot.deconnecter();
    state = const Session(etat: EtatSession.deconnecte);
  }

  /// Appelé par l'intercepteur quand le rafraîchissement a définitivement
  /// échoué : la session est perdue côté serveur, l'application doit le
  /// refléter sans attendre le prochain écran.
  void sessionPerdue() {
    state = const Session(etat: EtatSession.deconnecte);
  }

  void effacerErreur() {
    if (state.erreur != null) state = state.copie(effacerErreur: true);
  }

  Future<bool> _tenter(Future<ResultatAuth> Function() action) async {
    state = state.copie(effacerErreur: true);

    try {
      final ResultatAuth resultat = await action();
      state = _pour(
        resultat.utilisateur,
        etapeSuivante: resultat.etapeSuivante,
      );

      return true;
    } on ApiException catch (e) {
      state = Session(etat: EtatSession.deconnecte, erreur: e);

      return false;
    }
  }

  /// Traduit un compte en état de navigation.
  ///
  /// Un technicien dont le dossier n'est pas déposé n'est pas envoyé au
  /// tableau de bord : il n'y trouverait qu'un écran vide et n'aurait aucune
  /// idée de ce qu'on attend de lui.
  Session _pour(Utilisateur utilisateur, {String? etapeSuivante}) {
    final bool dossierAttendu =
        etapeSuivante == 'dossier_technicien' ||
        (utilisateur.estTechnicien &&
            utilisateur.verificationTechnicien == null);

    return Session(
      etat: dossierAttendu
          ? EtatSession.dossierACompleter
          : EtatSession.connecte,
      utilisateur: utilisateur,
    );
  }
}
