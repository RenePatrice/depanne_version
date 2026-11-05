import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../design_system/design_tokens.dart';
import '../providers.dart';

/// Bandeau « hors connexion », posé une fois pour toute l'application.
///
/// Il n'apparaît que lorsque l'écran affiche des données **tirées du cache**,
/// et dit de quand elles datent. Une information ancienne présentée comme
/// fraîche est pire qu'une erreur : le client croirait son technicien encore
/// en route alors qu'il est arrivé. Le bandeau disparaît à la première
/// réponse du serveur.
class BandeauHorsLigne extends ConsumerWidget {
  const BandeauHorsLigne({required this.enfant, super.key});

  final Widget enfant;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final DateTime? donneesDu = ref.watch(etatReseauProvider);

    return Column(
      children: <Widget>[
        AnimatedSize(
          duration: DmMotion.normal,
          child: donneesDu == null
              ? const SizedBox(width: double.infinity)
              : _Bandeau(donneesDu: donneesDu),
        ),
        // Le bandeau a déjà absorbé la barre d'état : sans cela, la barre de
        // l'écran en dessous la compterait une seconde fois.
        Expanded(
          child: donneesDu == null
              ? enfant
              : MediaQuery.removePadding(
                  context: context,
                  removeTop: true,
                  child: enfant,
                ),
        ),
      ],
    );
  }
}

class _Bandeau extends StatelessWidget {
  const _Bandeau({required this.donneesDu});

  final DateTime donneesDu;

  @override
  Widget build(BuildContext context) {
    final String heure =
        '${donneesDu.hour.toString().padLeft(2, '0')}:'
        '${donneesDu.minute.toString().padLeft(2, '0')}';

    return Material(
      color: DmColors.neutral700,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(
            horizontal: DmSpace.x4,
            vertical: DmSpace.x2,
          ),
          child: Row(
            children: <Widget>[
              const Icon(Icons.cloud_off, color: Colors.white, size: 18),
              const SizedBox(width: DmSpace.x3),
              Expanded(
                child: Text(
                  'Hors connexion — informations du $heure. '
                  'Rien ne peut être envoyé pour l\'instant.',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: DmType.fs6,
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
