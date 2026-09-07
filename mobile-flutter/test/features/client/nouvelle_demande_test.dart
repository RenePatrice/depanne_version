import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/design_system/app_theme.dart';
import 'package:depanne_moi/features/client/application/client_providers.dart';
import 'package:depanne_moi/features/client/data/client_repository.dart';
import 'package:depanne_moi/features/client/data/modeles.dart';
import 'package:depanne_moi/features/client/presentation/nouvelle_demande_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';

class _DepotFactice extends Mock implements ClientRepository {}

const Prestation _prestation = Prestation(
  id: 3,
  nom: 'Réparation de fuite',
  prixGnf: 85000,
  prixFormate: '85 000 GNF',
);

const Adresse _adresse = Adresse(
  id: 9,
  libelle: 'Maison',
  adresse: 'Kipé, carrefour Constantin',
  parDefaut: true,
  couverte: true,
);

Devis _devis({required bool ferme}) => Devis(
  totalGnf: 86000,
  totalFormate: '86 000 GNF',
  ferme: ferme,
  lignes: const <LigneDevis>[
    LigneDevis(
      libelle: 'Prestation',
      montantGnf: 85000,
      montantFormate: '85 000 GNF',
    ),
  ],
);

Future<void> _monter(WidgetTester tester, ClientRepository depot) async {
  final ProviderContainer conteneur = ProviderContainer(
    overrides: [
      clientRepositoryProvider.overrideWithValue(depot),
      adressesProvider.overrideWith(
        (Ref ref) async => const <Adresse>[_adresse],
      ),
    ],
  );
  addTearDown(conteneur.dispose);

  // Le brouillon est renseigné hors du rendu, comme le ferait le catalogue :
  // l'écran teste la suite du parcours, pas le choix de la prestation.
  conteneur.read(brouillonProvider.notifier).choisirPrestation(_prestation);

  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: conteneur,
      child: MaterialApp(
        theme: AppTheme.clair(AppMode.client),
        home: const NouvelleDemandeScreen(),
      ),
    ),
  );

  await tester.pumpAndSettle();
}

/// Le bouton de publication, tel que l'utilisateur le voit : actif ou non.
///
/// L'écran est une liste défilante : le bouton peut être hors du champ visible
/// une fois le devis affiché, et un `find` seul ne le trouverait pas — comme
/// l'utilisateur, il faut d'abord descendre jusqu'à lui.
Future<bool> _publicationActive(WidgetTester tester) async {
  final Finder libelle = find.text('Publier ma demande');

  // La liste, pas le champ de texte : les deux sont des `Scrollable`, et sans
  // cette précision le test ne sait pas lequel faire défiler.
  await tester.scrollUntilVisible(
    libelle,
    200,
    scrollable: find.byType(Scrollable).first,
  );

  final FilledButton bouton = tester.widget<FilledButton>(
    find.ancestor(of: libelle, matching: find.byType(FilledButton)),
  );

  return bouton.onPressed != null;
}

void main() {
  testWidgets('n’ouvre la publication qu’une fois le prix affiché', (
    WidgetTester tester,
  ) async {
    final _DepotFactice depot = _DepotFactice();

    when(
      () => depot.devis(
        prestationId: any(named: 'prestationId'),
        adresseId: any(named: 'adresseId'),
      ),
    ).thenAnswer((_) async => _devis(ferme: false));

    await _monter(tester, depot);

    // Toute la promesse de l'application tient ici : personne n'engage une
    // dépense sans l'avoir vue. Tant qu'aucune adresse n'est choisie, il n'y a
    // pas de prix, donc pas de publication.
    expect(await _publicationActive(tester), isFalse);

    await tester.tap(find.text('Maison'));
    await tester.pumpAndSettle();

    expect(find.text('86 000 GNF'), findsOneWidget);
    expect(await _publicationActive(tester), isTrue);
  });

  testWidgets('annonce « à partir de » tant que le prix n’est pas ferme', (
    WidgetTester tester,
  ) async {
    final _DepotFactice depot = _DepotFactice();

    when(
      () => depot.devis(
        prestationId: any(named: 'prestationId'),
        adresseId: any(named: 'adresseId'),
      ),
    ).thenAnswer((_) async => _devis(ferme: false));

    await _monter(tester, depot);
    await tester.tap(find.text('Maison'));
    await tester.pumpAndSettle();

    // Le déplacement dépend du technicien qui acceptera (ADR-0026) : annoncer
    // « total » ici serait le mensonge qui produit la première réclamation.
    expect(find.text('À partir de'), findsOneWidget);
    expect(find.text('Total'), findsNothing);
  });

  testWidgets('dit « total » quand le serveur donne un prix ferme', (
    WidgetTester tester,
  ) async {
    final _DepotFactice depot = _DepotFactice();

    when(
      () => depot.devis(
        prestationId: any(named: 'prestationId'),
        adresseId: any(named: 'adresseId'),
      ),
    ).thenAnswer((_) async => _devis(ferme: true));

    await _monter(tester, depot);
    await tester.tap(find.text('Maison'));
    await tester.pumpAndSettle();

    expect(find.text('Total'), findsOneWidget);
    expect(find.text('À partir de'), findsNothing);
  });
}
