import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../design_system/pc.dart';

/// Saisie du code d'arrivée (lot 2).
///
/// Le client lit quatre chiffres dans son application ; le technicien les
/// tape ici, devant la porte. Un pavé dessiné plutôt que le clavier du
/// système : de grosses touches, utilisables avec des gants ou des doigts
/// sales, et aucune suggestion de saisie qui garderait le code en mémoire.
///
/// La feuille ne vérifie rien : elle renvoie les chiffres, le serveur tranche
/// et compte les essais. Elle annonce seulement combien il en reste.
class CodeArriveeFeuille extends StatefulWidget {
  const CodeArriveeFeuille({this.essaisRestants, super.key});

  static const int longueur = 4;

  final int? essaisRestants;

  /// Renvoie le code saisi, ou `null` si le technicien a refermé la feuille.
  static Future<String?> demander(
    BuildContext context, {
    int? essaisRestants,
  }) => showPcSheet<String>(
    context,
    builder: (_) => CodeArriveeFeuille(essaisRestants: essaisRestants),
  );

  @override
  State<CodeArriveeFeuille> createState() => _CodeArriveeFeuilleState();
}

class _CodeArriveeFeuilleState extends State<CodeArriveeFeuille> {
  String _code = '';

  void _taper(String chiffre) {
    if (_code.length >= CodeArriveeFeuille.longueur) return;
    HapticFeedback.selectionClick();
    setState(() => _code += chiffre);
  }

  void _effacer() {
    if (_code.isEmpty) return;
    HapticFeedback.selectionClick();
    setState(() => _code = _code.substring(0, _code.length - 1));
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool complet = _code.length == CodeArriveeFeuille.longueur;
    final int? restants = widget.essaisRestants;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'Code d’arrivée',
          textAlign: TextAlign.center,
          style: TextStyle(
            fontFamily: PcFonts.display,
            fontSize: 20,
            fontWeight: FontWeight.w700,
            color: pc.ink,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          'Demandez au client les 4 chiffres affichés dans son application.',
          textAlign: TextAlign.center,
          style: TextStyle(color: pc.muted, fontSize: 13),
        ),
        const SizedBox(height: 20),
        Semantics(
          label: 'Code saisi : ${_code.length} chiffres sur 4',
          excludeSemantics: true,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              for (int i = 0; i < CodeArriveeFeuille.longueur; i++)
                _Case(
                  chiffre: i < _code.length ? _code[i] : null,
                  active: i == _code.length,
                ),
            ],
          ),
        ),
        if (restants != null && restants < 5) ...<Widget>[
          const SizedBox(height: 10),
          Text(
            restants > 1 ? '$restants essais restants' : 'Dernier essai',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: restants > 1 ? pc.muted : PcColors.danger,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        const SizedBox(height: 18),
        _Pave(onChiffre: _taper, onEffacer: _effacer),
        const SizedBox(height: 18),
        PcButton(
          libelle: 'Valider mon arrivée',
          icone: Icons.verified_rounded,
          variante: PcVariante.tech,
          onPressed: complet ? () => Navigator.of(context).pop(_code) : null,
        ),
      ],
    );
  }
}

class _Case extends StatelessWidget {
  const _Case({required this.chiffre, required this.active});

  final String? chiffre;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      width: 56,
      height: 64,
      margin: const EdgeInsets.symmetric(horizontal: 6),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: pc.soft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: active ? pc.accent : (chiffre != null ? pc.ink2 : pc.line),
          width: active ? 2 : 1,
        ),
      ),
      child: Text(
        chiffre ?? '',
        style: TextStyle(
          fontFamily: PcFonts.display,
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: pc.ink,
        ),
      ),
    );
  }
}

/// Pavé 3 × 4 : 1-9, puis effacer, 0.
class _Pave extends StatelessWidget {
  const _Pave({required this.onChiffre, required this.onEffacer});

  final ValueChanged<String> onChiffre;
  final VoidCallback onEffacer;

  @override
  Widget build(BuildContext context) {
    Widget ligne(List<Widget> touches) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: <Widget>[for (final Widget t in touches) Expanded(child: t)],
      ),
    );

    Widget chiffre(String c) =>
        _Touche(semantique: c, onTap: () => onChiffre(c), child: Text(c));

    return Column(
      children: <Widget>[
        ligne(<Widget>[chiffre('1'), chiffre('2'), chiffre('3')]),
        ligne(<Widget>[chiffre('4'), chiffre('5'), chiffre('6')]),
        ligne(<Widget>[chiffre('7'), chiffre('8'), chiffre('9')]),
        ligne(<Widget>[
          const SizedBox.shrink(),
          chiffre('0'),
          _Touche(
            semantique: 'Effacer',
            onTap: onEffacer,
            child: const Icon(Icons.backspace_outlined),
          ),
        ]),
      ],
    );
  }
}

class _Touche extends StatelessWidget {
  const _Touche({
    required this.semantique,
    required this.onTap,
    required this.child,
  });

  final String semantique;
  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5),
      child: Semantics(
        button: true,
        label: semantique,
        excludeSemantics: true,
        child: Material(
          color: pc.soft,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: onTap,
            child: SizedBox(
              height: 58,
              child: Center(
                child: DefaultTextStyle(
                  style: TextStyle(
                    fontFamily: PcFonts.display,
                    fontSize: 24,
                    fontWeight: FontWeight.w600,
                    color: pc.ink,
                  ),
                  child: IconTheme(
                    data: IconThemeData(color: pc.ink2),
                    child: child,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
