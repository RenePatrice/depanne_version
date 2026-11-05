/// Modèles du parcours client.
///
/// Chaque classe suit la ressource Laravel qui la produit, champ pour champ.
/// Les noms JSON sont conservés tels quels dans les fabriques : quand un champ
/// change côté API, la correspondance saute aux yeux ici plutôt que de se
/// perdre dans une couche de traduction.
///
/// Les montants sont des **entiers de francs guinéens**, comme en base. Aucun
/// flottant ne touche l'argent, ni côté serveur ni ici — et le serveur fournit
/// déjà les versions formatées, que l'on affiche telles quelles.
library;

// --------------------------------------------------------------- catalogue --

class Prestation {
  const Prestation({
    required this.id,
    required this.nom,
    required this.prixGnf,
    required this.prixFormate,
    this.description,
    this.inclus = const <String>[],
    this.exclus = const <String>[],
    this.dureeMin,
  });

  final int id;
  final String nom;
  final int prixGnf;
  final String prixFormate;
  final String? description;

  /// Ce que la prestation couvre et ne couvre pas. Affiché avant la
  /// publication : c'est ce qui évite qu'un client attende autre chose que ce
  /// qu'il a commandé, et donc une réclamation.
  final List<String> inclus;
  final List<String> exclus;

  final int? dureeMin;

  factory Prestation.depuisJson(Map<String, dynamic> json) => Prestation(
    id: json['id'] as int,
    nom: json['nom'] as String,
    prixGnf: json['prix_gnf'] as int,
    prixFormate: json['prix_formate'] as String,
    description: json['description'] as String?,
    inclus: _textes(json['inclus']),
    exclus: _textes(json['exclus']),
    dureeMin: json['duree_estimee_min'] as int?,
  );
}

class CategoriePrestations {
  const CategoriePrestations({
    required this.id,
    required this.code,
    required this.nom,
    required this.prestations,
    this.description,
    this.couleur,
  });

  final int id;
  final String code;
  final String nom;
  final String? description;
  final String? couleur;
  final List<Prestation> prestations;

  factory CategoriePrestations.depuisJson(
    Map<String, dynamic> json,
  ) => CategoriePrestations(
    id: json['id'] as int,
    code: json['code'] as String,
    nom: json['nom'] as String,
    description: json['description'] as String?,
    couleur: json['couleur'] as String?,
    prestations: ((json['prestations'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic p) => Prestation.depuisJson(p as Map<String, dynamic>))
        .toList(),
  );
}

// ---------------------------------------------------------------- adresses --

class Adresse {
  const Adresse({
    required this.id,
    required this.libelle,
    required this.adresse,
    required this.parDefaut,
    this.repere,
    this.latitude,
    this.longitude,
    this.couverte,
  });

  final int id;
  final String libelle;
  final String adresse;
  final String? repere;
  final double? latitude;
  final double? longitude;
  final bool parDefaut;

  /// Renseigné par le serveur : l'adresse tombe-t-elle dans une zone desservie.
  /// Permet de griser une adresse hors couverture **avant** que le client ne
  /// perde du temps à composer sa demande.
  final bool? couverte;

  bool get estDesservie => couverte ?? true;

  factory Adresse.depuisJson(Map<String, dynamic> json) => Adresse(
    id: json['id'] as int,
    libelle: json['libelle'] as String,
    adresse: json['adresse'] as String,
    repere: json['repere'] as String?,
    latitude: (json['latitude'] as num?)?.toDouble(),
    longitude: (json['longitude'] as num?)?.toDouble(),
    parDefaut: json['par_defaut'] as bool? ?? false,
    couverte: json['couverte'] as bool?,
  );
}

// ------------------------------------------------------------------- devis --

class LigneDevis {
  const LigneDevis({
    required this.libelle,
    required this.montantFormate,
    required this.montantGnf,
    this.detail,
  });

  final String libelle;
  final int montantGnf;
  final String montantFormate;

  /// Explication en toutes lettres, quand le serveur en fournit une — le détail
  /// du kilométrage, par exemple. Un prix opaque se conteste.
  final String? detail;

