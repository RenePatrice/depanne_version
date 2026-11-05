import 'package:flutter/foundation.dart';

import '../../../core/network/api_client.dart';

@immutable
class NotificationApp {
  const NotificationApp({
    required this.id,
    required this.type,
    required this.lue,
    this.titre,
    this.corps,
    this.ticketId,
    this.recueLe,
  });

  final String id;
  final String type;
  final bool lue;
  final String? titre;
  final String? corps;

  /// Le ticket concerné, quand il y en a un : toucher la notification y mène.
  final int? ticketId;
  final DateTime? recueLe;

  factory NotificationApp.depuisJson(Map<String, dynamic> json) {
    final Map<String, dynamic> donnees =
        (json['donnees'] as Map<String, dynamic>?) ?? const {};

    return NotificationApp(
      id: json['id'] as String,
      type: json['type'] as String? ?? '',
      lue: json['lue'] as bool? ?? false,
      titre: json['titre'] as String?,
      corps: json['corps'] as String?,
      ticketId: (donnees['ticket_id'] as num?)?.toInt(),
      recueLe: DateTime.tryParse(json['recue_le'] as String? ?? '')?.toLocal(),
    );
  }
}

@immutable
class CentreNotifications {
  const CentreNotifications({required this.nonLues, required this.liste});

  final int nonLues;
  final List<NotificationApp> liste;
}

/// Le centre de notifications lit **la table**, pas le push (§7.4) : sur le
/// réseau de Conakry, un message perdu est un cas courant, et ce qu'on a
/// manqué doit rester retrouvable.
class NotificationsRepository {
  const NotificationsRepository(this._api);

  final ApiClient _api;

  Future<CentreNotifications> lister() async {
    final Map<String, dynamic> reponse = await _api.get(
      '/notifications',
      repliHorsLigne: true,
    );

    return CentreNotifications(
      nonLues: (reponse['non_lues'] as num?)?.toInt() ?? 0,
      liste: ((reponse['notifications'] as List<dynamic>?) ?? const <dynamic>[])
          .map(
            (dynamic n) =>
                NotificationApp.depuisJson(n as Map<String, dynamic>),
          )
          .toList(),
    );
  }

  /// Sans identifiant, tout est marqué : c'est le geste « vider le badge ».
  Future<void> toutMarquerLu() => _api.post('/notifications/lues');
}
