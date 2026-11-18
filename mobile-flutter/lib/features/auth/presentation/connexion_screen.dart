import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';

/// Entrée par le numéro de téléphone (prototype v4).
///
/// Un seul champ pour commencer : le numéro. L'écran suivant demande le mot
/// de passe, ou propose de créer un compte avec ce numéro. L'application ne
/// cherche **pas** à savoir si le numéro est inscrit : l'API ne doit pas servir
/// à découvrir qui utilise Parema (§7, « ce que l'API ne dit pas »).
class ConnexionScreen extends StatefulWidget {
  const ConnexionScreen({super.key});

  @override
  State<ConnexionScreen> createState() => _ConnexionScreenState();
}

class _ConnexionScreenState extends State<ConnexionScreen> {
  final TextEditingController _numero = TextEditingController();
  String? _erreur;

  @override
  void dispose() {
    _numero.dispose();
    super.dispose();
  }

  String get _chiffres => _numero.text.replaceAll(RegExp(r'\D'), '');

  void _continuer() {
    if (_chiffres.length != 9) {
      setState(
        () => _erreur = 'Le numéro guinéen compte 9 chiffres, sans le +224.',
      );
      return;
    }
    context.push('/connexion/mot-de-passe?tel=$_chiffres');
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: RadialGradient(
            center: const Alignment(1, -1),
            radius: 1.2,
            colors: <Color>[
              pc.accent.withValues(alpha: 0.2),
              pc.bg.withValues(alpha: 0),
            ],
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints c) =>
                SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(22, 18, 22, 26),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(minHeight: c.maxHeight - 44),
                    child: IntrinsicHeight(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          Align(
                            alignment: Alignment.centerLeft,
                            child: Image.asset(
                              'assets/images/logo_mark.png',
                              height: 48,
                              semanticLabel: 'Parema Connect',
                              errorBuilder: (_, _, _) => Icon(
                                Icons.handyman_rounded,
                                color: pc.accent,
                                size: 40,
                              ),
                            ),
                          ),
                          const SizedBox(height: 28),
                          Text(
                            'Votre numéro,\net c’est parti.',
                            style: Theme.of(context).textTheme.displaySmall,
                          ),
                          const SizedBox(height: 10),
                          Text(
                            'Un seul écran pour vous connecter ou créer votre '
                            'compte.',
                            style: TextStyle(
                              color: pc.muted,
                              fontSize: 14.5,
                              fontWeight: FontWeight.w600,
                              height: 1.5,
                            ),
                          ),
                          const SizedBox(height: 26),
                          _ChampNumero(
                            controleur: _numero,
                            onChanged: (_) {
                              if (_erreur != null) {
                                setState(() => _erreur = null);
                              }
                            },
                            onSubmitted: (_) => _continuer(),
                          ),
                          if (_erreur != null)
                            Padding(
                              padding: const EdgeInsets.fromLTRB(4, 8, 4, 0),
                              child: Text(
                                _erreur!,
                                style: const TextStyle(
                                  color: PcColors.danger,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 12.5,
                                ),
                              ),
                            ),
                          const SizedBox(height: 18),
                          PcButton(
                            libelle: 'Continuer',
                            icone: Icons.arrow_forward_rounded,
                            onPressed: _continuer,
                          ),
                          const Spacer(),
                          const SizedBox(height: 26),
                          const _Promesses(),
                          const SizedBox(height: 12),
                          Text(
                            'En continuant, vous acceptez les conditions '
                            'd’utilisation et la politique de confidentialité.',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: pc.muted,
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
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

class _ChampNumero extends StatelessWidget {
  const _ChampNumero({
    required this.controleur,
    required this.onChanged,
    required this.onSubmitted,
  });

  final TextEditingController controleur;
  final ValueChanged<String> onChanged;
  final ValueChanged<String> onSubmitted;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Container(
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: pc.card,
        borderRadius: PcRadius.lgAll,
        boxShadow: pc.ombre,
      ),
      child: Row(
        children: <Widget>[
          Container(
            height: 52,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: pc.soft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Text(
              '🇬🇳 +224',
              style: TextStyle(
                fontWeight: FontWeight.w800,
                color: pc.ink,
                fontSize: 14.5,
              ),
            ),
          ),
          Expanded(
            child: TextField(
              controller: controleur,
              keyboardType: TextInputType.phone,
              autofillHints: const <String>[
                AutofillHints.telephoneNumberNational,
              ],
              textInputAction: TextInputAction.go,
              onChanged: onChanged,
              onSubmitted: onSubmitted,
              inputFormatters: <TextInputFormatter>[
                FilteringTextInputFormatter.allow(RegExp(r'[\d ]')),
                LengthLimitingTextInputFormatter(12),
              ],
              style: TextStyle(
                fontFamily: PcFonts.display,
                fontWeight: FontWeight.w700,
                fontSize: 19,
                letterSpacing: 0.4,
                color: pc.ink,
              ),
              decoration: InputDecoration(
                hintText: '620 12 34 56',
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12),
                hintStyle: TextStyle(
                  fontFamily: PcFonts.display,
                  fontWeight: FontWeight.w600,
                  fontSize: 19,
                  color: pc.muted.withValues(alpha: 0.6),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Promesses extends StatelessWidget {
  const _Promesses();

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    Widget ligne(IconData icone, String texte) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: <Widget>[
          Icon(icone, color: pc.accent, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              texte,
              style: TextStyle(
                color: pc.ink2,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );

    return PcCard(
      child: Column(
        children: <Widget>[
          ligne(
            Icons.verified_user_rounded,
            'Techniciens vérifiés, identité contrôlée',
          ),
          ligne(Icons.sell_outlined, 'Prix affiché avant de choisir'),
          ligne(
            Icons.receipt_long_rounded,
            'Chaque paiement confirmé et tracé',
          ),
        ],
      ),
    );
  }
}
