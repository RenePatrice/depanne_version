import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Onglet « Messages » : une conversation par intervention avec un technicien.
///
/// La messagerie n'est ouverte qu'entre l'acceptation et la clôture (§7) : les
/// conversations closes restent lisibles, mais ne permettent plus d'écrire.
class MessagesVue extends ConsumerWidget {
  const MessagesVue({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<Ticket>> tickets = ref.watch(ticketsProvider(false));

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async => ref.invalidate(ticketsProvider(false)),
      child: ListView(
        padding: EdgeInsets.fromLTRB(
          18,
          MediaQuery.of(context).padding.top + 10,
          18,
          PcTabBar.espaceReserve,
        ),
        children: <Widget>[
          Text('Messages', style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 4),
          Text(
            'Vos échanges avec les techniciens, numéros masqués.',
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 12.5,
            ),
          ),
          const SizedBox(height: 18),
          tickets.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, _) => PcErreur(
              message: 'Les conversations n’ont pas pu être chargées.',
              onReessayer: () => ref.invalidate(ticketsProvider(false)),
            ),
            data: (List<Ticket> liste) {
              final List<Ticket> conversations =
                  liste.where((Ticket t) => t.technicien != null).toList()
                    ..sort(
                      (Ticket a, Ticket b) =>
                          (b.etatActif ? 1 : 0).compareTo(a.etatActif ? 1 : 0),
                    );

              if (conversations.isEmpty) {
                return PcCard(
                  padding: const EdgeInsets.all(22),
                  child: Column(
                    children: <Widget>[
                      PcIco(Icons.forum_outlined, couleur: pc.accent),
                      const SizedBox(height: 12),
                      Text(
                        'Aucune conversation pour l’instant',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Dès qu’un technicien accepte votre demande, vous '
                        'pouvez lui écrire ici.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: pc.muted,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                );
              }

              return PcCard(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 4,
                ),
                child: Column(
                  children: <Widget>[
                    for (int i = 0; i < conversations.length; i++) ...<Widget>[
                      _Conversation(ticket: conversations[i]),
                      if (i < conversations.length - 1) Divider(color: pc.line),
                    ],
                  ],
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _Conversation extends StatelessWidget {
  const _Conversation({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final String nom = ticket.technicien!.nomComplet;

    return InkWell(
      onTap: () => context.push('/client/chat/${ticket.id}'),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: <Widget>[
            PcAvatar(nom: nom, image: ticket.technicien!.avatarUrl),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(nom, style: Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 2),
                  Text(
                    '${ticket.prestation?.nom ?? 'Intervention'} · ${ticket.reference}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w500,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
            PcPill(
              ticket.etatActif ? 'Ouverte' : 'Fermée',
              couleur: ticket.etatActif ? PcColors.tech : pc.muted,
            ),
          ],
        ),
      ),
    );
  }
}
