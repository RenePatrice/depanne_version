import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/app_mode.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/notifications_providers.dart';
import '../data/notifications_repository.dart';

/// Où mène une notification.
///
/// Le type dit à qui elle s'adresse mieux que le mode courant : un technicien
/// qui a aussi la casquette client peut lire, depuis le mode technicien, la
/// notification d'une demande qu'il a publiée — et doit arriver sur le suivi
/// client, pas sur un écran d'intervention qui ne le concerne pas. Le mode ne
/// tranche que les types partagés.
String? destinationDe(NotificationApp notification, AppMode mode) {
  const Set<String> pourLeClient = <String>{
    'TICKET_PUBLIE',
    'TICKET_ACCEPTE',
    'TECHNICIEN_EN_ROUTE',
    'TECHNICIEN_ARRIVE',
    'INTERVENTION_TERMINEE',
    'DIAGNOSTIC_PROPOSE',
    'AUCUN_TECHNICIEN',
    'TECHNICIEN_REMPLACE',
  };
  const Set<String> pourLeTechnicien = <String>{'DIAGNOSTIC_TRANCHE'};

  // Une sollicitation se traite sur l'onglet Demandes ; une demande expirée,
  // attribuée à un autre ou retirée n'est plus la sienne, et l'ouvrir
  // renverrait « introuvable ».
  const Set<String> sansTicket = <String>{
    'NOUVELLE_DEMANDE',
    'DEMANDE_EXPIREE',
    'DEMANDE_ATTRIBUEE',
    'DEMANDE_RETIREE',
  };

  if (notification.type == 'NOUVELLE_DEMANDE') return '/accueil';

  final int? id = notification.ticketId;
  if (id == null || sansTicket.contains(notification.type)) return null;

  if (pourLeClient.contains(notification.type)) return '/client/suivi/$id';
  if (pourLeTechnicien.contains(notification.type)) {
    return '/technicien/intervention/$id';
  }

  return mode == AppMode.client
      ? '/client/suivi/$id'
      : '/technicien/intervention/$id';
}

/// Centre de notifications (§7.4). L'ouvrir vide le badge.
class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() =>
      _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _marquerLues());
  }

  /// Un échec est silencieux : le badge restera allumé, et la prochaine
  /// ouverture réessaiera. Afficher une erreur pour un accusé de lecture
  /// serait disproportionné.
  Future<void> _marquerLues() async {
    try {
      await ref.read(notificationsRepositoryProvider).toutMarquerLu();
    } on ApiException {
      return;
    }

    if (mounted) ref.invalidate(notificationsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<CentreNotifications> centre = ref.watch(
      notificationsProvider,
    );
    final AppMode mode = ref.watch(modeProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: centre.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(notificationsProvider),
          ),
        ),
        data: (CentreNotifications c) => c.liste.isEmpty
            ? const DmVide(
                icone: Icons.notifications_none,
                titre: 'Rien de nouveau',
                texte: 'Les étapes de tes interventions s\'afficheront ici.',
              )
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(notificationsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.all(DmSpace.x4),
                  itemCount: c.liste.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: DmSpace.x2),
                  itemBuilder: (BuildContext context, int i) {
                    final NotificationApp n = c.liste[i];
                    final String? vers = destinationDe(n, mode);

                    return DmCarte(
                      onTape: vers == null ? null : () => context.push(vers),
                      enfant: _Ligne(notification: n),
                    );
                  },
                ),
              ),
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  const _Ligne({required this.notification});

  final NotificationApp notification;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.only(top: DmSpace.x1),
          child: Icon(
            notification.lue ? Icons.circle_outlined : Icons.circle,
            size: 10,
            color: notification.lue
                ? theme.colorScheme.outline
                : theme.colorScheme.primary,
          ),
        ),
        const SizedBox(width: DmSpace.x3),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                notification.titre ?? 'Notification',
                style: theme.textTheme.titleSmall,
              ),
              if (notification.corps != null) ...<Widget>[
                const SizedBox(height: DmSpace.x1),
                Text(notification.corps!, style: theme.textTheme.bodySmall),
              ],
              if (notification.recueLe != null) ...<Widget>[
                const SizedBox(height: DmSpace.x1),
                Text(
                  _quand(notification.recueLe!),
                  style: theme.textTheme.labelSmall,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  static String _quand(DateTime moment) {
    final Duration ecart = DateTime.now().difference(moment);

    if (ecart.inMinutes < 1) return 'À l\'instant';
    if (ecart.inHours < 1) return 'Il y a ${ecart.inMinutes} min';
    if (ecart.inDays < 1) return 'Il y a ${ecart.inHours} h';

    return 'Le ${moment.day.toString().padLeft(2, '0')}/'
        '${moment.month.toString().padLeft(2, '0')}';
  }
}

/// Cloche de la barre d'accueil, avec le nombre de non lues.
class ClocheNotifications extends ConsumerWidget {
  const ClocheNotifications({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final int nonLues = ref.watch(notificationsProvider).value?.nonLues ?? 0;

    return IconButton(
      tooltip: nonLues == 0
          ? 'Notifications'
          : '$nonLues notification${nonLues > 1 ? 's' : ''} non lue${nonLues > 1 ? 's' : ''}',
      onPressed: () => context.push('/notifications'),
      icon: Badge(
        isLabelVisible: nonLues > 0,
        label: Text(nonLues > 99 ? '99+' : '$nonLues'),
        child: const Icon(Icons.notifications_outlined),
      ),
    );
  }
}
