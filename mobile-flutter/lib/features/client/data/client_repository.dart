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
    final Map<String, dynamic> reponse = await _api.get(
      '/catalogue',
      repliHorsLigne: true,
    );

    return ((reponse['categories'] as List<dynamic>?) ?? const <dynamic>[])
        .map(
          (dynamic c) =>
              CategoriePrestations.depuisJson(c as Map<String, dynamic>),
        )
        .toList();
  }

  // -------------------------------------------------------------- adresses --

  Future<List<Adresse>> adresses() async {
    final Map<String, dynamic> reponse = await _api.get(
      '/adresses',
      repliHorsLigne: true,
    );

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
    final Map<String, dynamic> reponse = await _api.get(
      '/tickets/$id',
      repliHorsLigne: true,
    );

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
      repliHorsLigne: true,
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

  // --------------------------------------------------- place de marché (#2) --

  /// Les techniciens disponibles pour ma demande, avec leur prix déjà ferme,
  /// leur note et leur distance. L'ordre vient du serveur (les meilleurs
  /// d'abord), mais le client reste libre de choisir.
  Future<List<TechnicienDisponible>> techniciensDisponibles(
    int ticketId,
  ) async {
    final Map<String, dynamic> reponse = await _api.get(
      '/tickets/$ticketId/techniciens-disponibles',
    );

    return ((reponse['techniciens'] as List<dynamic>?) ?? const <dynamic>[])
        .map(
          (dynamic t) =>
              TechnicienDisponible.depuisJson(t as Map<String, dynamic>),
        )
        .toList();
  }

  /// Choisir un technicien : lui ouvre une fenêtre de réponse de 45 secondes.
  /// Aucun montant ne part — le prix est celui que le serveur a déjà fixé.
  Future<void> choisir(int ticketId, int technicienId) => _api.post(
    '/tickets/$ticketId/choisir',
    corps: <String, dynamic>{'technicien_id': technicienId},
  );

  // ------------------------------------------------------------- garantie (#4) --

  /// Ouvrir un retour sous garantie : la panne est revenue. Renvoie le
  /// **nouveau** ticket, gratuit et pré-attribué au même technicien.
  Future<Ticket> ouvrirGarantie(int ticketId, String description) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/garantie',
      corps: <String, dynamic>{'description': description},
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  /// Clôturer un retour sous garantie : la panne est réglée.
  Future<Ticket> confirmerGarantie(int ticketId) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/garantie/confirmer',
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  // ---------------------------------------------- anti-contournement (#3) --

  /// Signaler une sollicitation « hors plateforme ». Aucun montant : on décrit,
  /// le support examine.
  Future<void> signalerHorsPlateforme(int ticketId, String description) =>
      _api.post(
        '/tickets/$ticketId/signaler-hors-plateforme',
        corps: <String, dynamic>{'description': description},
      );

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

  /// Confirmer avoir payé le technicien **en espèces** (#1).
  ///
  /// Le technicien a déclaré le paiement ; seule la confirmation du client
  /// l'engage. La commission devient alors une dette du technicien, et le
  /// ticket se clôt sans séquestre.
  Future<void> confirmerPaiementEspeces(int ticketId) =>
      _api.post('/tickets/$ticketId/paiement-especes/confirmer');

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

  // ----------------------------------------------------------- réclamation --

  /// Signaler un problème sur une intervention terminée.
  ///
  /// Aucun montant ne part d'ici : ce que le client réclame, c'est un examen.
  /// Le remboursement éventuel est décidé et chiffré par le support, au vu du
  /// dossier — le laisser saisir ici ouvrirait une négociation dans
  /// l'application, l'inverse de ce que le séquestre cherche à éviter.
  Future<Reclamation> reclamer(
    int ticketId, {
    required String motif,
    required String description,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/reclamation',
      corps: <String, dynamic>{'motif': motif, 'description': description},
    );

    return Reclamation.depuisJson(
      reponse['reclamation'] as Map<String, dynamic>,
    );
  }

  /// Mes réclamations, et les motifs possibles — servis par le serveur plutôt
  /// que recopiés ici, pour qu'un libellé corrigé n'attende pas une mise à
  /// jour de l'application.
  Future<DossierReclamations> reclamations() async {
    final Map<String, dynamic> reponse = await _api.get(
      '/reclamations',
      repliHorsLigne: true,
    );

    return DossierReclamations(
      reclamations:
          ((reponse['reclamations'] as List<dynamic>?) ?? const <dynamic>[])
              .map(
                (dynamic r) =>
                    Reclamation.depuisJson(r as Map<String, dynamic>),
              )
              .toList(),
      motifs: ((reponse['motifs'] as List<dynamic>?) ?? const <dynamic>[]).map((
        dynamic m,
      ) {
        final Map<String, dynamic> motif = m as Map<String, dynamic>;
        return (
          code: motif['code'] as String,
          libelle: motif['libelle'] as String,
        );
      }).toList(),
    );
  }
}
