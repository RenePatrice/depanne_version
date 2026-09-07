import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/client_providers.dart';
import '../data/modeles.dart';

/// Paiement Mobile Money (§8.4).
///
/// Deux temps, sur une seule feuille : on lance l'encaissement, puis on attend
/// la confirmation de l'opérateur. Ce n'est pas l'application qui décide qu'un
/// paiement a réussi — c'est le webhook, côté serveur (ADR-0032). L'écran
/// interroge donc l'état plutôt que de conclure lui-même.
///
/// Rien ne garantit l'ordre : la notification de l'opérateur peut arriver avant
/// ou après que le client soit revenu dans l'application. L'interrogation gère
/// les deux.
class PaiementFeuille extends ConsumerStatefulWidget {
  const PaiementFeuille({required this.ticketId, super.key});

  final int ticketId;

  static Future<void> ouvrir(BuildContext context, int ticketId) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        isDismissible: false,
        showDragHandle: true,
        builder: (BuildContext context) => PaiementFeuille(ticketId: ticketId),
      );

  @override
  ConsumerState<PaiementFeuille> createState() => _PaiementFeuilleState();
}

class _PaiementFeuilleState extends ConsumerState<PaiementFeuille> {
  static const Duration _cadence = Duration(seconds: 3);

  /// Au-delà, on cesse d'interroger et on laisse le client fermer.
  ///
  /// Le paiement n'est pas perdu pour autant : le webhook fera son travail,
  /// et l'état du ticket le reflétera au prochain affichage. Interroger
  /// indéfiniment coûterait du forfait pour rien.
  static const Duration _patience = Duration(minutes: 3);

  Paiement? _paiement;
  Timer? _minuteur;
  DateTime? _debutAttente;
  ApiException? _erreur;
  bool _enCours = false;

  @override
  void dispose() {
    _minuteur?.cancel();
    super.dispose();
  }

  Future<void> _lancer() async {
    if (_enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      final Paiement paiement = await ref
          .read(clientRepositoryProvider)
          .lancerPaiement(widget.ticketId);

      if (!mounted) return;

      setState(() {
        _paiement = paiement;
        _debutAttente = DateTime.now();
      });

      _minuteur = Timer.periodic(_cadence, (_) => _verifier());
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  Future<void> _verifier() async {
    final Paiement? actuel = _paiement;

    if (actuel == null || !mounted) return;

    if (DateTime.now().difference(_debutAttente!) > _patience) {
      _minuteur?.cancel();
      return;
    }

    try {
      final Paiement frais = await ref
          .read(clientRepositoryProvider)
          .suivrePaiement(actuel.reference);

      if (!mounted) return;

      setState(() => _paiement = frais);

      if (frais.capture || frais.echoue) {
        _minuteur?.cancel();
        ref.invalidate(ticketProvider(widget.ticketId));
      }
    } on ApiException {
      // Une interrogation qui échoue n'est pas un échec de paiement : le
      // réseau a hoqueté, la suivante réessaiera. Afficher une erreur ici
      // ferait croire au client que son argent est perdu.
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Paiement? paiement = _paiement;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          DmSpace.x6,
          0,
          DmSpace.x6,
          DmSpace.x6,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text('Régler l\'intervention', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x4),

            if (_erreur != null) ...<Widget>[
              DmErreur(
                message: _erreur!.message,
                onReessayer: _erreur!.reseau ? _lancer : null,
              ),
              const SizedBox(height: DmSpace.x4),
            ],

            if (paiement == null) ...<Widget>[
              Text(
                'Le paiement se fait par Mobile Money, sur le numéro de ton compte. '
                'Ton argent reste bloqué jusqu\'à ce que tu valides l\'intervention.',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: DmSpace.x6),
              DmBouton(
                libelle: 'Lancer le paiement',
                enCours: _enCours,
                onPresse: _lancer,
              ),
            ] else
              _Attente(paiement: paiement),

            const SizedBox(height: DmSpace.x3),
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(paiement?.capture ?? false ? 'Fermer' : 'Plus tard'),
            ),
          ],
        ),
      ),
    );
  }
}

class _Attente extends StatelessWidget {
  const _Attente({required this.paiement});

  final Paiement paiement;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    if (paiement.capture) {
      return Column(
        children: <Widget>[
          const Icon(Icons.check_circle, size: 48, color: DmColors.success),
          const SizedBox(height: DmSpace.x3),
          Text('Paiement reçu', style: theme.textTheme.titleMedium),
          const SizedBox(height: DmSpace.x2),
          Text(
            'Ton argent est bloqué jusqu\'à ce que tu valides l\'intervention, '
            'ou automatiquement sous 24 heures.',
            style: theme.textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    if (paiement.echoue) {
      return Column(
        children: <Widget>[
          const Icon(Icons.error_outline, size: 48, color: DmColors.danger),
          const SizedBox(height: DmSpace.x3),
          Text('Paiement refusé', style: theme.textTheme.titleMedium),
          const SizedBox(height: DmSpace.x2),
          Text(
            paiement.motifEchec ??
                'Le paiement n\'a pas abouti. Tu peux réessayer.',
            style: theme.textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    return Column(
      children: <Widget>[
        const CircularProgressIndicator(),
        const SizedBox(height: DmSpace.x4),
        Text('En attente de confirmation', style: theme.textTheme.titleMedium),
        const SizedBox(height: DmSpace.x2),
        Text(
          paiement.instruction ?? 'Confirme le paiement depuis ton téléphone.',
          style: theme.textTheme.bodySmall,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: DmSpace.x3),
        Text(
          'Référence : ${paiement.reference}',
          style: theme.textTheme.labelSmall,
        ),
      ],
    );
  }
}
