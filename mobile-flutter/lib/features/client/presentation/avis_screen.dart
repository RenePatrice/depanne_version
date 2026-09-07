import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/client_providers.dart';

/// Noter l'intervention (§7.2).
///
/// L'avis pèse 30 % du score de matching : il décide de qui recevra les courses
/// suivantes. On le prend donc au sérieux — mais on ne l'impose pas, et la note
/// seule suffit à envoyer. Exiger un commentaire ferait chuter le taux de
/// réponse, et un avis manquant vaut moins qu'un avis sans texte.
///
/// Les étiquettes sont exactement celles acceptées par l'API
/// (`SubmitReview::ETIQUETTES`) : en proposer une de plus ferait rejeter tout
/// l'avis pour un mot que le client n'a pas écrit.
class AvisScreen extends ConsumerStatefulWidget {
  const AvisScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<AvisScreen> createState() => _AvisScreenState();
}

class _AvisScreenState extends ConsumerState<AvisScreen> {
  /// Les positives d'abord, et séparées : une liste qui mélange « Ponctuel » et
  /// « En retard » invite à cocher au hasard.
  static const Map<String, String> _positives = <String, String>{
    'ponctuel': 'Ponctuel',
    'travail_soigne': 'Travail soigné',
    'bon_conseil': 'De bon conseil',
    'materiel_complet': 'Bien équipé',
    'prix_respecte': 'Prix respecté',
    'poli': 'Poli',
    'explique_bien': 'Explique bien',
    'a_nettoye': 'A nettoyé',
  };

  static const Map<String, String> _negatives = <String, String>{
    'en_retard': 'En retard',
    'travail_a_refaire': 'Travail à refaire',
    'peu_communicant': 'Peu communicant',
  };

  /// L'API en accepte cinq au plus. Au-delà, l'avis ne dit plus rien : tout
  /// cocher revient à ne rien cocher.
  static const int _maximumEtiquettes = 5;

  final TextEditingController _commentaire = TextEditingController();
  final Set<String> _choisies = <String>{};

  int _note = 0;
  bool _enCours = false;
  ApiException? _erreur;

  @override
  void dispose() {
    _commentaire.dispose();
    super.dispose();
  }

  void _basculer(String cle, bool choisi) {
    setState(() {
      if (!choisi) {
        _choisies.remove(cle);
      } else if (_choisies.length < _maximumEtiquettes) {
        _choisies.add(cle);
      }
    });
  }

  Future<void> _envoyer() async {
    if (_note == 0 || _enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await ref
          .read(clientRepositoryProvider)
          .noter(
            widget.ticketId,
            note: _note,
            etiquettes: _choisies.toList(),
            commentaire: _commentaire.text.trim(),
          );

      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(ticketsProvider);

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Merci. Ton avis aide les prochains clients.'),
        ),
      );

      if (context.canPop()) {
        context.pop();
      } else {
        context.go('/accueil');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Ton avis')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(DmSpace.x6),
          children: <Widget>[
            Text(
              'Comment s\'est passée l\'intervention ?',
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: DmSpace.x6),

            _Etoiles(
              note: _note,
              onNoter: (int n) => setState(() => _note = n),
            ),

            if (_erreur != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              DmErreur(message: _erreur!.message),
            ],

            const SizedBox(height: DmSpace.x6),
            Text(
              'Ce qui s\'est bien passé',
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: DmSpace.x3),
            _Etiquettes(
              libelles: _positives,
              choisies: _choisies,
              onBasculer: _basculer,
            ),

            const SizedBox(height: DmSpace.x6),
            Text('Ce qui a manqué', style: theme.textTheme.titleMedium),
            const SizedBox(height: DmSpace.x3),
            _Etiquettes(
              libelles: _negatives,
              choisies: _choisies,
              onBasculer: _basculer,
            ),

            const SizedBox(height: DmSpace.x6),
            DmChamp(
              libelle: 'Un mot de plus (facultatif)',
              indication: 'Ce que tu retiens de l\'intervention…',
              controleur: _commentaire,
            ),

            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Envoyer mon avis',
              enCours: _enCours,
              // La note est le minimum : sans elle l'avis n'a pas de sens, et
              // l'API la refuserait. Le bouton le dit avant l'aller-retour.
              onPresse: _note == 0 ? null : _envoyer,
            ),
            const SizedBox(height: DmSpace.x3),
            Text(
              'Seul ton prénom apparaîtra à côté de ton avis.',
              style: theme.textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: DmSpace.x8),
          ],
        ),
      ),
    );
  }
}

class _Etoiles extends StatelessWidget {
  const _Etoiles({required this.note, required this.onNoter});

  final int note;
  final ValueChanged<int> onNoter;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        for (int i = 1; i <= 5; i++)
          IconButton(
            iconSize: 40,
            // Le libellé sonore dit la valeur, pas « étoile » : un lecteur
            // d'écran doit pouvoir noter aussi bien qu'un œil.
            tooltip: '$i sur 5',
            onPressed: () => onNoter(i),
            icon: Icon(
              i <= note ? Icons.star : Icons.star_border,
              color: i <= note ? DmColors.warning : theme.dividerColor,
            ),
          ),
      ],
    );
  }
}

class _Etiquettes extends StatelessWidget {
  const _Etiquettes({
    required this.libelles,
    required this.choisies,
    required this.onBasculer,
  });

  final Map<String, String> libelles;
  final Set<String> choisies;
  final void Function(String cle, bool choisi) onBasculer;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: DmSpace.x2,
    runSpacing: DmSpace.x2,
    children: <Widget>[
      for (final MapEntry<String, String> e in libelles.entries)
        FilterChip(
          label: Text(e.value),
          selected: choisies.contains(e.key),
          onSelected: (bool choisi) => onBasculer(e.key, choisi),
        ),
    ],
  );
}
