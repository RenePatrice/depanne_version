import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Régler l'intervention — paiement tracé (ADR-0047).
///
/// Le client **choisit** : espèces, Orange Money ou MTN MoMo, payés
/// directement au technicien. Quel que soit le moyen, le paiement n'est réglé
/// qu'avec **les deux** confirmations, et chaque étape s'inscrit au journal du
/// serveur — c'est ce journal que la feuille affiche en bas.
///
/// Les boutons proposés viennent du serveur (`actions`) : l'application ne
/// déduit rien de l'état, pour ne jamais offrir un geste qui échouerait.
///
/// Le paiement dans l'application, avec séquestre, reste proposé en second
/// choix : c'est le webhook qui en décide (ADR-0032), d'où l'interrogation.
class PaiementFeuille extends ConsumerStatefulWidget {
  const PaiementFeuille({required this.ticketId, super.key});

  final int ticketId;

  static Future<void> ouvrir(BuildContext context, int ticketId) =>
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: context.pc.card,
        builder: (BuildContext ctx) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(ctx).viewInsets.bottom,
          ),
          child: PaiementFeuille(ticketId: ticketId),
        ),
      );

  @override
  ConsumerState<PaiementFeuille> createState() => _PaiementFeuilleState();
}

class _PaiementFeuilleState extends ConsumerState<PaiementFeuille> {
  final TextEditingController _refTransaction = TextEditingController();

  EtatPaiement? _etat;
  Object? _erreurChargement;
  String? _moyen;
  bool _enCours = false;
  bool _parApplication = false;

  @override
  void initState() {
    super.initState();
    _charger();
  }

  @override
  void dispose() {
    _refTransaction.dispose();
    super.dispose();
  }

