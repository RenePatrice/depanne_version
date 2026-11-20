import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/format/montant.dart';
import '../pc_skin.dart';
import '../pc_tokens.dart';

/// Rend un widget « vivant » au toucher : il s'enfonce légèrement, comme une
/// touche. C'est ce qui donne aux cartes leur sensation de fluidité.
class PcPressable extends StatefulWidget {
  const PcPressable({
    super.key,
    required this.child,
    this.onTap,
    this.echelle = 0.97,
    this.vibrer = false,
  });

  final Widget child;
  final VoidCallback? onTap;
  final double echelle;

  /// Un léger retour haptique, réservé aux gestes qui engagent.
  final bool vibrer;

  @override
  State<PcPressable> createState() => _PcPressableState();
}

class _PcPressableState extends State<PcPressable> {
  bool _enfonce = false;

  void _etat(bool valeur) {
    if (widget.onTap == null || _enfonce == valeur) return;
    setState(() => _enfonce = valeur);
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: (_) => _etat(true),
      onTapUp: (_) => _etat(false),
      onTapCancel: () => _etat(false),
      onTap: widget.onTap == null
          ? null
          : () {
              if (widget.vibrer) HapticFeedback.lightImpact();
              widget.onTap!();
            },
      child: AnimatedScale(
        scale: _enfonce ? widget.echelle : 1,
        duration: PcMotion.fast,
        curve: PcMotion.ease,
        child: widget.child,
      ),
    );
  }
}

/// Carte : fond, grand rayon, ombre douce. Cliquable si `onTap` est fourni.
class PcCard extends StatelessWidget {
  const PcCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(16),
    this.couleur,
    this.gradient,
    this.bordure,
    this.rayon = PcRadius.lgAll,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final Color? couleur;
  final Gradient? gradient;
  final Color? bordure;
  final BorderRadius rayon;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    final Widget carte = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: gradient == null ? (couleur ?? pc.card) : null,
        gradient: gradient,
        borderRadius: rayon,
        boxShadow: pc.ombre,
        border: bordure == null ? null : Border.all(color: bordure!, width: 2),
      ),
      child: child,
    );

    return onTap == null ? carte : PcPressable(onTap: onTap, child: carte);
  }
}

enum PcVariante { accent, corail, tech, sombre, doux, contour }

/// Bouton principal de l'application.
class PcButton extends StatelessWidget {
  const PcButton({
    super.key,
    required this.libelle,
    this.onPressed,
    this.icone,
    this.variante = PcVariante.accent,
    this.petit = false,
    this.chargement = false,
    this.largeurPleine = true,
  });

  final String libelle;
  final VoidCallback? onPressed;
  final IconData? icone;
  final PcVariante variante;
  final bool petit;
  final bool chargement;
  final bool largeurPleine;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool actif = onPressed != null && !chargement;

    final (
      Gradient? degrade,
      Color? fond,
      Color texte,
      Color? lueur,
    ) = switch (variante) {
      PcVariante.accent => (pc.gradientAccent, null, Colors.white, pc.accent),
      PcVariante.corail => (
        const LinearGradient(
          colors: <Color>[
            PcColors.coralLight,
            PcColors.coral,
            PcColors.coralDeep,
          ],
        ),
        null,
        Colors.white,
        PcColors.coral,
      ),
      PcVariante.tech => (
        const LinearGradient(
          colors: <Color>[PcColors.techLight, PcColors.tech, PcColors.techDeep],
        ),
        null,
        Colors.white,
        PcColors.tech,
      ),
      PcVariante.sombre => (null, pc.ink, pc.bg, null),
      PcVariante.doux => (null, pc.soft, pc.ink, null),
      PcVariante.contour => (null, Colors.transparent, pc.ink, null),
    };

