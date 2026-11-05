/// Modèles propres au parcours technicien.
///
/// Le ticket, lui, est partagé : c'est le même objet des deux côtés, et le
/// serveur décide seul de ce qu'il montre à chacun.
library;

// --------------------------------------------------------- sollicitation --

/// Une demande proposée à un technicien, et la fenêtre pour y répondre.
///
/// L'adresse exacte n'y figure pas tant qu'il n'a pas accepté : seuls le
/// quartier et la distance. Sans cette réserve, refuser systématiquement les
/// demandes deviendrait un moyen de collecter des adresses.
class Sollicitation {
  const Sollicitation({
    required this.id,
    required this.reference,
    required this.distanceKm,
    required this.totalFormate,
    required this.netFormate,
    required this.netTechnicienGnf,
    required this.expireDansS,
    required this.reponse,
    required this.cycle,
    this.prestation,
    this.description,
    this.quartier,
    this.photos = const <String>[],
    this.solliciteLe,
  });

  final int id;
  final String reference;
  final String? prestation;
  final String? description;
  final List<String> photos;

  /// Le quartier, jamais le numéro de rue : de quoi se repérer, pas de quoi
  /// se présenter à la porte.
  final String? quartier;
  final double distanceKm;

  final String totalFormate;
  final String netFormate;
  final int netTechnicienGnf;

  /// Recalculé par le serveur à chaque lecture, jamais dérivé d'un horodatage.
  /// L'horloge d'un téléphone peut être décalée de plusieurs minutes, et un
  /// compte à rebours fondé dessus se tromperait de fenêtre.
  final int expireDansS;

  final String reponse;
  final int cycle;
  final DateTime? solliciteLe;

  bool get estVivante => reponse == 'EN_ATTENTE' && expireDansS > 0;

  factory Sollicitation.depuisJson(Map<String, dynamic> json) => Sollicitation(
    id: json['id'] as int,
    reference: json['reference'] as String? ?? '',
    prestation: json['prestation'] as String?,
    description: json['description'] as String?,
    photos: _textes(json['photos']),
    quartier: json['quartier'] as String?,
    distanceKm: (json['distance_km'] as num?)?.toDouble() ?? 0,
    totalFormate: json['total_formate'] as String? ?? '',
    netFormate: json['net_formate'] as String? ?? '',
    netTechnicienGnf: json['net_technicien_gnf'] as int? ?? 0,
    expireDansS: json['expire_dans_s'] as int? ?? 0,
    reponse: json['reponse'] as String? ?? 'EN_ATTENTE',
    cycle: json['cycle'] as int? ?? 1,
    solliciteLe: DateTime.tryParse(json['sollicite_le'] as String? ?? ''),
  );
}

// ---------------------------------------------------------- portefeuille --

class Mouvement {
  const Mouvement({
    required this.id,
    required this.type,
    required this.typeLibelle,
    required this.montantGnf,
    required this.montantFormate,
    this.libelle,
    this.ticket,
    this.date,
  });

  final int id;
  final String type;
  final String typeLibelle;

  /// Signé : une commission est négative. Le solde est la somme des
  /// mouvements, jamais un compteur (ADR-0004).
  final int montantGnf;
  final String montantFormate;

  final String? libelle;
  final String? ticket;
  final DateTime? date;

  bool get estUneEntree => montantGnf >= 0;

  factory Mouvement.depuisJson(Map<String, dynamic> json) => Mouvement(
    id: json['id'] as int,
    type: json['type'] as String? ?? '',
    typeLibelle: json['type_libelle'] as String? ?? '',
    montantGnf: json['montant_gnf'] as int? ?? 0,
    montantFormate: json['montant_formate'] as String? ?? '',
    libelle: json['libelle'] as String?,
    ticket: json['ticket'] as String?,
    date: DateTime.tryParse(json['date'] as String? ?? ''),
  );
}

class Portefeuille {
  const Portefeuille({
    required this.soldeGnf,
    required this.soldeFormate,
    required this.engageGnf,
    required this.retirableGnf,
    required this.retraitMinimumGnf,
    required this.commissionDueGnf,
    required this.commissionDueFormate,
    required this.commissionPlafondGnf,
    required this.bloque,
    required this.mouvements,
  });

  final int soldeGnf;
  final String soldeFormate;

  /// Ce qui est déjà demandé mais pas encore versé. Le retirable en tient
  /// compte : sans cela, un technicien déposerait trois demandes de la
  /// totalité de son solde.
  final int engageGnf;
  final int retirableGnf;
  final int retraitMinimumGnf;

