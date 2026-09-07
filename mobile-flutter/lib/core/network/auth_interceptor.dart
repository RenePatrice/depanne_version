import 'dart:async';

import 'package:dio/dio.dart';

import '../auth/token_pair.dart';
import '../auth/token_store.dart';

/// Porte le jeton sur chaque requête, et le renouvelle quand il expire.
///
/// ## Pourquoi ce n'est pas trivial
///
/// Le refresh token **tourne à chaque usage** (ADR-0022) : l'échange en délivre
/// un neuf et révoque l'ancien sur-le-champ. Présenter deux fois le même
/// révoque *toutes* les sessions du compte.
///
/// Or une application mobile lance volontiers cinq requêtes en parallèle au
/// retour sur un écran. Si le jeton a expiré entre-temps, cinq
/// rafraîchissements partiraient avec le même refresh token : le premier
/// réussirait, les quatre autres seraient vus par le serveur comme un rejeu —
/// et l'utilisateur serait déconnecté par le mécanisme censé le maintenir
/// connecté.
///
/// D'où la file : **un seul rafraîchissement à la fois**, les autres requêtes
/// attendent son résultat et repartent avec le nouveau jeton.
///
/// ## Deux déclencheurs
///
/// Le renouvellement se fait *avant* l'envoi quand le jeton est sur le point
/// d'expirer, et *après* un 401 quand le serveur l'a jugé invalide malgré
/// tout — horloge du téléphone décalée, session révoquée à distance. Le second
/// cas ne se rejoue qu'une fois par requête, sans quoi un refresh token mort
/// ferait boucler l'application indéfiniment.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required TokenStore store,
    required Dio dioRafraichissement,
    required this.surDeconnexion,
  }) : _coffre = store,
       _dio = dioRafraichissement;

  /// Chemins que l'on n'authentifie jamais, et qui ne doivent jamais
  /// déclencher de rafraîchissement : un 401 y est une réponse métier
  /// — mauvais mot de passe — pas une session expirée.
  static const Set<String> _cheminsOuverts = {
    '/auth/inscription',
    '/auth/connexion',
    '/auth/refresh',
    '/auth/mot-de-passe-oublie',
    '/auth/mot-de-passe-reinitialiser',
    '/catalogue',
    '/zones',
    '/reglages',
  };

  static const String _dejaRejoue = 'depanne.rejoue';

  final TokenStore _coffre;
  final Dio _dio;

  /// Appelé quand le rafraîchissement échoue définitivement : la session est
  /// perdue, l'application doit revenir à l'écran de connexion.
  final Future<void> Function() surDeconnexion;

  /// Rafraîchissement en cours, s'il y en a un. C'est *lui* qui sérialise :
  /// tout appelant qui arrive pendant l'opération attend le même future.
  Future<TokenPair?>? _enCours;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (_estOuvert(options.path)) {
      return handler.next(options);
    }

    TokenPair? jetons = await _coffre.lire();

    if (jetons == null) {
      return handler.next(options);
    }

    // Renouvellement préventif : envoyer une requête avec un jeton qui expirera
    // pendant son trajet produirait un 401 parfaitement évitable, et un
    // aller-retour de plus sur un réseau qui n'en a pas les moyens.
    if (jetons.expireBientot()) {
      jetons = await _rafraichir(jetons);
    }

    if (jetons != null) {
      options.headers['Authorization'] = 'Bearer ${jetons.accessToken}';
    }

    return handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final RequestOptions requete = err.requestOptions;

    final bool rejouable =
        err.response?.statusCode == 401 &&
        !_estOuvert(requete.path) &&
        requete.extra[_dejaRejoue] != true;

    if (!rejouable) {
      return handler.next(err);
    }

    final TokenPair? actuels = await _coffre.lire();

    if (actuels == null) {
      return handler.next(err);
    }

    final TokenPair? renouveles = await _rafraichir(actuels);

    if (renouveles == null) {
      return handler.next(err);
    }

    // Un seul rejeu par requête : si le nouveau jeton est refusé à son tour,
    // l'erreur remonte. Sans ce garde-fou, un compte révoqué ferait tourner
    // l'application en boucle sur le réseau du client.
    requete.extra[_dejaRejoue] = true;
    requete.headers['Authorization'] = 'Bearer ${renouveles.accessToken}';

    try {
      final Response<dynamic> reponse = await _dio.fetch<dynamic>(requete);
      return handler.resolve(reponse);
    } on DioException catch (e) {
      return handler.next(e);
    }
  }

  /// Renouvelle le couple de jetons, une seule fois à la fois.
  Future<TokenPair?> _rafraichir(TokenPair actuels) {
    // Un rafraîchissement est déjà en vol : on s'y accroche au lieu d'en
    // lancer un second avec le même refresh token.
    final Future<TokenPair?>? enCours = _enCours;
    if (enCours != null) return enCours;

    final Future<TokenPair?> operation = _demanderNouveauxJetons(actuels);
    _enCours = operation;

    return operation.whenComplete(() => _enCours = null);
  }

  Future<TokenPair?> _demanderNouveauxJetons(TokenPair actuels) async {
    try {
      final Response<dynamic> reponse = await _dio.post<dynamic>(
        '/auth/refresh',
        data: {'refresh_token': actuels.refreshToken},
      );

      final dynamic corps = reponse.data;
      final Map<String, dynamic>? jetons = corps is Map<String, dynamic>
          ? corps['jetons'] as Map<String, dynamic>?
          : null;

      if (jetons == null) {
        await _abandonner();
        return null;
      }

      final TokenPair nouveaux = TokenPair.depuisJson(jetons);
      await _coffre.ecrire(nouveaux);

      return nouveaux;
    } on DioException catch (e) {
      // Un 401 sur le rafraîchissement lui-même est sans appel : le refresh
      // token est expiré, révoqué, ou a déjà servi. Toute autre erreur — un
      // réseau coupé, un 500 — n'invalide rien : on garde les jetons et la
      // requête d'origine échouera normalement, pour être réessayée plus tard.
      if (e.response?.statusCode == 401) {
        await _abandonner();
      }

      return null;
    }
  }

  Future<void> _abandonner() async {
    await _coffre.effacer();
    await surDeconnexion();
  }

  bool _estOuvert(String chemin) =>
      _cheminsOuverts.any((ouvert) => chemin.endsWith(ouvert));
}