    final Widget contenu = chargement
        ? SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: texte),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (icone != null) ...<Widget>[
                Icon(icone, size: 19, color: texte),
                const SizedBox(width: 9),
              ],
              Flexible(
                child: Text(
                  libelle,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontFamily: PcFonts.display,
                    fontWeight: FontWeight.w600,
                    fontSize: petit ? 14.5 : 15.5,
                    letterSpacing: -0.15,
                    color: texte,
                  ),
                ),
              ),
            ],
          );

    final Widget bouton = AnimatedOpacity(
      opacity: actif || chargement ? 1 : 0.45,
      duration: PcMotion.fast,
      child: Container(
        height: petit ? 46 : 56,
        width: largeurPleine ? double.infinity : null,
        padding: const EdgeInsets.symmetric(horizontal: 18),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: degrade,
          color: fond,
          borderRadius: BorderRadius.circular(petit ? 15 : PcRadius.md),
          border: variante == PcVariante.contour
              ? Border.all(color: pc.line, width: 1.5)
              : null,
          boxShadow: lueur != null && actif ? PcShadow.glow(lueur) : null,
        ),
        child: contenu,
      ),
    );

    return Semantics(
      button: true,
      enabled: actif,
      label: libelle,
      child: PcPressable(
        onTap: actif ? onPressed : null,
        vibrer: true,
        child: bouton,
      ),
    );
  }
}

/// Puce sélectionnable (filtres, tris, choix rapides).
class PcChip extends StatelessWidget {
  const PcChip({
    super.key,
    required this.libelle,
    this.selectionne = false,
    this.onTap,
    this.icone,
  });

  final String libelle;
  final bool selectionne;
  final VoidCallback? onTap;
  final IconData? icone;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcPressable(
      onTap: onTap,
      child: AnimatedContainer(
        duration: PcMotion.fast,
        curve: PcMotion.ease,
        height: 38,
        padding: const EdgeInsets.symmetric(horizontal: 15),
        decoration: BoxDecoration(
          color: selectionne ? pc.ink : pc.card,
          borderRadius: BorderRadius.circular(99),
          border: Border.all(color: selectionne ? pc.ink : pc.line, width: 1.5),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            if (icone != null) ...<Widget>[
              Icon(icone, size: 16, color: selectionne ? pc.bg : pc.ink2),
              const SizedBox(width: 7),
            ],
            Text(
              libelle,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: selectionne ? pc.bg : pc.ink2,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Petite étiquette colorée (statut, badge).
class PcPill extends StatelessWidget {
  const PcPill(
    this.libelle, {
    super.key,
    this.couleur,
    this.icone,
    this.plein = false,
  });

  final String libelle;
  final Color? couleur;
  final IconData? icone;

  /// Fond plein de la couleur plutôt qu'une teinte légère.
  final bool plein;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = couleur ?? pc.accent;

    return Container(
      height: 24,
      padding: const EdgeInsets.symmetric(horizontal: 9),
      decoration: BoxDecoration(
        color: plein ? c : pc.teinte(c, 0.15),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icone != null) ...<Widget>[
            Icon(icone, size: 12, color: plein ? Colors.white : c),
            const SizedBox(width: 4),
          ],
          Text(
            libelle,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: plein ? Colors.white : c,
            ),
          ),
        ],
      ),
    );
  }
}

/// Icône dans un carré arrondi teinté de sa couleur (métier, action).
class PcIco extends StatelessWidget {
  const PcIco(this.icone, {super.key, this.couleur, this.taille = 46});

  final IconData icone;
  final Color? couleur;
  final double taille;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = couleur ?? pc.accent;

    return Container(
      width: taille,
      height: taille,
      decoration: BoxDecoration(
        color: pc.teinte(c),
        borderRadius: BorderRadius.circular(taille * 0.33),
      ),
      child: Icon(icone, color: c, size: taille * 0.48),
    );
  }
}

/// Avatar à initiales, sur un dégradé.
class PcAvatar extends StatelessWidget {
  const PcAvatar({
    super.key,
    required this.nom,
    this.taille = 48,
    this.couleurs = const <Color>[PcColors.brandLight, PcColors.brandDark],
    this.image,
    this.rond = false,
  });

  final String nom;
  final double taille;
  final List<Color> couleurs;
  final String? image;

