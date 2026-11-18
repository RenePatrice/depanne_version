import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'composants_client.dart';

/// Adresse, description, prix, publication.
///
/// Le prix apparaît **avant** le bouton de publication, jamais après. C'est
/// toute la promesse de l'onboarding : le client sait ce qu'il engage au moment
/// où il l'engage. La barre du bas porte les deux ensemble — le montant et le
/// bouton — pour qu'on ne puisse pas lire l'un sans voir l'autre.
///
/// Le devis est marqué « à partir de » tant qu'aucun technicien n'a accepté :
/// le déplacement dépend de la distance du technicien, qui n'existe pas encore
/// (ADR-0026). L'annoncer comme ferme serait le mensonge qui produit la
/// première réclamation.
class NouvelleDemandeScreen extends ConsumerStatefulWidget {
  const NouvelleDemandeScreen({super.key});

  @override
  ConsumerState<NouvelleDemandeScreen> createState() =>
      _NouvelleDemandeScreenState();
}

class _NouvelleDemandeScreenState extends ConsumerState<NouvelleDemandeScreen> {
  final TextEditingController _description = TextEditingController();

  Devis? _devis;
  bool _calculEnCours = false;
  bool _publicationEnCours = false;
  ApiException? _erreur;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  Future<void> _calculerLePrix() async {
    final BrouillonDemande brouillon = ref.read(brouillonProvider);

    if (!brouillon.estComplet) return;

    setState(() {
      _calculEnCours = true;
      _erreur = null;
      _devis = null;
    });

    try {
      final Devis devis = await ref
          .read(clientRepositoryProvider)
          .devis(
            prestationId: brouillon.prestation!.id,
            adresseId: brouillon.adresse!.id,
          );

      if (mounted) setState(() => _devis = devis);
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _calculEnCours = false);
    }
  }

  Future<void> _publier() async {
    final BrouillonDemande brouillon = ref.read(brouillonProvider);

    if (!brouillon.estComplet || _publicationEnCours) return;

    setState(() {
      _publicationEnCours = true;
      _erreur = null;
    });

    try {
      final Ticket ticket = await ref
          .read(clientRepositoryProvider)
          .publier(
            prestationId: brouillon.prestation!.id,
            adresseId: brouillon.adresse!.id,
            description: _description.text.trim().isEmpty
                ? null
                : _description.text.trim(),
          );

      ref.read(brouillonProvider.notifier).vider();
      ref.invalidate(ticketsProvider);

      if (mounted) context.go('/client/suivi/${ticket.id}');
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _publicationEnCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final BrouillonDemande brouillon = ref.watch(brouillonProvider);
    final AsyncValue<List<Adresse>> adresses = ref.watch(adressesProvider);
    final Prestation? prestation = brouillon.prestation;

    if (prestation == null) {
      return Scaffold(
        body: SafeArea(
          child: Column(
            children: <Widget>[
              const PcHeader(titre: 'Nouvelle demande'),
              Expanded(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(28),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        PcIco(Icons.help_outline_rounded, couleur: pc.accent),
                        const SizedBox(height: 14),
                        Text(
                          'Aucune prestation choisie',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Revenez au catalogue pour choisir ce dont vous '
                          'avez besoin.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: pc.muted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 18),
                        PcButton(
                          libelle: 'Voir le catalogue',
                          petit: true,
                          largeurPleine: false,
                          onPressed: () => context.go('/accueil'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(titre: 'Votre demande', sousTitre: _etape(brouillon)),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 0, 18, 24),
                children: <Widget>[
                  _RecapPrestation(prestation: prestation),

                  const PcSection('Où intervenir ?'),
                  adresses.when(
                    loading: () => const Padding(
                      padding: EdgeInsets.all(24),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                    error: (Object e, _) => PcErreur(
                      message: messageErreur(e),
                      onReessayer: () => ref.invalidate(adressesProvider),
                    ),
                    data: (List<Adresse> liste) => _ChoixAdresse(
                      adresses: liste,
                      choisie: brouillon.adresse,
                      onChoisir: (Adresse a) {
                        ref.read(brouillonProvider.notifier).choisirAdresse(a);
                        _calculerLePrix();
                      },
                    ),
                  ),

                  if (_erreur != null) ...<Widget>[
                    const SizedBox(height: 16),
                    PcErreur(
                      message: _erreur!.message,
                      onReessayer: _erreur!.reseau ? _calculerLePrix : null,
                    ),
                  ],

                  if (_calculEnCours || _devis != null) const PcSection('Prix'),
                  AnimatedSwitcher(
                    duration: PcMotion.normal,
                    child: _calculEnCours
                        ? const Padding(
                            key: ValueKey<String>('calcul'),
                            padding: EdgeInsets.all(24),
                            child: Center(child: CircularProgressIndicator()),
                          )
                        : _devis == null
                        ? const SizedBox.shrink(key: ValueKey<String>('vide'))
                        : _CarteDevis(
                            key: const ValueKey<String>('devis'),
                            devis: _devis!,
                          ),
                  ),
                  const PcSection('Décrivez le problème'),
                  PcField(
                    libelle: 'Ce qui ne va pas (facultatif)',
                    indice: 'Fuite sous l’évier depuis hier soir…',
                    icone: Icons.edit_note_rounded,
                    controleur: _description,
                    lignes: 3,
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(4, 8, 4, 0),
                    child: Text(
                      'Plus vous êtes précis, mieux le technicien se prépare. '
                      'Ne donnez pas votre numéro : la messagerie s’ouvre dès '
                      'qu’il accepte.',
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w500,
                        fontSize: 12,
                      ),
                    ),
                  ),

                  const SizedBox(height: 18),
                  _CommentCaMarche(),
                ],
              ),
            ),
            _BarrePublication(
              devis: _devis,
              enCours: _publicationEnCours,
              // Publier sans avoir vu le prix est possible techniquement, mais
              // contraire à la promesse : le bouton reste inactif tant que le
              // devis n'est pas affiché.
              onPublier: _devis == null ? null : _publier,
            ),
          ],
        ),
      ),
    );
  }

  String _etape(BrouillonDemande b) {
    if (b.adresse == null) return 'Étape 1 sur 2 · l’adresse';
    if (_devis == null) return 'Calcul du prix…';
    return 'Étape 2 sur 2 · vérifiez et publiez';
  }
}

class _RecapPrestation extends StatelessWidget {
  const _RecapPrestation({required this.prestation});

  final Prestation prestation;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color couleur = couleurType(prestation.type);

    return PcCard(
      child: Row(
        children: <Widget>[
          PcIco(Icons.build_circle_outlined, couleur: couleur),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  prestation.nom,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 4),
                Row(
                  children: <Widget>[
                    PcPill(prestation.typeLibelle, couleur: couleur),
                    ...<Widget>[
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          'Indicatif : ${prestation.prixFormate}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: pc.muted,
                            fontWeight: FontWeight.w600,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).maybePop(),
            child: const Text('Changer'),
          ),
        ],
      ),
    );
  }
}

class _ChoixAdresse extends StatelessWidget {
  const _ChoixAdresse({
    required this.adresses,
    required this.choisie,
    required this.onChoisir,
  });

  final List<Adresse> adresses;
  final Adresse? choisie;
  final ValueChanged<Adresse> onChoisir;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    if (adresses.isEmpty) {
      return PcCard(
        onTap: () => context.push('/client/adresse'),
        bordure: pc.accent.withValues(alpha: 0.35),
        child: Row(
          children: <Widget>[
            PcIco(Icons.add_location_alt_outlined, couleur: pc.accent),
            const SizedBox(width: 13),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Ajoutez votre première adresse',
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  Text(
                    'Quartier, puis le point exact sur la carte.',
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: pc.muted),
          ],
        ),
      );
    }

    return Column(
      children: <Widget>[
        for (final Adresse a in adresses) ...<Widget>[
          _CarteAdresse(
            adresse: a,
            choisie: choisie?.id == a.id,
            onTap: a.estDesservie ? () => onChoisir(a) : null,
          ),
          const SizedBox(height: 10),
        ],
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => context.push('/client/adresse'),
            icon: const Icon(Icons.add_rounded),
            label: const Text('Ajouter une adresse'),
          ),
        ),
      ],
    );
  }
}

