/// Câblage des dépendances.
///
/// Tout est déclaré ici plutôt qu'au fil des fichiers : on voit d'un coup ce
/// dont l'application dépend, et un test peut remplacer n'importe quelle pièce
/// par une doublure en surchargeant un seul fournisseur.
library;

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/application/session_controller.dart';
import '../features/auth/data/auth_repository.dart';
import '../features/shared/mode/mode_controller.dart';
import 'app_mode.dart';
import 'auth/token_store.dart';
import 'config/app_config.dart';
import 'network/api_client.dart';

final Provider<AppConfig> configProvider = Provider<AppConfig>(
  (Ref ref) => AppConfig.depuisEnvironnement(),
);

final Provider<TokenStore> tokenStoreProvider = Provider<TokenStore>(
  (Ref ref) => SecureTokenStore(),
);

final Provider<ModeStore> modeStoreProvider = Provider<ModeStore>(
  (Ref ref) => const SecureModeStore(),
);

/// Le client HTTP.
///
/// Sa fabrication reçoit `surDeconnexion`, qui remonte au contrôleur de
/// session : quand le rafraîchissement échoue définitivement, l'application
/// doit revenir à l'écran de connexion **sans attendre** que l'utilisateur
/// touche un bouton.
///
/// La lecture différée du contrôleur — `ref.read` dans la fermeture, pas au
/// moment de la construction — évite la dépendance circulaire : le client a
/// besoin de la session, la session a besoin du client.
final Provider<ApiClient> apiClientProvider = Provider<ApiClient>((Ref ref) {
  return ApiClient.creer(
    config: ref.watch(configProvider),
    store: ref.watch(tokenStoreProvider),
    surDeconnexion: () async {
      ref.read(sessionProvider.notifier).sessionPerdue();
    },
  );
});

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>(
      (Ref ref) => AuthRepository(
        client: ref.watch(apiClientProvider),
        store: ref.watch(tokenStoreProvider),
      ),
    );

final NotifierProvider<SessionController, Session> sessionProvider =
    NotifierProvider<SessionController, Session>(SessionController.new);

final NotifierProvider<ModeController, AppMode> modeProvider =
    NotifierProvider<ModeController, AppMode>(ModeController.new);
