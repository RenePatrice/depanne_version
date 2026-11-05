import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/features/notifications/data/notifications_repository.dart';
import 'package:depanne_moi/features/notifications/presentation/notifications_screen.dart';
import 'package:flutter_test/flutter_test.dart';

NotificationApp _notification(String type, {int? ticketId = 42}) =>
    NotificationApp.depuisJson(<String, dynamic>{
      'id': '9b1c7f4e-0000-4000-8000-000000000001',
      'type': type,
      'titre': 'Titre',
      'corps': 'Corps',
      'lue': false,
      'donnees': <String, dynamic>{'ticket_id': ?ticketId},
    });

void main() {
  group('destination d’une notification', () {
    test('mène le client à son suivi, même depuis le mode technicien', () {
      // Un technicien qui a aussi la casquette client lit la notification
      // d'une demande qu'il a publiée : elle doit ouvrir le suivi client.
      expect(
        destinationDe(_notification('TICKET_ACCEPTE'), AppMode.technicien),
        '/client/suivi/42',
      );
    });

    test('mène le technicien à son intervention', () {
      expect(
        destinationDe(_notification('DIAGNOSTIC_TRANCHE'), AppMode.client),
        '/technicien/intervention/42',
      );
    });

    test('renvoie une sollicitation vers l’onglet Demandes', () {
      expect(
        destinationDe(_notification('NOUVELLE_DEMANDE'), AppMode.technicien),
        '/accueil',
      );
    });

    test('n’ouvre pas un ticket qui n’est plus le sien', () {
      for (final String type in <String>[
        'DEMANDE_EXPIREE',
        'DEMANDE_ATTRIBUEE',
        'DEMANDE_RETIREE',
      ]) {
        expect(destinationDe(_notification(type), AppMode.technicien), isNull);
      }
    });

    test('suit le mode pour un type partagé', () {
      expect(
        destinationDe(_notification('PAIEMENT_RECU'), AppMode.technicien),
        '/technicien/intervention/42',
      );
      expect(
        destinationDe(_notification('PAIEMENT_RECU'), AppMode.client),
        '/client/suivi/42',
      );
    });

    test('ne mène nulle part sans ticket', () {
      expect(
        destinationDe(
          _notification('PAIEMENT_RECU', ticketId: null),
          AppMode.client,
        ),
        isNull,
      );
    });
  });
}
