import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../../auth/data/utilisateur.dart';
import '../../notifications/application/notifications_providers.dart';
import '../../shared/mode/selecteur_mode.dart';

/// En-tête des onglets technicien : le nom de la personne (jamais « mode
/// technicien »), la cloche et, si le compte a les deux casquettes, la bascule.
class EnteteTechnicien extends ConsumerWidget {
  const EnteteTechnicien({required this.titre, this.sousTitre, super.key});

  final String titre;
  final String? sousTitre;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final Utilisateur? u = ref.watch(sessionProvider).utilisateur;
    final int nonLues = ref.watch(notificationsProvider).value?.nonLues ?? 0;
    final bool peutBasculer = (u?.modesDisponibles.length ?? 1) > 1;

    return Row(
      children: <Widget>[
        PcAvatar(
          nom: u?.nomComplet ?? '',
          image: u?.avatarUrl,
          taille: 44,
          couleurs: const <Color>[PcColors.techLight, PcColors.techDeep],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                sousTitre ?? u?.prenom ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12.5,
                ),
              ),
              Text(
                titre,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ],
          ),
        ),
        PcIconButton(
          icone: Icons.notifications_none_rounded,
          pastille: nonLues > 0,
          libelle: 'Notifications',
          onTap: () => context.push('/notifications'),
        ),
        if (peutBasculer) ...<Widget>[
          const SizedBox(width: 8),
          PcIconButton(
            icone: Icons.swap_horiz_rounded,
            libelle: 'Changer d’espace',
            onTap: () => SelecteurMode.ouvrir(context),
          ),
        ],
      ],
    );
  }
}

/// Marges d'un onglet technicien : sous la barre d'état, au-dessus de la barre
/// d'onglets flottante.
EdgeInsets margesOnglet(BuildContext context) => EdgeInsets.fromLTRB(
  18,
  MediaQuery.of(context).padding.top + 10,
  18,
  PcTabBar.espaceReserve,
);

/// Petit chiffre-clé sur fond de carte (note, interventions, taux…).
class ChiffreTech extends StatelessWidget {
  const ChiffreTech({
    required this.valeur,
    required this.libelle,
    this.icone,
    this.couleur,
    super.key,
  });

  final String valeur;
  final String libelle;
  final IconData? icone;
  final Color? couleur;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Expanded(
      child: PcCard(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
        child: Column(
          children: <Widget>[
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                if (icone != null) ...<Widget>[
                  Icon(icone, size: 15, color: couleur ?? pc.accent),
                  const SizedBox(width: 4),
                ],
                Flexible(
                  child: Text(
                    valeur,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontFamily: PcFonts.display,
                      fontWeight: FontWeight.w700,
                      fontSize: 18,
                      color: pc.ink,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              libelle,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: pc.muted,
                fontWeight: FontWeight.w600,
                fontSize: 11.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Affiche une photo en plein écran, avec zoom.
void ouvrirPhoto(BuildContext context, String url) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(
      fullscreenDialog: true,
      builder: (BuildContext ctx) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          foregroundColor: Colors.white,
        ),
        body: Center(
          child: InteractiveViewer(
            child: Image.network(
              url,
              errorBuilder: (_, _, _) => const Icon(
                Icons.broken_image_outlined,
                color: Colors.white54,
                size: 48,
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

/// Bande de photos jointes par le client, touchables pour agrandir.
class BandePhotos extends StatelessWidget {
  const BandePhotos({required this.photos, super.key});

  final List<String> photos;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    // Seules les adresses web s'affichent : un chemin brut n'est pas une image
    // que l'appareil sait aller chercher.
    final List<String> urls = photos
        .where((String p) => p.startsWith('http'))
        .toList();

    if (urls.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 84,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: urls.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (BuildContext context, int i) => Semantics(
          button: true,
          label: 'Photo ${i + 1} du client, agrandir',
          child: PcPressable(
            onTap: () => ouvrirPhoto(context, urls[i]),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Container(
                width: 84,
                color: pc.soft,
                child: Image.network(
                  urls[i],
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) =>
                      Icon(Icons.broken_image_outlined, color: pc.muted),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Glisser pour confirmer : le geste principal d'une intervention (« je
/// pars », « je suis arrivé »…) ne part pas d'un effleurement accidentel dans
/// une poche. Un lecteur d'écran, lui, l'active d'un simple appui (`onTap` de
/// la sémantique) : glisser à l'aveugle n'est pas un geste raisonnable.
class GlisserPourConfirmer extends StatefulWidget {
  const GlisserPourConfirmer({
    required this.libelle,
    required this.onConfirme,
    this.chargement = false,
    super.key,
  });

  final String libelle;
  final VoidCallback? onConfirme;
  final bool chargement;

  @override
  State<GlisserPourConfirmer> createState() => _GlisserPourConfirmerState();
}

class _GlisserPourConfirmerState extends State<GlisserPourConfirmer> {
  static const double _hauteur = 60;
  double _decalage = 0;

  @override
  void didUpdateWidget(GlisserPourConfirmer ancien) {
    super.didUpdateWidget(ancien);
    // Le geste a abouti (ou échoué) : la poignée revient au départ.
    if (ancien.chargement && !widget.chargement) _decalage = 0;
    if (ancien.libelle != widget.libelle) _decalage = 0;
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool actif = widget.onConfirme != null && !widget.chargement;

    return Semantics(
      button: true,
      enabled: actif,
      label: widget.libelle,
      onTap: actif ? widget.onConfirme : null,
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints c) {
          final double course = c.maxWidth - _hauteur;
          final double part = course <= 0 ? 0 : _decalage / course;

          return Container(
            height: _hauteur,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(_hauteur / 2),
              color: Color.lerp(
                pc.teinte(PcColors.tech, 0.22),
                PcColors.tech,
                part,
              ),
            ),
            child: Stack(
              alignment: Alignment.centerLeft,
              children: <Widget>[
                Center(
                  child: Opacity(
                    opacity: (1 - part * 1.4).clamp(0.0, 1.0),
                    child: Text(
                      widget.chargement ? 'Envoi…' : widget.libelle,
                      style: TextStyle(
                        fontFamily: PcFonts.display,
                        fontWeight: FontWeight.w700,
                        fontSize: 15.5,
                        color: pc.ink,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  left: _decalage,
                  child: GestureDetector(
                    onHorizontalDragUpdate: actif
                        ? (DragUpdateDetails d) => setState(
                            () => _decalage = (_decalage + d.delta.dx).clamp(
                              0.0,
                              course,
                            ),
                          )
                        : null,
                    onHorizontalDragEnd: actif
                        ? (_) {
                            if (_decalage >= course * 0.85) {
                              setState(() => _decalage = course);
                              HapticFeedback.mediumImpact();
                              widget.onConfirme!();
                            } else {
                              setState(() => _decalage = 0);
                            }
                          }
                        : null,
                    child: Container(
                      width: _hauteur,
                      height: _hauteur,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: actif ? PcColors.tech : pc.muted,
                        border: Border.all(color: pc.bg, width: 4),
                      ),
                      child: widget.chargement
                          ? const Padding(
                              padding: EdgeInsets.all(16),
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(
                              Icons.double_arrow_rounded,
                              color: Colors.white,
                            ),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
