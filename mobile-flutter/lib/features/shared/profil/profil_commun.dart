import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/mise_a_jour/mise_a_jour.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../../auth/data/utilisateur.dart';

/// Pièces communes aux profils client et technicien (point 5 des notes) :
/// photo, nom, mot de passe, mise à jour, déconnexion.
///
/// L'en-tête porte **le nom de la personne**, jamais « mode client » ou
/// « mode technicien » : c'est la couleur de l'espace qui dit où l'on est.
class EnteteProfil extends ConsumerStatefulWidget {
  const EnteteProfil({this.sousTitre, super.key});

  /// Une ligne sous le nom (« Technicien · Plomberie », par exemple).
  final String? sousTitre;

  @override
  ConsumerState<EnteteProfil> createState() => _EnteteProfilState();
}

class _EnteteProfilState extends ConsumerState<EnteteProfil> {
  bool _envoi = false;

  Future<void> _changerPhoto() async {
    final ImageSource? source = await showPcSheet<ImageSource>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Photo de profil', style: Theme.of(ctx).textTheme.titleLarge),
          const SizedBox(height: 6),
          Text(
            'Visible par l’autre partie d’une intervention. La position '
            'enregistrée dans la photo est effacée avant l’envoi.',
            style: TextStyle(color: ctx.pc.muted, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 16),
          PcButton(
            libelle: 'Prendre une photo',
            icone: Icons.photo_camera_rounded,
            onPressed: () => Navigator.of(ctx).pop(ImageSource.camera),
          ),
          const SizedBox(height: 10),
          PcButton(
            libelle: 'Choisir dans la galerie',
            icone: Icons.photo_library_rounded,
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(ImageSource.gallery),
          ),
          if ((ref.read(sessionProvider).utilisateur?.avatarUrl ?? '')
              .isNotEmpty) ...<Widget>[
            const SizedBox(height: 6),
            TextButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('Annuler'),
            ),
            TextButton(
              style: TextButton.styleFrom(foregroundColor: PcColors.coral),
              onPressed: () {
                Navigator.of(ctx).pop();
                _retirerPhoto();
              },
              child: const Text('Retirer ma photo'),
            ),
          ],
        ],
      ),
    );

    if (source == null) return;

    final XFile? fichier = await ImagePicker().pickImage(
      source: source,
      // Réduite sur l'appareil : moins de forfait consommé. Le serveur
      // recadre et ré-encode de toute façon.
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 85,
    );

    if (fichier == null) return;

    await _envoyer(
      () => ref.read(authRepositoryProvider).changerAvatar(fichier.path),
      'Photo mise à jour.',
    );
  }

  Future<void> _retirerPhoto() => _envoyer(
    () => ref.read(authRepositoryProvider).retirerAvatar(),
    'Photo retirée.',
  );

  Future<void> _envoyer(
    Future<Utilisateur> Function() action,
    String succes,
  ) async {
    setState(() => _envoi = true);

    try {
      final Utilisateur u = await action();
      ref.read(sessionProvider.notifier).mettreAJour(u);
      if (mounted) showPcToast(context, succes);
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
    final String nom = u?.nomComplet ?? '';

    return Column(
      children: <Widget>[
        Semantics(
          button: true,
          label: 'Changer ma photo de profil',
          child: PcPressable(
            onTap: _envoi ? null : _changerPhoto,
            child: Stack(
              clipBehavior: Clip.none,
              children: <Widget>[
                Container(
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: pc.gradientAccent,
                  ),
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: pc.bg,
                    ),
                    child: PcAvatar(nom: nom, image: u?.avatarUrl, taille: 92),
                  ),
                ),
                Positioned(
                  right: 0,
                  bottom: 2,
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: pc.accent,
                      shape: BoxShape.circle,
                      border: Border.all(color: pc.bg, width: 3),
                    ),
                    child: _envoi
                        ? const Padding(
                            padding: EdgeInsets.all(7),
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(
                            Icons.photo_camera_rounded,
                            size: 15,
                            color: Colors.white,
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Text(
          nom,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 2),
        Text(
          widget.sousTitre ?? u?.telephoneAffichage ?? '',
          textAlign: TextAlign.center,
          style: TextStyle(color: pc.muted, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}

/// Une ligne de menu du profil.
class LigneProfil extends StatelessWidget {
  const LigneProfil({
    required this.icone,
    required this.libelle,
    this.detail,
    this.onTap,
    this.couleur,
    this.fin,
    super.key,
  });

  final IconData icone;
  final String libelle;
  final String? detail;
  final VoidCallback? onTap;
  final Color? couleur;
  final Widget? fin;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = couleur ?? pc.accent;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 11),
        child: Row(
          children: <Widget>[
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: pc.teinte(c),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icone, size: 19, color: c),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    libelle,
                    style: TextStyle(
                      color: couleur ?? pc.ink,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                  if (detail != null)
                    Text(
                      detail!,
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w500,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
            ),
            fin ??
                (onTap == null
                    ? const SizedBox.shrink()
                    : Icon(Icons.chevron_right_rounded, color: pc.muted)),
          ],
        ),
      ),
    );
  }
}

/// Groupe de lignes dans une carte, séparées d'un filet.
class GroupeProfil extends StatelessWidget {
  const GroupeProfil({required this.lignes, super.key});

  final List<Widget> lignes;

  @override
  Widget build(BuildContext context) {
    return PcCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Column(
        children: <Widget>[
          for (int i = 0; i < lignes.length; i++) ...<Widget>[
            lignes[i],
            if (i < lignes.length - 1)
              Divider(height: 1, color: context.pc.line),
          ],
        ],
      ),
    );
  }
}

/// Lignes « compte » communes : nom, mot de passe, mise à jour, déconnexion.
class LignesCompte extends ConsumerWidget {
  const LignesCompte({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<EtatMiseAJour> maj = ref.watch(miseAJourProvider);
    final EtatMiseAJour? etat = maj.value;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        const PcSection('Compte'),
        GroupeProfil(
          lignes: <Widget>[
            LigneProfil(
              icone: Icons.badge_outlined,
              libelle: 'Modifier mon nom',
              onTap: () => _modifierNom(context, ref),
            ),
            LigneProfil(
              icone: Icons.lock_outline_rounded,
              libelle: 'Changer mon mot de passe',
              detail: 'Vos autres appareils seront déconnectés',
              onTap: () => _changerMotDePasse(context, ref),
            ),
          ],
        ),
        const PcSection('Application'),
        GroupeProfil(
          lignes: <Widget>[
            LigneProfil(
              icone: Icons.system_update_rounded,
              libelle: etat?.disponible ?? false
                  ? 'Mise à jour disponible'
                  : 'Rechercher une mise à jour',
              detail: etat == null
                  ? 'Vérification…'
                  : etat.disponible
                  ? 'Version ${etat.derniere} · vous avez ${etat.installee}'
                  : 'Version ${etat.installee} · à jour',
              couleur: etat?.disponible ?? false ? PcColors.coral : null,
              fin: etat?.disponible ?? false
                  ? const PcPill(
                      'Nouveau',
                      couleur: PcColors.coral,
                      plein: true,
                    )
                  : null,
              onTap: () => _verifierMiseAJour(context, ref),
            ),
            LigneProfil(
              icone: Icons.logout_rounded,
              libelle: 'Se déconnecter',
              couleur: PcColors.coral,
              onTap: () => _deconnecter(context, ref),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _verifierMiseAJour(BuildContext context, WidgetRef ref) async {
    ref.invalidate(miseAJourProvider);
    final EtatMiseAJour etat = await ref.read(miseAJourProvider.future);
    if (!context.mounted) return;

    if (!etat.disponible || etat.urlTelechargement == null) {
      showPcToast(context, 'Vous avez la dernière version.');
      return;
    }

    final bool? installer = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const PcIco(Icons.system_update_rounded, couleur: PcColors.coral),
          const SizedBox(height: 12),
          Text(
            'Version ${etat.derniere} disponible',
            style: Theme.of(ctx).textTheme.titleLarge,
          ),
          const SizedBox(height: 6),
          Text(
            'Vous utilisez la version ${etat.installee}. Le téléchargement '
            's’ouvre dans votre navigateur ; installez ensuite le fichier.',
            style: TextStyle(color: ctx.pc.muted, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Télécharger la mise à jour',
            icone: Icons.download_rounded,
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

    if (installer == true) {
      await launchUrl(
        Uri.parse(etat.urlTelechargement!),
        mode: LaunchMode.externalApplication,
      );
    }
  }

  Future<void> _modifierNom(BuildContext context, WidgetRef ref) async {
    final TextEditingController champ = TextEditingController(
      text: ref.read(sessionProvider).utilisateur?.nomComplet ?? '',
    );

    final String? nom = await showPcSheet<String>(
      context,
      builder: (BuildContext ctx) => StatefulBuilder(
        builder: (BuildContext ctx, StateSetter setState) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text('Votre nom', style: Theme.of(ctx).textTheme.titleLarge),
            const SizedBox(height: 14),
            PcField(
              libelle: 'Nom complet',
              icone: Icons.person_outline_rounded,
              controleur: champ,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 14),
            PcButton(
              libelle: 'Enregistrer',
              onPressed: champ.text.trim().length >= 3
                  ? () => Navigator.of(ctx).pop(champ.text.trim())
                  : null,
            ),
          ],
        ),
      ),
    ).whenComplete(champ.dispose);

    if (nom == null) return;

    try {
      final Utilisateur u = await ref
          .read(authRepositoryProvider)
          .modifierNom(nom);
      ref.read(sessionProvider.notifier).mettreAJour(u);
      if (context.mounted) showPcToast(context, 'Nom mis à jour.');
    } on ApiException catch (e) {
      if (context.mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    }
  }

  Future<void> _changerMotDePasse(BuildContext context, WidgetRef ref) async {
    final TextEditingController actuel = TextEditingController();
    final TextEditingController nouveau = TextEditingController();

    final bool? valide = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => StatefulBuilder(
        builder: (BuildContext ctx, StateSetter setState) {
          final String n = nouveau.text;
          final bool conforme =
              n.length >= 8 &&
              RegExp('[A-Z]').hasMatch(n) &&
              RegExp('[0-9]').hasMatch(n);

          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                'Changer de mot de passe',
                style: Theme.of(ctx).textTheme.titleLarge,
              ),
              const SizedBox(height: 14),
              PcField(
                libelle: 'Mot de passe actuel',
                icone: Icons.lock_outline_rounded,
                motDePasse: true,
                controleur: actuel,
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: 12),
              PcField(
                libelle: 'Nouveau mot de passe',
                icone: Icons.lock_reset_rounded,
                indice: '8 caractères, une majuscule, un chiffre',
                motDePasse: true,
                controleur: nouveau,
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: 14),
              PcButton(
                libelle: 'Changer',
                onPressed: actuel.text.isNotEmpty && conforme
                    ? () => Navigator.of(ctx).pop(true)
                    : null,
              ),
            ],
          );
        },
      ),
    );

    try {
      if (valide != true) return;
      await ref
          .read(authRepositoryProvider)
          .changerMotDePasse(actuel: actuel.text, nouveau: nouveau.text);
      if (context.mounted) showPcToast(context, 'Mot de passe changé.');
    } on ApiException catch (e) {
      if (context.mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      actuel.dispose();
      nouveau.dispose();
    }
  }

  Future<void> _deconnecter(BuildContext context, WidgetRef ref) async {
    final bool? ok = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Se déconnecter ?', style: Theme.of(ctx).textTheme.titleLarge),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Se déconnecter',
            variante: PcVariante.corail,
            onPressed: () => Navigator.of(ctx).pop(true),
          ),
          const SizedBox(height: 8),
          PcButton(
            libelle: 'Rester connecté',
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(false),
          ),
        ],
      ),
    );

    if (ok == true) await ref.read(sessionProvider.notifier).deconnecter();
  }
}
