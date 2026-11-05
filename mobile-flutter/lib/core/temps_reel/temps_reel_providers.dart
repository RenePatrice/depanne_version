/// Temps réel : câblage et repli.
library;

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'temps_reel_service.dart';

/// Le service, tant qu'une session est ouverte.
///
/// Il naît à la connexion et meurt à la déconnexion : un WebSocket ouvert au
/// nom d'un utilisateur parti continuerait de recevoir ses notifications.
/// Sans configuration Reverb, il n'existe pas et l'application interroge le
/// serveur, exactement comme avant D4.
final Provider<TempsReelService?> tempsReelProvider =
    Provider<TempsReelService?>((Ref ref) {
      final int? utilisateurId = ref.watch(
        sessionProvider.select((s) => s.utilisateur?.id),
      );
      final config = ref.watch(configProvider);

      if (utilisateurId == null || !config.tempsReelConfigure) return null;

      final api = ref.watch(apiClientProvider);

      final TempsReelService service = TempsReelService(
        url: config.reverbUrl,
        cle: config.reverbCle,
        autoriser: (String socketId, String canal) async {
          final Map<String, dynamic> reponse = await api.post(
            '/broadcasting/auth',
            corps: <String, dynamic>{
              'socket_id': socketId,
              'channel_name': canal,
            },
          );

          return reponse['auth'] as String;
        },
      );

      service
        ..abonner('utilisateur.$utilisateurId')
        ..demarrer();

      ref.onDispose(service.fermer);

      return service;
    });

/// Vrai quand le WebSocket est ouvert.
final StreamProvider<bool> tempsReelConnecteProvider = StreamProvider<bool>((
  Ref ref,
) {
  final TempsReelService? service = ref.watch(tempsReelProvider);
  if (service == null) return Stream<bool>.value(false);

  return service.etats;
});

/// Les événements d'un canal, pour la durée de vie de l'écran qui l'écoute.
final signauxProvider = StreamProvider.autoDispose
    .family<EvenementTempsReel, String>((Ref ref, String canal) {
      final TempsReelService? service = ref.watch(tempsReelProvider);
      if (service == null) return const Stream<EvenementTempsReel>.empty();

      service.abonner(canal);
      ref.onDispose(() => service.desabonner(canal));

      return service.surCanal(canal);
    });

/// Interrogation de repli, partagée par les écrans qui suivent un état vivant.
///
/// Sans WebSocket, l'écran interroge toutes les dix secondes, comme en D3.
/// Avec, il n'interroge plus que toutes les trente : le signal arrive par
/// Reverb, et la boucle ne sert plus qu'à rattraper un message perdu. Elle ne
/// s'arrête jamais tout à fait — le réseau de Conakry coupe sans prévenir, et
/// un WebSocket peut se croire ouvert bien après que la ligne est tombée.
mixin InterrogationDeRepli<T extends ConsumerStatefulWidget>
    on ConsumerState<T> {
  static const Duration _pas = Duration(seconds: 10);
  static const int _pasSiConnecte = 3;

  Timer? _minuteur;
  int _tics = 0;

  /// Lance la boucle. `rafraichir` renvoie `false` pour l'arrêter — sur un
  /// état final, continuer coûterait du forfait pour rien.
  void demarrerInterrogation(bool Function() rafraichir) {
    _minuteur?.cancel();
    _minuteur = Timer.periodic(_pas, (_) {
      if (!mounted) return;

      _tics++;
      final bool connecte = ref.read(tempsReelConnecteProvider).value ?? false;
      if (connecte && _tics % _pasSiConnecte != 0) return;

      if (!rafraichir()) arreterInterrogation();
    });
  }

  void arreterInterrogation() {
    _minuteur?.cancel();
    _minuteur = null;
  }

  @override
  void dispose() {
    arreterInterrogation();
    super.dispose();
  }
}
