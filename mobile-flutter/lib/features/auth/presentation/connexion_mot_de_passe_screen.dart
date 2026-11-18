import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../application/session_controller.dart';

/// Deuxième étape de l'entrée : le mot de passe pour ce numéro, ou la création
/// d'un compte avec ce numéro.
///
/// Les deux portes sont proposées ensemble, quel que soit le numéro : afficher
/// l'une ou l'autre selon que le numéro existe dirait à n'importe qui qui est
/// inscrit. Une connexion refusée renvoie le même message dans tous les cas.
class ConnexionMotDePasseScreen extends ConsumerStatefulWidget {
  const ConnexionMotDePasseScreen({super.key, required this.chiffres});

  /// Les neuf chiffres saisis à l'écran précédent.
  final String chiffres;

  @override
  ConsumerState<ConnexionMotDePasseScreen> createState() =>
      _ConnexionMotDePasseScreenState();
}

class _ConnexionMotDePasseScreenState
    extends ConsumerState<ConnexionMotDePasseScreen> {
  final TextEditingController _motDePasse = TextEditingController();
  bool _visible = false;
  bool _envoi = false;

  @override
  void dispose() {
    _motDePasse.dispose();
    super.dispose();
  }

  String get _affichage {
    final String c = widget.chiffres;
    if (c.length != 9) return c;

    return '${c.substring(0, 3)} ${c.substring(3, 5)} ${c.substring(5, 7)} ${c.substring(7)}';
  }

  Future<void> _connecter() async {
    if (_motDePasse.text.isEmpty) return;
    FocusScope.of(context).unfocus();
    setState(() => _envoi = true);

    // En cas de succès, le routeur quitte cet écran de lui-même.
    await ref
        .read(sessionProvider.notifier)
        .connecter(
          telephone: '+224${widget.chiffres}',
          motDePasse: _motDePasse.text,
        );

    if (mounted) setState(() => _envoi = false);
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final Session session = ref.watch(sessionProvider);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(
              titre: 'Bon retour 👋',
              sousTitre: '+224 $_affichage',
              onRetour: () {
                ref.read(sessionProvider.notifier).effacerErreur();
                context.pop();
              },
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
                children: <Widget>[
                  if (session.erreur != null) ...<Widget>[
                    PcErreur(
                      message: session.erreur!.message,
                      onReessayer: session.erreur!.reseau ? _connecter : null,
                    ),
                    const SizedBox(height: 16),
                  ],
                  PcField(
                    libelle: 'Mot de passe',
                    controleur: _motDePasse,
                    icone: Icons.lock_outline_rounded,
                    motDePasse: !_visible,
                    autofill: const <String>[AutofillHints.password],
                    actionClavier: TextInputAction.done,
                    onSubmitted: (_) => _connecter(),
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
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () => context.push('/mot-de-passe-oublie'),
                      child: const Text('Mot de passe oublié ?'),
                    ),
                  ),
                  const SizedBox(height: 8),
                  PcButton(
                    libelle: 'Se connecter',
                    chargement: _envoi,
                    onPressed: _connecter,
                  ),
                  const SizedBox(height: 28),
                  PcCard(
                    onTap: () =>
                        context.push('/inscription?tel=${widget.chiffres}'),
                    child: Row(
                      children: <Widget>[
                        PcIco(
                          Icons.person_add_alt_1_rounded,
                          couleur: pc.accent,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                'Première fois sur Parema ?',
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Créer mon compte avec ce numéro',
                                style: TextStyle(
                                  color: pc.muted,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 12.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded, color: pc.muted),
                      ],
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
