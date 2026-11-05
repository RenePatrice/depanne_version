import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/notifications_repository.dart';

final Provider<NotificationsRepository> notificationsRepositoryProvider =
    Provider<NotificationsRepository>(
      (Ref ref) => NotificationsRepository(ref.watch(apiClientProvider)),
    );

/// Relu à chaque notification poussée par Reverb sur le canal personnel, et à
/// l'ouverture du centre. Sans WebSocket, le badge se met à jour au prochain
/// passage sur l'accueil : une pastille en retard d'une minute est un
/// désagrément, pas une panne.
final FutureProvider<CentreNotifications> notificationsProvider =
    FutureProvider<CentreNotifications>(
      (Ref ref) => ref.watch(notificationsRepositoryProvider).lister(),
    );