class _CarteAdresse extends StatelessWidget {
  const _CarteAdresse({
    required this.adresse,
    required this.choisie,
    required this.onTap,
  });

  final Adresse adresse;
  final bool choisie;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    // Une adresse hors zone reste visible mais grisée : la masquer laisserait
    // le client chercher pourquoi elle a disparu.
    return Opacity(
      opacity: adresse.estDesservie ? 1 : 0.5,
      child: AnimatedContainer(
        duration: PcMotion.normal,
        curve: PcMotion.ease,
        decoration: BoxDecoration(
          borderRadius: PcRadius.lgAll,
          border: Border.all(
            color: choisie ? pc.accent : Colors.transparent,
            width: 2,
          ),
        ),
        child: PcCard(
          onTap: onTap,
          child: Row(
            children: <Widget>[
              PcIco(
                adresse.libelle.toLowerCase().contains('bureau')
                    ? Icons.work_outline_rounded
                    : Icons.home_outlined,
                couleur: choisie ? pc.accent : pc.muted,
                taille: 42,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      adresse.libelle,
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    Text(
                      adresse.adresse,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w500,
                        fontSize: 12.5,
                      ),
                    ),
                    if (!adresse.estDesservie)
                      const Padding(
                        padding: EdgeInsets.only(top: 4),
                        child: PcPill(
                          'Hors zone de couverture',
                          couleur: PcColors.coral,
                        ),
                      ),
                  ],
                ),
              ),
              AnimatedSwitcher(
                duration: PcMotion.fast,
                child: Icon(
                  choisie
                      ? Icons.check_circle_rounded
                      : Icons.radio_button_unchecked_rounded,
                  key: ValueKey<bool>(choisie),
                  color: choisie ? pc.accent : pc.line,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CarteDevis extends StatelessWidget {
  const _CarteDevis({super.key, required this.devis});

  final Devis devis;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          for (final LigneDevis l in devis.lignes) ...<Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        l.libelle,
                        style: TextStyle(
                          color: pc.ink,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (l.detail != null)
                        Text(
                          l.detail!,
                          style: TextStyle(
                            color: pc.muted,
                            fontWeight: FontWeight.w500,
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
                ),
                Text(
                  l.montantFormate,
                  style: TextStyle(color: pc.ink2, fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 10),
          ],
          Divider(color: pc.line),
          const SizedBox(height: 6),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  // Le mot compte : « à partir de » tant que le déplacement
                  // n'est pas connu, « total » une fois qu'il l'est.
                  devis.ferme ? 'Total' : 'À partir de',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              Text(
                devis.totalFormate,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: pc.accent,
                  fontFeatures: const <FontFeature>[
                    FontFeature.tabularFigures(),
                  ],
                ),
              ),
            ],
          ),
          if (!devis.ferme) ...<Widget>[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: pc.teinte(pc.accent),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Icon(Icons.info_outline_rounded, size: 18, color: pc.accent),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Chaque technicien affiche son prix ferme avant que vous '
                      'ne le choisissiez : sa prestation et son déplacement.',
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
          ],
        ],
      ),
    );
  }
}

