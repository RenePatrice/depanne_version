import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/design_system/app_theme.dart';
import 'package:depanne_moi/features/client/application/client_providers.dart';
import 'package:depanne_moi/features/client/presentation/adresse_form_screen.dart';
import 'package:depanne_moi/features/shared/data/modeles.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Le formulaire d'adresse lit les quartiers desservis sur le serveur
/// (`/zones`) et les présente commune par commune. Ces tests vérifient le
/// parcours de choix ; la carte, qui suit le choix du quartier, n'est pas
/// affichée ici.

const List<QuartierCouvert> _quartiers = <QuartierCouvert>[
  QuartierCouvert(
    id: 1,
    nom: 'Boulbinet',
    commune: 'Kaloum',
    latitude: 9.511,
    longitude: -13.713,
  ),
  QuartierCouvert(
    id: 2,
    nom: 'Kipé',
    commune: 'Ratoma',
    latitude: 9.598,
    longitude: -13.643,
  ),
  QuartierCouvert(
    id: 3,
    nom: 'Nongo',
    commune: 'Ratoma',
    latitude: 9.589,
    longitude: -13.651,
  ),
];

Future<void> _monter(
  WidgetTester tester,
  Future<List<QuartierCouvert>> Function() source,
) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [quartiersProvider.overrideWith((Ref ref) => source())],
      child: MaterialApp(
        theme: AppTheme.clair(AppMode.client),
        home: const AdresseFormScreen(),
      ),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  testWidgets('propose les communes venues du serveur, pas une liste figée', (
    WidgetTester tester,
  ) async {
    await _monter(tester, () async => _quartiers);

    expect(find.widgetWithText(ChoiceChip, 'Kaloum'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Ratoma'), findsOneWidget);

    // Aucun quartier tant qu'aucune commune n'est choisie.
    expect(find.widgetWithText(ChoiceChip, 'Kipé'), findsNothing);
    expect(
      find.text('Choisis d\'abord ta commune pour voir ses quartiers.'),
      findsOneWidget,
    );
  });

  testWidgets('n\'affiche que les quartiers de la commune choisie', (
    WidgetTester tester,
  ) async {
    await _monter(tester, () async => _quartiers);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Ratoma'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(ChoiceChip, 'Kipé'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Nongo'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Boulbinet'), findsNothing);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Kaloum'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(ChoiceChip, 'Boulbinet'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Kipé'), findsNothing);
  });

  testWidgets('propose de réessayer si les quartiers ne se chargent pas', (
    WidgetTester tester,
  ) async {
    await _monter(tester, () async => throw Exception('réseau coupé'));

    expect(find.text('Réessayer'), findsOneWidget);
  });
}
