import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../design_system/pc.dart';
import 'mise_a_jour.dart';

bool _dejaProposee = false;

/// Propose la mise à jour à l'ouverture de l'application, une fois par
/// session. Sous la version minimale, la fenêtre ne se ferme pas : la version
/// installée n'est plus prise en charge par le serveur.
Future<void> proposerMiseAJour(BuildContext context, WidgetRef ref) async {
  if (_dejaProposee) return;

  final EtatMiseAJour etat;
  try {
    etat = await ref.read(miseAJourProvider.future);
  } on Object {
    return;
  }
  if (!context.mounted || etat.urlTelechargement == null) return;
  if (!etat.disponible && !etat.obligatoire) return;

  _dejaProposee = true;

  Future<void> telecharger() => launchUrl(
    Uri.parse(etat.urlTelechargement!),
    mode: LaunchMode.externalApplication,
  );

  if (etat.obligatoire) {
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext ctx) => PopScope(
        canPop: false,
        child: AlertDialog(
          icon: const Icon(Icons.system_update_rounded, color: PcColors.coral),
          title: const Text('Mise à jour nécessaire'),
          content: Text(
            'La version ${etat.installee} n’est plus prise en charge. '
            'Installez la version ${etat.derniere ?? etat.minimale} pour '
            'continuer.',
          ),
          actions: <Widget>[
            FilledButton.icon(
              onPressed: telecharger,
              icon: const Icon(Icons.download_rounded),
              label: const Text('Télécharger'),
            ),
          ],
        ),
      ),
    );
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
          'Vous avez la version ${etat.installee}. Le téléchargement s’ouvre '
          'dans votre navigateur ; installez ensuite le fichier.',
          style: TextStyle(color: ctx.pc.muted, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 18),
        PcButton(
          libelle: 'Mettre à jour',
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

  if (installer == true) await telecharger();
}