  Future<void> _charger() async {
    setState(() => _erreurChargement = null);

    try {
      final EtatPaiement etat = await ref
          .read(clientRepositoryProvider)
          .etatPaiement(widget.ticketId);
      if (mounted) setState(() => _etat = etat);
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreurChargement = e);
    }
  }

  /// Exécute un geste, puis affiche l'état que le serveur renvoie.
  Future<void> _agir(
    Future<EtatPaiement> Function() geste, {
    required String succes,
  }) async {
    if (_enCours) return;

    setState(() => _enCours = true);

    try {
      final EtatPaiement etat = await geste();
      ref.invalidate(ticketProvider(widget.ticketId));
      if (!mounted) return;
      setState(() => _etat = etat);
      showPcToast(context, succes);
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  void _declarer() {
    final String? moyen = _moyen;
    if (moyen == null) return;

    final String refOperateur = _refTransaction.text.trim();

    _agir(
      () => ref
          .read(clientRepositoryProvider)
          .declarerPaiement(
            widget.ticketId,
            moyen: moyen,
            referenceTransaction: moyen == 'ESPECES' || refOperateur.isEmpty
                ? null
                : refOperateur,
          ),
      succes: _etat?.monRole == 'TECHNICIEN'
          ? 'Déclaré : le client doit le confirmer.'
          : 'Paiement déclaré : le technicien doit le confirmer.',
    );
  }

  Future<void> _contester() async {
    final String? motif = await _demanderMotif(context);
    if (motif == null) return;

    await _agir(
      () => ref
          .read(clientRepositoryProvider)
          .contesterPaiement(widget.ticketId, motif),
      succes: 'Contestation transmise au support.',
    );
  }

  @override
  Widget build(BuildContext context) {
    final EtatPaiement? etat = _etat;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Center(
            child: Container(
              width: 42,
              height: 5,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: context.pc.line,
                borderRadius: BorderRadius.circular(5),
              ),
            ),
          ),
          if (_erreurChargement != null)
            PcErreur(
              message: messageErreur(_erreurChargement!),
              onReessayer: _charger,
            )
          else if (etat == null)
            const Padding(
              padding: EdgeInsets.all(40),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_parApplication)
            _PaiementApplication(
              ticketId: widget.ticketId,
              onRetour: () => setState(() => _parApplication = false),
            )
          else
            ..._contenu(etat),
        ],
      ),
    );
  }

  List<Widget> _contenu(EtatPaiement etat) {
    final PcSkin pc = context.pc;
    final PaiementTrace? p = etat.paiement;
    final bool tech = etat.monRole == 'TECHNICIEN';

    return <Widget>[
      Text(
        tech ? 'Encaissement' : 'Régler l’intervention',
        style: Theme.of(context).textTheme.headlineSmall,
      ),
      const SizedBox(height: 4),
      Row(
        children: <Widget>[
          Text(
            tech ? 'À encaisser  ' : 'Montant dû  ',
            style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
          ),
          PcMontant(etat.totalGnf, taille: 20, couleur: pc.accent),
        ],
      ),
      const SizedBox(height: 18),

      if (p == null && etat.peutDeclarer) ..._choixMoyen(etat),

      if (p != null) _CartePaiement(paiement: p, technicien: tech),

      if (p != null && etat.peutConfirmer) ...<Widget>[
        const SizedBox(height: 14),
        PcButton(
          libelle: 'Je confirme ce paiement',
          icone: Icons.verified_rounded,
          chargement: _enCours,
          onPressed: () => _agir(
            () => ref
                .read(clientRepositoryProvider)
                .confirmerPaiement(widget.ticketId),
            succes: 'Paiement confirmé, merci !',
          ),
        ),
      ],
      if (p != null && etat.peutContester) ...<Widget>[
        const SizedBox(height: 10),
        PcButton(
          libelle: 'Ce n’est pas exact',
          variante: PcVariante.contour,
          petit: true,
          onPressed: _enCours ? null : _contester,
        ),
      ],
      if (p != null && etat.peutAnnuler) ...<Widget>[
        const SizedBox(height: 10),
        PcButton(
          libelle: 'Retirer ma déclaration',
          variante: PcVariante.doux,
          petit: true,
          onPressed: _enCours
              ? null
              : () => _agir(
                  () => ref
                      .read(clientRepositoryProvider)
                      .annulerPaiement(widget.ticketId),
                  succes: 'Déclaration retirée.',
                ),
        ),
      ],

      if (p != null && p.historique.isNotEmpty) ...<Widget>[
        const PcSection('Historique'),
        _Historique(etapes: p.historique),
      ],

      if (p == null && !etat.peutDeclarer)
        Text(
          'Le paiement s’ouvre une fois l’intervention terminée.',
          style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
        ),

      const SizedBox(height: 12),
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: Text(p?.regle ?? false ? 'Fermer' : 'Plus tard'),
      ),
    ];
  }

  List<Widget> _choixMoyen(EtatPaiement etat) {
    final PcSkin pc = context.pc;
    final MoyenPaiement? choisi = etat.moyens
        .where((MoyenPaiement m) => m.code == _moyen)
        .firstOrNull;
    final bool tech = etat.monRole == 'TECHNICIEN';

    return <Widget>[
      Text(
        tech
            ? 'Comment le client vous a-t-il payé ?'
            : 'Comment avez-vous payé le technicien ?',
        style: Theme.of(context).textTheme.titleMedium,
      ),
      const SizedBox(height: 10),
      for (final MoyenPaiement m in etat.moyens) ...<Widget>[
        _CarteMoyen(
          moyen: m,
          choisi: m.code == _moyen,
          onTap: () => setState(() => _moyen = m.code),
        ),
        const SizedBox(height: 10),
      ],
      AnimatedSize(
        duration: PcMotion.normal,
        curve: PcMotion.ease,
        child: choisi != null && !choisi.estEspeces
            ? Padding(
                padding: const EdgeInsets.only(top: 4, bottom: 10),
                child: PcField(
                  libelle: 'Référence de la transaction (facultatif)',
                  indice: 'Reçue par SMS de l’opérateur',
                  icone: Icons.tag_rounded,
                  controleur: _refTransaction,
                ),
              )
            : const SizedBox(width: double.infinity),
      ),
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: pc.teinte(pc.accent),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(Icons.shield_outlined, size: 18, color: pc.accent),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                tech
                    ? 'Le client confirme à son tour. La commission de la '
                          'plateforme vous restera due, suivie dans Gains.'
                    : 'Le technicien confirme à son tour. Un paiement tracé '
                          'ici garde votre garantie active ; en cas de '
                          'désaccord, le support tranche.',
                style: TextStyle(
                  color: pc.ink2,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                ),
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 14),
      PcButton(
        libelle: tech
            ? 'J’ai reçu ${etat.totalFormate}'
            : 'J’ai payé ${etat.totalFormate}',
        icone: Icons.check_rounded,
        chargement: _enCours,
        onPressed: _moyen == null ? null : _declarer,
      ),
      // Le paiement dans l'application, avec séquestre, ne se lance que du
      // côté du client : c'est lui qui paie.
      if (!tech) ...<Widget>[
        const SizedBox(height: 6),
        TextButton.icon(
          onPressed: () => setState(() => _parApplication = true),
          icon: const Icon(Icons.phone_android_rounded, size: 18),
          label: const Text('Payer plutôt dans l’application'),
        ),
      ],
    ];
  }
}