  /// Cercle plein plutôt que carré arrondi (photo du profil, dans son anneau).
  final bool rond;

  static String initiales(String nom) {
    final List<String> mots = nom
        .trim()
        .split(RegExp(r'\s+'))
        .where((String m) => m.isNotEmpty)
        .toList();
    if (mots.isEmpty) return '?';
    if (mots.length == 1) return mots.first.substring(0, 1).toUpperCase();

    return (mots.first.substring(0, 1) + mots.last.substring(0, 1))
        .toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: taille,
      height: taille,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        shape: rond ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: rond ? null : BorderRadius.circular(taille * 0.34),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: couleurs,
        ),
      ),
      alignment: Alignment.center,
      child: image != null && image!.isNotEmpty
          ? Image.network(
              image!,
              fit: BoxFit.cover,
              width: taille,
              height: taille,
              errorBuilder: (_, _, _) => _initiales(),
            )
          : _initiales(),
    );
  }

  Widget _initiales() => Text(
    initiales(nom),
    style: TextStyle(
      fontFamily: PcFonts.display,
      fontWeight: FontWeight.w700,
      color: Colors.white,
      fontSize: taille * 0.34,
    ),
  );
}

/// Contrôle segmenté à curseur glissant.
class PcSegmented extends StatelessWidget {
  const PcSegmented({
    super.key,
    required this.options,
    required this.index,
    required this.onChanged,
    this.icones,
  });

