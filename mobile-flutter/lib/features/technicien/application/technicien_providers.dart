/// Fournisseurs du parcours technicien.
library;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../shared/data/modeles.dart';
import '../data/modeles.dart';
import '../data/technicien_repository.dart';

final Provider<TechnicienRepository> technicienRepositoryProvider =
    Provider<TechnicienRepository>(
      (Ref ref) => TechnicienRepository(ref.watch(apiClientProvider)),
    );

/// La sollicitation vivante, s'il y en a une.
///
/// Une seule à la fois : c'est la règle du matching séquentiel (§8.3), et
/// c'est ce qui distingue Parema Connect d'une place de marché où dix techniciens
/// reçoivent la même demande et où neuf perdent leur temps.
final sollicitationsProvider = FutureProvider.family<List<Sollicitation>, bool>(
  (Ref ref, bool historique) => ref
      .watch(technicienRepositoryProvider)
      .sollicitations(historique: historique),
);

/// Mes interventions. `true` ne renvoie que celles en cours.
final interventionsProvider = FutureProvider.family<List<Ticket>, bool>(
  (Ref ref, bool enCoursSeulement) => ref
      .watch(technicienRepositoryProvider)
      .interventions(enCoursSeulement: enCoursSeulement),
);

final FutureProvider<Portefeuille> portefeuilleProvider =
    FutureProvider<Portefeuille>(
      (Ref ref) => ref.watch(technicienRepositoryProvider).portefeuille(),
    );

final FutureProvider<List<Retrait>> retraitsProvider =
    FutureProvider<List<Retrait>>(
      (Ref ref) => ref.watch(technicienRepositoryProvider).retraits(),
    );

/// Mes tarifs par prestation — place de marché (#2).
final FutureProvider<List<TarifTechnicien>> tarifsProvider =
    FutureProvider<List<TarifTechnicien>>(
      (Ref ref) => ref.watch(technicienRepositoryProvider).tarifs(),
    );

/// Affluence prévue maintenant (#10). `autoDispose` : recalculée à chaque
/// ouverture de l'écran, l'affluence de l'heure passée n'a plus d'intérêt.
final affluenceProvider = FutureProvider.autoDispose<Affluence>(
  (Ref ref) => ref.watch(technicienRepositoryProvider).affluence(),
);

/// Disponibilité, telle que l'écran doit l'afficher.
///
/// L'état vient du serveur — c'est lui qui refuse un dossier non validé ou une
/// mise hors ligne en pleine intervention — mais l'interrupteur doit réagir au
/// doigt. D'où trois états : la valeur, l'attente, et le refus explicite.
class Disponibilite {
  const Disponibilite({this.enLigne = false, this.enCours = false, this.refus});

  final bool enLigne;
  final bool enCours;

  /// Le message du serveur quand il a dit non. Affiché tel quel : il est écrit
  /// en français et explique la raison, ce qu'un « échec » générique ne ferait
  /// pas.
  final String? refus;

  Disponibilite copie({bool? enLigne, bool? enCours, String? refus}) =>
      Disponibilite(
        enLigne: enLigne ?? this.enLigne,
        enCours: enCours ?? this.enCours,
        refus: refus,
      );
}

class DisponibiliteController extends Notifier<Disponibilite> {
  @override
  Disponibilite build() => const Disponibilite();

  /// Reprend l'état connu du compte, au premier affichage de l'écran.
  void amorcer(bool enLigne) {
    if (!state.enCours && state.enLigne != enLigne) {
      state = state.copie(enLigne: enLigne);
    }
  }

  Future<void> basculer(bool vers) async {
    if (state.enCours) return;

    state = state.copie(enCours: true);

    try {
      final bool obtenu = await ref
          .read(technicienRepositoryProvider)
          .definirDisponibilite(vers);

      state = Disponibilite(enLigne: obtenu);

      // Se mettre en ligne, c'est accepter d'être sollicité : on va donc
      // chercher tout de suite s'il y a déjà quelque chose pour soi.
      ref.invalidate(sollicitationsProvider);
    } on Object catch (e) {
      // L'interrupteur revient sur sa position d'avant : laisser « en ligne »
      // affiché alors que le serveur a refusé ferait attendre des demandes qui
      // ne viendront jamais.
      state = Disponibilite(enLigne: state.enLigne, refus: messageErreur(e));
    }
  }

  void oublierLeRefus() => state = Disponibilite(enLigne: state.enLigne);
}

final NotifierProvider<DisponibiliteController, Disponibilite>
disponibiliteProvider =
    NotifierProvider<DisponibiliteController, Disponibilite>(
      DisponibiliteController.new,
    );