Future<String?> _demanderMotif(BuildContext context) {
  final TextEditingController motif = TextEditingController();

  return showPcSheet<String>(
    context,
    builder: (BuildContext ctx) => StatefulBuilder(
      builder: (BuildContext ctx, StateSetter setState) {
        final bool valide = motif.text.trim().length >= 10;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text(
              'Qu’est-ce qui ne va pas ?',
              style: Theme.of(ctx).textTheme.titleLarge,
            ),
            const SizedBox(height: 6),
            Text(
              'Le support reçoit votre explication et tranche. Dix caractères '
              'au minimum.',
              style: TextStyle(
                color: ctx.pc.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 14),
            PcField(
              libelle: 'Explication',
              controleur: motif,
              lignes: 3,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 14),
            PcButton(
              libelle: 'Envoyer au support',
              variante: PcVariante.corail,
              onPressed: valide
                  ? () => Navigator.of(ctx).pop(motif.text.trim())
                  : null,
            ),
          ],
        );
      },
    ),
  ).whenComplete(motif.dispose);
}

IconData _iconeMoyen(String code) => switch (code) {
  'ESPECES' => Icons.payments_rounded,
  'ORANGE_MONEY' => Icons.phone_iphone_rounded,
  'MTN_MOMO' => Icons.phone_android_rounded,
  _ => Icons.account_balance_wallet_outlined,
};

Color _couleurMoyen(String code) => switch (code) {
  'ESPECES' => PcColors.tech,
  'ORANGE_MONEY' => const Color(0xFFFF7900),
  'MTN_MOMO' => const Color(0xFFE0A800),
  _ => PcColors.coral,
};

class _CarteMoyen extends StatelessWidget {
  const _CarteMoyen({
    required this.moyen,
    required this.choisi,
    required this.onTap,
  });

  final MoyenPaiement moyen;
  final bool choisi;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = _couleurMoyen(moyen.code);

    return AnimatedContainer(
      duration: PcMotion.normal,
      curve: PcMotion.ease,
      decoration: BoxDecoration(
        borderRadius: PcRadius.lgAll,
        border: Border.all(
          color: choisi ? pc.accent : pc.line,
          width: choisi ? 2 : 1,
        ),
      ),
      child: PcCard(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: <Widget>[
            PcIco(_iconeMoyen(moyen.code), couleur: c, taille: 40),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    moyen.libelle,
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  Text(
                    moyen.estEspeces
                        ? 'Remis en main propre'
                        : 'Transfert Mobile Money',
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w500,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              choisi
                  ? Icons.check_circle_rounded
                  : Icons.radio_button_unchecked_rounded,
              color: choisi ? pc.accent : pc.line,
            ),
          ],
        ),
      ),
    );
  }
}