  final List<String> options;
  final List<IconData>? icones;
  final int index;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Container(
      height: 50,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: pc.soft,
        borderRadius: BorderRadius.circular(17),
      ),
      child: LayoutBuilder(
        builder: (BuildContext context, BoxConstraints contraintes) {
          final double largeur = contraintes.maxWidth / options.length;

          return Stack(
            children: <Widget>[
              AnimatedPositioned(
                duration: PcMotion.normal,
                curve: PcMotion.ease,
                left: largeur * index,
                top: 0,
                bottom: 0,
                width: largeur,
                child: Container(
                  decoration: BoxDecoration(
                    color: pc.card,
                    borderRadius: BorderRadius.circular(13),
                    boxShadow: const <BoxShadow>[
                      BoxShadow(
                        color: Color(0x2E0D1220),
                        blurRadius: 8,
                        spreadRadius: -2,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                ),
              ),
              Row(
                children: <Widget>[
                  for (int i = 0; i < options.length; i++)
                    Expanded(
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () {
                          if (i != index) {
                            HapticFeedback.selectionClick();
                            onChanged(i);
                          }
                        },
                        child: Center(
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              if (icones != null) ...<Widget>[
                                Icon(
                                  icones![i],
                                  size: 16,
                                  color: i == index ? pc.ink : pc.muted,
                                ),
                                const SizedBox(width: 6),
                              ],
                              Flexible(
                                child: AnimatedDefaultTextStyle(
                                  duration: PcMotion.fast,
                                  style: TextStyle(
                                    fontFamily: PcFonts.body,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 13.5,
                                    color: i == index ? pc.ink : pc.muted,
                                  ),
                                  child: Text(
                                    options[i],
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }
}

/// Bouton d'icône carré arrondi (retour, cloche, favori).
class PcIconButton extends StatelessWidget {
  const PcIconButton({
    super.key,
    required this.icone,
    this.onTap,
    this.pastille = false,
    this.fond,
    this.couleur,
    this.libelle,
  });

  final IconData icone;
  final VoidCallback? onTap;

  /// Point orange signalant du nouveau (notifications).
  final bool pastille;
  final Color? fond;
  final Color? couleur;
  final String? libelle;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Semantics(
      button: true,
      label: libelle,
      child: PcPressable(
        onTap: onTap,
        echelle: 0.92,
        child: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: fond ?? pc.card,
            borderRadius: BorderRadius.circular(14),
            boxShadow: fond == null ? pc.ombre : null,
          ),
          child: Stack(
            alignment: Alignment.center,
            children: <Widget>[
              Icon(icone, size: 21, color: couleur ?? pc.ink),
              if (pastille)
                Positioned(
                  top: 10,
                  right: 11,
                  child: Container(
                    width: 9,
                    height: 9,
                    decoration: BoxDecoration(
                      color: PcColors.coral,
                      shape: BoxShape.circle,
                      border: Border.all(color: fond ?? pc.card, width: 2),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Bouton retour standard.
class PcBackButton extends StatelessWidget {
  const PcBackButton({super.key, this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => PcIconButton(
    icone: Icons.arrow_back_ios_new_rounded,
    libelle: 'Retour',
    onTap: onTap ?? () => Navigator.of(context).maybePop(),
  );
}

/// En-tête d'écran : retour, titre, sous-titre, action.
class PcHeader extends StatelessWidget {
  const PcHeader({
    super.key,
    required this.titre,
    this.sousTitre,
    this.retour = true,
    this.action,
    this.onRetour,
  });

  final String titre;
  final String? sousTitre;
  final bool retour;
  final Widget? action;
  final VoidCallback? onRetour;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
      child: Row(
        children: <Widget>[
          if (retour) ...<Widget>[
            PcBackButton(onTap: onRetour),
            const SizedBox(width: 12),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  titre,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                if (sousTitre != null)
                  Text(
                    sousTitre!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 12.5,
                    ),
                  ),
              ],
            ),
          ),
          ?action,
        ],
      ),
    );
  }
}

/// Titre de section avec lien optionnel.
class PcSection extends StatelessWidget {
  const PcSection(this.titre, {super.key, this.lien, this.onLien});

  final String titre;
  final String? lien;
  final VoidCallback? onLien;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Padding(
      padding: const EdgeInsets.only(top: 26, bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: <Widget>[
          Expanded(
            child: Text(titre, style: Theme.of(context).textTheme.titleLarge),
          ),
          if (lien != null)
            GestureDetector(
              onTap: onLien,
              child: Text(
                lien!,
                style: TextStyle(
                  color: pc.accent,
                  fontWeight: FontWeight.w700,
                  fontSize: 13.5,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Champ de saisie avec libellé au-dessus et icône.
class PcField extends StatelessWidget {
  const PcField({
    super.key,
    required this.libelle,
    this.controleur,
    this.icone,
    this.indice,
    this.erreur,
    this.motDePasse = false,
    this.clavier,
    this.suffixe,
    this.onChanged,
    this.autofill,
    this.actionClavier,
    this.onSubmitted,
    this.lignes = 1,
    this.focus,
  });

  final String libelle;
  final TextEditingController? controleur;
  final IconData? icone;
  final String? indice;
  final String? erreur;
  final bool motDePasse;
  final TextInputType? clavier;
  final Widget? suffixe;
  final ValueChanged<String>? onChanged;
  final Iterable<String>? autofill;
  final TextInputAction? actionClavier;
  final ValueChanged<String>? onSubmitted;
  final int lignes;
  final FocusNode? focus;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            libelle,
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: pc.muted,
            ),
          ),
        ),
        TextField(
          controller: controleur,
          focusNode: focus,
          obscureText: motDePasse,
          keyboardType: clavier,
          onChanged: onChanged,
          autofillHints: autofill,
          textInputAction: actionClavier,
          onSubmitted: onSubmitted,
          minLines: lignes,
          maxLines: motDePasse ? 1 : lignes,
          style: TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 15,
            color: pc.ink,
          ),
          decoration: InputDecoration(
            hintText: indice,
            errorText: erreur,
            prefixIcon: icone == null
                ? null
                : Icon(icone, color: pc.muted, size: 20),
            suffixIcon: suffixe,
          ),
        ),
      ],
    );
  }
}

/// Montant en francs guinéens, unité allégée.
class PcMontant extends StatelessWidget {
  const PcMontant(
    this.montantGnf, {
    super.key,
    this.taille = 15,
    this.couleur,
    this.prefixe,
  });

  final int montantGnf;
  final double taille;
  final Color? couleur;

  /// « dès », pour un prix sur devis.
  final String? prefixe;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Color c = couleur ?? pc.ink;

    return Text.rich(
      TextSpan(
        children: <InlineSpan>[
          if (prefixe != null)
            TextSpan(
              text: '$prefixe ',
              style: TextStyle(
                fontFamily: PcFonts.body,
                fontSize: taille * 0.72,
                fontWeight: FontWeight.w700,
                color: pc.muted,
              ),
            ),
          TextSpan(
            text: formaterGnf(montantGnf)
                .replaceAll('${separateurMillier}GNF', ''),
          ),
          TextSpan(
            text: '${separateurMillier}GNF',
            style: TextStyle(
              fontSize: taille * 0.62,
              fontWeight: FontWeight.w600,
              color: couleur == null ? pc.muted : c.withValues(alpha: 0.75),
            ),
          ),
        ],
      ),
      style: TextStyle(
        fontFamily: PcFonts.display,
        fontWeight: FontWeight.w700,
        fontSize: taille,
        letterSpacing: -0.4,
        color: c,
        fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
      ),
    );
  }
}

/// Message d'erreur avec bouton « Réessayer ».
class PcErreur extends StatelessWidget {
  const PcErreur({super.key, required this.message, this.onReessayer});

  final String message;
  final VoidCallback? onReessayer;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcCard(
      couleur: pc.teinte(PcColors.danger, 0.1),
      child: Row(
        children: <Widget>[
          const Icon(Icons.error_outline_rounded, color: PcColors.danger),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: pc.ink,
                fontWeight: FontWeight.w600,
                fontSize: 13.5,
              ),
            ),
          ),
          if (onReessayer != null)
            TextButton(onPressed: onReessayer, child: const Text('Réessayer')),
        ],
      ),
    );
  }
}

/// Ouvre une feuille montante au style de l'application.
Future<T?> showPcSheet<T>(
  BuildContext context, {
  required WidgetBuilder builder,
}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: context.pc.card,
    builder: (BuildContext ctx) => Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 10, 20, 28),
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
                  color: ctx.pc.line,
                  borderRadius: BorderRadius.circular(5),
                ),
              ),
            ),
            builder(ctx),
          ],
        ),
      ),
    ),
  );
}

/// Bandeau éphémère qui descend du haut de l'écran.
void showPcToast(
  BuildContext context,
  String message, {
  IconData icone = Icons.check_circle_rounded,
}) {
  final OverlayState? overlay = Overlay.maybeOf(context);
  if (overlay == null) return;

  late OverlayEntry entree;
  entree = OverlayEntry(
    builder: (BuildContext ctx) =>
        _Toast(message: message, icone: icone, onFin: () => entree.remove()),
  );
  overlay.insert(entree);
}

class _Toast extends StatefulWidget {
  const _Toast({
    required this.message,
    required this.icone,
    required this.onFin,
  });

  final String message;
  final IconData icone;
  final VoidCallback onFin;

  @override
  State<_Toast> createState() => _ToastState();
}

class _ToastState extends State<_Toast> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: PcMotion.normal,
  );

  @override
  void initState() {
    super.initState();
    _c.forward();
    Future<void>.delayed(const Duration(milliseconds: 2400), () async {
      if (!mounted) return;
      await _c.reverse();
      widget.onFin();
    });
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final double haut = MediaQuery.of(context).padding.top + 10;

    return Positioned(
      top: haut,
      left: 16,
      right: 16,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, -1.6),
          end: Offset.zero,
        ).animate(CurvedAnimation(parent: _c, curve: PcMotion.ease)),
        child: Center(
          child: Material(
            color: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              decoration: BoxDecoration(
                color: PcColors.ink,
                borderRadius: BorderRadius.circular(18),
                boxShadow: const <BoxShadow>[
                  BoxShadow(
                    color: Color(0x80000000),
                    blurRadius: 40,
                    spreadRadius: -16,
                    offset: Offset(0, 18),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(widget.icone, color: PcColors.techLight, size: 18),
                  const SizedBox(width: 9),
                  Flexible(
                    child: Text(
                      widget.message,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
