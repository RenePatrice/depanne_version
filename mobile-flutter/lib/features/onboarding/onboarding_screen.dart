import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../design_system/components/dm_composants.dart';
import '../../design_system/design_tokens.dart';

/// Ce que fait Dépanne-Moi, en trois écrans.
///
/// Volontairement court. L'onboarding n'existe pas pour vendre : il répond aux
/// trois questions que se pose quelqu'un dont le robinet fuit — qui vient,
/// combien ça coûte, et qu'est-ce qui se passe si ça se passe mal.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final PageController _pages = PageController();
  int _page = 0;

  static const List<_Argument> _arguments = <_Argument>[
    _Argument(
      icone: Icons.verified_user_outlined,
      titre: 'Des techniciens vérifiés',
      texte:
          'Pièce d\'identité contrôlée, spécialité validée, avis des clients '
          'précédents. Tu sais qui sonne à ta porte.',
    ),
    _Argument(
      icone: Icons.receipt_long_outlined,
      titre: 'Le prix avant l\'intervention',
      texte:
          'Le montant t\'est annoncé avant que tu confirmes, et il est ferme '
          'dès qu\'un technicien accepte. Pas de surprise à la fin.',
    ),
    _Argument(
      icone: Icons.shield_outlined,
      titre: 'Ton paiement est protégé',
      texte:
          'L\'argent n\'est versé au technicien qu\'une fois le travail validé. '
          'Un problème ? Tu ouvres une réclamation et le paiement reste bloqué.',
    ),
  ];

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  void _suivant() {
    if (_page < _arguments.length - 1) {
      _pages.nextPage(duration: DmMotion.normal, curve: Curves.easeOut);
    } else {
      context.go('/connexion');
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool dernier = _page == _arguments.length - 1;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                // Toujours accessible : quelqu'un qui a déjà un compte n'a
                // aucune raison de faire défiler trois écrans avant de pouvoir
                // se connecter.
                onPressed: () => context.go('/connexion'),
                child: const Text('Passer'),
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pages,
                onPageChanged: (int i) => setState(() => _page = i),
                itemCount: _arguments.length,
                itemBuilder: (BuildContext context, int i) => _arguments[i],
              ),
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                for (int i = 0; i < _arguments.length; i++)
                  AnimatedContainer(
                    duration: DmMotion.fast,
                    margin: const EdgeInsets.symmetric(horizontal: DmSpace.x1),
                    height: 8,
                    width: i == _page ? 24 : 8,
                    decoration: BoxDecoration(
                      color: i == _page
                          ? theme.colorScheme.primary
                          : theme.dividerColor,
                      borderRadius: DmRadius.pillAll,
                    ),
                  ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(DmSpace.x6),
              child: Column(
                children: <Widget>[
                  DmBouton(
                    libelle: dernier ? 'Commencer' : 'Suivant',
                    onPresse: _suivant,
                  ),
                  const SizedBox(height: DmSpace.x2),
                  TextButton(
                    onPressed: () => context.go('/inscription'),
                    child: const Text('Créer un compte'),
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

class _Argument extends StatelessWidget {
  const _Argument({
    required this.icone,
    required this.titre,
    required this.texte,
  });

  final IconData icone;
  final String titre;
  final String texte;

  @override
  Widget build(BuildContext context) =>
      DmVide(icone: icone, titre: titre, texte: texte);
}
