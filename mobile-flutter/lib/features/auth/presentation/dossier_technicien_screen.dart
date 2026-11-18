import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/app_mode.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../core/temps_reel/temps_reel_service.dart';
import '../../../design_system/pc.dart';
import '../../client/application/client_providers.dart';
import '../../client/presentation/selecteur_carte.dart';
import '../../shared/data/modeles.dart';
import '../../technicien/application/technicien_providers.dart';
import '../data/utilisateur.dart';

/// Le dossier professionnel du technicien (point 5 des notes du client).
///
/// Trois visages, selon ce que le serveur dit du dossier :
///
/// - **à déposer** (ou **rejeté**, avec le motif) : spécialités, point de
///   départ et rayon, puis les trois photos — recto, verso, selfie — envoyées
///   une à une sur le disque privé du serveur ;
/// - **en examen** : l'écran attend, et **bascule tout seul** à la décision.
///   La notification poussée sur `utilisateur.{id}` déclenche une relecture du
///   compte, et une relecture toutes les trente secondes couvre l'absence de
///   WebSocket. Personne n'a à se reconnecter pour découvrir qu'il est validé.
class DossierTechnicienScreen extends ConsumerWidget {
  const DossierTechnicienScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final Utilisateur? u = ref.watch(sessionProvider).utilisateur;
    final bool enExamen =
        (u?.dossierDepose ?? false) &&
        u?.verificationTechnicien == 'EN_ATTENTE_VALIDATION';

    return Scaffold(
      body: SafeArea(child: enExamen ? const _EnExamen() : const _Formulaire()),
    );
  }
}

/// En-tête commun : titre, et les deux portes de sortie (mode client si le
/// compte l'a, déconnexion).
class _Entete extends ConsumerWidget {
  const _Entete({required this.titre, required this.sousTitre});

  final String titre;
  final String sousTitre;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bool aLeModeClient =
        ref.watch(sessionProvider).utilisateur?.estClient ?? false;

    return PcHeader(
      titre: titre,
      sousTitre: sousTitre,
      retour: false,
      action: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (aLeModeClient) ...<Widget>[
            PcIconButton(
              icone: Icons.swap_horiz_rounded,
              libelle: 'Continuer en client',
              onTap: () async {
                await ref.read(modeProvider.notifier).basculer(AppMode.client);
                if (context.mounted) context.go('/accueil');
              },
            ),
            const SizedBox(width: 8),
          ],
          PcIconButton(
            icone: Icons.logout_rounded,
            libelle: 'Se déconnecter',
            onTap: () => ref.read(sessionProvider.notifier).deconnecter(),
          ),
        ],
      ),
    );
  }
}

// ================================================================ examen ====

class _EnExamen extends ConsumerStatefulWidget {
  const _EnExamen();

  @override
  ConsumerState<_EnExamen> createState() => _EnExamenState();
}

