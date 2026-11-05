import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Choix du technicien — place de marché (#2).
///
/// Après publication, personne n'est sollicité automatiquement : le client voit
/// les techniciens disponibles, chacun avec **son prix déjà ferme**, sa note et
/// sa distance, et en choisit un. Le prix ne se négocie pas et ne se saisit pas
/// ici — il vient du serveur.
///
/// Choisir ouvre au technicien une fenêtre de 45 secondes pour confirmer. S'il
/// refuse ou ne répond pas, la demande revient en sélection et le client en
/// choisit un autre : c'est le suivi qui le ramènera ici.
class ChoixTechnicienScreen extends ConsumerWidget {
  const ChoixTechnicienScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<TechnicienDisponible>> techniciens = ref.watch(
      techniciensDisponiblesProvider(ticketId),
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Choisis ton technicien')),
      body: RefreshIndicator(
        onRefresh: () async =>
            ref.invalidate(techniciensDisponiblesProvider(ticketId)),
        child: techniciens.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (Object e, StackTrace _) => ListView(
            children: <Widget>[
              const SizedBox(height: 120),
              DmErreur(
                message: messageErreur(e),
                onReessayer: () =>
                    ref.invalidate(techniciensDisponiblesProvider(ticketId)),
              ),
            ],
          ),
          data: (List<TechnicienDisponible> liste) => liste.isEmpty
              ? ListView(
                  children: const <Widget>[
                    SizedBox(height: 80),
                    DmVide(
                      icone: Icons.person_search_outlined,
                      titre: 'Aucun technicien disponible',
                      texte:
                          'Personne n\'est en ligne près de chez toi pour le '
                          'moment. Réessaie dans quelques minutes en tirant '
                          'vers le bas.',
                    ),
                  ],
                )
              : ListView(
                  padding: const EdgeInsets.all(DmSpace.x4),
                  children: <Widget>[
                    Text(
                      'Les plus proches et les mieux notés d\'abord. À toi de '
                      'choisir — le prix affiché est ferme.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: DmSpace.x3),
                    for (final TechnicienDisponible t in liste)
                      _CarteTechnicien(
                        technicien: t,
                        onChoisir: () => _choisir(context, ref, t),
                      ),
                  ],
                ),
        ),
      ),
    );
  }

  Future<void> _choisir(
    BuildContext context,
    WidgetRef ref,
    TechnicienDisponible technicien,
  ) async {
    try {
      await ref.read(clientRepositoryProvider).choisir(ticketId, technicien.id);
      ref.invalidate(ticketProvider(ticketId));

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${technicien.nom} est sollicité. Il a quelques secondes pour '
              'confirmer.',
            ),
          ),
        );
        context.pop();
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }
}

class _CarteTechnicien extends StatelessWidget {
  const _CarteTechnicien({required this.technicien, required this.onChoisir});

  final TechnicienDisponible technicien;
  final VoidCallback onChoisir;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x3),
      child: DmCarte(
        enfant: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                CircleAvatar(
                  radius: 22,
                  backgroundColor: theme.colorScheme.primary.withValues(
                    alpha: 0.12,
                  ),
                  child: Text(
                    technicien.nom.isEmpty ? '?' : technicien.nom[0],
                    style: theme.textTheme.titleMedium,
                  ),
                ),
                const SizedBox(width: DmSpace.x3),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(technicien.nom, style: theme.textTheme.titleMedium),
                      const SizedBox(height: 2),
                      _Note(technicien: technicien),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: <Widget>[
                    Text(
                      technicien.prixFormate,
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: theme.colorScheme.primary,
                      ),
                    ),
                    Text('prix ferme', style: theme.textTheme.bodySmall),
                  ],
                ),
              ],
            ),
            const SizedBox(height: DmSpace.x3),
            Row(
              children: <Widget>[
                Icon(
                  Icons.near_me_outlined,
                  size: 16,
                  color: DmColors.neutral500,
                ),
                const SizedBox(width: 4),
                Text(
                  technicien.distanceEstimee
                      ? '~${technicien.distanceKm} km'
                      : '${technicien.distanceKm} km',
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(width: DmSpace.x4),
                Icon(
                  Icons.schedule_outlined,
                  size: 16,
                  color: DmColors.neutral500,
                ),
                const SizedBox(width: 4),
                Text(
                  '~${technicien.etaMin} min',
                  style: theme.textTheme.bodySmall,
                ),
              ],
            ),
            const SizedBox(height: DmSpace.x3),
            DmBouton(libelle: 'Choisir', onPresse: onChoisir),
          ],
        ),
      ),
    );
  }
}

class _Note extends StatelessWidget {
  const _Note({required this.technicien});

  final TechnicienDisponible technicien;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    // Un nouveau venu n'a pas de note réelle : le dire franchement vaut mieux
    // qu'afficher une note neutre qui passerait pour un avis.
    if (technicien.nouveau) {
      return Text(
        'Nouveau sur Parema Connect',
        style: theme.textTheme.bodySmall?.copyWith(color: DmColors.neutral500),
      );
    }

    return Row(
      children: <Widget>[
        const Icon(Icons.star, size: 15, color: DmColors.warning),
        const SizedBox(width: 3),
        Text(
          technicien.note.toStringAsFixed(1),
          style: theme.textTheme.bodySmall,
        ),
        const SizedBox(width: 6),
        Text(
          '${technicien.interventions} intervention'
          '${technicien.interventions > 1 ? 's' : ''}',
          style: theme.textTheme.bodySmall?.copyWith(
            color: DmColors.neutral500,
          ),
        ),
      ],
    );
  }
}