  factory LigneDevis.depuisJson(Map<String, dynamic> json) => LigneDevis(
    libelle: json['libelle'] as String,
    montantGnf: json['montant_gnf'] as int,
    montantFormate: json['montant_formate'] as String,
    detail: json['detail'] as String?,
  );
}

class Devis {
  const Devis({
    required this.totalGnf,
    required this.totalFormate,
    required this.ferme,
    required this.lignes,
    this.distanceKm,
    this.distanceEstimee = false,
  });

  final int totalGnf;
  final String totalFormate;

  /// Faux avant qu'un technicien n'ait accepté : le déplacement dépend de sa
  /// position, et il n'y a pas encore de technicien. L'écran doit alors dire
  /// « à partir de » et non « total ».
  final bool ferme;

  final List<LigneDevis> lignes;
  final double? distanceKm;
  final bool distanceEstimee;

  factory Devis.depuisJson(Map<String, dynamic> json) => Devis(
    totalGnf: json['total_gnf'] as int,
    totalFormate: json['total_formate'] as String,
    ferme: json['ferme'] as bool? ?? false,
    distanceKm: (json['distance_km'] as num?)?.toDouble(),
    distanceEstimee: json['distance_estimee'] as bool? ?? false,
    lignes: ((json['lignes'] as List<dynamic>?) ?? const <dynamic>[])
        .map((dynamic l) => LigneDevis.depuisJson(l as Map<String, dynamic>))
        .toList(),
  );
}

// ----------------------------------------------------------------- tickets --

class PrixTicket {
  const PrixTicket({
    required this.totalGnf,
    required this.totalFormate,
    required this.prestationGnf,
    required this.deplacementGnf,
    required this.supplementGnf,
    required this.majorationProximiteGnf,
    required this.ferme,
    this.netTechnicienGnf,
  });

  final int totalGnf;
  final String totalFormate;
  final int prestationGnf;
  final int deplacementGnf;
  final int supplementGnf;
  final int majorationProximiteGnf;
  final bool ferme;

  /// Ce que le technicien touchera, commission déduite.
  ///
  /// Le serveur ne l'envoie **qu'à lui** : la réponse servie au client ne
  /// contient pas cette clé, et le champ reste nul de ce côté. C'est cette
  /// séparation qui fait qu'un client ne lit pas la marge de la plateforme
  /// dans sa propre facture.
  final int? netTechnicienGnf;

  factory PrixTicket.depuisJson(Map<String, dynamic> json) => PrixTicket(
    totalGnf: json['total_gnf'] as int? ?? 0,
    totalFormate: json['total_formate'] as String? ?? '',
    prestationGnf: json['prestation_gnf'] as int? ?? 0,
    deplacementGnf: json['deplacement_gnf'] as int? ?? 0,
    supplementGnf: json['supplement_gnf'] as int? ?? 0,
    majorationProximiteGnf: json['majoration_proximite_gnf'] as int? ?? 0,
    ferme: json['ferme'] as bool? ?? false,
    netTechnicienGnf: json['net_technicien_gnf'] as int?,
  );
}

class PartieTicket {
  const PartieTicket({
    required this.id,
    required this.nomComplet,
    this.telephone,
    this.avatarUrl,
  });

  final int id;
  final String nomComplet;

  /// En clair seulement pendant une intervention active ; masqué avant et
  /// après. C'est le serveur qui décide, l'application n'a pas à trancher.
  final String? telephone;
  final String? avatarUrl;

  factory PartieTicket.depuisJson(Map<String, dynamic> json) => PartieTicket(
    id: json['id'] as int,
    nomComplet: json['nom_complet'] as String? ?? '',
    telephone: json['telephone'] as String?,
    avatarUrl: json['avatar_url'] as String?,
  );
}

class Ticket {
  const Ticket({
    required this.id,
    required this.reference,
    required this.etat,
    required this.etatLibelle,
    required this.etatActif,
    required this.etatFinal,
    required this.annulable,
    required this.prix,
    required this.jalons,
    this.prestation,
    this.description,
    this.adresse,
    this.client,
    this.technicien,
    this.diagnostic,
    this.fraisAnnulationGnf = 0,
    this.creeeLe,
  });