class _EnExamenState extends ConsumerState<_EnExamen>
    with SingleTickerProviderStateMixin {
  static const Duration _repli = Duration(seconds: 30);

  late final AnimationController _anim = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 2),
  );
  Timer? _minuteur;
  bool _verification = false;

  @override
  void initState() {
    super.initState();
    _minuteur = Timer.periodic(_repli, (_) => _verifier());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _anim.stop();
    } else if (!_anim.isAnimating) {
      _anim.repeat();
    }
  }

  @override
  void dispose() {
    _minuteur?.cancel();
    _anim.dispose();
    super.dispose();
  }

  Future<void> _verifier() async {
    if (_verification) return;
    setState(() => _verification = true);
    await ref.read(sessionProvider.notifier).rafraichir();
    if (mounted) setState(() => _verification = false);
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final int? id = ref.watch(sessionProvider).utilisateur?.id;

    // La décision du support arrive en notification : on relit le compte, et
    // le routeur emmène vers le tableau de bord si le dossier est validé.
    ref.listen(signauxProvider('utilisateur.$id'), (
      _,
      AsyncValue<EvenementTempsReel> signal,
    ) {
      if (signal.value?.nom == 'notification') _verifier();
    });

    return ListView(
      padding: const EdgeInsets.fromLTRB(0, 0, 0, 28),
      children: <Widget>[
        const _Entete(
          titre: 'Dossier en examen',
          sousTitre: 'Vous serez prévenu dès la décision',
        ),
        const SizedBox(height: 24),
        Center(
          child: SizedBox(
            width: 150,
            height: 150,
            child: AnimatedBuilder(
              animation: _anim,
              builder: (BuildContext context, Widget? _) => Stack(
                alignment: Alignment.center,
                children: <Widget>[
                  Opacity(
                    opacity: 1 - _anim.value,
                    child: Container(
                      width: 80 + 70 * _anim.value,
                      height: 80 + 70 * _anim.value,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: pc.accent, width: 2),
                      ),
                    ),
                  ),
                  Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: pc.gradientAccent,
                      boxShadow: PcShadow.glow(pc.accent),
                    ),
                    child: const Icon(
                      Icons.hourglass_top_rounded,
                      color: Colors.white,
                      size: 34,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: 18),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28),
          child: Text(
            'Notre équipe vérifie vos pièces, en général sous 48 heures '
            'ouvrées. Cet écran se met à jour tout seul : inutile de vous '
            'reconnecter.',
            textAlign: TextAlign.center,
            style: TextStyle(color: pc.ink2, fontWeight: FontWeight.w600),
          ),
        ),
        const SizedBox(height: 24),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: PcCard(
            child: Column(
              children: <Widget>[
                _Jalon(
                  icone: Icons.cloud_done_rounded,
                  titre: 'Dossier déposé',
                  texte: 'Spécialités, zone et pièces reçues.',
                  etat: _EtatJalon.fait,
                ),
                _Jalon(
                  icone: Icons.fact_check_rounded,
                  titre: 'Vérification par l’équipe',
                  texte: 'Pièce d’identité comparée au selfie.',
                  etat: _EtatJalon.enCours,
                ),
                _Jalon(
                  icone: Icons.rocket_launch_rounded,
                  titre: 'Mise en ligne',
                  texte: 'Vous recevez vos premières demandes.',
                  etat: _EtatJalon.aVenir,
                  dernier: true,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 18),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: PcButton(
            libelle: 'Vérifier maintenant',
            icone: Icons.refresh_rounded,
            variante: PcVariante.doux,
            chargement: _verification,
            onPressed: _verifier,
          ),
        ),
      ],
    );
  }
}

enum _EtatJalon { fait, enCours, aVenir }

class _Jalon extends StatelessWidget {
  const _Jalon({
    required this.icone,
    required this.titre,
    required this.texte,
    required this.etat,
    this.dernier = false,
  });

  final IconData icone;
  final String titre;
  final String texte;
  final _EtatJalon etat;
  final bool dernier;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = switch (etat) {
      _EtatJalon.fait => PcColors.tech,
      _EtatJalon.enCours => pc.accent,
      _EtatJalon.aVenir => pc.muted,
    };

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Column(
            children: <Widget>[
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: etat == _EtatJalon.aVenir ? pc.soft : c,
                ),
                child: Icon(
                  etat == _EtatJalon.fait ? Icons.check_rounded : icone,
                  size: 17,
                  color: etat == _EtatJalon.aVenir ? pc.muted : Colors.white,
                ),
              ),
              if (!dernier)
                Expanded(child: Container(width: 2, color: pc.line)),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: dernier ? 0 : 16, top: 6),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    titre,
                    style: TextStyle(
                      color: etat == _EtatJalon.aVenir ? pc.muted : pc.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    texte,
                    style: TextStyle(color: pc.muted, fontSize: 12.5),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================ formulaire ====

class _Formulaire extends ConsumerStatefulWidget {
  const _Formulaire();

  @override
  ConsumerState<_Formulaire> createState() => _FormulaireState();
}

class _FormulaireState extends ConsumerState<_Formulaire> {
  static const LatLng _conakry = LatLng(9.6412, -13.5784);

  final Set<String> _specialites = <String>{};
  LatLng? _point;
  double _rayon = 5;
  final Map<String, String> _chemins = <String, String>{};
  final Map<String, String> _apercus = <String, String>{};
  final Set<String> _envois = <String>{};
  bool _localisation = false;
  bool _envoi = false;

  @override
  void initState() {
    super.initState();
    // Un dossier rejeté garde ce qui était juste : on ne refait que la pièce
    // en cause.
    final Utilisateur? u = ref.read(sessionProvider).utilisateur;
    for (final (String code, String _)
        in u?.profilTechnicien?.specialites ?? const <(String, String)>[]) {
      _specialites.add(code);
    }
    final int? rayon = u?.profilTechnicien?.rayonKm;
    if (rayon != null) _rayon = rayon.toDouble().clamp(1, 30);
  }

  bool get _complet =>
      _specialites.isNotEmpty &&
      _point != null &&
      _chemins.length == 3 &&
      _envois.isEmpty;

  Future<void> _maPosition() async {
    setState(() => _localisation = true);
    try {
      LocationPermission p = await Geolocator.checkPermission();
      if (p == LocationPermission.denied) {
        p = await Geolocator.requestPermission();
      }
      if (p == LocationPermission.denied ||
          p == LocationPermission.deniedForever) {
        if (mounted) {
          showPcToast(
            context,
            'Position refusée : choisissez votre quartier à la place.',
            icone: Icons.location_off_rounded,
          );
        }
        return;
      }
      final Position pos = await Geolocator.getCurrentPosition();
      if (mounted) {
        setState(() => _point = LatLng(pos.latitude, pos.longitude));
      }
    } on Object {
      if (mounted) {
        showPcToast(
          context,
          'Position introuvable : choisissez votre quartier.',
          icone: Icons.location_off_rounded,
        );
      }
    } finally {
      if (mounted) setState(() => _localisation = false);
    }
  }

  Future<void> _photographier(String type) async {
    final XFile? fichier = await ImagePicker().pickImage(
      source: ImageSource.camera,
      preferredCameraDevice: type == 'selfie'
          ? CameraDevice.front
          : CameraDevice.rear,
      maxWidth: 2000,
      maxHeight: 2000,
      imageQuality: 85,
    );
    if (fichier == null) return;

    setState(() {
      _envois.add(type);
      _apercus[type] = fichier.path;
      _chemins.remove(type);
    });

    try {
      final String chemin = await ref
          .read(technicienRepositoryProvider)
          .deposerPiece(type, fichier.path);
      if (mounted) setState(() => _chemins[type] = chemin);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _apercus.remove(type));
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _envois.remove(type));
    }
  }

  Future<void> _envoyer() async {
    if (!_complet || _envoi) return;
    setState(() => _envoi = true);

    try {
      await ref
          .read(technicienRepositoryProvider)
          .envoyerDossier(
            specialites: _specialites.toList(),
            latitude: _point!.latitude,
            longitude: _point!.longitude,
            rayon: _rayon.round(),
            recto: _chemins['recto']!,
            verso: _chemins['verso']!,
            selfie: _chemins['selfie']!,
          );
      // Le compte relu dit « déposé, en examen » : l'écran bascule seul.
      await ref.read(sessionProvider.notifier).rafraichir();
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _envoi = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Utilisateur? u = ref.watch(sessionProvider).utilisateur;
    final bool rejete = u?.verificationTechnicien == 'REJETE';
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );
    final AsyncValue<List<QuartierCouvert>> quartiers = ref.watch(
      quartiersProvider,
    );

    return Column(
      children: <Widget>[
        _Entete(
          titre: rejete ? 'Dossier à reprendre' : 'Votre dossier',
          sousTitre: 'Trois étapes, environ cinq minutes',
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(18, 0, 18, 24),
            children: <Widget>[
              if (rejete)
                PcCard(
                  bordure: PcColors.coral.withValues(alpha: 0.5),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const PcIco(
                        Icons.error_outline_rounded,
                        couleur: PcColors.coral,
                        taille: 42,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Text(
                              'Le support demande une correction',
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              u?.motifRejet ??
                                  'Une pièce n’a pas pu être '
                                      'validée.',
                              style: TextStyle(color: pc.ink2, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

              // ---- 1. Métiers -------------------------------------------
              const _Etape(numero: 1, titre: 'Vos métiers'),
              catalogue.when(
                loading: () => const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (Object e, _) => PcErreur(
                  message: messageErreur(e),
                  onReessayer: () => ref.invalidate(catalogueProvider),
                ),
                data: (List<CategoriePrestations> categories) => Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    for (final CategoriePrestations c in categories)
                      PcChip(
                        libelle: c.nom,
                        icone: PcMetier.de(c.code).icone,
                        selectionne: _specialites.contains(c.code),
                        onTap: () => setState(
                          () => _specialites.contains(c.code)
                              ? _specialites.remove(c.code)
                              : _specialites.add(c.code),
                        ),
                      ),
                  ],
                ),
              ),

              // ---- 2. Zone ----------------------------------------------
              const _Etape(numero: 2, titre: 'Votre zone d’intervention'),
              Row(
                children: <Widget>[
                  Expanded(
                    child: PcButton(
                      libelle: 'Ma position',
                      icone: Icons.my_location_rounded,
                      petit: true,
                      variante: PcVariante.doux,
                      chargement: _localisation,
                      onPressed: _maPosition,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: quartiers.maybeWhen(
                      data: (List<QuartierCouvert> liste) => _ChoixQuartier(
                        quartiers: liste,
                        onChoisir: (QuartierCouvert q) => setState(
                          () => _point = LatLng(q.latitude, q.longitude),
                        ),
                      ),
                      orElse: () => const SizedBox.shrink(),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              SelecteurCarte(
                point: _point ?? _conakry,
                onChange: (LatLng p) => setState(() => _point = p),
              ),
              const SizedBox(height: 6),
              Text(
                _point == null
                    ? 'Touchez la carte pour placer votre point de départ.'
                    : 'Point de départ placé. Touchez la carte pour l’ajuster.',
                style: TextStyle(
                  color: _point == null ? PcColors.coral : pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: <Widget>[
                  Text(
                    'Rayon',
                    style: TextStyle(
                      color: pc.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Expanded(
                    child: Slider(
                      value: _rayon,
                      min: 1,
                      max: 30,
                      divisions: 29,
                      label: '${_rayon.round()} km',
                      activeColor: pc.accent,
                      onChanged: (double v) => setState(() => _rayon = v),
                    ),
                  ),
                  SizedBox(
                    width: 52,
                    child: Text(
                      '${_rayon.round()} km',
                      textAlign: TextAlign.end,
                      style: TextStyle(
                        color: pc.ink,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),

              // ---- 3. Pièces --------------------------------------------
              const _Etape(numero: 3, titre: 'Vos pièces'),
              Text(
                'Photos nettes, sans reflet. Elles restent privées : seule '
                'l’équipe de validation y a accès.',
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: <Widget>[
                  for (final (String type, String libelle, IconData icone)
                      in const <(String, String, IconData)>[
                        ('recto', 'Pièce · recto', Icons.badge_outlined),
                        ('verso', 'Pièce · verso', Icons.flip_rounded),
                        ('selfie', 'Selfie', Icons.face_rounded),
                      ]) ...<Widget>[
                    Expanded(
                      child: _TuilePiece(
                        libelle: libelle,
                        icone: icone,
                        apercu: _apercus[type],
                        envoi: _envois.contains(type),
                        recue: _chemins.containsKey(type),
                        onTap: _envois.contains(type)
                            ? null
                            : () => _photographier(type),
                      ),
                    ),
                    if (type != 'selfie') const SizedBox(width: 10),
                  ],
                ],
              ),
              const SizedBox(height: 24),
              PcButton(
                libelle: 'Envoyer mon dossier',
                icone: Icons.send_rounded,
                chargement: _envoi,
                onPressed: _complet ? _envoyer : null,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _Etape extends StatelessWidget {
  const _Etape({required this.numero, required this.titre});

  final int numero;
  final String titre;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Padding(
      padding: const EdgeInsets.fromLTRB(0, 22, 0, 10),
      child: Row(
        children: <Widget>[
          Container(
            width: 26,
            height: 26,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: pc.gradientAccent,
            ),
            child: Text(
              '$numero',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Text(titre, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    );
  }
}

class _ChoixQuartier extends StatelessWidget {
  const _ChoixQuartier({required this.quartiers, required this.onChoisir});

  final List<QuartierCouvert> quartiers;
  final ValueChanged<QuartierCouvert> onChoisir;

  @override
  Widget build(BuildContext context) {
    return PcButton(
      libelle: 'Un quartier',
      icone: Icons.place_outlined,
      petit: true,
      variante: PcVariante.doux,
      onPressed: () async {
        final QuartierCouvert? q = await showPcSheet<QuartierCouvert>(
          context,
          builder: (BuildContext ctx) => Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                'Votre quartier de départ',
                style: Theme.of(ctx).textTheme.titleLarge,
              ),
              const SizedBox(height: 10),
              for (final QuartierCouvert q in quartiers)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.place_outlined),
                  title: Text(q.nom),
                  subtitle: Text(q.commune),
                  onTap: () => Navigator.of(ctx).pop(q),
                ),
            ],
          ),
        );
        if (q != null) onChoisir(q);
      },
    );
  }
}

class _TuilePiece extends StatelessWidget {
  const _TuilePiece({
    required this.libelle,
    required this.icone,
    required this.apercu,
    required this.envoi,
    required this.recue,
    required this.onTap,
  });

  final String libelle;
  final IconData icone;
  final String? apercu;
  final bool envoi;
  final bool recue;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Semantics(
      button: true,
      label: recue ? '$libelle, reçue. Reprendre la photo' : libelle,
      child: PcPressable(
        onTap: onTap,
        child: AspectRatio(
          aspectRatio: 0.8,
          child: Container(
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              color: pc.soft,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: recue ? PcColors.tech : pc.line,
                width: recue ? 2 : 1,
              ),
            ),
            child: Stack(
              fit: StackFit.expand,
              children: <Widget>[
                if (apercu != null)
                  Image.file(File(apercu!), fit: BoxFit.cover)
                else
                  Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      Icon(icone, color: pc.muted, size: 28),
                      const SizedBox(height: 6),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 6),
                        child: Text(
                          libelle,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: pc.ink2,
                            fontWeight: FontWeight.w700,
                            fontSize: 11.5,
                          ),
                        ),
                      ),
                    ],
                  ),
                if (envoi)
                  Container(
                    color: Colors.black38,
                    alignment: Alignment.center,
                    child: const CircularProgressIndicator(color: Colors.white),
                  ),
                if (recue)
                  const Positioned(
                    top: 6,
                    right: 6,
                    child: CircleAvatar(
                      radius: 11,
                      backgroundColor: PcColors.tech,
                      child: Icon(Icons.check, size: 14, color: Colors.white),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
