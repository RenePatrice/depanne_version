import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Choix du technicien — place de marché (#2).
///
/// Après publication, personne n'est sollicité automatiquement : le client voit
/// les techniciens disponibles, chacun avec **son prix déjà ferme**, sa note et
/// sa distance, et en choisit un. Le prix ne se négocie pas et ne se saisit pas
/// ici — il vient du serveur.
///
/// L'ordre « Recommandés » est celui du serveur (`MatchScorer`) ; les autres
/// tris ne font que réordonner la même liste sur l'appareil. Le premier de
/// l'ordre serveur porte la mention « Recommandé » : c'est ce que le score dit,
/// rien de plus.
///
/// Choisir ouvre au technicien une fenêtre de 45 secondes pour confirmer. S'il
/// refuse ou ne répond pas, la demande revient en sélection et le client en
/// choisit un autre : c'est le suivi qui le ramènera ici.
class ChoixTechnicienScreen extends ConsumerStatefulWidget {
  const ChoixTechnicienScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<ChoixTechnicienScreen> createState() =>
      _ChoixTechnicienScreenState();
}

enum _Tri { recommandes, prix, distance, note }

class _ChoixTechnicienScreenState extends ConsumerState<ChoixTechnicienScreen> {
  _Tri _tri = _Tri.recommandes;
  int? _enCours;

  List<TechnicienDisponible> _trier(List<TechnicienDisponible> liste) {
    final List<TechnicienDisponible> copie = List<TechnicienDisponible>.of(
      liste,
    );

    switch (_tri) {
      case _Tri.recommandes:
        break;
      case _Tri.prix:
        copie.sort(
          (TechnicienDisponible a, TechnicienDisponible b) =>
              a.prixTotalGnf.compareTo(b.prixTotalGnf),
        );
      case _Tri.distance:
        copie.sort(
          (TechnicienDisponible a, TechnicienDisponible b) =>
              a.distanceKm.compareTo(b.distanceKm),
        );
      case _Tri.note:
        // Un nouveau venu n'a pas de note : il passe après les notés plutôt
        // que d'être classé sur un zéro qui ne veut rien dire.
        copie.sort((TechnicienDisponible a, TechnicienDisponible b) {
          if (a.nouveau != b.nouveau) return a.nouveau ? 1 : -1;
          return b.note.compareTo(a.note);
        });
    }

    return copie;
  }

  Future<void> _choisir(TechnicienDisponible technicien) async {
    if (_enCours != null) return;

    setState(() => _enCours = technicien.id);

    try {
      await ref
          .read(clientRepositoryProvider)
          .choisir(widget.ticketId, technicien.id);
      ref.invalidate(ticketProvider(widget.ticketId));

      if (mounted) {
        showPcToast(
          context,
          '${technicien.nom} est sollicité : il a 45 secondes pour confirmer.',
          icone: Icons.hourglass_top_rounded,
        );
        context.pop();
      }
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
        ref.invalidate(techniciensDisponiblesProvider(widget.ticketId));
      }
    } finally {
      if (mounted) setState(() => _enCours = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final AsyncValue<List<TechnicienDisponible>> techniciens = ref.watch(
      techniciensDisponiblesProvider(widget.ticketId),
    );

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(
              titre: 'Choisissez votre technicien',
              sousTitre: techniciens.maybeWhen(
                data: (List<TechnicienDisponible> l) => l.isEmpty
                    ? 'Personne de disponible pour l’instant'
                    : '${l.length} disponible${l.length > 1 ? 's' : ''} près de chez vous',
                orElse: () => 'Recherche autour de vous…',
              ),
              action: PcIconButton(
                icone: Icons.refresh_rounded,
                libelle: 'Actualiser',
                onTap: () => ref.invalidate(
                  techniciensDisponiblesProvider(widget.ticketId),
                ),
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                color: pc.accent,
                onRefresh: () async => ref.invalidate(
                  techniciensDisponiblesProvider(widget.ticketId),
                ),
                child: techniciens.when(
                  loading: () => const _Recherche(),
                  error: (Object e, StackTrace _) => ListView(
                    padding: const EdgeInsets.all(18),
                    children: <Widget>[
                      PcErreur(
                        message: messageErreur(e),
                        onReessayer: () => ref.invalidate(
                          techniciensDisponiblesProvider(widget.ticketId),
                        ),
                      ),
                    ],
                  ),
                  data: (List<TechnicienDisponible> liste) =>
                      liste.isEmpty ? const _Personne() : _liste(liste),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _liste(List<TechnicienDisponible> liste) {
    final PcSkin pc = context.pc;
    final int recommande = liste.first.id;
    final List<TechnicienDisponible> triee = _trier(liste);

    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 28),
      children: <Widget>[
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: <Widget>[
              for (final (_Tri t, String l, IconData i)
                  in const <(_Tri, String, IconData)>[
                    (_Tri.recommandes, 'Recommandés', Icons.auto_awesome),
                    (_Tri.prix, 'Moins cher', Icons.payments_outlined),
                    (_Tri.distance, 'Plus proche', Icons.near_me_outlined),
                    (_Tri.note, 'Mieux notés', Icons.star_outline_rounded),
                  ]) ...<Widget>[
                PcChip(
                  libelle: l,
                  icone: i,
                  selectionne: _tri == t,
                  onTap: () => setState(() => _tri = t),
                ),
                const SizedBox(width: 8),
              ],
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(2, 12, 2, 12),
          child: Row(
            children: <Widget>[
              Icon(Icons.verified_user_outlined, size: 16, color: pc.muted),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Prix fermes : prestation et déplacement compris.',
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
        for (final TechnicienDisponible t in triee) ...<Widget>[
          _CarteTechnicien(
            technicien: t,
            recommande: t.id == recommande,
            enCours: _enCours == t.id,
            bloque: _enCours != null && _enCours != t.id,
            onChoisir: () => _choisir(t),
          ),
          const SizedBox(height: 12),
        ],
      ],
    );
  }
}

class _CarteTechnicien extends StatelessWidget {
  const _CarteTechnicien({
    required this.technicien,
    required this.recommande,
    required this.enCours,
    required this.bloque,
    required this.onChoisir,
  });

  final TechnicienDisponible technicien;
  final bool recommande;
  final bool enCours;
  final bool bloque;
  final VoidCallback onChoisir;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      bordure: recommande ? pc.accent.withValues(alpha: 0.45) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (recommande)
            const Padding(
              padding: EdgeInsets.only(bottom: 10),
              child: PcPill(
                'Recommandé',
                icone: Icons.auto_awesome,
                couleur: PcColors.coral,
              ),
            ),
          Row(
            children: <Widget>[
              PcAvatar(nom: technicien.nom, taille: 50),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      technicien.nom,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 3),
                    _Note(technicien: technicien),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: <Widget>[
                  PcMontant(
                    technicien.prixTotalGnf,
                    taille: 19,
                    couleur: pc.accent,
                  ),
                  Text(
                    'prix ferme',
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 11.5,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              _Info(
                icone: Icons.near_me_outlined,
                texte: technicien.distanceEstimee
                    ? '~${technicien.distanceKm.toStringAsFixed(1)} km'
                    : '${technicien.distanceKm.toStringAsFixed(1)} km',
              ),
              const SizedBox(width: 8),
              _Info(
                icone: Icons.schedule_rounded,
                texte: 'arrive en ~${technicien.etaMin} min',
              ),
            ],
          ),
          const SizedBox(height: 14),
          PcButton(
            libelle: 'Choisir ${_prenom(technicien.nom)}',
            petit: true,
            icone: Icons.check_rounded,
            variante: recommande ? PcVariante.accent : PcVariante.doux,
            chargement: enCours,
            onPressed: bloque ? null : onChoisir,
          ),
        ],
      ),
    );
  }

  static String _prenom(String nom) {
    final String n = nom.trim();
    if (n.isEmpty) return 'ce technicien';
    return n.split(RegExp(r'\s+')).first;
  }
}

class _Info extends StatelessWidget {
  const _Info({required this.icone, required this.texte});

  final IconData icone;
  final String texte;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: pc.soft,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icone, size: 14, color: pc.ink2),
          const SizedBox(width: 5),
          Text(
            texte,
            style: TextStyle(
              color: pc.ink2,
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}

class _Note extends StatelessWidget {
  const _Note({required this.technicien});

  final TechnicienDisponible technicien;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    // Un nouveau venu n'a pas de note réelle : le dire franchement vaut mieux
    // qu'afficher une note neutre qui passerait pour un avis.
    if (technicien.nouveau) {
      return const PcPill(
        'Nouveau sur Parema Connect',
        icone: Icons.fiber_new_outlined,
        couleur: PcColors.tech,
      );
    }

    return Row(
      children: <Widget>[
        const Icon(Icons.star_rounded, size: 16, color: Color(0xFFF5B000)),
        const SizedBox(width: 3),
        Text(
          technicien.note.toStringAsFixed(1),
          style: TextStyle(color: pc.ink, fontWeight: FontWeight.w800),
        ),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            '· ${technicien.interventions} intervention'
            '${technicien.interventions > 1 ? 's' : ''}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 12.5,
            ),
          ),
        ),
      ],
    );
  }
}

/// Recherche en cours : un radar discret, qui s'arrête si l'utilisateur a
/// demandé moins d'animations.
class _Recherche extends StatefulWidget {
  const _Recherche();

  @override
  State<_Recherche> createState() => _RechercheState();
}

class _RechercheState extends State<_Recherche>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _c.stop();
    } else if (!_c.isAnimating) {
      _c.repeat();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return ListView(
      children: <Widget>[
        const SizedBox(height: 90),
        Center(
          child: SizedBox(
            width: 160,
            height: 160,
            child: AnimatedBuilder(
              animation: _c,
              builder: (BuildContext context, Widget? _) => Stack(
                alignment: Alignment.center,
                children: <Widget>[
                  for (int i = 0; i < 2; i++)
                    Builder(
                      builder: (BuildContext context) {
                        final double t = (_c.value + i / 2) % 1;
                        return Opacity(
                          opacity: 1 - t,
                          child: Container(
                            width: 60 + 100 * t,
                            height: 60 + 100 * t,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              border: Border.all(color: pc.accent, width: 2),
                            ),
                          ),
                        );
                      },
                    ),
                  Container(
                    width: 60,
                    height: 60,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: pc.gradientAccent,
                    ),
                    child: const Icon(
                      Icons.person_search_rounded,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          'On cherche les techniciens disponibles…',
          textAlign: TextAlign.center,
          style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}

class _Personne extends StatelessWidget {
  const _Personne();

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return ListView(
      padding: const EdgeInsets.all(18),
      children: <Widget>[
        const SizedBox(height: 40),
        PcCard(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: <Widget>[
              PcIco(Icons.person_search_outlined, couleur: pc.accent),
              const SizedBox(height: 14),
              Text(
                'Aucun technicien disponible',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 6),
              Text(
                'Personne n’est en ligne près de chez vous pour le moment. '
                'Votre demande reste publiée : réessayez dans quelques '
                'minutes en tirant vers le bas.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
