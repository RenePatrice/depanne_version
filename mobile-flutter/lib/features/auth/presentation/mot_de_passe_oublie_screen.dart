import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';

/// Mot de passe oublié, par code **WhatsApp** (§4, #8).
///
/// Le seul endroit où un OTP intervient. Pas d'API SMS : le code arrive par
/// WhatsApp. Deux temps sur un même écran plutôt que deux écrans : l'utilisateur
/// reçoit son code pendant qu'il regarde l'application.
///
/// L'API répond **la même chose** pour un numéro inconnu (ADR-0023). L'écran
/// affiche donc toujours le second temps : il ne doit pas non plus révéler qui
/// est inscrit. En **mode test** (flag serveur, hors production), le serveur
/// renvoie le code et l'écran le pré-remplit, pour tester sans passerelle.
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

  /// Lien WhatsApp du support (wa.me), si le serveur en fournit un.
  String? _waSupportUrl;

  /// Vrai quand le code a été pré-rempli par le mode test du serveur.
  bool _codePrerempli = false;

  @override
  void dispose() {
    _telephone.dispose();
    _code.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  /// Exécute le changement de mot de passe (second temps) et revient à la
  /// connexion. La demande du code passe, elle, par `_demanderCode`.
  Future<void> _executer(Future<void> Function() action) async {
    if (_enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      await action();
      if (!mounted) return;
      _confirmerEtRevenir();
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  /// Demande (ou renvoie) le code par WhatsApp et traite la réponse : passage
  /// au second temps, lien support, et pré-remplissage en mode test.
  Future<void> _demanderCode() async {
    if (_enCours) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      final Map<String, dynamic> reponse = await ref
          .read(authRepositoryProvider)
          .demanderCodeMotDePasse(_telephone.text.trim());

      if (!mounted) return;

      final String? codeTest = reponse['code_test'] as String?;

      setState(() {
        _codeDemande = true;
        _waSupportUrl = reponse['wa_support_url'] as String?;
        if (codeTest != null && codeTest.isNotEmpty) {
          _code.text = codeTest;
          _codePrerempli = true;
        }
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  Future<void> _ouvrirSupport() async {
    final String? url = _waSupportUrl;
    if (url == null) return;

    final Uri uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
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
                    ? 'Saisis le code reçu par WhatsApp et choisis un nouveau mot de passe.'
                    : 'Indique ton numéro : nous t\'enverrons un code par WhatsApp.',
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
                  libelle: 'Code reçu par WhatsApp',
                  indication: '6 chiffres',
                  controleur: _code,
                  clavier: TextInputType.number,
                  autoRemplissage: const <String>[AutofillHints.oneTimeCode],
                  prefixe: const Icon(Icons.chat_outlined),
                  erreur: _erreur?.pourChamp('code'),
                ),
                if (_codePrerempli) ...<Widget>[
                  const SizedBox(height: DmSpace.x2),
                  Text(
                    'Code pré-rempli (mode test). En production, il arrivera '
                    'sur ton WhatsApp.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: DmColors.neutral500,
                    ),
                  ),
                ],
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
                    _demanderCode();
                  }
                },
              ),

              if (_codeDemande) ...<Widget>[
                const SizedBox(height: DmSpace.x3),
                TextButton(
                  onPressed: _enCours ? null : _demanderCode,
                  child: const Text('Renvoyer le code'),
                ),
                if (_waSupportUrl != null)
                  TextButton.icon(
                    onPressed: _ouvrirSupport,
                    icon: const Icon(Icons.chat_outlined),
                    label: const Text('Besoin d\'aide ? Ouvrir WhatsApp'),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
