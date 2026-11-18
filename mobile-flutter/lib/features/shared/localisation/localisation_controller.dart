import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Retient que la permission de localisation a été demandée une fois.
///
/// La demande est faite dès l'entrée dans l'application (décision du client) :
/// elle sert à trouver les techniciens proches et à mesurer le déplacement.
/// On ne la repose pas à chaque lancement — un refus se rattrape depuis le
/// profil ou au moment où la position sert.
abstract interface class LocalisationStore {
  Future<bool> dejaDemandee();

  Future<void> marquer();
}

class SecureLocalisationStore implements LocalisationStore {
  const SecureLocalisationStore([this._coffre = const FlutterSecureStorage()]);

  static const String _cle = 'parema.localisation_demandee';

  final FlutterSecureStorage _coffre;

  @override
  Future<bool> dejaDemandee() async => await _coffre.read(key: _cle) == '1';

  @override
  Future<void> marquer() => _coffre.write(key: _cle, value: '1');
}

class MemoryLocalisationStore implements LocalisationStore {
  bool _demandee = false;

  @override
  Future<bool> dejaDemandee() async => _demandee;

  @override
  Future<void> marquer() async => _demandee = true;
}

final Provider<LocalisationStore> localisationStoreProvider =
    Provider<LocalisationStore>((Ref ref) => const SecureLocalisationStore());

/// `null` tant qu'on ne sait pas encore ; le routeur n'insiste pas tant que la
/// réponse n'est pas connue, pour ne pas faire clignoter l'écran.
class LocalisationController extends Notifier<bool?> {
  @override
  bool? build() => null;

  LocalisationStore get _coffre => ref.read(localisationStoreProvider);

  Future<void> charger() async {
    try {
      state = await _coffre.dejaDemandee();
    } catch (_) {
      // Coffre illisible : mieux vaut ne pas bloquer l'entrée que redemander
      // en boucle.
      state = true;
    }
  }

  Future<void> marquer() async {
    state = true;
    try {
      await _coffre.marquer();
    } catch (_) {
      // Sans coffre, la question reviendra au prochain lancement : sans gravité.
    }
  }
}

final NotifierProvider<LocalisationController, bool?>
localisationDemandeeProvider = NotifierProvider<LocalisationController, bool?>(
  LocalisationController.new,
);
