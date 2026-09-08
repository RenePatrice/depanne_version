import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';

/// Demander un retrait (§8.4).
///
/// Le montant vient du technicien : c'est son argent, il choisit combien il en
/// sort. Rien à voir avec un prix d'intervention — le garde-fou « aucun montant
/// ne remonte » porte sur ce que le **client** paie, pas sur ce que le
/// technicien retire de son propre solde.
///
/// Le serveur revérifie le retirable dans la transaction qui écrit le
/// mouvement : entre l'ouverture de cette feuille et l'envoi, un remboursement
/// de litige a pu passer.
class RetraitFeuille extends ConsumerStatefulWidget {
  const RetraitFeuille({required this.portefeuille, super.key});

  final Portefeuille portefeuille;

  static Future<void> ouvrir(BuildContext context, Portefeuille portefeuille) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (BuildContext context) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: RetraitFeuille(portefeuille: portefeuille),
        ),
      );

  @override
  ConsumerState<RetraitFeuille> createState() => _RetraitFeuilleState();
}

class _RetraitFeuilleState extends ConsumerState<RetraitFeuille> {
  /// Les deux opérateurs que l'API accepte (`PaymentMethod`). Orange Money est
  /// celui du pilote ; MTN suivra derrière la même interface.
  static const Map<String, String> _operateurs = <String, String>{
    'ORANGE_MONEY': 'Orange Money',
    'MTN_MOMO': 'MTN MoMo',
  };

  final TextEditingController _montant = TextEditingController();
  final TextEditingController _numero = TextEditingController();

  String _operateur = 'ORANGE_MONEY';
  bool _enCours = false;
  ApiException? _erreur;

  @override
  void initState() {
    super.initState();

    _montant.addListener(_rafraichir);
    _numero.addListener(_rafraichir);

    // Le numéro du compte, pré-rempli : c'est celui sur lequel neuf retraits
    // sur dix partiront. Il reste modifiable — un technicien peut se faire
    // verser sur un autre compte Mobile Money que celui de sa connexion.
    final String? telephone = ref.read(sessionProvider).utilisateur?.telephone;
    if (telephone != null) _numero.text = telephone;
  }

  void _rafraichir() => setState(() {});

  @override
  void dispose() {
    _montant
      ..removeListener(_rafraichir)
      ..dispose();
    _numero
      ..removeListener(_rafraichir)
      ..dispose();
    super.dispose();
  }

  int get _montantGnf => int.tryParse(_montant.text.replaceAll(' ', '')) ?? 0;

  String? get _probleme {
    if (_montantGnf <= 0) return null;

    if (_montantGnf < widget.portefeuille.retraitMinimumGnf) {
      return 'Minimum ${formaterGnf(widget.portefeuille.retraitMinimumGnf)}.';
    }

    if (_montantGnf > widget.portefeuille.retirableGnf) {
      return 'Tu peux retirer '
          '${formaterGnf(widget.portefeuille.retirableGnf)} au maximum.';
    }

    return null;
  }

  bool get _estComplet =>
      _montantGnf > 0 && _probleme == null && _numero.text.trim().length >= 8;

  Future<void> _demander() async {
    if (!_estComplet || _enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await ref
          .read(technicienRepositoryProvider)
          .demanderRetrait(
            montantGnf: _montantGnf,
            numero: _numero.text.trim(),
            operateur: _operateur,
          );

      ref.invalidate(portefeuilleProvider);
      ref.invalidate(retraitsProvider);

      if (!mounted) return;

      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Demande enregistrée. Le versement part après validation.',
          ),
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
            Text('Demander un retrait', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x2),
            Text(
              'Disponible : ${formaterGnf(widget.portefeuille.retirableGnf)}',
              style: theme.textTheme.bodyMedium,
            ),

            const SizedBox(height: DmSpace.x6),
            DmChamp(
              libelle: 'Montant (GNF)',
              indication: '${widget.portefeuille.retirableGnf}',
              controleur: _montant,
              clavier: TextInputType.number,
              erreur: _probleme,
            ),
            const SizedBox(height: DmSpace.x2),
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: () =>
                    _montant.text = '${widget.portefeuille.retirableGnf}',
                child: const Text('Tout retirer'),
              ),
            ),

            const SizedBox(height: DmSpace.x4),
            Text('Opérateur', style: theme.textTheme.labelLarge),
            const SizedBox(height: DmSpace.x2),
            Wrap(
              spacing: DmSpace.x2,
              children: <Widget>[
                for (final MapEntry<String, String> o in _operateurs.entries)
                  ChoiceChip(
                    label: Text(o.value),
                    selected: _operateur == o.key,
                    onSelected: (_) => setState(() => _operateur = o.key),
                  ),
              ],
            ),

            const SizedBox(height: DmSpace.x4),
            DmChamp(
              libelle: 'Numéro à créditer',
              controleur: _numero,
              clavier: TextInputType.phone,
            ),

            if (_erreur != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              DmErreur(message: _erreur!.message),
            ],

            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Envoyer la demande',
              enCours: _enCours,
              onPresse: _estComplet ? _demander : null,
            ),
            const SizedBox(height: DmSpace.x3),
            Text(
              // Le solde ne bouge qu'au versement effectif : le dire évite un
              // « où est passé mon argent ? » le lendemain de la demande.
              'Ton solde ne bougera qu\'au versement. Jusque-là, le montant est '
              'seulement réservé.',
              style: theme.textTheme.bodySmall,
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}
