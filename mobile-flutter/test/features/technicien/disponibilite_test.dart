import 'package:depanne_moi/features/technicien/application/technicien_providers.dart';
import 'package:depanne_moi/features/technicien/data/technicien_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

class _DepotFactice extends Mock implements TechnicienRepository {}

void main() {
  test(
    'reste en ligne après le basculement, même si la session dit hors ligne',
    () async {
      final _DepotFactice depot = _DepotFactice();
      when(() => depot.definirDisponibilite(true))
          .thenAnswer((_) async => true);

      final ProviderContainer conteneur = ProviderContainer(
        overrides: [technicienRepositoryProvider.overrideWithValue(depot)],
      );
      addTearDown(conteneur.dispose);

      final DisponibiliteController controleur = conteneur.read(
        disponibiliteProvider.notifier,
      );

      // Au premier affichage, la session (non relue depuis) dit « hors ligne ».
      controleur.amorcer(false);
      await controleur.basculer(true);

      // L'écran se redessine et repasse la même valeur de session périmée.
      controleur.amorcer(false);

      expect(conteneur.read(disponibiliteProvider).enLigne, isTrue);
    },
  );

  test('reprend l’état connu du compte au premier affichage', () {
    final ProviderContainer conteneur = ProviderContainer(
      overrides: [
        technicienRepositoryProvider.overrideWithValue(_DepotFactice()),
      ],
    );
    addTearDown(conteneur.dispose);

    conteneur.read(disponibiliteProvider.notifier).amorcer(true);

    expect(conteneur.read(disponibiliteProvider).enLigne, isTrue);
  });
}
