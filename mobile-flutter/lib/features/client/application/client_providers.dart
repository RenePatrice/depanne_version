/// Fournisseurs du parcours client.
///
/// Les lectures passent par des `FutureProvider` : Riverpod porte alors lui-même
/// les trois états — chargement, erreur, données — que chaque écran devrait
/// sinon gérer à la main, et qu'on oublie toujours de gérer au même endroit.
library;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../shared/data/modeles.dart';
import '../data/client_repository.dart';

final Provider<ClientRepository> clientRepositoryProvider =
    Provider<ClientRepository>(
      (Ref ref) => ClientRepository(ref.watch(apiClientProvider)),
    );

/// Quartiers desservis, rangés par commune. Gardés pour la session, comme le
/// catalogue : ils ne changent pas d'une minute à l'autre.
final FutureProvider<List<QuartierCouvert>> quartiersProvider =
    FutureProvider<List<QuartierCouvert>>(
      (Ref ref) => ref.watch(clientRepositoryProvider).quartiers(),
    );

/// Catalogue des prestations.
///
/// Gardé en cache pour la session : il ne change pas d'une minute à l'autre, et
/// le recharger à chaque ouverture de l'écran coûterait un aller-retour sur un
/// réseau qui n'en a pas les moyens.
final FutureProvider<List<CategoriePrestations>> catalogueProvider =
    FutureProvider<List<CategoriePrestations>>(
      (Ref ref) => ref.watch(clientRepositoryProvider).catalogue(),
    );

final FutureProvider<List<Adresse>> adressesProvider =
    FutureProvider<List<Adresse>>(
      (Ref ref) => ref.watch(clientRepositoryProvider).adresses(),
    );

/// Mes demandes. `true` ne renvoie que celles en cours.
final ticketsProvider = FutureProvider.family<List<Ticket>, bool>(
  (Ref ref, bool enCoursSeulement) => ref
      .watch(clientRepositoryProvider)
      .tickets(enCoursSeulement: enCoursSeulement),
);

/// Le détail d'une demande.
final ticketProvider = FutureProvider.family<Ticket, int>(
  (Ref ref, int id) => ref.watch(clientRepositoryProvider).ticket(id),
);

/// Les techniciens disponibles pour une demande — place de marché (#2).
///
/// `autoDispose` : la liste n'a de sens que tant que l'écran de choix est
/// ouvert, et chaque réouverture doit repartir d'une liste fraîche — un
/// technicien a pu passer hors ligne entre-temps.
final techniciensDisponiblesProvider = FutureProvider.autoDispose
    .family<List<TechnicienDisponible>, int>(
      (Ref ref, int ticketId) =>
          ref.watch(clientRepositoryProvider).techniciensDisponibles(ticketId),
    );

/// Le supplément en attente sur une demande, s'il y en a un.
final supplementProvider = FutureProvider.family<SupplementPropose, int>(
  (Ref ref, int ticketId) =>
      ref.watch(clientRepositoryProvider).supplementPropose(ticketId),
);

/// Mes réclamations et les motifs possibles.
final FutureProvider<DossierReclamations> reclamationsProvider =
    FutureProvider<DossierReclamations>(
      (Ref ref) => ref.watch(clientRepositoryProvider).reclamations(),
    );

/// Brouillon de demande, entre le catalogue et la publication.
///
/// Il vit hors des écrans pour que le client puisse revenir en arrière choisir
/// une autre adresse sans perdre la prestation qu'il avait sélectionnée.
class BrouillonDemande {
  const BrouillonDemande({this.prestation, this.adresse, this.description});

  final Prestation? prestation;
  final Adresse? adresse;
  final String? description;

  bool get estComplet => prestation != null && adresse != null;

  BrouillonDemande copie({
    Prestation? prestation,
    Adresse? adresse,
    String? description,
  }) => BrouillonDemande(
    prestation: prestation ?? this.prestation,
    adresse: adresse ?? this.adresse,
    description: description ?? this.description,
  );
}

class BrouillonController extends Notifier<BrouillonDemande> {
  @override
  BrouillonDemande build() => const BrouillonDemande();

  void choisirPrestation(Prestation prestation) =>
      state = state.copie(prestation: prestation);

  void choisirAdresse(Adresse adresse) => state = state.copie(adresse: adresse);

  void decrire(String description) =>
      state = state.copie(description: description);

  /// Vidé après publication : garder un brouillon d'une demande déjà publiée
  /// ferait réapparaître la même prestation au prochain passage, comme si
  /// l'application n'avait pas compris ce qui venait de se passer.
  void vider() => state = const BrouillonDemande();
}

final NotifierProvider<BrouillonController, BrouillonDemande>
brouillonProvider = NotifierProvider<BrouillonController, BrouillonDemande>(
  BrouillonController.new,
);