  final int id;
  final String reference;
  final String etat;
  final String etatLibelle;
  final bool etatActif;
  final bool etatFinal;
  final bool annulable;

  final PrixTicket prix;
  final Prestation? prestation;
  final String? description;

  /// Instantané figé à la publication, jamais l'adresse vivante : le client
  /// peut l'avoir supprimée, l'historique doit rester lisible.
  final Map<String, dynamic>? adresse;

  /// L'autre partie, selon qui regarde : le serveur envoie `client` au
  /// technicien et `technicien` au client, jamais les deux. Deux champs
  /// distincts plutôt qu'une « autre partie » générique, parce que les écrans
  /// n'en disent pas la même chose — on n'appelle pas son client comme on
  /// appelle son dépanneur.
  final PartieTicket? client;
  final PartieTicket? technicien;

  final String? diagnostic;
  final int fraisAnnulationGnf;

  /// Horodatages des étapes franchies, dans l'ordre du parcours.
  final Map<String, String> jalons;

  final DateTime? creeeLe;

  /// La recherche d'un technicien est en cours.
  bool get enRecherche => etat == 'PUBLIEE';

  /// Un supplément attend la décision du client.
  bool get attendUneDecision => etat == 'DIAGNOSTIC_EN_ATTENTE';

  /// Le travail est fait, le paiement reste à faire.
  bool get aPayer => etat == 'TERMINEE';

  /// Payé, fonds en séquestre : le client peut valider pour libérer.
  bool get aValider => etat == 'PAYEE';

  /// L'API accepte aussi une note en TERMINEE, c'est-à-dire avant paiement.
  /// L'application ne le propose pas là : demander une note à quelqu'un qui
  /// n'a pas encore réglé mélange deux gestes, et la note passerait pour une
  /// condition du paiement.
  bool get estNotable => etat == 'CLOTUREE' || etat == 'PAYEE';

  /// Une intervention a eu lieu : un problème peut être signalé. La fenêtre
  /// de 72 heures est tenue par le serveur.
  bool get reclamable =>
      etat == 'TERMINEE' || etat == 'PAYEE' || etat == 'CLOTUREE';

  String get adresseLisible =>
      (adresse?['formatted_address'] as String?) ?? 'Adresse non renseignée';

  factory Ticket.depuisJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? prestation =
        json['prestation'] as Map<String, dynamic>?;
    final Map<String, dynamic>? technicien =
        json['technicien'] as Map<String, dynamic>?;
    final Map<String, dynamic>? client =
        json['client'] as Map<String, dynamic>?;

    return Ticket(
      id: json['id'] as int,
      reference: json['reference'] as String,
      etat: json['etat'] as String,
      etatLibelle: json['etat_libelle'] as String? ?? '',
      etatActif: json['etat_actif'] as bool? ?? false,
      etatFinal: json['etat_final'] as bool? ?? false,
      annulable: json['annulable'] as bool? ?? false,
      prix: PrixTicket.depuisJson(
        (json['prix'] as Map<String, dynamic>?) ?? const <String, dynamic>{},
      ),
      prestation: prestation == null ? null : Prestation.depuisJson(prestation),
      description: json['description'] as String?,
      adresse: json['adresse'] as Map<String, dynamic>?,
      client: client == null ? null : PartieTicket.depuisJson(client),
      technicien: technicien == null
          ? null
          : PartieTicket.depuisJson(technicien),
      diagnostic: json['diagnostic'] as String?,
      fraisAnnulationGnf: json['frais_annulation_gnf'] as int? ?? 0,
      jalons: <String, String>{
        for (final MapEntry<String, dynamic> e
            in ((json['jalons'] as Map<String, dynamic>?) ??
                    const <String, dynamic>{})
                .entries)
          if (e.value is String) e.key: e.value as String,
      },
      creeeLe: DateTime.tryParse(json['creee_le'] as String? ?? ''),
    );
  }
}

// ------------------------------------------------------------- diagnostic --

class SupplementPropose {
  const SupplementPropose({
    required this.diagnostic,
    required this.supplementGnf,
    required this.totalActuelGnf,
    required this.devis,
    this.photos = const <String>[],
  });

