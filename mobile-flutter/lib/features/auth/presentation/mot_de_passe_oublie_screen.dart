import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';

/// Mot de passe oublié, par code SMS (§4).
///
/// Le seul endroit où un OTP intervient. Deux temps sur un même écran plutôt
/// que deux écrans : l'utilisateur reçoit son code pendant qu'il regarde
/// l'application, et le faire naviguer entre-temps lui ferait perdre le fil.
///
/// L'API répond **la même chose** pour un numéro inconnu (ADR-0023). L'écran
/// affiche donc toujours le second temps : il ne doit pas non plus révéler qui
/// est inscrit.
class MotDePasseOublieScreen extends ConsumerStatefulWidget {
  const MotDePasseOublieScreen({super.key});

  @override
  ConsumerState<MotDePasseOublieScreen> createState() =>
      _MotDePasseOublieScreenState();
}

class _MotDePasseOublieScreenState
    extends ConsumerState<MotDePasseOublieScreen> {
  final TextEditingController _telephone = TextEditingController();
  final TextEditingController _code = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();

  bool _codeDemande = false;
  bool _enCours = false;
  ApiException? _erreur;

  @override
  void dispose() {
    _telephone.dispose();
    _code.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _executer(
    Future<void> Function() action, {
    bool passerAuCode = false,
  }) async {
    if (_enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await action();
      if (!mounted) return;

      if (passerAuCode) {
        setState(() => _codeDemande = true);
      } else {
        _confirmerEtRevenir();
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  void _confirmerEtRevenir() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Mot de passe modifié. Connecte-toi avec le nouveau.'),
      ),
    );
    context.go('/connexion');
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Mot de passe oublié')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(DmSpace.x6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                _codeDemande
                    ? 'Saisis le code reçu par SMS et choisis un nouveau mot de passe.'
                    : 'Indique ton numéro : nous t\'enverrons un code par SMS.',
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: DmColors.neutral500,
                ),
              ),
              const SizedBox(height: DmSpace.x6),

              if (_erreur != null) ...<Widget>[
                DmErreur(message: _erreur!.message),
                const SizedBox(height: DmSpace.x4),
              ],

              DmChamp(
                libelle: 'Numéro de téléphone',
                indication: '620 00 00 00',
                controleur: _telephone,
                clavier: TextInputType.phone,
                prefixe: const Icon(Icons.phone_outlined),
                erreur: _erreur?.pourChamp('phone'),
              ),

              if (_codeDemande) ...<Widget>[
                const SizedBox(height: DmSpace.x4),
                DmChamp(
                  libelle: 'Code reçu par SMS',
                  indication: '6 chiffres',
                  controleur: _code,
                  clavier: TextInputType.number,
                  autoRemplissage: const <String>[AutofillHints.oneTimeCode],
                  prefixe: const Icon(Icons.sms_outlined),
                  erreur: _erreur?.pourChamp('code'),
                ),
                const SizedBox(height: DmSpace.x4),
                DmChamp(
                  libelle: 'Nouveau mot de passe',
                  indication: '8 caractères, une majuscule, un chiffre',
                  controleur: _motDePasse,
                  masque: true,
                  prefixe: const Icon(Icons.lock_outline),
                  erreur: _erreur?.pourChamp('password'),
                ),
              ],

              const SizedBox(height: DmSpace.x6),
              DmBouton(
                libelle: _codeDemande
                    ? 'Changer mon mot de passe'
                    : 'Recevoir le code',
                enCours: _enCours,
                onPresse: () {
                  final repo = ref.read(authRepositoryProvider);

                  if (_codeDemande) {
                    _executer(
                      () => repo.reinitialiserMotDePasse(
                        telephone: _telephone.text.trim(),
                        code: _code.text.trim(),
                        motDePasse: _motDePasse.text,
                      ),
                    );
                  } else {
                    _executer(
                      () => repo.demanderCodeMotDePasse(_telephone.text.trim()),
                      passerAuCode: true,
                    );
                  }
                },
              ),

              if (_codeDemande) ...<Widget>[
                const SizedBox(height: DmSpace.x3),
                TextButton(
                  onPressed: _enCours
                      ? null
                      : () => _executer(
                          () => ref
                              .read(authRepositoryProvider)
                              .demanderCodeMotDePasse(_telephone.text.trim()),
                          passerAuCode: true,
                        ),
                  child: const Text('Renvoyer le code'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
