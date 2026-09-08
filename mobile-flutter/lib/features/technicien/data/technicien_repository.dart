import '../../../core/network/api_client.dart';
import '../../shared/data/modeles.dart';
import 'modeles.dart';

/// Tous les appels du parcours technicien.
///
/// Comme côté client, c'est la seule classe qui connaisse la forme du JSON :
/// au-dessus, on ne manipule que des objets typés.
class TechnicienRepository {
  const TechnicienRepository(this._api);

  final ApiClient _api;

  // ---------------------------------------------------------- disponibilité --

  /// Se mettre en ligne, ou hors ligne.
  ///
  /// Le serveur refuse les deux cas qui comptent — dossier non validé, et
  /// passage hors ligne pendant une intervention — avec un message lisible.
  /// L'application ne les rejoue pas ici : deux gardes qui divergent, c'est
  /// celle de l'écran qui finit par se tromper.
  Future<bool> definirDisponibilite(bool enLigne) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/technicien/disponibilite',
      corps: <String, dynamic>{'en_ligne': enLigne},
    );

    return reponse['en_ligne'] as bool? ?? enLigne;
  }

  /// Remonter sa position.
  ///
  /// Appelée jusqu'à toutes les huit secondes pendant une intervention : la
  /// réponse est minimale et rien n'en dépend à l'écran.
  Future<void> remonterPosition({
    required double latitude,
    required double longitude,
  }) => _api.post(
    '/technicien/position',
    corps: <String, dynamic>{'latitude': latitude, 'longitude': longitude},
  );

  // -------------------------------------------------------- sollicitations --

  /// Les sollicitations. Sans `historique`, seule celle qui est vivante.
  Future<List<Sollicitation>> sollicitations({bool historique = false}) async {
    final Map<String, dynamic> reponse = await _api.get(
      '/technicien/sollicitations',
      parametres: historique ? <String, dynamic>{'historique': 1} : null,
    );

    return ((reponse['sollicitations'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic s) => Sollicitation.depuisJson(s as Map<String, dynamic>))
        .toList();
  }

  /// Accepter. Un 409 signifie qu'un autre est passé devant.
  Future<Ticket> accepter(int sollicitationId) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/sollicitations/$sollicitationId/accepter',
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  Future<void> refuser(int sollicitationId, {String? motif}) => _api.post(
    '/sollicitations/$sollicitationId/refuser',
    corps: <String, dynamic>{'motif': ?motif},
  );

  // ---------------------------------------------------------- intervention --

  /// Franchir une étape.
  ///
  /// On nomme un geste — `en-route`, `sur-place`, `demarrer`, `terminer` — et
  /// non un état : c'est la machine à états du serveur qui décide s'il est
  /// possible. Une liste d'états côté application finirait par diverger de la
  /// sienne, et l'écran proposerait des boutons qui échouent.
  Future<Ticket> avancer(
    int ticketId,
    String etape, {
    String? diagnostic,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/avancer',
      corps: <String, dynamic>{'etape': etape, 'diagnostic': ?diagnostic},
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  /// Proposer un supplément après diagnostic.
  ///
  /// **La seule route de l'application qui envoie un montant au serveur**, et
  /// elle propose : le total ne bouge qu'au moment où le client accepte. Le
  /// serveur plafonne le supplément au double de la prestation et exige un
  /// diagnostic écrit ; on ne recopie pas ces bornes ici, elles seraient à
  /// tenir à jour à deux endroits.
  Future<Ticket> proposerSupplement(
    int ticketId, {
    required String diagnostic,
    required int supplementGnf,
    List<String> photos = const <String>[],
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/diagnostic',
      corps: <String, dynamic>{
        'diagnostic': diagnostic,
        'supplement_gnf': supplementGnf,
        if (photos.isNotEmpty) 'photos': photos,
      },
    );

    return Ticket.depuisJson(reponse['ticket'] as Map<String, dynamic>);
  }

  /// Mes interventions. `enCoursSeulement` sert l'écran du jour.
  Future<List<Ticket>> interventions({bool enCoursSeulement = false}) async {
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

  // ---------------------------------------------------------- portefeuille --

  Future<Portefeuille> portefeuille() async =>
      Portefeuille.depuisJson(await _api.get('/portefeuille'));

  Future<List<Retrait>> retraits() async {
    final Map<String, dynamic> reponse = await _api.get('/retraits');

    return ((reponse['retraits'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic r) => Retrait.depuisJson(r as Map<String, dynamic>))
        .toList();
  }

  /// Demander un retrait.
  ///
  /// Le montant vient du technicien — c'est son argent, il choisit combien il
  /// en sort. Rien à voir avec un prix d'intervention : le serveur revérifie
  /// simplement que le retirable le couvre, dans la transaction qui écrit le
  /// mouvement.
  Future<Retrait> demanderRetrait({
    required int montantGnf,
    required String numero,
    required String operateur,
  }) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/retraits',
      corps: <String, dynamic>{
        'montant_gnf': montantGnf,
        'numero': numero,
        'operateur': operateur,
      },
    );

    return Retrait.depuisJson(reponse['retrait'] as Map<String, dynamic>);
  }
}