  final String diagnostic;
  final int supplementGnf;
  final int totalActuelGnf;

  /// Le devis complet **tel qu'il serait** si le client acceptait. Il doit
  /// voir le nouveau total avant de décider, pas le découvrir après.
  final Devis devis;

  final List<String> photos;

  factory SupplementPropose.depuisJson(Map<String, dynamic> json) =>
      SupplementPropose(
        diagnostic: json['diagnostic'] as String? ?? '',
        supplementGnf: json['supplement_gnf'] as int? ?? 0,
        totalActuelGnf: json['total_actuel_gnf'] as int? ?? 0,
        devis: Devis.depuisJson(json['devis'] as Map<String, dynamic>),
        photos: _textes(json['photos']),
      );
}

// ---------------------------------------------------------------- paiement --

class Paiement {
  const Paiement({
    required this.reference,
    required this.statut,
    required this.montantGnf,
    this.instruction,
    this.urlPaiement,
    this.motifEchec,
  });

  final String reference;
  final String statut;
  final int montantGnf;
  final String? instruction;
  final String? urlPaiement;
  final String? motifEchec;

  bool get enAttente => statut == 'EN_ATTENTE';

  bool get capture => statut == 'CAPTUREE' || statut == 'LIBEREE';

  bool get echoue => statut == 'ECHOUEE' || statut == 'EXPIREE';

  factory Paiement.depuisJson(Map<String, dynamic> json) => Paiement(
    reference: json['reference'] as String,
    statut: json['statut'] as String,
    montantGnf: json['montant_gnf'] as int? ?? 0,
    instruction: json['instruction'] as String?,
    urlPaiement: json['url_paiement'] as String?,
    motifEchec: json['motif_echec'] as String?,
  );
}

List<String> _textes(dynamic brut) => brut is List
    ? brut.map((dynamic v) => v.toString()).toList()
    : const <String>[];

/// Une réclamation, telle que le déclarant la voit.
///
/// Ni la priorité ni l'échéance interne n'en font partie : le serveur ne les
/// expose pas, pour que personne ne négocie son rang dans la file du support.
class Reclamation {
  const Reclamation({
    required this.reference,
    required this.ticket,
    required this.motifLibelle,
    required this.statut,
    required this.statutLibelle,
    this.description,
    this.ouverteLe,
    this.decision,
  });

  final String reference;
  final String ticket;
  final String motifLibelle;
  final String statut;
  final String statutLibelle;
  final String? description;
  final DateTime? ouverteLe;
  final DecisionReclamation? decision;

  bool get enCours => statut == 'OUVERT' || statut == 'EN_COURS';

  factory Reclamation.depuisJson(Map<String, dynamic> json) {
    final Map<String, dynamic>? decision =
        json['decision'] as Map<String, dynamic>?;

    return Reclamation(
      reference: json['reference'] as String,
      ticket: json['ticket'] as String? ?? '',
      motifLibelle: json['motif_libelle'] as String? ?? '',
      statut: json['statut'] as String? ?? '',
      statutLibelle: json['statut_libelle'] as String? ?? '',
      description: json['description'] as String?,
      ouverteLe: DateTime.tryParse(json['ouverte_le'] as String? ?? '')
          ?.toLocal(),
      decision: decision == null
          ? null
          : DecisionReclamation(
              libelle: decision['libelle'] as String? ?? '',
              motivation: decision['motivation'] as String?,
              remboursementGnf:
                  (decision['rembourse_gnf'] as num?)?.toInt() ?? 0,
            ),
    );
  }
}

/// Ce que le support a décidé, et pourquoi.
class DecisionReclamation {
  const DecisionReclamation({
    required this.libelle,
    required this.remboursementGnf,
    this.motivation,
  });

  final String libelle;
  final String? motivation;
  final int remboursementGnf;
}

typedef MotifReclamation = ({String code, String libelle});

class DossierReclamations {
  const DossierReclamations({required this.reclamations, required this.motifs});

  final List<Reclamation> reclamations;
  final List<MotifReclamation> motifs;
}
