import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/design_system/app_theme.dart';
import 'package:depanne_moi/design_system/pc.dart';
import 'package:depanne_moi/features/shared/data/modeles.dart';
import 'package:depanne_moi/features/technicien/presentation/code_arrivee_feuille.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Code d'arrivée (lot 2) : le pavé renvoie les chiffres tapés, le modèle lit
/// ce que le serveur envoie selon qui regarde.
void main() {
  Future<Future<String?> Function()> monter(
    WidgetTester tester, {
    int? essais,
  }) async {
    String? resultat;
    bool ferme = false;

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.clair(AppMode.technicien),
        home: Builder(
          builder: (BuildContext context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () async {
                  resultat = await CodeArriveeFeuille.demander(
                    context,
                    essaisRestants: essais,
                  );
                  ferme = true;
                },
                child: const Text('ouvrir'),
              ),
            ),
          ),
        ),
      ),
    );

    return () async => ferme ? resultat : 'pas fermé';
  }

  bool valideActif(WidgetTester tester) =>
      tester
          .widget<PcButton>(
            find.widgetWithText(PcButton, 'Valider mon arrivée'),
          )
          .onPressed !=
      null;

  testWidgets('renvoie les quatre chiffres tapés, effacement compris', (
    WidgetTester tester,
  ) async {
    final Future<String?> Function() resultat = await monter(tester);
    await tester.pumpAndSettle();
    await tester.tap(find.text('ouvrir'));
    await tester.pumpAndSettle();

    for (final String c in <String>['1', '9', '7']) {
      await tester.tap(find.bySemanticsLabel(c));
      await tester.pump();
    }
    expect(valideActif(tester), isFalse);

    await tester.tap(find.bySemanticsLabel('Effacer'));
    await tester.pump();
    for (final String c in <String>['0', '4']) {
      await tester.tap(find.bySemanticsLabel(c));
      await tester.pump();
    }
    expect(valideActif(tester), isTrue);

    await tester.tap(find.text('Valider mon arrivée'));
    await tester.pumpAndSettle();

    expect(await resultat(), '1904');
  });

  testWidgets('annonce le dernier essai', (WidgetTester tester) async {
    await monter(tester, essais: 1);
    await tester.pumpAndSettle();
    await tester.tap(find.text('ouvrir'));
    await tester.pumpAndSettle();

    expect(find.text('Dernier essai'), findsOneWidget);
  });

  test('lit le code côté client et les essais côté technicien', () {
    final ArriveeTicket client = ArriveeTicket.depuisJson(<String, dynamic>{
      'code_requis': true,
      'code': '0427',
      'bloque': false,
      'confirmable': true,
    });
    final ArriveeTicket technicien = ArriveeTicket.depuisJson(<String, dynamic>{
      'code_requis': true,
      'essais_restants': 3,
      'bloque': false,
    });

    expect(client.code, '0427');
    expect(client.confirmable, isTrue);
    expect(technicien.code, isNull);
    expect(technicien.essaisRestants, 3);

    // Serveur plus ancien : pas de clé `arrivee`, l'étape passe comme avant.
    expect(ArriveeTicket.depuisJson(null).codeRequis, isFalse);
  });
}
