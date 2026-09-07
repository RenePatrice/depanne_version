import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../core/app_mode.dart';
import '../../../core/providers.dart';
import '../../auth/data/utilisateur.dart';

/// Mémorise le mode choisi, d'un lancement à l'autre.
///
/// Un technicien qui rouvre l'application veut y retrouver son tableau de bord,
/// pas le catalogue client. Reposer la question à chaque démarrage serait une
/// friction quotidienne pour la moitié des utilisateurs.
abstract interface class ModeStore {
  Future<AppMode?> lire();

  Future<void> ecrire(AppMode mode);
}

class SecureModeStore implements ModeStore {
  const SecureModeStore([this._coffre = const FlutterSecureStorage()]);

  static const String _cle = 'depanne.mode';

  final FlutterSecureStorage _coffre;

  @override
  Future<AppMode?> lire() async =>
      AppMode.depuisCle(await _coffre.read(key: _cle));

  @override
  Future<void> ecrire(AppMode mode) =>
      _coffre.write(key: _cle, value: mode.cle);
}

class MemoryModeStore implements ModeStore {
  AppMode? _mode;

  @override
  Future<AppMode?> lire() async => _mode;

  @override
  Future<void> ecrire(AppMode mode) async => _mode = mode;
}

/// Le mode courant de l'application.
///
/// Il n'est pas dérivé du compte : un utilisateur à double casquette choisit,
/// et son choix tient. C'est seulement quand le mode mémorisé n'est plus
/// accessible — un technicien dont le compte a perdu sa casquette — qu'on
/// retombe sur celui qui reste.
class ModeController extends Notifier<AppMode> {
  @override
  AppMode build() => AppMode.client;

  ModeStore get _coffre => ref.read(modeStoreProvider);

  /// Rétablit le mode mémorisé, en le confrontant aux casquettes du compte.
  Future<void> restaurer(Utilisateur? utilisateur) async {
    final List<AppMode> disponibles =
        utilisateur?.modesDisponibles ?? const <AppMode>[AppMode.client];

    if (disponibles.isEmpty) {
      state = AppMode.client;
      return;
    }

    final AppMode? memorise = await _coffre.lire();

    state = (memorise != null && disponibles.contains(memorise))
        ? memorise
        : disponibles.first;
  }

  Future<void> basculer(AppMode mode) async {
    if (state == mode) return;

    state = mode;
    await _coffre.ecrire(mode);
  }
}
