import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/design_system/app_theme.dart';
import 'package:depanne_moi/features/shared/data/modeles.dart';
import 'package:depanne_moi/features/technicien/application/technicien_providers.dart';
import 'package:depanne_moi/features/technicien/data/technicien_repository.dart';
import 'package:depanne_moi/features/technicien/presentation/diagnostic_feuille.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

class _DepotFactice extends Mock implements TechnicienRepository {}

/// Un ticket accepté à 85 000 GNF de prestation : le supplément y est donc
/// plafonné à 170 000 GNF.
final Ticket _ticket = Ticket.depuisJson(<String, dynamic>{
  'id': 7,
  'reference': 'DM-2026-000007',
  'etat': 'SUR_PLACE',
  'etat_libelle': 'Sur place',
  'etat_actif': true,
  'prix': <String, dynamic>{
    'prestation_gnf': 85000,
    'total_gnf': 86000,
    'total_formate': '86 000 GNF',
  },
});

Future<void> _monter(WidgetTester tester, TechnicienRepository depot) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [technicienRepositoryProvider.overrideWithValue(depot)],
      child: MaterialApp(
        theme: AppTheme.clair(AppMode.technicien),
        home: Scaffold(body: ProposerDiagnosticFeuille(ticket: _ticket)),
      ),
    ),
  );

  await tester.pumpAndSettle();
}

bool _envoiActif(WidgetTester tester) {
  final FilledButton bouton = tester.widget<FilledButton>(
    find.ancestor(
      of: find.text('Envoyer la proposition'),
      matching: find.byType(FilledButton),
    ),
  );

  return bouton.onPressed != null;
}

void main() {
  testWidgets('n’envoie rien sans diagnostic écrit', (
    WidgetTester tester,
  ) async {
    await _monter(tester, _DepotFactice());

    expect(_envoiActif(tester), isFalse);

    // Un montant seul ne suffit pas : un supplément sans explication est un
    // litige à venir dont le support n'aurait rien à examiner.
    await tester.enterText(
      find.widgetWithText(TextField, 'Supplément (GNF)'),
      '25000',
    );
    await tester.pumpAndSettle();

    expect(_envoiActif(tester), isFalse);

    await tester.enterText(
      find.widgetWithText(TextField, 'Ce que tu as constaté'),
      'Le siphon est fendu, il faut le remplacer entièrement.',
    );
    await tester.pumpAndSettle();

    expect(_envoiActif(tester), isTrue);
  });

  testWidgets('annonce le nouveau total avant l’envoi', (
    WidgetTester tester,
  ) async {
    await _monter(tester, _DepotFactice());

    await tester.enterText(
      find.widgetWithText(TextField, 'Supplément (GNF)'),
      '25000',
    );
    await tester.pumpAndSettle();

    // 86 000 + 25 000 : le technicien voit ce qu'il demande au client avant de
    // le lui demander.
    expect(find.textContaining('111'), findsWidgets);
  });

  testWidgets('signale le dépassement du plafond', (WidgetTester tester) async {
    await _monter(tester, _DepotFactice());

    await tester.enterText(
      find.widgetWithText(TextField, 'Supplément (GNF)'),
      '200000',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Ce que tu as constaté'),
      'Le siphon est fendu, il faut le remplacer entièrement.',
    );
    await tester.pumpAndSettle();

    // Le serveur refuserait au-delà du double de la prestation. L'écran le dit
    // avant l'aller-retour — mais c'est bien le serveur qui tranche.
    expect(find.textContaining('double de la prestation'), findsWidgets);
  });
}
