import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../auth/application/session_controller.dart';
import '../application/client_providers.dart';
import '../data/modeles.dart';

/// Profil client : identité et carnet d'adresses.
///
/// Le carnet vit ici plutôt que dans le seul tunnel de commande : une adresse
/// se corrige au calme, pas au moment où l'on a une fuite d'eau.
class ProfilClientScreen extends ConsumerWidget {
  const ProfilClientScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Session session = ref.watch(sessionProvider);
    final AsyncValue<List<Adresse>> adresses = ref.watch(adressesProvider);
    final ThemeData theme = Theme.of(context);

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(adressesProvider),
      child: ListView(
        padding: const EdgeInsets.all(DmSpace.x4),
        children: <Widget>[
          DmCarte(
            enfant: Row(
              children: <Widget>[
                CircleAvatar(
                  radius: 26,
                  backgroundColor: theme.colorScheme.primary.withValues(
                    alpha: 0.12,
                  ),
                  child: Icon(
                    Icons.person_outline,
                    color: theme.colorScheme.primary,
                  ),
                ),
                const SizedBox(width: DmSpace.x4),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        session.utilisateur?.nomComplet ?? 'Mon compte',
                        style: theme.textTheme.titleMedium,
                      ),
                      Text(
                        session.utilisateur?.telephoneAffichage ?? '',
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.fromLTRB(
              DmSpace.x2,
              DmSpace.x6,
              DmSpace.x2,
              DmSpace.x3,
            ),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'Mes adresses',
                    style: theme.textTheme.titleLarge,
                  ),
                ),
                TextButton.icon(
                  onPressed: () => context.push('/client/adresse'),
                  icon: const Icon(Icons.add),
                  label: const Text('Ajouter'),
                ),
              ],
            ),
          ),

          adresses.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(DmSpace.x8),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => DmErreur(
              message: messageErreur(e),
              onReessayer: () => ref.invalidate(adressesProvider),
            ),
            data: (List<Adresse> liste) => liste.isEmpty
                ? DmVide(
                    icone: Icons.location_off_outlined,
                    titre: 'Carnet vide',
                    texte:
                        'Ajoute une adresse maintenant : le jour de la panne, '
                        'tu n\'auras plus qu\'à choisir.',
                    action: DmBouton(
                      libelle: 'Ajouter une adresse',
                      onPresse: () => context.push('/client/adresse'),
                    ),
                  )
                : Column(
                    children: <Widget>[
                      for (final Adresse a in liste) _LigneAdresse(adresse: a),
                    ],
                  ),
          ),

          const SizedBox(height: DmSpace.x8),
        ],
      ),
    );
  }
}

class _LigneAdresse extends ConsumerStatefulWidget {
  const _LigneAdresse({required this.adresse});

  final Adresse adresse;

  @override
  ConsumerState<_LigneAdresse> createState() => _LigneAdresseState();
}

class _LigneAdresseState extends ConsumerState<_LigneAdresse> {
  bool _enCours = false;

  Future<void> _supprimer() async {
    // Une adresse supprimée ne réécrit pas l'historique — le ticket en garde
    // un instantané (ADR-0013) — mais elle disparaît pour de bon du carnet,
    // et un carnet vidé par erreur se reconstitue à la main.
    final bool confirme =
        await showDialog<bool>(
          context: context,
          builder: (BuildContext context) => AlertDialog(
            title: const Text('Supprimer cette adresse ?'),
            content: Text(
              '« ${widget.adresse.libelle} » ne sera plus proposée lors de tes '
              'prochaines demandes. Tes interventions passées gardent l\'adresse '
              'où elles ont eu lieu.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Annuler'),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Supprimer'),
              ),
            ],
          ),
        ) ??
        false;

    if (!confirme || _enCours) return;

    setState(() => _enCours = true);

    try {
      await ref
          .read(clientRepositoryProvider)
          .supprimerAdresse(widget.adresse.id);

      ref.invalidate(adressesProvider);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Adresse a = widget.adresse;

    return Padding(
      padding: const EdgeInsets.only(bottom: DmSpace.x3),
      child: DmCarte(
        enfant: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Flexible(
                        child: Text(
                          a.libelle,
                          style: theme.textTheme.titleMedium,
                        ),
                      ),
                      if (a.parDefaut) ...<Widget>[
                        const SizedBox(width: DmSpace.x2),
                        Text(
                          'par défaut',
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: DmSpace.x1),
                  Text(a.adresse, style: theme.textTheme.bodySmall),
                  if (a.repere != null)
                    Text(a.repere!, style: theme.textTheme.bodySmall),
                  if (!a.estDesservie) ...<Widget>[
                    const SizedBox(height: DmSpace.x2),
                    Text(
                      'Hors de notre zone de couverture',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: DmColors.warning,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            IconButton(
              tooltip: 'Supprimer',
              onPressed: _enCours ? null : _supprimer,
              icon: _enCours
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.delete_outline),
            ),
          ],
        ),
      ),
    );
  }
}
