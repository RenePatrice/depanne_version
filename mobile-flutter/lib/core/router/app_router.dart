import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/application/session_controller.dart';
import '../../features/auth/presentation/connexion_screen.dart';
import '../../features/auth/presentation/dossier_technicien_screen.dart';
import '../../features/auth/presentation/inscription_screen.dart';
import '../../features/auth/presentation/mot_de_passe_oublie_screen.dart';
import '../../features/onboarding/onboarding_screen.dart';
import '../../features/shared/accueil_screen.dart';
import '../../features/shared/demarrage_screen.dart';
import '../providers.dart';

/// Navigation de l'application.
///
/// Une **seule** règle de redirection, ici, plutôt qu'un `Navigator.push` dans
/// chaque écran : c'est ce qui garantit qu'une session perdue en plein
/// parcours ramène partout à la connexion, y compris depuis un écran ouvert
/// depuis dix minutes.
final Provider<GoRouter> routerProvider = Provider<GoRouter>((Ref ref) {
  // `_Rafraichisseur` relaie les changements de session à go_router : sans
  // lui, la redirection ne serait réévaluée qu'à la prochaine navigation, et
  // une déconnexion en arrière-plan laisserait l'écran en place.
  final _Rafraichisseur rafraichisseur = _Rafraichisseur(ref);
  ref.onDispose(rafraichisseur.dispose);

  return GoRouter(
    initialLocation: '/demarrage',
    refreshListenable: rafraichisseur,
    redirect: (BuildContext context, GoRouterState state) {
      final Session session = ref.read(sessionProvider);
      final String vers = state.matchedLocation;

      // Tant que la session n'est pas tranchée, on reste sur le démarrage :
      // afficher la connexion puis basculer sur l'accueil donnerait
      // l'impression d'une déconnexion à chaque lancement.
      if (session.etat == EtatSession.inconnu) {
        return vers == '/demarrage' ? null : '/demarrage';
      }

      const Set<String> ecransOuverts = <String>{
        '/onboarding',
        '/connexion',
        '/inscription',
        '/mot-de-passe-oublie',
      };

      if (!session.estConnecte) {
        return ecransOuverts.contains(vers) ? null : '/onboarding';
      }

      // Un technicien sans dossier **et sans casquette client** n'a rien à
      // faire ailleurs : l'accueil ne lui montrerait que des onglets vides.
      // S'il a aussi la casquette client, en revanche, il circule librement.
      final bool bloqueSurLeDossier =
          session.etat == EtatSession.dossierACompleter &&
          !(session.utilisateur?.estClient ?? false);

      if (bloqueSurLeDossier) {
        return vers == '/dossier-technicien' ? null : '/dossier-technicien';
      }

      // Connecté : les écrans d'accueil et d'authentification n'ont plus de
      // sens, on renvoie vers l'application.
      if (ecransOuverts.contains(vers) || vers == '/demarrage') {
        return '/accueil';
      }

      return null;
    },
    routes: <RouteBase>[
      GoRoute(path: '/demarrage', builder: (_, _) => const DemarrageScreen()),
      GoRoute(path: '/onboarding', builder: (_, _) => const OnboardingScreen()),
      GoRoute(path: '/connexion', builder: (_, _) => const ConnexionScreen()),
      GoRoute(
        path: '/inscription',
        builder: (_, _) => const InscriptionScreen(),
      ),
      GoRoute(
        path: '/mot-de-passe-oublie',
        builder: (_, _) => const MotDePasseOublieScreen(),
      ),
      GoRoute(
        path: '/dossier-technicien',
        builder: (_, _) => const DossierTechnicienScreen(),
      ),
      GoRoute(path: '/accueil', builder: (_, _) => const AccueilScreen()),
    ],
  );
});

/// Pont entre Riverpod et go_router.
///
/// go_router réévalue sa redirection quand ce `Listenable` notifie ; on lui
/// fait suivre chaque changement d'état de session.
class _Rafraichisseur extends ChangeNotifier {
  _Rafraichisseur(Ref ref) {
    _fermer = ref.listen<Session>(sessionProvider, (
      Session? avant,
      Session apres,
    ) {
      if (avant?.etat != apres.etat) notifyListeners();
    }).close;
  }

  late final VoidCallback _fermer;

  @override
  void dispose() {
    _fermer();
    super.dispose();
  }
}