class _CommentCaMarche extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    const List<(IconData, String)> etapes = <(IconData, String)>[
      (Icons.campaign_outlined, 'Vous publiez, sans engagement.'),
      (Icons.people_alt_outlined, 'Vous comparez prix, notes et distances.'),
      (Icons.handshake_outlined, 'Vous choisissez votre technicien.'),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        for (final (IconData icone, String texte) in etapes)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Row(
              children: <Widget>[
                Icon(icone, size: 18, color: pc.muted),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    texte,
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Barre basse : le montant et le bouton, toujours ensemble.
class _BarrePublication extends StatelessWidget {
  const _BarrePublication({
    required this.devis,
    required this.enCours,
    required this.onPublier,
  });

  final Devis? devis;
  final bool enCours;
  final VoidCallback? onPublier;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Container(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
      decoration: BoxDecoration(
        color: pc.card,
        border: Border(top: BorderSide(color: pc.line)),
      ),
      child: Row(
        children: <Widget>[
          if (devis != null) ...<Widget>[
            Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  devis!.ferme ? 'Prix ferme' : 'Estimation',
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w700,
                    fontSize: 11.5,
                  ),
                ),
                PcMontant(devis!.totalGnf, taille: 18),
              ],
            ),
            const SizedBox(width: 14),
          ],
          Expanded(
            child: PcButton(
              libelle: 'Publier ma demande',
              icone: Icons.send_rounded,
              chargement: enCours,
              onPressed: onPublier,
            ),
          ),
        ],
      ),
    );
  }
}
