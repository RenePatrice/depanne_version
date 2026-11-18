import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../application/session_controller.dart';

/// Création de compte (prototype v4).
///
/// Pas de code par SMS ni par WhatsApp à l'inscription (décision du client) :
/// un numéro = un compte, garanti par le serveur. Le rôle se choisit d'un
/// toucher ; un technicien est ensuite conduit vers son dossier.
class InscriptionScreen extends ConsumerStatefulWidget {
  const InscriptionScreen({super.key, this.chiffres});

  /// Le numéro déjà saisi à l'écran d'entrée, s'il y en a un.
  final String? chiffres;

  @override
  ConsumerState<InscriptionScreen> createState() => _InscriptionScreenState();
}

class _InscriptionScreenState extends ConsumerState<InscriptionScreen> {
  late final TextEditingController _numero = TextEditingController(
    text: widget.chiffres ?? '',
  );
  final TextEditingController _nom = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();

  bool _technicien = false;
  bool _visible = false;
  bool _envoi = false;
  String? _erreurLocale;

  @override
  void dispose() {
    _numero.dispose();
    _nom.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  /// Force du mot de passe, de 0 à 4. Indicative : la règle qui compte est
  /// celle du serveur.
  int get _force {
    final String v = _motDePasse.text;
    int f = 0;
    if (v.length >= 8) f++;
    if (RegExp(r'\d').hasMatch(v)) f++;
    if (RegExp('[A-Za-z]').hasMatch(v)) f++;
    if (RegExp(r'[^A-Za-z0-9]').hasMatch(v) || v.length >= 12) f++;

    return f;
  }

  Future<void> _creer() async {
    final String chiffres = _numero.text.replaceAll(RegExp(r'\D'), '');

    if (_nom.text.trim().length < 3) {
      setState(() => _erreurLocale = 'Indiquez votre prénom et votre nom.');
      return;
    }
    if (chiffres.length != 9) {
      setState(() => _erreurLocale = 'Le numéro guinéen compte 9 chiffres.');
      return;
    }
    if (_motDePasse.text.length < 8) {
      setState(
        () =>
            _erreurLocale = 'Le mot de passe doit faire au moins 8 caractères.',
      );
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _erreurLocale = null;
      _envoi = true;
    });

    // En cas de succès, le routeur conduit à l'accueil — ou au dossier
    // technicien pour un compte technicien.
    await ref
        .read(sessionProvider.notifier)
        .inscrire(
          nomComplet: _nom.text.trim(),
          telephone: '+224$chiffres',
          motDePasse: _motDePasse.text,
          estTechnicien: _technicien,
        );

    if (mounted) setState(() => _envoi = false);
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Session session = ref.watch(sessionProvider);
    final int force = _force;
    const List<Color> couleursForce = <Color>[
      PcColors.danger,
      PcColors.amber,
      PcColors.brand,
      PcColors.tech,
    ];
    const List<String> libellesForce = <String>[
      'Mélangez lettres et chiffres.',
      'Faible',
      'Moyen',
      'Bon',
      'Excellent',
    ];

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(
              titre: 'Créer un compte',
              sousTitre: 'Une minute, c’est tout',
              onRetour: () {
                ref.read(sessionProvider.notifier).effacerErreur();
                context.pop();
              },
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 6, 18, 28),
                children: <Widget>[
                  if (session.erreur != null ||
                      _erreurLocale != null) ...<Widget>[
                    PcErreur(message: _erreurLocale ?? session.erreur!.message),
                    const SizedBox(height: 16),
                  ],
                  Padding(
                    padding: const EdgeInsets.only(left: 4, bottom: 8),
                    child: Text(
                      'Vous êtes…',
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: pc.muted,
                      ),
                    ),
                  ),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: _CarteRole(
                          icone: Icons.search_rounded,
                          couleur: PcColors.brand,
                          titre: 'Je cherche un pro',
                          sousTitre: 'Compte client',
                          choisi: !_technicien,
                          onTap: () => setState(() => _technicien = false),
                        ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: _CarteRole(
                          icone: Icons.handyman_rounded,
                          couleur: PcColors.tech,
                          titre: 'Je suis un pro',
                          sousTitre: 'Compte technicien',
                          choisi: _technicien,
                          onTap: () => setState(() => _technicien = true),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  PcField(
                    libelle: 'Prénom et nom',
                    controleur: _nom,
                    icone: Icons.person_outline_rounded,
                    indice: 'Aïssatou Diallo',
                    autofill: const <String>[AutofillHints.name],
                    actionClavier: TextInputAction.next,
                    erreur: session.erreur?.pourChamp('full_name'),
                  ),
                  const SizedBox(height: 14),
                  PcField(
                    libelle: 'Téléphone',
                    controleur: _numero,
                    icone: Icons.phone_outlined,
                    indice: '620 12 34 56',
                    clavier: TextInputType.phone,
                    autofill: const <String>[
                      AutofillHints.telephoneNumberNational,
                    ],
                    actionClavier: TextInputAction.next,
                    erreur: session.erreur?.pourChamp('phone'),
                  ),
                  const SizedBox(height: 14),
                  PcField(
                    libelle: 'Mot de passe',
                    controleur: _motDePasse,
                    icone: Icons.lock_outline_rounded,
                    indice: '8 caractères minimum',
                    motDePasse: !_visible,
                    autofill: const <String>[AutofillHints.newPassword],
                    actionClavier: TextInputAction.done,
                    onChanged: (_) => setState(() {}),
                    onSubmitted: (_) => _creer(),
                    erreur: session.erreur?.pourChamp('password'),
                    suffixe: IconButton(
                      tooltip: _visible ? 'Masquer' : 'Afficher',
                      icon: Icon(
                        _visible
                            ? Icons.visibility_off_outlined
                            : Icons.visibility_outlined,
                        color: pc.muted,
                      ),
                      onPressed: () => setState(() => _visible = !_visible),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: <Widget>[
                      for (int i = 0; i < 4; i++) ...<Widget>[
                        Expanded(
                          child: AnimatedContainer(
                            duration: PcMotion.fast,
                            height: 4,
                            decoration: BoxDecoration(
                              color: i < force
                                  ? couleursForce[force - 1]
                                  : pc.soft,
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                        ),
                        if (i < 3) const SizedBox(width: 6),
                      ],
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(4, 6, 4, 0),
                    child: Text(
                      libellesForce[force],
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  PcButton(
                    libelle: 'Créer mon compte',
                    variante: _technicien ? PcVariante.tech : PcVariante.accent,
                    chargement: _envoi,
                    onPressed: _creer,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Un numéro = un compte. Aucun SMS payant.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: pc.muted,
                      fontWeight: FontWeight.w600,
                      fontSize: 12,
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

class _CarteRole extends StatelessWidget {
  const _CarteRole({
    required this.icone,
    required this.couleur,
    required this.titre,
    required this.sousTitre,
    required this.choisi,
    required this.onTap,
  });

  final IconData icone;
  final Color couleur;
  final String titre;
  final String sousTitre;
  final bool choisi;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Semantics(
      selected: choisi,
      button: true,
      child: PcPressable(
        onTap: () {
          HapticFeedback.selectionClick();
          onTap();
        },
        child: AnimatedContainer(
          duration: PcMotion.fast,
          curve: PcMotion.ease,
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: pc.card,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: choisi ? couleur : pc.line,
              width: choisi ? 2 : 1.5,
            ),
            boxShadow: choisi ? PcShadow.glow(couleur) : null,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              PcIco(icone, couleur: couleur, taille: 38),
              const SizedBox(height: 10),
              Text(titre, style: Theme.of(context).textTheme.titleSmall),
              Text(
                sousTitre,
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
