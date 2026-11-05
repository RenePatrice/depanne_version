import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../auth/application/session_controller.dart';
import '../../shared/data/modeles.dart';
import '../application/technicien_providers.dart';
import '../data/modeles.dart';

/// Disponibilité et sollicitation en cours (§7.3, §8.3).
///
/// L'écran ne montre **qu'une** demande à la fois : le matching est
/// séquentiel, et une file de demandes concurrentes serait un mensonge sur ce
/// que le technicien peut réellement accepter.
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
    final Session session = ref.watch(sessionProvider);

    ref.listen(signauxProvider('utilisateur.${session.utilisateur?.id}'), (
      _,
      AsyncValue<EvenementTempsReel> suivant,
    ) {
      if (_signaux.contains(suivant.value?.donnees['type'])) {
        ref.invalidate(sollicitationsProvider);
        ref.invalidate(interventionsProvider);
      }
    });

    final Disponibilite dispo = ref.watch(disponibiliteProvider);
    final AsyncValue<List<Sollicitation>> sollicitations = ref.watch(
      sollicitationsProvider(false),
    );

    // L'état connu du compte amorce l'interrupteur : sans cela, un technicien
    // déjà en ligne verrait « hors ligne » au lancement et se remettrait en
    // ligne pour rien.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final bool? connu = session.utilisateur?.enLigne;

      if (connu != null) {
        ref.read(disponibiliteProvider.notifier).amorcer(connu);
      }
    });

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(sollicitationsProvider),
      child: ListView(
        padding: const EdgeInsets.all(DmSpace.x4),
        children: <Widget>[
          _Interrupteur(disponibilite: dispo),
          const SizedBox(height: DmSpace.x4),

          sollicitations.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(DmSpace.x8),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => DmErreur(
              message: messageErreur(e),
              onReessayer: () => ref.invalidate(sollicitationsProvider),
            ),
            data: (List<Sollicitation> liste) {
              final Iterable<Sollicitation> vivantes = liste.where(
                (Sollicitation s) => s.estVivante,
              );

              if (vivantes.isEmpty) {
                return _Attente(enLigne: dispo.enLigne);
              }

              return _CarteSollicitation(sollicitation: vivantes.first);
            },
          ),

          const SizedBox(height: DmSpace.x8),
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
    final ThemeData theme = Theme.of(context);
    final bool enLigne = disponibilite.enLigne;

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                height: 12,
                width: 12,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: enLigne ? DmColors.success : DmColors.neutral500,
                ),
              ),
              const SizedBox(width: DmSpace.x3),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      enLigne ? 'En ligne' : 'Hors ligne',
                      style: theme.textTheme.titleMedium,
                    ),
                    Text(
                      enLigne
                          ? 'Tu reçois les demandes proches de toi.'
                          : 'Tu ne reçois aucune demande.',
                      style: theme.textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              if (disponibilite.enCours)
                const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2.5),
                )
              else
                Switch(
                  value: enLigne,
                  onChanged: (bool v) =>
                      ref.read(disponibiliteProvider.notifier).basculer(v),
                ),
            ],
          ),

          // Le refus du serveur est affiché tel quel : « ton dossier est en
          // cours de validation » ou « tu as une intervention en cours » sont
          // des phrases utiles, un « échec » générique ne l'est pas.
          if (disponibilite.refus != null) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            DmErreur(message: disponibilite.refus!),
          ],
        ],
      ),
    );
  }
}

class _Attente extends StatelessWidget {
  const _Attente({required this.enLigne});

  final bool enLigne;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: DmSpace.x8),
    child: DmVide(
      icone: enLigne
          ? Icons.notifications_active_outlined
          : Icons.bedtime_outlined,
      titre: enLigne ? 'En attente d\'une demande' : 'Tu es hors ligne',
      texte: enLigne
          ? 'Reste en ligne : la prochaine demande de ton quartier arrivera ici.'
          : 'Mets-toi en ligne pour recevoir les demandes autour de toi.',
    ),
  );
}

