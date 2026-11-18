import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../design_system/pc.dart';

/// Présentation en trois écrans, avant la connexion.
///
/// Trois promesses, pas une de plus : trouver vite, connaître le prix avant de
/// choisir, être suivi et protégé. Chaque illustration montre l'écran qui tient
/// la promesse, plutôt qu'un dessin abstrait.
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  static const List<({String titre, String texte})> pages =
      <({String titre, String texte})>[
        (
          titre: 'Le bon pro en 2 minutes',
          texte:
              'Décrivez votre panne ou choisissez un métier : on vous montre '
              'les techniciens vérifiés autour de vous.',
        ),
        (
          titre: 'Le prix, avant de choisir',
          texte:
              'Chaque pro affiche son prix ferme, déplacement compris. Vous '
              'comparez, vous décidez. Aucune surprise.',
        ),
        (
          titre: 'Suivi en direct, paiement tracé',
          texte:
              'Suivez son arrivée sur la carte, payez en espèces ou par Mobile '
              'Money, et profitez de la garantie Parema.',
        ),
      ];

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen>
    with SingleTickerProviderStateMixin {
  final PageController _pages = PageController();
  late final AnimationController _flottement = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 5),
  );
  int _index = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Le flottement s'arrête si le téléphone demande moins d'animations.
    if (MediaQuery.of(context).disableAnimations) {
      _flottement.stop();
    } else if (!_flottement.isAnimating) {
      _flottement.repeat();
    }
  }

  @override
  void dispose() {
    _pages.dispose();
    _flottement.dispose();
    super.dispose();
  }

  void _suivant() {
    if (_index >= OnboardingScreen.pages.length - 1) {
      context.go('/connexion');
      return;
    }
    _pages.nextPage(duration: PcMotion.slow, curve: PcMotion.ease);
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool dernier = _index == OnboardingScreen.pages.length - 1;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(0, 6, 12, 0),
                child: TextButton(
                  onPressed: () => context.go('/connexion'),
                  style: TextButton.styleFrom(foregroundColor: pc.muted),
                  child: const Text('Passer'),
                ),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pages,
                itemCount: OnboardingScreen.pages.length,
                onPageChanged: (int i) => setState(() => _index = i),
                itemBuilder: (BuildContext context, int i) {
                  final ({String titre, String texte}) page =
                      OnboardingScreen.pages[i];

                  return Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Expanded(
                          child: _Illustration(
                            numero: i,
                            flottement: _flottement,
                          ),
                        ),
                        Text(
                          page.titre,
                          style: Theme.of(context).textTheme.displaySmall
                              ?.copyWith(fontSize: 28),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          page.texte,
                          style: TextStyle(
                            color: pc.muted,
                            fontSize: 15,
                            height: 1.55,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 22, 24, 26),
              child: Row(
                children: <Widget>[
                  for (int i = 0; i < OnboardingScreen.pages.length; i++)
                    AnimatedContainer(
                      duration: PcMotion.normal,
                      curve: PcMotion.ease,
                      margin: const EdgeInsets.only(right: 6),
                      width: i == _index ? 26 : 7,
                      height: 7,
                      decoration: BoxDecoration(
                        color: i == _index ? pc.accent : pc.line,
                        borderRadius: BorderRadius.circular(7),
                      ),
                    ),
                  const Spacer(),
                  SizedBox(
                    width: 160,
                    child: PcButton(
                      libelle: dernier ? 'Commencer' : 'Suivant',
                      icone: Icons.arrow_forward_rounded,
                      onPressed: _suivant,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Composition flottante propre à chaque page.
class _Illustration extends StatelessWidget {
  const _Illustration({required this.numero, required this.flottement});

  final int numero;
  final Animation<double> flottement;

  @override
  Widget build(BuildContext context) {
    final List<Widget> elements = switch (numero) {
      0 => <Widget>[
        _flotte(const Alignment(0, -0.05), 0, const _CarteTechnicien()),
        _flotte(
          const Alignment(-0.85, -0.85),
          0.2,
          _metier(PcMetier.de('PLOMBERIE'), 60),
        ),
        _flotte(
          const Alignment(0.85, -0.75),
          0.5,
          _metier(PcMetier.de('ELECTRICITE'), 56),
        ),
        _flotte(
          const Alignment(0.75, 0.8),
          0.7,
          _metier(PcMetier.de('PEINTURE'), 58),
        ),
        _flotte(
          const Alignment(-0.75, 0.7),
          0.35,
          _metier(PcMetier.de('CARRELAGE'), 52),
        ),
      ],
      1 => <Widget>[
        _flotte(const Alignment(0, -0.25), 0, const _CartePrix()),
        _flotte(
          const Alignment(-0.85, 0.75),
          0.4,
          const _Etiquette(
            icone: Icons.check_circle_rounded,
            couleur: PcColors.tech,
            texte: 'Aucun frais caché',
          ),
        ),
        _flotte(
          const Alignment(0.85, 0.45),
          0.6,
          const _Etiquette(
            icone: Icons.compare_arrows_rounded,
            couleur: PcColors.brand,
            texte: 'Offres comparées',
          ),
        ),
      ],
      _ => <Widget>[
        _flotte(const Alignment(0, -0.3), 0, const _CarteSuivi()),
        _flotte(
          const Alignment(0.85, 0.55),
          0.3,
          const _Etiquette(
            icone: Icons.timer_outlined,
            couleur: PcColors.coral,
            texte: 'Arrive dans 12 min',
          ),
        ),
        _flotte(
          const Alignment(-0.85, 0.85),
          0.6,
          const _Etiquette(
            icone: Icons.verified_user_rounded,
            couleur: PcColors.brand,
            texte: 'Paiement tracé',
          ),
        ),
      ],
    };

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Stack(children: elements),
    );
  }

  Widget _flotte(Alignment position, double decalage, Widget enfant) {
    return Align(
      alignment: position,
      child: AnimatedBuilder(
        animation: flottement,
        builder: (BuildContext context, Widget? c) => Transform.translate(
          offset: Offset(
            0,
            math.sin((flottement.value + decalage) * 2 * math.pi) * 8,
          ),
          child: c,
        ),
        child: enfant,
      ),
    );
  }

  Widget _metier(PcMetier m, double taille) => Builder(
    builder: (BuildContext context) => Container(
      width: taille,
      height: taille,
      decoration: BoxDecoration(
        color: context.pc.teinte(m.couleur),
        borderRadius: BorderRadius.circular(taille * 0.34),
        boxShadow: context.pc.ombre,
      ),
      child: Icon(m.icone, color: m.couleur, size: taille * 0.46),
    ),
  );
}

class _CarteTechnicien extends StatelessWidget {
  const _CarteTechnicien();

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return SizedBox(
      width: 230,
      child: PcCard(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Row(
              children: <Widget>[
                const PcAvatar(nom: 'Mamadou Keïta', taille: 40),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Mamadou K.',
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      Text(
                        'Plombier · 1,8 km',
                        style: TextStyle(color: pc.muted, fontSize: 12),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.verified_rounded, color: pc.accent, size: 18),
              ],
            ),
            const SizedBox(height: 12),
            const Row(
              children: <Widget>[
                PcPill('Disponible', couleur: PcColors.tech),
                SizedBox(width: 8),
                Expanded(
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: PcMontant(75000),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CartePrix extends StatelessWidget {
  const _CartePrix();

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    TextStyle petit = TextStyle(color: pc.muted, fontSize: 13);

    return SizedBox(
      width: 245,
      child: PcCard(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              'Prix ferme · tout compris',
              style: TextStyle(
                color: pc.muted,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            const PcMontant(102000, taille: 30),
            const SizedBox(height: 12),
            Divider(color: pc.line),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                Text('Prestation', style: petit),
                const PcMontant(95000, taille: 13),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                Text('Déplacement', style: petit),
                const PcMontant(7000, taille: 13),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CarteSuivi extends StatelessWidget {
  const _CarteSuivi();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 250,
      height: 190,
      child: ClipRRect(
        borderRadius: PcRadius.lgAll,
        child: CustomPaint(painter: _MiniCarte(context.pc)),
      ),
    );
  }
}

class _MiniCarte extends CustomPainter {
  _MiniCarte(this.pc);

  final PcSkin pc;

  @override
  void paint(Canvas canvas, Size taille) {
    canvas.drawRect(
      Offset.zero & taille,
      Paint()
        ..color = pc.estSombre
            ? const Color(0xFF141B2A)
            : const Color(0xFFEEF2F7),
    );

    final Paint rue = Paint()
      ..color = pc.estSombre ? const Color(0xFF1D2638) : Colors.white
      ..strokeWidth = 14;
    for (final double y in <double>[0.32, 0.68]) {
      canvas.drawLine(
        Offset(0, taille.height * y),
        Offset(taille.width, taille.height * y),
        rue,
      );
    }
    for (final double x in <double>[0.28, 0.72]) {
      canvas.drawLine(
        Offset(taille.width * x, 0),
        Offset(taille.width * x, taille.height),
        rue,
      );
    }

    final Path trajet = Path()
      ..moveTo(taille.width * 0.16, taille.height * 0.84)
      ..lineTo(taille.width * 0.28, taille.height * 0.68)
      ..lineTo(taille.width * 0.72, taille.height * 0.68)
      ..lineTo(taille.width * 0.72, taille.height * 0.32)
      ..lineTo(taille.width * 0.86, taille.height * 0.16);
    canvas.drawPath(
      trajet,
      Paint()
        ..color = PcColors.brand
        ..style = PaintingStyle.stroke
        ..strokeWidth = 4
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    void point(Offset o, Color c) {
      canvas.drawCircle(o, 11, Paint()..color = Colors.white);
      canvas.drawCircle(o, 7, Paint()..color = c);
    }

    point(Offset(taille.width * 0.16, taille.height * 0.84), PcColors.coral);
    point(Offset(taille.width * 0.86, taille.height * 0.16), PcColors.brand);
  }

  @override
  bool shouldRepaint(covariant _MiniCarte ancien) => ancien.pc != pc;
}

class _Etiquette extends StatelessWidget {
  const _Etiquette({
    required this.icone,
    required this.couleur,
    required this.texte,
  });

  final IconData icone;
  final Color couleur;
  final String texte;

  @override
  Widget build(BuildContext context) {
    return PcCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icone, color: couleur, size: 18),
          const SizedBox(width: 8),
          Text(texte, style: Theme.of(context).textTheme.titleSmall),
        ],
      ),
    );
  }
}