  /// Commission due sur les paiements en espèces (#1) : le technicien a tout
  /// encaissé, la commission lui reste à régler. Au-delà du plafond, il ne peut
  /// plus accepter de demandes tant qu'il n'a pas réglé (`bloque`).
  final int commissionDueGnf;
  final String commissionDueFormate;
  final int commissionPlafondGnf;
  final bool bloque;

  final List<Mouvement> mouvements;

  factory Portefeuille.depuisJson(Map<String, dynamic> json) => Portefeuille(
    soldeGnf: json['solde_gnf'] as int? ?? 0,
    soldeFormate: json['solde_formate'] as String? ?? '',
    engageGnf: json['engage_gnf'] as int? ?? 0,
    retirableGnf: json['retirable_gnf'] as int? ?? 0,
    retraitMinimumGnf: json['retrait_minimum_gnf'] as int? ?? 0,
    commissionDueGnf: json['commission_due_gnf'] as int? ?? 0,
    commissionDueFormate: json['commission_due_formate'] as String? ?? '',
    commissionPlafondGnf: json['commission_plafond_gnf'] as int? ?? 0,
    bloque: json['bloque'] as bool? ?? false,
    mouvements: ((json['mouvements'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic m) => Mouvement.depuisJson(m as Map<String, dynamic>))
        .toList(),
  );
}

/// Tarif propre du technicien pour une prestation — place de marché (#2).
///
/// Le catalogue n'est qu'un **défaut** : le technicien peut fixer son prix dans
/// les bornes autorisées. `monPrixGnf` nul signifie « je prends le prix
/// catalogue ».
class TarifTechnicien {
  const TarifTechnicien({
    required this.serviceId,
    required this.prestation,
    required this.categorie,
    required this.type,
    required this.prixCatalogueGnf,
    required this.prixCatalogueFormate,
    required this.monPrixGnf,
    required this.minGnf,
    required this.maxGnf,
  });

  final int serviceId;
  final String prestation;
  final String categorie;
  final String type;
  final int prixCatalogueGnf;
  final String prixCatalogueFormate;
  final int? monPrixGnf;
  final int minGnf;
  final int maxGnf;

  factory TarifTechnicien.depuisJson(Map<String, dynamic> json) =>
      TarifTechnicien(
        serviceId: json['service_id'] as int,
        prestation: json['prestation'] as String? ?? '',
        categorie: json['categorie'] as String? ?? '',
        type: json['type'] as String? ?? 'DEPANNAGE',
        prixCatalogueGnf: json['prix_catalogue_gnf'] as int? ?? 0,
        prixCatalogueFormate: json['prix_catalogue_formate'] as String? ?? '',
        monPrixGnf: json['mon_prix_gnf'] as int?,
        minGnf: json['min_gnf'] as int? ?? 0,
        maxGnf: json['max_gnf'] as int? ?? 0,
      );
}

class Retrait {
  const Retrait({
    required this.reference,
    required this.montantFormate,
    required this.montantGnf,
    required this.numero,
    required this.operateur,
    required this.statut,
    required this.statutLibelle,
    this.note,
    this.demandeLe,
    this.verseLe,
  });

  final String reference;
  final int montantGnf;
  final String montantFormate;
  final String numero;
  final String operateur;
  final String statut;
  final String statutLibelle;

  /// Le motif d'un rejet. Un retrait refusé sans explication est le meilleur
  /// moyen de perdre un technicien.
  final String? note;

  final DateTime? demandeLe;
  final DateTime? verseLe;

  bool get estVerse => statut == 'PAID';

  bool get estRejete => statut == 'REJECTED';

  factory Retrait.depuisJson(Map<String, dynamic> json) => Retrait(
    reference: json['reference'] as String? ?? '',
    montantGnf: json['montant_gnf'] as int? ?? 0,
    montantFormate: json['montant_formate'] as String? ?? '',
    numero: json['numero'] as String? ?? '',
    operateur: json['operateur'] as String? ?? '',
    statut: json['statut'] as String? ?? '',
    statutLibelle: json['statut_libelle'] as String? ?? '',
    note: json['note'] as String?,
    demandeLe: DateTime.tryParse(json['demande_le'] as String? ?? ''),
    verseLe: DateTime.tryParse(json['verse_le'] as String? ?? ''),
  );
}

List<String> _textes(dynamic brut) => brut is List
    ? brut.map((dynamic v) => v.toString()).toList()
    : const <String>[];
