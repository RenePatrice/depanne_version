import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/format/montant.dart';
import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';
import 'composants_technicien.dart';
import 'retrait_feuille.dart';

/// Onglet « Gains » : solde, commission due, retraits, mouvements (§7.3, §8.4).
///
/// Deux chiffres, pas un : le **solde** est ce que le technicien a gagné, le
/// **retirable** est ce qu'il peut sortir aujourd'hui. Les confondre ferait
/// déposer trois demandes de la totalité du solde, dont deux seraient rejetées.
class PortefeuilleScreen extends ConsumerWidget {
  const PortefeuilleScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final AsyncValue<Portefeuille> portefeuille = ref.watch(
      portefeuilleProvider,
    );
    final AsyncValue<List<Retrait>> retraits = ref.watch(retraitsProvider);

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async {
        ref.invalidate(portefeuilleProvider);
        ref.invalidate(retraitsProvider);
      },
      child: ListView(
        padding: margesOnglet(context),
        children: <Widget>[
          const EnteteTechnicien(titre: 'Gains'),
          const SizedBox(height: 16),
          ...portefeuille.when(
            loading: () => <Widget>[
              const Padding(
                padding: EdgeInsets.all(40),
                child: Center(child: CircularProgressIndicator()),
              ),
            ],
            error: (Object e, StackTrace _) => <Widget>[
              PcErreur(
                message: messageErreur(e),
                onReessayer: () => ref.invalidate(portefeuilleProvider),
              ),
            ],
            data: (Portefeuille p) => <Widget>[
              _Solde(portefeuille: p),
              if (p.commissionDueGnf > 0) ...<Widget>[
                const SizedBox(height: 12),
                _Commission(portefeuille: p),
              ],
              const PcSection('Mes retraits'),
              retraits.when(
                loading: () => const Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (Object e, StackTrace _) =>
                    PcErreur(message: messageErreur(e)),
                data: (List<Retrait> liste) => liste.isEmpty
                    ? _Vide('Aucun retrait demandé pour l’instant.')
                    : PcCard(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 4,
                        ),
                        child: Column(
                          children: <Widget>[
                            for (int i = 0; i < liste.take(5).length; i++) ...[
                              _LigneRetrait(retrait: liste[i]),
                              if (i < liste.take(5).length - 1)
                                Divider(height: 1, color: pc.line),
                            ],
                          ],
                        ),
                      ),
              ),
              const PcSection('Mouvements'),
              if (p.mouvements.isEmpty)
                _Vide(
                  'Vos gains apparaîtront ici après votre première '
                  'intervention clôturée.',
                )
              else
                PcCard(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 4,
                  ),
                  child: Column(
                    children: <Widget>[
                      for (int i = 0; i < p.mouvements.length; i++) ...[
                        _LigneMouvement(mouvement: p.mouvements[i]),
                        if (i < p.mouvements.length - 1)
                          Divider(height: 1, color: pc.line),
                      ],
                    ],
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Vide extends StatelessWidget {
  const _Vide(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
    child: Text(
      texte,
      style: TextStyle(color: context.pc.muted, fontWeight: FontWeight.w600),
    ),
  );
}

class _Solde extends StatelessWidget {
  const _Solde({required this.portefeuille});

  final Portefeuille portefeuille;

  @override
  Widget build(BuildContext context) {
    final bool peutRetirer =
        portefeuille.retirableGnf >= portefeuille.retraitMinimumGnf &&
        portefeuille.retraitMinimumGnf > 0;
    final TextStyle doux = TextStyle(
      color: Colors.white.withValues(alpha: 0.8),
      fontWeight: FontWeight.w600,
      fontSize: 12.5,
    );

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: PcRadius.lgAll,
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[PcColors.tech, PcColors.techDeep],
        ),
        boxShadow: PcShadow.glow(PcColors.tech),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text('Solde', style: doux),
          const SizedBox(height: 2),
          PcMontant(portefeuille.soldeGnf, taille: 32, couleur: Colors.white),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Retirable maintenant', style: doux),
                    PcMontant(
                      portefeuille.retirableGnf,
                      taille: 17,
                      couleur: Colors.white,
                    ),
                  ],
                ),
              ),
              if (portefeuille.engageGnf > 0)
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('Demandé, pas versé', style: doux),
                      PcMontant(
                        portefeuille.engageGnf,
                        taille: 17,
                        couleur: Colors.white,
                      ),
                    ],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          Semantics(
            button: true,
            enabled: peutRetirer,
            label: 'Demander un retrait',
            child: PcPressable(
              onTap: peutRetirer
                  ? () => RetraitFeuille.ouvrir(context, portefeuille)
                  : null,
              child: Container(
                height: 48,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: peutRetirer ? 1 : 0.4),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: const Text(
                  'Demander un retrait',
                  style: TextStyle(
                    fontFamily: PcFonts.display,
                    fontWeight: FontWeight.w700,
                    color: PcColors.techDeep,
                  ),
                ),
              ),
            ),
          ),
          if (!peutRetirer) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              // Dire le seuil plutôt que griser en silence : un bouton
              // inactif sans explication se lit comme une panne.
              'Retrait possible à partir de '
              '${formaterGnf(portefeuille.retraitMinimumGnf)}.',
              style: doux,
            ),
          ],
        ],
      ),
    );
  }
}

