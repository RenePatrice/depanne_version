import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/pc.dart';
import '../../auth/application/session_controller.dart';
import '../../auth/data/utilisateur.dart';
import '../../shared/data/modeles.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';
import 'composants_technicien.dart';

/// Tableau de bord du technicien : disponibilité, demande entrante, mission en
/// cours (§7.3).
///
/// L'écran ne montre **qu'une** demande à la fois : le client choisit un
/// technicien et le sollicite, et une file de demandes concurrentes serait un
/// mensonge sur ce que le technicien peut réellement accepter.
///
/// Une sollicitation arrive par Reverb, sous la forme de la notification
/// « nouvelle demande » sur le canal personnel du technicien : la fenêtre de
/// réponse est de quarante-cinq secondes, et dix secondes d'attente d'une
/// interrogation en mangeraient le quart. La boucle reste en repli.
class DemandesScreen extends ConsumerStatefulWidget {
  const DemandesScreen({super.key});

  @override
  ConsumerState<DemandesScreen> createState() => _DemandesScreenState();
}

class _DemandesScreenState extends ConsumerState<DemandesScreen>
    with InterrogationDeRepli<DemandesScreen> {
  /// Les notifications qui changent ce que l'écran doit montrer.
  static const Set<String> _signaux = <String>{
    'NOUVELLE_DEMANDE',
    'DEMANDE_EXPIREE',
    'DEMANDE_ATTRIBUEE',
    'DEMANDE_RETIREE',
  };

  @override
  void initState() {
    super.initState();
    demarrerInterrogation(_rafraichir);
  }

  bool _rafraichir() {
    // Hors ligne, il n'y a rien à attendre : interroger consommerait le
    // forfait de quelqu'un pour une réponse toujours vide.
    if (ref.read(disponibiliteProvider).enLigne) {
      ref.invalidate(sollicitationsProvider);
    }

    return true;
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Session session = ref.watch(sessionProvider);
    final Utilisateur? u = session.utilisateur;

    ref.listen(signauxProvider('utilisateur.${u?.id}'), (
      _,
      AsyncValue<EvenementTempsReel> suivant,
    ) {
      if (_signaux.contains(suivant.value?.donnees['type'])) {
        // Une nouvelle demande doit se sentir, pas seulement se voir.
        if (suivant.value?.donnees['type'] == 'NOUVELLE_DEMANDE') {
          HapticFeedback.heavyImpact();
        }
        ref.invalidate(sollicitationsProvider);
        ref.invalidate(interventionsProvider);
      }
    });

    final Disponibilite dispo = ref.watch(disponibiliteProvider);
    final AsyncValue<List<Sollicitation>> sollicitations = ref.watch(
      sollicitationsProvider(false),
    );
    final List<Ticket> missions =
        ref.watch(interventionsProvider(false)).value ?? const <Ticket>[];
    final Ticket? enCours = missions
        .where((Ticket t) => t.etatActif)
        .firstOrNull;
    final Portefeuille? portefeuille = ref.watch(portefeuilleProvider).value;

    // L'état connu du compte amorce l'interrupteur : sans cela, un technicien
    // déjà en ligne verrait « hors ligne » au lancement et se remettrait en
    // ligne pour rien.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final bool? connu = u?.enLigne;
      if (connu != null) {
        ref.read(disponibiliteProvider.notifier).amorcer(connu);
      }
    });

    final ProfilTechnicien? profil = u?.profilTechnicien;

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async {
        ref.invalidate(sollicitationsProvider);
        ref.invalidate(interventionsProvider);
        ref.invalidate(portefeuilleProvider);
      },
      child: ListView(
        padding: margesOnglet(context),
        children: <Widget>[
          EnteteTechnicien(
            titre: 'Tableau de bord',
            sousTitre: 'Bonjour ${u?.prenom ?? ''}',
          ),
          const SizedBox(height: 18),
          _Interrupteur(disponibilite: dispo),

          if (portefeuille?.bloque ?? false) ...<Widget>[
            const SizedBox(height: 12),
            _AlerteCommission(portefeuille: portefeuille!),
          ],

          // Affluence prévue (#10) : un repère pour savoir si le moment est
          // porteur. Seulement en ligne, et jamais bloquant.
          if (dispo.enLigne) ...<Widget>[
            const SizedBox(height: 12),
            const _Affluence(),
          ],

          if (enCours != null) ...<Widget>[
            const PcSection('Mission en cours'),
            _CarteMission(ticket: enCours),
          ],

          const PcSection('Demande'),
          sollicitations.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(32),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => PcErreur(
              message: messageErreur(e),
              onReessayer: () => ref.invalidate(sollicitationsProvider),
            ),
            data: (List<Sollicitation> liste) {
              final Sollicitation? vivante = liste
                  .where((Sollicitation s) => s.estVivante)
                  .firstOrNull;

              return AnimatedSwitcher(
                duration: PcMotion.normal,
                child: vivante == null
                    ? _Attente(
                        key: const ValueKey<String>('attente'),
                        enLigne: dispo.enLigne,
                      )
                    : _CarteSollicitation(
                        key: ValueKey<int>(vivante.id),
                        sollicitation: vivante,
                      ),
              );
            },
          ),

          // Les chiffres qui décident de qui reçoit les courses, montrés au
          // technicien pour qu'il comprenne pourquoi il en reçoit plus ou moins.
          if (profil != null) ...<Widget>[
            const PcSection('Vos chiffres'),
            Row(
              children: <Widget>[
                ChiffreTech(
                  valeur: profil.avis == 0
                      ? '—'
                      : profil.noteMoyenne.toStringAsFixed(1),
                  libelle: profil.avis == 0
                      ? 'Pas encore noté'
                      : '${profil.avis} avis',
                  icone: Icons.star_rounded,
                  couleur: const Color(0xFFF5B000),
                ),
                const SizedBox(width: 10),
                ChiffreTech(
                  valeur: '${profil.interventions}',
                  libelle: 'Interventions',
                  icone: Icons.handyman_rounded,
                ),
                const SizedBox(width: 10),
                ChiffreTech(
                  valeur: '${(profil.tauxAcceptation * 100).round()} %',
                  libelle: 'Acceptation',
                  icone: Icons.thumb_up_alt_rounded,
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _Interrupteur extends ConsumerWidget {
  const _Interrupteur({required this.disponibilite});

  final Disponibilite disponibilite;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final bool enLigne = disponibilite.enLigne;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Semantics(
          toggled: enLigne,
          label: enLigne ? 'En ligne' : 'Hors ligne',
          button: true,
          child: PcPressable(
            onTap: disponibilite.enCours
                ? null
                : () => ref
                      .read(disponibiliteProvider.notifier)
                      .basculer(!enLigne),
            child: AnimatedContainer(
              duration: PcMotion.normal,
              curve: PcMotion.ease,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                borderRadius: PcRadius.lgAll,
                color: enLigne ? null : pc.card,
                gradient: enLigne
                    ? const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: <Color>[PcColors.tech, PcColors.techDeep],
                      )
                    : null,
                boxShadow: enLigne ? PcShadow.glow(PcColors.tech) : pc.ombre,
              ),
              child: Row(
                children: <Widget>[
                  AnimatedContainer(
                    duration: PcMotion.normal,
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: enLigne
                          ? Colors.white.withValues(alpha: 0.2)
                          : pc.soft,
                    ),
                    child: disponibilite.enCours
                        ? Padding(
                            padding: const EdgeInsets.all(15),
                            child: CircularProgressIndicator(
                              strokeWidth: 2.5,
                              color: enLigne ? Colors.white : pc.accent,
                            ),
                          )
                        : Icon(
                            Icons.power_settings_new_rounded,
                            color: enLigne ? Colors.white : pc.muted,
                            size: 26,
                          ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          enLigne
                              ? 'Vous êtes en ligne'
                              : 'Vous êtes hors ligne',
                          style: TextStyle(
                            fontFamily: PcFonts.display,
                            fontWeight: FontWeight.w700,
                            fontSize: 17,
                            color: enLigne ? Colors.white : pc.ink,
                          ),
                        ),
                        Text(
                          enLigne
                              ? 'Les clients proches peuvent vous choisir.'
                              : 'Touchez pour recevoir des demandes.',
                          style: TextStyle(
                            color: enLigne
                                ? Colors.white.withValues(alpha: 0.85)
                                : pc.muted,
                            fontWeight: FontWeight.w600,
                            fontSize: 12.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),

        // Le refus du serveur est affiché tel quel : « votre dossier est en
        // cours de validation » ou « intervention en cours » sont des phrases
        // utiles, un « échec » générique ne l'est pas.
        if (disponibilite.refus != null) ...<Widget>[
          const SizedBox(height: 10),
          PcErreur(message: disponibilite.refus!),
        ],
      ],
    );
  }
}

class _AlerteCommission extends StatelessWidget {
  const _AlerteCommission({required this.portefeuille});

  final Portefeuille portefeuille;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      bordure: PcColors.coral.withValues(alpha: 0.5),
      child: Row(
        children: <Widget>[
          const PcIco(
            Icons.lock_clock_rounded,
            couleur: PcColors.coral,
            taille: 42,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Nouvelles demandes en pause',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                Text(
                  'Commission due : ${portefeuille.commissionDueFormate}. '
                  'Réglez-la dans Gains pour être de nouveau choisi.',
                  style: TextStyle(color: pc.ink2, fontSize: 12.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Banneau d'affluence prévue (#10). Silencieux tant que la donnée n'est pas
/// là : une prédiction qui tarde ne doit afficher ni erreur ni sablier.
class _Affluence extends ConsumerWidget {
  const _Affluence();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;

    return ref
        .watch(affluenceProvider)
        .maybeWhen(
          data: (Affluence a) {
            final (IconData icone, Color couleur) = switch (a.niveau) {
              'forte' => (Icons.trending_up_rounded, PcColors.tech),
              'moyenne' => (Icons.trending_flat_rounded, PcColors.coral),
              _ => (Icons.trending_down_rounded, pc.muted),
            };

            return PcCard(
              child: Row(
                children: <Widget>[
                  PcIco(icone, couleur: couleur, taille: 40),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          a.libelle,
                          style: Theme.of(context).textTheme.titleSmall,
                        ),
                        Text(
                          a.message,
                          style: TextStyle(color: pc.muted, fontSize: 12.5),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
          orElse: () => const SizedBox.shrink(),
        );
  }
}

class _CarteMission extends StatelessWidget {
  const _CarteMission({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      onTap: () => context.push('/technicien/intervention/${ticket.id}'),
      bordure: pc.accent.withValues(alpha: 0.5),
      child: Row(
        children: <Widget>[
          PcIco(Icons.handyman_rounded, couleur: pc.accent, taille: 46),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  ticket.prestation?.nom ?? ticket.reference,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 4),
                PcPill(ticket.etatLibelle, couleur: pc.accent),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: pc.accent,
              borderRadius: BorderRadius.circular(99),
            ),
            child: const Text(
              'Reprendre',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: 12.5,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// En ligne sans demande : un radar discret. Hors ligne : une invitation.
class _Attente extends StatefulWidget {
  const _Attente({required this.enLigne, super.key});

  final bool enLigne;

  @override
  State<_Attente> createState() => _AttenteState();
}

class _AttenteState extends State<_Attente>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 2400),
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _regler();
  }

  @override
  void didUpdateWidget(_Attente ancien) {
    super.didUpdateWidget(ancien);
    _regler();
  }

  void _regler() {
    final bool animer =
        widget.enLigne && !MediaQuery.of(context).disableAnimations;
    if (animer && !_c.isAnimating) {
      _c.repeat();
    } else if (!animer) {
      _c.stop();
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

    return PcCard(
      padding: const EdgeInsets.fromLTRB(18, 22, 18, 22),
      child: Column(
        children: <Widget>[
          SizedBox(
            width: 120,
            height: 120,
            child: AnimatedBuilder(
              animation: _c,
              builder: (BuildContext context, Widget? _) => Stack(
                alignment: Alignment.center,
                children: <Widget>[
                  if (widget.enLigne)
                    for (int i = 0; i < 2; i++)
                      Builder(
                        builder: (BuildContext context) {
                          final double t = (_c.value + i / 2) % 1;
                          return Opacity(
                            opacity: (1 - t) * 0.8,
                            child: Container(
                              width: 54 + 66 * t,
                              height: 54 + 66 * t,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: PcColors.tech,
                                  width: 2,
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                  Container(
                    width: 54,
                    height: 54,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: widget.enLigne ? PcColors.tech : pc.soft,
                    ),
                    child: Icon(
                      widget.enLigne
                          ? Icons.radar_rounded
                          : Icons.bedtime_rounded,
                      color: widget.enLigne ? Colors.white : pc.muted,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            widget.enLigne
                ? 'En attente d’une demande'
                : 'Vous ne recevez rien pour l’instant',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(
            widget.enLigne
                ? 'Quand un client vous choisit, sa demande s’affiche ici avec '
                      '45 secondes pour répondre.'
                : 'Mettez-vous en ligne pour que les clients proches puissent '
                      'vous choisir.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: pc.muted,
              fontWeight: FontWeight.w600,
              fontSize: 12.5,
            ),
          ),
        ],
      ),
    );
  }
}

/// La demande, et les quarante-cinq secondes pour y répondre.
class _CarteSollicitation extends ConsumerStatefulWidget {
  const _CarteSollicitation({required this.sollicitation, super.key});

  final Sollicitation sollicitation;

  @override
  ConsumerState<_CarteSollicitation> createState() =>
      _CarteSollicitationState();
}

class _CarteSollicitationState extends ConsumerState<_CarteSollicitation> {
  Timer? _minuteur;
  late int _restant;
  bool _enCours = false;

  @override
  void initState() {
    super.initState();
    _demarrerLeCompteARebours();
  }

  @override
  void didUpdateWidget(_CarteSollicitation ancien) {
    super.didUpdateWidget(ancien);

    // Le serveur recalcule le restant à chaque lecture : on se recale sur lui
    // plutôt que sur l'horloge du téléphone, qui peut être décalée.
    if (ancien.sollicitation.id != widget.sollicitation.id ||
        (widget.sollicitation.expireDansS - _restant).abs() > 2) {
      _demarrerLeCompteARebours();
    }
  }

  void _demarrerLeCompteARebours() {
    _minuteur?.cancel();
    _restant = widget.sollicitation.expireDansS;

    _minuteur = Timer.periodic(const Duration(seconds: 1), (Timer t) {
      if (!mounted) return;

      setState(() => _restant = _restant > 0 ? _restant - 1 : 0);

      if (_restant == 0) {
        t.cancel();
        // La fenêtre s'est refermée : l'écran doit cesser de proposer un
        // bouton qui échouerait.
        ref.invalidate(sollicitationsProvider);
      }
    });
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    super.dispose();
  }

  Future<void> _accepter() async {
    if (_enCours) return;
    setState(() => _enCours = true);

    try {
      final Ticket ticket = await ref
          .read(technicienRepositoryProvider)
          .accepter(widget.sollicitation.id);

      ref.invalidate(sollicitationsProvider);
      ref.invalidate(interventionsProvider);

      if (mounted) context.push('/technicien/intervention/${ticket.id}');
    } on ApiException catch (e) {
      // 409 ou plafond de commission : le message du serveur dit mieux la
      // raison qu'un « erreur » sec.
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
        ref.invalidate(sollicitationsProvider);
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  Future<void> _refuser() async {
    if (_enCours) return;
    setState(() => _enCours = true);

    try {
      await ref
          .read(technicienRepositoryProvider)
          .refuser(widget.sollicitation.id);
      ref.invalidate(sollicitationsProvider);
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Sollicitation s = widget.sollicitation;

    return PcCard(
      bordure: PcColors.tech.withValues(alpha: 0.6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              const PcPill(
                'Nouvelle demande',
                icone: Icons.bolt_rounded,
                couleur: PcColors.tech,
                plein: true,
              ),
              const Spacer(),
              _Chrono(restant: _restant, depart: s.expireDansS),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            s.prestation ?? 'Intervention',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          if (s.description != null) ...<Widget>[
            const SizedBox(height: 4),
            Text(
              '« ${s.description!} »',
              style: TextStyle(color: pc.ink2, fontSize: 13.5),
            ),
          ],
          if (s.photos.isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            BandePhotos(photos: s.photos),
          ],
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              _Info(
                icone: Icons.near_me_rounded,
                texte: '${s.distanceKm.toStringAsFixed(1)} km',
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _Info(
                  icone: Icons.place_rounded,
                  texte: s.quartier ?? 'Quartier non précisé',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: pc.teinte(PcColors.tech),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Vous touchez',
                        style: TextStyle(
                          color: pc.muted,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                      PcMontant(
                        s.netTechnicienGnf,
                        taille: 22,
                        couleur: PcColors.tech,
                      ),
                    ],
                  ),
                ),
                Text(
                  'Client : ${s.totalFormate}',
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Text(
            // Dit avant d'accepter, pour que ce ne soit pas une surprise :
            // l'adresse exacte est réservée à celui qui s'est engagé.
            'L’adresse exacte s’affiche dès que vous acceptez.',
            textAlign: TextAlign.center,
            style: TextStyle(color: pc.muted, fontSize: 12),
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: PcButton(
                  libelle: 'Passer',
                  variante: PcVariante.doux,
                  onPressed: _restant > 0 && !_enCours ? _refuser : null,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: PcButton(
                  libelle: 'Accepter',
                  icone: Icons.check_rounded,
                  variante: PcVariante.tech,
                  chargement: _enCours,
                  onPressed: _restant > 0 ? _accepter : null,
                ),
              ),
            ],
          ),
        ],
      ),
    );
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: pc.soft,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icone, size: 14, color: pc.ink2),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              texte,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: pc.ink2,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le compte à rebours, en anneau et en chiffres : l'anneau se lit d'un coup
/// d'œil, le chiffre quand il ne reste que quelques secondes.
class _Chrono extends StatelessWidget {
  const _Chrono({required this.restant, required this.depart});

  final int restant;
  final int depart;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final double part = depart <= 0 ? 0 : (restant / depart).clamp(0.0, 1.0);
    final Color c = restant <= 10 ? PcColors.coral : PcColors.tech;

    return Semantics(
      label: '$restant secondes pour répondre',
      child: SizedBox(
        width: 52,
        height: 52,
        child: Stack(
          alignment: Alignment.center,
          children: <Widget>[
            SizedBox(
              width: 52,
              height: 52,
              child: CircularProgressIndicator(
                value: part,
                strokeWidth: 4,
                backgroundColor: pc.line,
                color: c,
              ),
            ),
            Text(
              '$restant',
              style: TextStyle(
                fontFamily: PcFonts.display,
                fontWeight: FontWeight.w700,
                fontSize: 17,
                color: c,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
