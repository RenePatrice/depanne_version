import 'package:depanne_moi/core/network/api_exception.dart';
import 'package:depanne_moi/features/chat/application/chat_providers.dart';
import 'package:depanne_moi/features/chat/data/chat_repository.dart';
import 'package:depanne_moi/features/chat/presentation/chat_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _ChatFactice implements ChatRepository {
  _ChatFactice({this.ouverte = true, this.echec});

  final bool ouverte;
  final ApiException? echec;
  final List<MessageChat> messages = <MessageChat>[];
  final List<String> envoyes = <String>[];

  @override
  Future<Conversation> conversation(int ticketId) async =>
      Conversation(ouverte: ouverte, messages: List.of(messages));

  @override
  Future<MessageEnvoye> envoyer(int ticketId, String contenu) async {
    if (echec != null) throw echec!;
    envoyes.add(contenu);

    // Ce que ferait le serveur : le message part, masqué, avec l'explication.
    final MessageChat message = MessageChat(
      id: envoyes.length,
      contenu: 'Appelle-moi au [numéro masqué]',
      deMoi: true,
      masque: true,
    );
    messages.add(message);

    return MessageEnvoye(
      message: message,
      avertissement:
          'Ton message contenait un numéro de téléphone : il a été masqué. '
          'Utilise l’appel masqué pour te joindre sans le partager.',
    );
  }

  @override
  Future<String> appeler(int ticketId) async =>
      'La mise en relation par numéro masqué arrive bientôt.';
}

Future<void> _ouvrir(WidgetTester tester, _ChatFactice depot) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [chatRepositoryProvider.overrideWithValue(depot)],
      child: const MaterialApp(home: ChatScreen(ticketId: 7)),
    ),
  );
  await tester.pumpAndSettle();
}

/// L'écran interroge le serveur en repli : on le démonte avant la fin pour
/// arrêter sa boucle.
Future<void> _fermer(WidgetTester tester) =>
    tester.pumpWidget(const SizedBox.shrink());

void main() {
  testWidgets('affiche l’avertissement du filtre sans bloquer le message', (
    WidgetTester tester,
  ) async {
    final _ChatFactice depot = _ChatFactice();
    await _ouvrir(tester, depot);

    await tester.enterText(
      find.byType(TextField),
      'Appelle-moi au 620 12 34 56',
    );
    await tester.tap(find.byTooltip('Envoyer'));
    await tester.pumpAndSettle();

    // Le message est parti — masqué — et l'explication est affichée telle que
    // le serveur l'a rédigée : c'est elle qui dissuade (ADR-0030).
    expect(depot.envoyes, hasLength(1));
    expect(find.textContaining('[numéro masqué]'), findsOneWidget);
    expect(find.text('Coordonnées masquées'), findsOneWidget);
    expect(find.textContaining('il a été masqué'), findsOneWidget);

    await _fermer(tester);
  });

  testWidgets('ne propose ni saisie ni appel quand le fil est fermé', (
    WidgetTester tester,
  ) async {
    await _ouvrir(tester, _ChatFactice(ouverte: false));

    expect(find.byType(TextField), findsNothing);
    expect(find.byTooltip('Appeler sans dévoiler mon numéro'), findsNothing);
    expect(find.text('Conversation fermée'), findsOneWidget);

    await _fermer(tester);
  });

  testWidgets('garde le texte saisi quand l’envoi échoue', (
    WidgetTester tester,
  ) async {
    await _ouvrir(
      tester,
      _ChatFactice(
        echec: const ApiException(
          'Pas de connexion. Vérifie ton réseau et réessaie.',
          reseau: true,
        ),
      ),
    );

    await tester.enterText(find.byType(TextField), 'J’arrive dans dix minutes');
    await tester.tap(find.byTooltip('Envoyer'));
    await tester.pumpAndSettle();

    // Sur une coupure, retaper le message serait la pire des réponses.
    expect(find.text('J’arrive dans dix minutes'), findsOneWidget);
    expect(find.textContaining('Pas de connexion'), findsOneWidget);

    await _fermer(tester);
  });
}
