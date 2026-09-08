import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../client/application/client_providers.dart';
import '../../shared/data/modeles.dart';
import '../application/technicien_providers.dart';

/// Proposer un supplément après diagnostic (§8.1).
///
/// C'est le **seul** endroit de l'application où un montant part vers le
/// serveur, et il ne l'applique pas : le ticket passe en attente, le total ne
/// bouge que si le client accepte. Trois gardes le rendent acceptable, toutes
/// tenues par le serveur — le plafond au double de la prestation, le
/// diagnostic écrit d'au moins vingt caractères, et la décision du client.
///
/// L'écran les annonce mais ne les rejoue pas : deux jeux de règles qui
/// divergent, c'est celui de l'application qui finit par se tromper.
class ProposerDiagnosticFeuille extends ConsumerStatefulWidget {
  const ProposerDiagnosticFeuille({required this.ticket, super.key});

  final Ticket ticket;

  static Future<void> ouvrir(BuildContext context, Ticket ticket) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (BuildContext context) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: ProposerDiagnosticFeuille(ticket: ticket),
        ),
      );

  @override
  ConsumerState<ProposerDiagnosticFeuille> createState() =>
      _ProposerDiagnosticFeuilleState();
}

class _ProposerDiagnosticFeuilleState
    extends ConsumerState<ProposerDiagnosticFeuille> {
  /// Le serveur exige vingt caractères. On l'écrit ici pour pouvoir l'annoncer
  /// pendant la frappe — pas pour valider à sa place.
  static const int _minimumDiagnostic = 20;

  final TextEditingController _diagnostic = TextEditingController();
  final TextEditingController _montant = TextEditingController();

  bool _enCours = false;
  ApiException? _erreur;

  @override
  void initState() {
    super.initState();
    _diagnostic.addListener(_rafraichir);
    _montant.addListener(_rafraichir);
  }

  void _rafraichir() => setState(() {});

  @override
  void dispose() {
    _diagnostic
      ..removeListener(_rafraichir)
      ..dispose();
    _montant
      ..removeListener(_rafraichir)
      ..dispose();
    super.dispose();
  }

  int get _supplementGnf =>
      int.tryParse(_montant.text.replaceAll(' ', '')) ?? 0;

  int get _plafondGnf => widget.ticket.prix.prestationGnf * 2;

  bool get _estComplet =>
      _diagnostic.text.trim().length >= _minimumDiagnostic &&
      _supplementGnf > 0;

  Future<void> _proposer() async {
    if (!_estComplet || _enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await ref
          .read(technicienRepositoryProvider)
          .proposerSupplement(
            widget.ticket.id,
            diagnostic: _diagnostic.text.trim(),
            supplementGnf: _supplementGnf,
          );

      ref.invalidate(ticketProvider(widget.ticket.id));
      ref.invalidate(interventionsProvider);

      if (!mounted) return;

      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Proposition envoyée. Le client doit répondre.'),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final int manquants = _minimumDiagnostic - _diagnostic.text.trim().length;

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(
          DmSpace.x6,
          0,
          DmSpace.x6,
          DmSpace.x6,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text('Proposer un supplément', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x2),
            Text(
              'Le total ne changera que si le client accepte. Tant qu\'il n\'a '
              'pas répondu, l\'intervention est en pause.',
              style: theme.textTheme.bodySmall,
            ),

            const SizedBox(height: DmSpace.x6),
            DmChamp(
              libelle: 'Ce que tu as constaté',
              indication: 'Le siphon est fendu, il faut le remplacer…',
              controleur: _diagnostic,
              erreur: _diagnostic.text.isEmpty || manquants <= 0
                  ? null
                  : 'Encore $manquants caractère${manquants > 1 ? 's' : ''}',
            ),

            const SizedBox(height: DmSpace.x4),
            DmChamp(
              libelle: 'Supplément (GNF)',
              indication: '25000',
              controleur: _montant,
              clavier: TextInputType.number,
              erreur: _supplementGnf > _plafondGnf
                  ? 'Au-delà du double de la prestation, le serveur refusera.'
                  : null,
            ),
            const SizedBox(height: DmSpace.x2),
            Text(
              'Maximum ${formaterGnf(_plafondGnf)} — le double de la prestation.',
              style: theme.textTheme.bodySmall,
            ),

            if (_supplementGnf > 0) ...<Widget>[
              const SizedBox(height: DmSpace.x6),
              DmCarte(
                enfant: Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        'Nouveau total pour le client',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                    Text(
                      formaterGnf(widget.ticket.prix.totalGnf + _supplementGnf),
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: theme.colorScheme.primary,
                      ),
                    ),
                  ],
                ),
              ),
            ],

            if (_erreur != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              DmErreur(message: _erreur!.message),
            ],

            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Envoyer la proposition',
              enCours: _enCours,
              onPresse: _estComplet ? _proposer : null,
            ),
          ],
        ),
      ),
    );
  }
}
