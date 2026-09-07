import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/session_controller.dart';

/// Connexion par téléphone et mot de passe (§4).
///
/// Pas de code SMS ici : l'OTP est réservé au mot de passe oublié. Demander un
/// code à chaque connexion coûterait un SMS par ouverture de session, sur un
/// marché où le SMS se paie.
class ConnexionScreen extends ConsumerStatefulWidget {
  const ConnexionScreen({super.key});

  @override
  ConsumerState<ConnexionScreen> createState() => _ConnexionScreenState();
}

class _ConnexionScreenState extends ConsumerState<ConnexionScreen> {
  final TextEditingController _telephone = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();

  bool _enCours = false;
  bool _masque = true;

  @override
  void dispose() {
    _telephone.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _soumettre() async {
    if (_enCours) return;

    setState(() => _enCours = true);

    await ref
        .read(sessionProvider.notifier)
        .connecter(
          telephone: _telephone.text.trim(),
          motDePasse: _motDePasse.text,
        );

    // Le routeur écoute la session : en cas de succès il redirige seul. Il n'y
    // a donc rien à faire ici de la valeur de retour, sinon rendre la main.
    if (mounted) setState(() => _enCours = false);
  }

  @override
  Widget build(BuildContext context) {
    final Session session = ref.watch(sessionProvider);
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                const SizedBox(height: DmSpace.x8),
                Text('Bon retour', style: theme.textTheme.displaySmall),
                const SizedBox(height: DmSpace.x2),
                Text(
                  'Connecte-toi avec ton numéro de téléphone.',
                  style: theme.textTheme.bodyLarge?.copyWith(
                    color: DmColors.neutral500,
                  ),
                ),
                const SizedBox(height: DmSpace.x8),

                if (session.erreur != null) ...<Widget>[
                  DmErreur(
                    message: session.erreur!.message,
                    onReessayer: session.erreur!.reseau ? _soumettre : null,
                  ),
                  const SizedBox(height: DmSpace.x4),
                ],

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
                  controleur: _motDePasse,
                  masque: _masque,
                  autoRemplissage: const <String>[AutofillHints.password],
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
                  libelle: 'Se connecter',
                  enCours: _enCours,
                  onPresse: _soumettre,
                ),

                const SizedBox(height: DmSpace.x4),
                TextButton(
                  onPressed: () => context.push('/mot-de-passe-oublie'),
                  child: const Text('Mot de passe oublié ?'),
                ),

                const SizedBox(height: DmSpace.x8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Text(
                      'Pas encore de compte ?',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: DmColors.neutral500,
                      ),
                    ),
                    TextButton(
                      onPressed: () => context.push('/inscription'),
                      child: const Text('Créer un compte'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