class _CartePaiement extends StatelessWidget {
  const _CartePaiement({required this.paiement, required this.technicien});

  final PaiementTrace paiement;

  /// Vu du technicien : les phrases changent de sens (« le client déclare
  /// vous avoir payé » plutôt que « le technicien déclare avoir été payé »).
  final bool technicien;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final (IconData icone, Color couleur, String titre, String texte) = _resume(
      paiement,
      technicien,
    );

    return PcCard(
      bordure: couleur.withValues(alpha: 0.4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              PcIco(icone, couleur: couleur, taille: 42),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(titre, style: Theme.of(context).textTheme.titleSmall),
                    Text(
                      '${paiement.moyenLibelle} · ${paiement.statutLibelle}',
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              PcMontant(paiement.montantGnf, taille: 16),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            texte,
            style: TextStyle(
              color: pc.ink2,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          if (paiement.referenceTransaction != null) ...<Widget>[
            const SizedBox(height: 6),
            Text(
              'Réf. opérateur : ${paiement.referenceTransaction}',
              style: TextStyle(color: pc.muted, fontSize: 12),
            ),
          ],
          if (paiement.motifContestation != null) ...<Widget>[
            const SizedBox(height: 6),
            Text(
              '« ${paiement.motifContestation} »',
              style: TextStyle(
                color: pc.muted,
                fontStyle: FontStyle.italic,
                fontSize: 12.5,
              ),
            ),
          ],
        ],
      ),
    );
  }

  static (IconData, Color, String, String) _resume(
    PaiementTrace p,
    bool technicien,
  ) {
    if (p.regle) {
      return (
        Icons.verified_rounded,
        PcColors.tech,
        'Paiement réglé',
        technicien
            ? 'Les deux confirmations sont enregistrées.'
            : 'Les deux confirmations sont enregistrées. Votre garantie est '
                  'active.',
      );
    }
    if (p.conteste) {
      return (
        Icons.gavel_rounded,
        PcColors.coral,
        'Paiement contesté',
        'Le support examine le désaccord et vous tiendra informé.',
      );
    }
    if (technicien) {
      return p.enAttenteDe == 'TECHNICIEN'
          ? (
              Icons.pending_actions_rounded,
              PcColors.coral,
              'Le client déclare vous avoir payé',
              'Confirmez seulement si vous avez bien reçu ce montant.',
            )
          : (
              Icons.hourglass_top_rounded,
              PcColors.coral,
              'En attente du client',
              'Vous avez déclaré ce paiement. Le client doit le confirmer.',
            );
    }
    if (p.enAttenteDe == 'CLIENT') {
      return (
        Icons.pending_actions_rounded,
        PcColors.coral,
        p.declarePar == 'TECHNICIEN'
            ? 'Le technicien déclare avoir été payé'
            : 'Votre confirmation est attendue',
        'Confirmez seulement si vous avez bien remis ce montant.',
      );
    }
    return (
      Icons.hourglass_top_rounded,
      PcColors.coral,
      'En attente du technicien',
      'Vous avez déclaré ce paiement. Le technicien doit confirmer l’avoir '
          'reçu.',
    );
  }
}

class _Historique extends StatelessWidget {
  const _Historique({required this.etapes});

