import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/app_mode.dart';
import 'core/providers.dart';
import 'core/router/app_router.dart';
import 'design_system/app_theme.dart';
import 'features/auth/application/session_controller.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  runApp(const ProviderScope(child: DepanneMoiApp()));
}

/// Racine de l'application.
///
/// Le thème suit **deux** variables : la luminosité du système, et le mode
/// Client / Technicien. La seconde change la couleur d'accentuation, ce qui
/// fait que la bascule se voit dès le premier pixel plutôt que dans un libellé
/// en haut de l'écran.
class DepanneMoiApp extends ConsumerStatefulWidget {
  const DepanneMoiApp({super.key});

  @override
  ConsumerState<DepanneMoiApp> createState() => _DepanneMoiAppState();
}

class _DepanneMoiAppState extends ConsumerState<DepanneMoiApp> {
  @override
  void initState() {
    super.initState();

    // Après le premier rendu : `restaurer()` modifie un fournisseur, ce qui
    // est interdit pendant la construction du widget.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(sessionProvider.notifier).restaurer();
    });
  }

  @override
  Widget build(BuildContext context) {
    final AppMode mode = ref.watch(modeProvider);

    // Le mode mémorisé est rétabli dès qu'on sait qui est connecté : il dépend
    // des casquettes du compte, qu'on ignore avant.
    ref.listen<Session>(sessionProvider, (Session? avant, Session apres) {
      if (avant?.utilisateur?.id != apres.utilisateur?.id) {
        ref.read(modeProvider.notifier).restaurer(apres.utilisateur);
      }
    });

    return MaterialApp.router(
      title: 'Dépanne-Moi',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.clair(mode),
      darkTheme: AppTheme.sombre(mode),
      routerConfig: ref.watch(routerProvider),
    );
  }
}
