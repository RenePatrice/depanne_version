import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Signaler un problème sur une intervention (§8.1, réclamation).
///
/// Le back-office traite les litiges depuis B5 ; c'est la porte côté client.
/// Ouvrir une réclamation **gèle le paiement du technicien** le temps de
/// l'examen — l'écran le dit avant l'envoi, parce que c'est ce qui donne du
/// poids au geste et ce qui le rend sérieux.
///
/// Le client ne chiffre rien. Il décrit ; le support décide et chiffre, au vu
/// du dossier. La fenêtre, l'unicité et la légitimité sont tenues par le
/// serveur, et son refus s'affiche tel quel.
class ReclamationScreen extends ConsumerStatefulWidget {
  const ReclamationScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<ReclamationScreen> createState() => _ReclamationScreenState();
}

class _ReclamationScreenState extends ConsumerState<ReclamationScreen> {
  /// La borne du serveur : une description plus courte ne laisserait au
  /// support rien à examiner.
  static const int _minimum = 20;

  final TextEditingController _description = TextEditingController();

  String? _motif;
  bool _enCours = false;
  ApiException? _erreur;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  bool get _pret =>
      _motif != null && _description.text.trim().length >= _minimum;

  Future<void> _envoyer() async {
    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await ref
          .read(clientRepositoryProvider)
          .reclamer(
            widget.ticketId,
            motif: _motif!,
            description: _description.text.trim(),
          );

      ref
        ..invalidate(reclamationsProvider)
        ..invalidate(ticketProvider(widget.ticketId));

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Réclamation envoyée. Le paiement du technicien reste bloqué '
            'le temps de l\'examen.',
          ),
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<DossierReclamations> dossier = ref.watch(
      reclamationsProvider,
    );
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Signaler un problème')),
      body: dossier.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(reclamationsProvider),
          ),
        ),
        data: (DossierReclamations d) => ListView(
          padding: const EdgeInsets.all(DmSpace.x6),
          children: <Widget>[
            Text('Que s\'est-il passé ?', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x2),
            Text(
              'Le support examine chaque réclamation et te répond ici. '
              'Pendant l\'examen, le paiement du technicien est bloqué.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: DmSpace.x6),
            RadioGroup<String>(
              groupValue: _motif,
              onChanged: (String? choisi) => setState(() => _motif = choisi),
              child: Column(
                children: <Widget>[
                  for (final MotifReclamation m in d.motifs)
                    RadioListTile<String>(
                      value: m.code,
                      title: Text(m.libelle),
                      contentPadding: EdgeInsets.zero,
                    ),
                ],
              ),
            ),
            const SizedBox(height: DmSpace.x4),
            TextField(
              controller: _description,
              minLines: 4,
              maxLines: 8,
              maxLength: 2000,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: 'Décris le problème',
                hintText:
                    'Ce qui ne va pas, depuis quand, ce que le technicien '
                    'a fait.',
                helperText: 'Au moins $_minimum caractères.',
                errorText:
                    _erreur?.pourChamp('description') ??
                    _erreur?.pourChamp('motif'),
              ),
            ),
            if (_erreur != null && _erreur!.erreursChamps.isEmpty) ...<Widget>[
              const SizedBox(height: DmSpace.x3),
              DmErreur(message: _erreur!.message),
            ],
            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Envoyer la réclamation',
              enCours: _enCours,
              onPresse: _pret ? _envoyer : null,
            ),
          ],
        ),
      ),
    );
  }
}

/// Mes réclamations et leur issue.
///
/// La décision du support est montrée avec sa motivation : un « résolu » sans
/// explication laisserait le client deviner, et un client qui devine finit par
/// contourner la plateforme.
class MesReclamationsScreen extends ConsumerWidget {
  const MesReclamationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<DossierReclamations> dossier = ref.watch(
      reclamationsProvider,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Mes réclamations')),
      body: dossier.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object e, _) => Padding(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: DmErreur(
            message: messageErreur(e),
            onReessayer: () => ref.invalidate(reclamationsProvider),
          ),
        ),
        data: (DossierReclamations d) => d.reclamations.isEmpty
            ? const DmVide(
                icone: Icons.verified_outlined,
                titre: 'Aucune réclamation',
                texte:
                    'Un problème après une intervention ? Signale-le depuis '
                    'le suivi de la demande.',
              )
            : RefreshIndicator(
                onRefresh: () async => ref.invalidate(reclamationsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.all(DmSpace.x4),
                  itemCount: d.reclamations.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: DmSpace.x3),
                  itemBuilder: (_, int i) =>
                      _CarteReclamation(reclamation: d.reclamations[i]),
                ),
              ),
      ),
    );
  }
}

class _CarteReclamation extends StatelessWidget {
  const _CarteReclamation({required this.reclamation});

  final Reclamation reclamation;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final DecisionReclamation? decision = reclamation.decision;

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  reclamation.motifLibelle,
                  style: theme.textTheme.titleSmall,
                ),
              ),
              Chip(
                label: Text(reclamation.statutLibelle),
                visualDensity: VisualDensity.compact,
              ),
            ],
          ),
          Text(
            '${reclamation.reference} · demande ${reclamation.ticket}',
            style: theme.textTheme.labelSmall,
          ),
          if (decision != null) ...<Widget>[
            const Divider(height: DmSpace.x6),
            Text(decision.libelle, style: theme.textTheme.titleSmall),
            if (decision.remboursementGnf > 0)
              Text(
                'Remboursement accordé : '
                '${formaterGnf(decision.remboursementGnf)}',
                style: theme.textTheme.bodyMedium,
              ),
            if (decision.motivation != null) ...<Widget>[
              const SizedBox(height: DmSpace.x1),
              Text(decision.motivation!, style: theme.textTheme.bodySmall),
            ],
          ] else if (reclamation.enCours) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            Text(
              'En cours d\'examen par le support.',
              style: theme.textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}