  final List<EtapePaiement> etapes;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Column(
      children: <Widget>[
        for (int i = 0; i < etapes.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                SizedBox(
                  width: 18,
                  child: Column(
                    children: <Widget>[
                      Container(
                        width: 10,
                        height: 10,
                        margin: const EdgeInsets.only(top: 4),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: i == etapes.length - 1 ? pc.accent : pc.line,
                        ),
                      ),
                      if (i < etapes.length - 1)
                        Expanded(child: Container(width: 2, color: pc.line)),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          etapes[i].libelle,
                          style: TextStyle(
                            color: pc.ink,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          '${_acteur(etapes[i].acteur)}'
                          '${etapes[i].le == null ? '' : ' · ${_heure(etapes[i].le!)}'}',
                          style: TextStyle(color: pc.muted, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  static String _acteur(String a) => switch (a) {
    'CLIENT' => 'Vous',
    'TECHNICIEN' => 'Technicien',
    'ADMIN' || 'SUPPORT' => 'Support',
    _ => 'Système',
  };

  static String _heure(DateTime d) {
    String deux(int n) => n.toString().padLeft(2, '0');
    return '${deux(d.day)}/${deux(d.month)} à ${deux(d.hour)}h${deux(d.minute)}';
  }
}

/// Paiement Mobile Money **dans l'application**, avec séquestre (§8.4).
///
/// Ce n'est pas l'application qui décide qu'un paiement a réussi — c'est le
/// webhook, côté serveur (ADR-0032). L'écran interroge donc l'état plutôt que
/// de conclure lui-même.
class _PaiementApplication extends ConsumerStatefulWidget {
  const _PaiementApplication({required this.ticketId, required this.onRetour});

  final int ticketId;
  final VoidCallback onRetour;

  @override
  ConsumerState<_PaiementApplication> createState() =>
      _PaiementApplicationState();
}

class _PaiementApplicationState extends ConsumerState<_PaiementApplication> {
  static const Duration _cadence = Duration(seconds: 3);

  /// Au-delà, on cesse d'interroger : le webhook fera son travail et l'état du
  /// ticket le reflétera au prochain affichage.
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
    final PcSkin pc = context.pc;
    final Paiement? paiement = _paiement;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            PcBackButton(onTap: paiement == null ? widget.onRetour : null),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                'Payer dans l’application',
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (_erreur != null) ...<Widget>[
          PcErreur(
            message: _erreur!.message,
            onReessayer: _erreur!.reseau ? _lancer : null,
          ),
          const SizedBox(height: 14),
        ],
        if (paiement == null) ...<Widget>[
          Text(
            'Orange Money, sur le numéro de votre compte. L’argent reste '
            'bloqué jusqu’à ce que vous validiez l’intervention, ou '
            'automatiquement sous 24 heures.',
            style: TextStyle(color: pc.ink2, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Lancer le paiement',
            icone: Icons.lock_outline_rounded,
            chargement: _enCours,
            onPressed: _lancer,
          ),
        ] else
          _Attente(paiement: paiement),
        const SizedBox(height: 10),
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(paiement?.capture ?? false ? 'Fermer' : 'Plus tard'),
        ),
      ],
    );
  }
}

class _Attente extends StatelessWidget {
  const _Attente({required this.paiement});

  final Paiement paiement;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final TextStyle doux = TextStyle(
      color: pc.muted,
      fontWeight: FontWeight.w600,
      fontSize: 13,
    );

    if (paiement.capture) {
      return Column(
        children: <Widget>[
          const PcIco(Icons.check_circle_rounded, couleur: PcColors.tech),
          const SizedBox(height: 12),
          Text('Paiement reçu', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 6),
          Text(
            'Votre argent est bloqué jusqu’à ce que vous validiez '
            'l’intervention, ou automatiquement sous 24 heures.',
            style: doux,
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    if (paiement.echoue) {
      return Column(
        children: <Widget>[
          const PcIco(Icons.error_outline_rounded, couleur: PcColors.coral),
          const SizedBox(height: 12),
          Text(
            'Paiement refusé',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 6),
          Text(
            paiement.motifEchec ??
                'Le paiement n’a pas abouti. Vous pouvez réessayer.',
            style: doux,
            textAlign: TextAlign.center,
          ),
        ],
      );
    }

    return Column(
      children: <Widget>[
        const CircularProgressIndicator(),
        const SizedBox(height: 14),
        Text(
          'En attente de confirmation',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 6),
        Text(
          paiement.instruction ??
              'Confirmez le paiement depuis votre téléphone.',
          style: doux,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 10),
        Text('Référence : ${paiement.reference}', style: doux),
      ],
    );
  }
}