/// La demande, et les quarante-cinq secondes pour y répondre.
class _CarteSollicitation extends ConsumerStatefulWidget {
  const _CarteSollicitation({required this.sollicitation});

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
    // plutôt que sur l'horloge du téléphone, qui peut être décalée de
    // plusieurs minutes.
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
        // La fenêtre s'est refermée : le serveur a déjà relancé la recherche
        // ailleurs, l'écran doit cesser de proposer un bouton qui échouerait.
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
      // 409 : un autre technicien est passé devant. Ce n'est pas une panne, et
      // le message du serveur le dit mieux qu'un « erreur » sec.
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
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
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Sollicitation s = widget.sollicitation;

    return DmCarte(
      padding: const EdgeInsets.all(DmSpace.x6),
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          _Chrono(restant: _restant, depart: s.expireDansS),
          const SizedBox(height: DmSpace.x6),

          Text(
            s.prestation ?? 'Intervention',
            style: theme.textTheme.headlineSmall,
            textAlign: TextAlign.center,
          ),
          if (s.description != null) ...<Widget>[
            const SizedBox(height: DmSpace.x2),
            Text(
              s.description!,
              style: theme.textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ],

          const SizedBox(height: DmSpace.x6),
          Row(
            children: <Widget>[
              Expanded(
                child: _Chiffre(
                  libelle: 'Distance',
                  valeur: '${s.distanceKm.toStringAsFixed(1)} km',
                ),
              ),
              Expanded(
                child: _Chiffre(
                  libelle: 'Tu touches',
                  valeur: s.netFormate,
                  accentue: true,
                ),
              ),
            ],
          ),

          const SizedBox(height: DmSpace.x4),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              const Icon(
                Icons.place_outlined,
                size: 16,
                color: DmColors.neutral500,
              ),
              const SizedBox(width: DmSpace.x1),
              Flexible(
                child: Text(
                  s.quartier ?? 'Quartier non précisé',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: DmSpace.x1),
          Text(
            // Dit avant d'accepter, pour que ce ne soit pas une surprise : le
            // §7.3 réserve l'adresse exacte à celui qui s'est engagé.
            'L\'adresse exacte s\'affichera dès que tu auras accepté.',
            style: theme.textTheme.bodySmall,
            textAlign: TextAlign.center,
          ),

          const SizedBox(height: DmSpace.x6),
          DmBouton(
            libelle: 'J\'accepte',
            enCours: _enCours,
            onPresse: _restant > 0 ? _accepter : null,
          ),
          const SizedBox(height: DmSpace.x3),
          DmBouton(
            libelle: 'Je passe mon tour',
            secondaire: true,
            enCours: _enCours,
            onPresse: _restant > 0 ? _refuser : null,
          ),
        ],
      ),
    );
  }
}

/// Le compte à rebours, en anneau et en chiffres.
///
/// Les deux ensemble : l'anneau se lit d'un coup d'œil, le chiffre se lit
/// quand il ne reste que quelques secondes et qu'on veut savoir lesquelles.
class _Chrono extends StatelessWidget {
  const _Chrono({required this.restant, required this.depart});

  final int restant;
  final int depart;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final double part = depart <= 0 ? 0 : (restant / depart).clamp(0.0, 1.0);
    final bool presse = restant <= 10;

    return SizedBox(
      height: 96,
      width: 96,
      child: Stack(
        alignment: Alignment.center,
        children: <Widget>[
          SizedBox(
            height: 96,
            width: 96,
            child: CircularProgressIndicator(
              value: part,
              strokeWidth: 6,
              backgroundColor: theme.dividerColor,
              color: presse ? DmColors.danger : theme.colorScheme.primary,
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(
                '$restant',
                style: theme.textTheme.headlineMedium?.copyWith(
                  color: presse ? DmColors.danger : null,
                ),
              ),
              Text('secondes', style: theme.textTheme.labelSmall),
            ],
          ),
        ],
      ),
    );
  }
}

class _Chiffre extends StatelessWidget {
  const _Chiffre({
    required this.libelle,
    required this.valeur,
    this.accentue = false,
  });

  final String libelle;
  final String valeur;
  final bool accentue;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Column(
      children: <Widget>[
        Text(libelle, style: theme.textTheme.labelSmall),
        const SizedBox(height: DmSpace.x1),
        Text(
          valeur,
          style: theme.textTheme.titleLarge?.copyWith(
            color: accentue ? theme.colorScheme.primary : null,
          ),
        ),
      ],
    );
  }
}