/// Commission due sur les paiements directs (#1). Au-delà du plafond, elle
/// **bloque** l'acceptation de nouvelles demandes : elle se règle d'un geste.
class _Commission extends ConsumerWidget {
  const _Commission({required this.portefeuille});

  final Portefeuille portefeuille;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final double part = portefeuille.commissionPlafondGnf <= 0
        ? 0
        : (portefeuille.commissionDueGnf / portefeuille.commissionPlafondGnf)
              .clamp(0.0, 1.0);

    return PcCard(
      bordure: portefeuille.bloque
          ? PcColors.coral.withValues(alpha: 0.6)
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              const PcIco(
                Icons.receipt_long_rounded,
                couleur: PcColors.coral,
                taille: 42,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Commission à régler',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              PcMontant(
                portefeuille.commissionDueGnf,
                taille: 17,
                couleur: PcColors.coral,
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: part,
              minHeight: 8,
              backgroundColor: pc.soft,
              color: portefeuille.bloque ? PcColors.coral : PcColors.tech,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            portefeuille.bloque
                ? 'Plafond de ${formaterGnf(portefeuille.commissionPlafondGnf)} '
                      'atteint : réglez pour recevoir de nouvelles demandes.'
                : 'Sur les paiements reçus directement. Plafond : '
                      '${formaterGnf(portefeuille.commissionPlafondGnf)}.',
            style: TextStyle(color: pc.muted, fontSize: 12.5),
          ),
          const SizedBox(height: 12),
          PcButton(
            libelle: 'Régler ${portefeuille.commissionDueFormate}',
            petit: true,
            variante: portefeuille.bloque ? PcVariante.corail : PcVariante.doux,
            onPressed: () => _regler(context, ref),
          ),
        ],
      ),
    );
  }

  Future<void> _regler(BuildContext context, WidgetRef ref) async {
    final bool? confirme = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Régler ${portefeuille.commissionDueFormate} ?',
            style: Theme.of(ctx).textTheme.titleLarge,
          ),
          const SizedBox(height: 6),
          Text(
            'Pilote : le règlement est simulé et aboutit immédiatement.',
            style: TextStyle(color: ctx.pc.muted, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Régler',
            variante: PcVariante.tech,
            onPressed: () => Navigator.of(ctx).pop(true),
          ),
          const SizedBox(height: 8),
          PcButton(
            libelle: 'Plus tard',
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(false),
          ),
        ],
      ),
    );

    if (confirme != true) return;

    try {
      await ref
          .read(technicienRepositoryProvider)
          .reglerCommission(portefeuille.commissionDueGnf);
      ref.invalidate(portefeuilleProvider);
      if (context.mounted) showPcToast(context, 'Commission réglée.');
    } on ApiException catch (e) {
      if (context.mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    }
  }
}

class _LigneMouvement extends StatelessWidget {
  const _LigneMouvement({required this.mouvement});

  final Mouvement mouvement;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool entree = mouvement.estUneEntree;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        children: <Widget>[
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: pc.teinte(entree ? PcColors.tech : PcColors.coral),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(
              entree ? Icons.south_west_rounded : Icons.north_east_rounded,
              size: 17,
              color: entree ? PcColors.tech : PcColors.coral,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  mouvement.libelle ?? mouvement.typeLibelle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: pc.ink, fontWeight: FontWeight.w700),
                ),
                if (mouvement.ticket != null)
                  Text(
                    mouvement.ticket!,
                    style: TextStyle(color: pc.muted, fontSize: 12),
                  ),
              ],
            ),
          ),
          Text(
            mouvement.montantFormate,
            style: TextStyle(
              color: entree ? PcColors.tech : pc.ink2,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _LigneRetrait extends StatelessWidget {
  const _LigneRetrait({required this.retrait});

  final Retrait retrait;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color couleur = retrait.estVerse
        ? PcColors.tech
        : retrait.estRejete
        ? PcColors.coral
        : pc.accent;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(child: PcMontant(retrait.montantGnf, taille: 16)),
              PcPill(retrait.statutLibelle, couleur: couleur),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            '${retrait.operateur} · ${retrait.numero}',
            style: TextStyle(color: pc.muted, fontSize: 12),
          ),
          // Le motif d'un rejet est affiché, jamais avalé.
          if (retrait.note != null && retrait.note!.isNotEmpty) ...<Widget>[
            const SizedBox(height: 4),
            Text(retrait.note!, style: TextStyle(color: couleur, fontSize: 12)),
          ],
        ],
      ),
    );
  }
}
