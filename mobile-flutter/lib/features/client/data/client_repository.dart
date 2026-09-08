import '../../../core/network/api_client.dart';
import '../../shared/data/modeles.dart';

/// Tous les appels du parcours client.
///
/// Une seule classe plutôt qu'un dépôt par écran : les huit étapes du parcours
/// partagent les mêmes modèles et se lisent mieux ensemble. Le jour où l'une
/// d'elles grossit — le paiement, sans doute — elle sortira.
///
/// Cette couche est la seule à connaître la forme du JSON. Au-dessus, on ne
/// manipule que des objets typés.
class ClientRepository {
  const ClientRepository(this._api);

  final ApiClient _api;

  // ------------------------------------------------------------- catalogue --

  Future<List<CategoriePrestations>> catalogue() async {
    final Map<String, dynamic> reponse = await _api.get('/catalogue');

    return ((reponse['categories'] as List<dynamic>?) ?? const <dynamic>[])
        .map(
          (dynamic c) =>
              CategoriePrestations.depuisJson(c as Map<String, dynamic>),
        )
        .toList();
  }

  // -------------------------------------------------------------- adresses --

  Future<List<Adresse>> adresses() async {
    final Map<String, dynamic> reponse = await _api.get('/adresses');

    return ((reponse['adresses'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic a) => Adresse.depuisJson(a as Map<String, dynamic>))
        .toList();
  }

  Future<Adresse> ajouterAdresse({
    required String libelle,
    required String adresse,
    required double latitude,
    required double longitude,
    String? repere,
    bool parDefaut = false,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/adresses',
      corps: <String, dynamic>{
        'label': libelle,
        'formatted_address': adresse,
        'landmark': repere,
        'latitude': latitude,
        'longitude': longitude,
        'is_default': parDefaut,
      },
    );

    return Adresse.depuisJson(reponse['adresse'] as Map<String, dynamic>);
  }

  Future<void> supprimerAdresse(int id) => _api.delete('/adresses/$id');

  // ----------------------------------------------------------------- devis --

  /// Estimation avant publication. Ne crée rien.
  Future<Devis> devis({
    required int prestationId,
    required int adresseId,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/devis',
      corps: <String, dynamic>{
        'service_id': prestationId,
        'address_id': adresseId,
      },
    );

    return Devis.depuisJson(reponse['devis'] as Map<String, dynamic>);
  }

  // --------------------------------------------------------------- tickets --

  Future<Ticket> publier({
    required int prestationId,
    required int adresseId,
    String? description,
    List<String> photos = const <String>[],
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets',
      corps: <String, dynamic>{
        'service_id': prestationId,
        'address_id': adresseId,
        'problem_description': description,
        if (photos.isNotEmpty) 'photos': photos,
      },
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  Future<Ticket> ticket(int id) async {
    final Map<String, dynamic> reponse = await _api.get('/tickets/$id');

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  /// Mes demandes, les plus récentes d'abord.
  ///
  /// `enCoursSeulement` sert l'écran d'accueil, qui n'a que faire de
  /// l'historique complet à chaque ouverture.
  Future<List<Ticket>> tickets({bool enCoursSeulement = false}) async {
    final Map<String, dynamic> reponse = await _api.get(
      '/tickets',
      parametres: enCoursSeulement
          ? <String, dynamic>{'filtre': 'en_cours'}
          : null,
    );

    return ((reponse['data'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic t) => Ticket.depuisJson(t as Map<String, dynamic>))
        .toList();
  }

  Future<Ticket> annuler(int id, {String? motif}) async {
    final Map<String, dynamic> reponse = await _api.delete(
      '/tickets/$id',
      corps: <String, dynamic>{'motif': motif},
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  // ------------------------------------------------------------ diagnostic --

  /// Le supplément proposé, s'il y en a un en attente.
  Future<SupplementPropose> supplementPropose(int ticketId) async {
    final Map<String, dynamic> reponse = await _api.get(
      '/tickets/$ticketId/diagnostic',
    );

    return SupplementPropose.depuisJson(reponse);
  }

  Future<Ticket> repondreAuSupplement(
    int ticketId, {
    required bool accepte,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/diagnostic/reponse',
      corps: <String, dynamic>{'accepte': accepte},
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  // -------------------------------------------------------------- paiement --

  Future<Paiement> lancerPaiement(int ticketId, {String? telephone}) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/paiement',
      corps: <String, dynamic>{'telephone': ?telephone},
    );

    return Paiement.depuisJson(reponse['paiement'] as Map<String, dynamic>);
  }

  /// État d'un paiement.
  ///
  /// L'application interroge cette route pendant que le client confirme sur son
  /// téléphone : la notification de l'opérateur peut arriver avant ou après son
  /// retour dans l'application, et rien ne garantit l'ordre.
  Future<Paiement> suivrePaiement(String reference) async =>
      Paiement.depuisJson(await _api.get('/paiements/$reference'));

  /// Valider l'intervention : libère les fonds au technicien.
  Future<bool> validerIntervention(int ticketId) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/valider',
    );

    return reponse['libere'] as bool? ?? false;
  }

  // ------------------------------------------------------------------ avis --

  Future<void> noter(
    int ticketId, {
    required int note,
    List<String> etiquettes = const <String>[],
    String? commentaire,
  }) => _api.post(
    '/tickets/$ticketId/avis',
    corps: <String, dynamic>{
      'note': note,
      if (etiquettes.isNotEmpty) 'etiquettes': etiquettes,
      if (commentaire != null && commentaire.isNotEmpty)
        'commentaire': commentaire,
    },
  );
}
