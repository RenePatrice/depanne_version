import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Le supplément proposé par le technicien, et la décision du client (§8.1).
///
/// Le nouveau total est affiché **avant** les deux boutons. C'est la règle qui
/// rend le supplément acceptable : le client voit ce qu'il engage au moment où
/// il l'engage, pas sur la facture.
///
/// Refuser n'annule pas l'intervention : le technicien est sur place et fera ce
/// qui était prévu au départ. Le dire explicitement évite qu'un client accepte
/// par crainte de se retrouver sans rien.
class DiagnosticFeuille extends ConsumerStatefulWidget {
  const DiagnosticFeuille({required this.ticketId, super.key});

  final int ticketId;

  static Future<void> ouvrir(BuildContext context, int ticketId) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (BuildContext context) =>
            DiagnosticFeuille(ticketId: ticketId),
      );

  @override
  ConsumerState<DiagnosticFeuille> createState() => _DiagnosticFeuilleState();
}

class _DiagnosticFeuilleState extends ConsumerState<DiagnosticFeuille> {
  bool _enCours = false;

  Future<void> _repondre(bool accepte) async {
    if (_enCours) return;

    setState(() => _enCours = true);

    try {
      await ref
          .read(clientRepositoryProvider)
          .repondreAuSupplement(widget.ticketId, accepte: accepte);

      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(supplementProvider(widget.ticketId));

      if (mounted) Navigator.of(context).pop();
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
    final AsyncValue<SupplementPropose> supplement = ref.watch(
      supplementProvider(widget.ticketId),
    );
    final ThemeData theme = Theme.of(context);

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          DmSpace.x6,
          0,
          DmSpace.x6,
          DmSpace.x6,
        ),
        child: supplement.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(DmSpace.x8),
            child: Center(child: CircularProgressIndicator()),
          ),
          error: (Object e, _) => DmErreur(
            message: messageErreur(e),
            onReessayer: () =>
                ref.invalidate(supplementProvider(widget.ticketId)),
          ),
          data: (SupplementPropose s) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text('Supplément proposé', style: theme.textTheme.titleLarge),
              const SizedBox(height: DmSpace.x4),

              Text(
                'Ce que le technicien a constaté',
                style: theme.textTheme.labelLarge,
              ),
              const SizedBox(height: DmSpace.x1),
              Text(s.diagnostic, style: theme.textTheme.bodyMedium),

              const SizedBox(height: DmSpace.x6),
              DmCarte(
                enfant: Column(
                  children: <Widget>[
                    for (final LigneDevis l in s.devis.lignes) ...<Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              l.libelle,
                              style: theme.textTheme.bodyMedium,
                            ),
                          ),
                          Text(
                            l.montantFormate,
                            style: theme.textTheme.bodyMedium,
                          ),
                        ],
                      ),
                      const SizedBox(height: DmSpace.x2),
                    ],
                    const Divider(),
                    const SizedBox(height: DmSpace.x2),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            'Nouveau total',
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        Text(
                          s.devis.totalFormate,
                          style: theme.textTheme.titleLarge?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: DmSpace.x6),
              DmBouton(
                libelle: 'J\'accepte le supplément',
                enCours: _enCours,
                onPresse: () => _repondre(true),
              ),
              const SizedBox(height: DmSpace.x3),
              DmBouton(
                libelle: 'Je refuse',
                secondaire: true,
                enCours: _enCours,
                onPresse: () => _repondre(false),
              ),
              const SizedBox(height: DmSpace.x3),
              Text(
                'Si tu refuses, le technicien fera ce qui était prévu au départ, '
                'au prix annoncé. L\'intervention n\'est pas annulée.',
                style: theme.textTheme.bodySmall,
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
