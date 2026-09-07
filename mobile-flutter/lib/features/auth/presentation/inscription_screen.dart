import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/app_mode.dart';
import '../../../core/providers.dart';
import '../../../design_system/app_theme.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/session_controller.dart';

/// Inscription (§4).
///
/// Le cahier des charges décrit trois étapes ; les deux premières se font ici,
/// et la troisième — le dossier professionnel — n'est demandée qu'aux
/// techniciens, après création du compte. C'est le serveur qui l'annonce, via
/// `etape_suivante` : l'application ne décide pas de la longueur du parcours.
///
/// Le choix de la casquette est **la première question**, avant même le nom.
/// Il change ce qu'on demande ensuite, et il change la couleur de
/// l'application : le poser en dernier obligerait à revenir en arrière.
class InscriptionScreen extends ConsumerStatefulWidget {
  const InscriptionScreen({super.key});

  @override
  ConsumerState<InscriptionScreen> createState() => _InscriptionScreenState();
}

class _InscriptionScreenState extends ConsumerState<InscriptionScreen> {
  final TextEditingController _nom = TextEditingController();
  final TextEditingController _telephone = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();

  AppMode _casquette = AppMode.client;
  bool _enCours = false;
  bool _masque = true;

  @override
  void dispose() {
    _nom.dispose();
    _telephone.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _soumettre() async {
    if (_enCours) return;

    setState(() => _enCours = true);

    await ref
        .read(sessionProvider.notifier)
        .inscrire(
          nomComplet: _nom.text.trim(),
          telephone: _telephone.text.trim(),
          motDePasse: _motDePasse.text,
          estTechnicien: _casquette == AppMode.technicien,
        );

    if (mounted) setState(() => _enCours = false);
  }

  @override
  Widget build(BuildContext context) {
    final Session session = ref.watch(sessionProvider);
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Créer un compte')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text('Je suis…', style: theme.textTheme.titleLarge),
                const SizedBox(height: DmSpace.x3),

                for (final AppMode mode in AppMode.values) ...<Widget>[
                  _CarteCasquette(
                    mode: mode,
                    choisie: _casquette == mode,
                    onChoisir: () => setState(() => _casquette = mode),
                  ),
                  const SizedBox(height: DmSpace.x3),
                ],

                const SizedBox(height: DmSpace.x4),

                if (session.erreur != null) ...<Widget>[
                  DmErreur(
                    message: session.erreur!.message,
                    onReessayer: session.erreur!.reseau ? _soumettre : null,
                  ),
                  const SizedBox(height: DmSpace.x4),
                ],

                DmChamp(
                  libelle: 'Nom complet',
                  controleur: _nom,
                  autoRemplissage: const <String>[AutofillHints.name],
                  action: TextInputAction.next,
                  prefixe: const Icon(Icons.person_outline),
                  erreur: session.erreur?.pourChamp('full_name'),
                ),
                const SizedBox(height: DmSpace.x4),

                DmChamp(
                  libelle: 'Numéro de téléphone',
                  indication: '620 00 00 00',
                  controleur: _telephone,
                  clavier: TextInputType.phone,
                  autoRemplissage: const <String>[
                    AutofillHints.telephoneNumber,
                  ],
                  action: TextInputAction.next,
                  prefixe: const Icon(Icons.phone_outlined),
                  erreur: session.erreur?.pourChamp('phone'),
                ),
                const SizedBox(height: DmSpace.x4),

                DmChamp(
                  libelle: 'Mot de passe',
                  indication: '8 caractères, une majuscule, un chiffre',
                  controleur: _motDePasse,
                  masque: _masque,
                  autoRemplissage: const <String>[AutofillHints.newPassword],
                  action: TextInputAction.done,
                  onSoumis: (_) => _soumettre(),
                  prefixe: const Icon(Icons.lock_outline),
                  erreur: session.erreur?.pourChamp('password'),
                ),

                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton(
                    onPressed: () => setState(() => _masque = !_masque),
                    child: Text(_masque ? 'Afficher' : 'Masquer'),
                  ),
                ),

                const SizedBox(height: DmSpace.x2),
                DmBouton(
                  libelle: _casquette == AppMode.technicien
                      ? 'Continuer'
                      : 'Créer mon compte',
                  enCours: _enCours,
                  onPresse: _soumettre,
                ),

                const SizedBox(height: DmSpace.x4),
                Text(
                  'En continuant, tu acceptes les conditions d\'utilisation de '
                  'Dépanne-Moi.',
                  style: theme.textTheme.bodySmall,
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Choix de la casquette, sous forme de carte plutôt que de bouton radio.
///
/// La carte porte la couleur du mode : on voit le bleu et l'orange avant même
/// de lire, et le choix devient une décision visuelle plutôt qu'une case à
/// cocher.
class _CarteCasquette extends StatelessWidget {
  const _CarteCasquette({
    required this.mode,
    required this.choisie,
    required this.onChoisir,
  });

  final AppMode mode;
  final bool choisie;
  final VoidCallback onChoisir;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color couleur = AppTheme.accent(mode);

    return InkWell(
      onTap: onChoisir,
      borderRadius: DmRadius.mdAll,
      child: AnimatedContainer(
        duration: DmMotion.fast,
        padding: const EdgeInsets.all(DmSpace.x4),
        decoration: BoxDecoration(
          color: choisie ? couleur.withValues(alpha: 0.08) : Colors.transparent,
          borderRadius: DmRadius.mdAll,
          border: Border.all(
            color: choisie ? couleur : theme.dividerColor,
            width: choisie ? 2 : 1,
          ),
        ),
        child: Row(
          children: <Widget>[
            Icon(
              mode == AppMode.client
                  ? Icons.home_repair_service_outlined
                  : Icons.handyman_outlined,
              color: couleur,
              size: 28,
            ),
            const SizedBox(width: DmSpace.x4),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(mode.libelle, style: theme.textTheme.titleMedium),
                  const SizedBox(height: DmSpace.x1),
                  Text(mode.description, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
            if (choisie) Icon(Icons.check_circle, color: couleur),
          ],
        ),
      ),
    );
  }
}
