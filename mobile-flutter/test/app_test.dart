import 'package:depanne_moi/core/app_mode.dart';
import 'package:depanne_moi/core/auth/token_store.dart';
import 'package:depanne_moi/core/providers.dart';
import 'package:depanne_moi/design_system/app_theme.dart';
import 'package:depanne_moi/design_system/design_tokens.dart';
import 'package:depanne_moi/features/auth/application/session_controller.dart';
import 'package:depanne_moi/features/auth/data/utilisateur.dart';
import 'package:depanne_moi/features/shared/demarrage_screen.dart';
import 'package:depanne_moi/features/shared/mode/mode_controller.dart';
import 'package:depanne_moi/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Compte de test, avec les casquettes demandées.
Utilisateur _compte({
  bool client = true,
  bool technicien = false,
  String? verification,
}) => Utilisateur(
  id: 1,
  nomComplet: 'Aïssatou Barry',
  prenom: 'Aïssatou',
  telephone: '+224620112233',
  telephoneAffichage: '620 11 22 33',
  estClient: client,
  estTechnicien: technicien,
  statut: 'ACTIF',
  verificationTechnicien: verification,
);

void main() {
  group('jetons de design', () {
    test('reflète les couleurs de marque du fichier SCSS', () {
      // Ces trois valeurs sont l'identité de la marque. Si elles bougent d'un
      // côté sans l'autre, le back-office et l'application cessent de se
      // ressembler — et ce test le dit avant que quelqu'un ne le remarque à
      // l'œil.
      expect(DmColors.primary, const Color(0xFF1B6FF3));
      expect(DmColors.accentTech, const Color(0xFFFF7A1A));
      expect(DmColors.neutral900, const Color(0xFF0F172A));
    });

    test('espace tout sur une grille de 4 pixels', () {
      for (final double espace in <double>[
        DmSpace.x1,
        DmSpace.x2,
        DmSpace.x3,
        DmSpace.x4,
        DmSpace.x6,
        DmSpace.x8,
        DmSpace.x12,
      ]) {
        expect(espace % 4, 0, reason: '$espace n’est pas sur la grille');
      }
    });
  });

  group('thème', () {
    test('change de couleur d’accentuation avec le mode', () {
      // C'est ce qui rend la bascule visible au premier coup d'œil : si les
      // deux modes partageaient une couleur, un technicien pourrait se croire
      // en mode client et chercher un bouton qui n'existe pas.
      expect(
        AppTheme.clair(AppMode.client).colorScheme.primary,
        DmColors.primary,
      );
      expect(
        AppTheme.clair(AppMode.technicien).colorScheme.primary,
        DmColors.accentTech,
      );
    });

    test('décline les deux luminosités pour chaque mode', () {
      for (final AppMode mode in AppMode.values) {
        expect(AppTheme.clair(mode).brightness, Brightness.light);
        expect(AppTheme.sombre(mode).brightness, Brightness.dark);
      }
    });

    test('donne aux boutons une cible tactile confortable', () {
      // 52 px : l'application s'utilise debout, souvent d'une main.
      final ThemeData theme = AppTheme.clair(AppMode.client);
      final Size? taille = theme.filledButtonTheme.style?.minimumSize?.resolve(
        <WidgetState>{},
      );

      expect(taille?.height, greaterThanOrEqualTo(48));
    });
  });

  group('mode', () {
    test('retient le mode choisi d’un lancement à l’autre', () async {
      final MemoryModeStore coffre = MemoryModeStore();
      final ProviderContainer conteneur = ProviderContainer(
        overrides: [modeStoreProvider.overrideWithValue(coffre)],
      );
      addTearDown(conteneur.dispose);

      await conteneur.read(modeProvider.notifier).basculer(AppMode.technicien);

      expect(await coffre.lire(), AppMode.technicien);
    });

    test('ignore un mode mémorisé que le compte ne permet plus', () async {
      // Un technicien qui perd sa casquette ne doit pas se retrouver bloqué
      // sur un mode auquel il n'a plus droit.
      final MemoryModeStore coffre = MemoryModeStore();
      await coffre.ecrire(AppMode.technicien);

      final ProviderContainer conteneur = ProviderContainer(
        overrides: [modeStoreProvider.overrideWithValue(coffre)],
      );
      addTearDown(conteneur.dispose);

      await conteneur
          .read(modeProvider.notifier)
          .restaurer(_compte(client: true, technicien: false));

      expect(conteneur.read(modeProvider), AppMode.client);
    });

    test('rétablit le mode mémorisé quand le compte le permet', () async {
      final MemoryModeStore coffre = MemoryModeStore();
      await coffre.ecrire(AppMode.technicien);

      final ProviderContainer conteneur = ProviderContainer(
        overrides: [modeStoreProvider.overrideWithValue(coffre)],
      );
      addTearDown(conteneur.dispose);

      await conteneur
          .read(modeProvider.notifier)
          .restaurer(_compte(client: true, technicien: true));

      expect(conteneur.read(modeProvider), AppMode.technicien);
    });
  });

  group('session', () {
    test('part d’un état indéterminé plutôt que déconnecté', () {
      // La nuance évite de faire clignoter l'écran de connexion au lancement
      // d'une session parfaitement valide.
      final ProviderContainer conteneur = ProviderContainer(
        overrides: [tokenStoreProvider.overrideWithValue(MemoryTokenStore())],
      );
      addTearDown(conteneur.dispose);

      expect(conteneur.read(sessionProvider).etat, EtatSession.inconnu);
    });

    test('considère connecté un compte dont le dossier reste à compléter', () {
      const Session session = Session(etat: EtatSession.dossierACompleter);

      expect(session.estConnecte, isTrue);
    });
  });

  group('casquettes', () {
    test('n’ouvre la bascule qu’aux comptes qui ont les deux', () {
      expect(_compte(client: true).modesDisponibles, <AppMode>[AppMode.client]);
      expect(
        _compte(client: false, technicien: true).modesDisponibles,
        <AppMode>[AppMode.technicien],
      );
      expect(
        _compte(client: true, technicien: true).modesDisponibles.length,
        2,
      );
    });

    test('lit les casquettes et la vérification depuis le JSON de l’API', () {
      // Les noms de champs suivent `UtilisateurResource` et
      // `ProfilTechnicienResource` : ce test tombe si l'un des deux change.
      final Utilisateur u = Utilisateur.depuisJson(<String, dynamic>{
        'id': 7,
        'nom_complet': 'Mamadou Diallo',
        'prenom': 'Mamadou',
        'telephone': '+224622445566',
        'telephone_affichage': '622 44 55 66',
        'statut': 'ACTIF',
        'casquettes': <String, dynamic>{'client': false, 'technicien': true},
        'profil_technicien': <String, dynamic>{
          'verification': <String, dynamic>{
            'statut': 'VALIDE',
            'peut_travailler': true,
          },
        },
      });

      expect(u.estTechnicien, isTrue);
      expect(u.estClient, isFalse);
      expect(u.verificationTechnicien, 'VALIDE');
      expect(u.peutTravailler, isTrue);
    });
  });

  group('navigation au lancement', () {
    testWidgets(
      'affiche l’écran de démarrage tant que la session est indécise',
      (WidgetTester tester) async {
        // L'écran est monté seul, dans l'état qu'on veut observer. Le monter à
        // travers l'application complète ferait dépendre le test d'une course
        // entre le premier rendu et la restauration de session — un test qui
        // passe ou échoue selon la machine ne protège rien.
        await tester.pumpWidget(
          ProviderScope(
            overrides: [
              tokenStoreProvider.overrideWithValue(MemoryTokenStore()),
            ],
            child: const MaterialApp(home: DemarrageScreen()),
          ),
        );

        expect(find.text('Dépanne-Moi'), findsOneWidget);
        expect(find.byType(CircularProgressIndicator), findsOneWidget);
      },
    );

    testWidgets('mène à l’onboarding quand aucun jeton n’est en coffre', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tokenStoreProvider.overrideWithValue(MemoryTokenStore()),
            modeStoreProvider.overrideWithValue(MemoryModeStore()),
          ],
          child: const DepanneMoiApp(),
        ),
      );

      await tester.pumpAndSettle();

      // Le premier argument de l'onboarding : la vérification des techniciens.
      expect(find.text('Des techniciens vérifiés'), findsOneWidget);
    });
  });
}
