import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../design_system/pc.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import '../application/recherche_prestations.dart';
import '../data/client_repository.dart';
import 'composants_client.dart';

/// Assistant de diagnostic : le client décrit sa panne avec ses mots, et
/// l'assistant propose la prestation qui correspond.
///
/// Le modèle open source hébergé répond quand il est branché (lot 4) ; sinon,
/// ou sans réseau, le moteur local du téléphone (`rechercherPrestations`).
class AssistantScreen extends ConsumerStatefulWidget {
  const AssistantScreen({super.key});

  @override
  ConsumerState<AssistantScreen> createState() => _AssistantScreenState();
}

sealed class _Bulle {
  const _Bulle();
}

class _BulleClient extends _Bulle {
  const _BulleClient(this.texte);
  final String texte;
}

class _BulleAssistant extends _Bulle {
  const _BulleAssistant(
    this.texte, [
    this.propositions = const <ResultatRecherche>[],
  ]);
  final String texte;
  final List<ResultatRecherche> propositions;
}

class _Reflexion extends _Bulle {
  const _Reflexion();
}

class _AssistantScreenState extends ConsumerState<AssistantScreen> {
  static const List<String> _exemples = <String>[
    'L’eau coule sous mon évier',
    'Ma prise a fait des étincelles',
    'Je veux repeindre mon salon',
    'Un carreau est cassé',
  ];

  final TextEditingController _saisie = TextEditingController();
  final ScrollController _defilement = ScrollController();
  final List<_Bulle> _fil = <_Bulle>[
    const _BulleAssistant(
      'Bonjour 👋 Décrivez votre problème avec vos mots, même '
      'approximatifs. Je trouve la bonne prestation.',
    ),
  ];
  bool _occupe = false;
  bool _iaDisponible = false;

  @override
  void dispose() {
    _saisie.dispose();
    _defilement.dispose();
    super.dispose();
  }

  void _defilerEnBas() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_defilement.hasClients) {
        _defilement.animateTo(
          _defilement.position.maxScrollExtent,
          duration: PcMotion.normal,
          curve: PcMotion.ease,
        );
      }
    });
  }

  Future<void> _envoyer(String texte) async {
    final String t = texte.trim();
    if (t.isEmpty || _occupe) return;

    _saisie.clear();
    setState(() {
      _occupe = true;
      _fil
        ..add(_BulleClient(t))
        ..add(const _Reflexion());
    });
    _defilerEnBas();

    List<CategoriePrestations> catalogue = const <CategoriePrestations>[];
    try {
      catalogue = await ref.read(catalogueProvider.future);
    } catch (_) {
      // Sans catalogue, la réponse dira ne pas pouvoir conclure.
    }

    final DiagnosticIa? ia = await _demanderAuModele(t);
    final List<ResultatRecherche> parIa = ia == null
        ? const <ResultatRecherche>[]
        : _depuisIdentifiants(catalogue, ia.prestations);

    if (parIa.isEmpty) {
      // Un court délai : une réponse instantanée paraît mécanique.
      await Future<void>.delayed(const Duration(milliseconds: 650));
    }
    final List<ResultatRecherche> resultats = parIa.isNotEmpty
        ? parIa
        : rechercherPrestations(catalogue, t, limite: 2);

    if (!mounted) return;
    setState(() {
      _fil.removeWhere((_Bulle b) => b is _Reflexion);
      if (parIa.isNotEmpty) {
        _iaDisponible = true;
        _fil.add(
          _BulleAssistant(
            <String>[
              if (ia!.explication.isNotEmpty) ia.explication,
              if ((ia.conseil ?? '').isNotEmpty) '⚠️ ${ia.conseil}',
            ].join('\n\n'),
            parIa,
          ),
        );
      } else if (resultats.isEmpty) {
        _fil.add(
          const _BulleAssistant(
            'Je n’ai pas bien identifié la panne 🤔 Pouvez-vous préciser la '
            'pièce ou l’appareil concerné (évier, prise, mur, porte…) ? Vous '
            'pouvez aussi parcourir les métiers.',
          ),
        );
      } else {
        final ResultatRecherche r = resultats.first;
        _fil.add(
          _BulleAssistant(
            'D’après votre description, il s’agit très probablement de : '
            '${r.prestation.nom} (${r.categorie.nom} · '
            '${r.prestation.typeLibelle}). Confiance ${confiance(resultats)} %.',
            resultats,
          ),
        );
      }
      _occupe = false;
    });
    _defilerEnBas();
  }

  /// Le modèle hébergé, s'il répond. Toute erreur rend `null` : le moteur
  /// local prend le relais sans rien afficher.
  Future<DiagnosticIa?> _demanderAuModele(String texte) async {
    try {
      return await ref
          .read(clientRepositoryProvider)
          .diagnostiquer(texte)
          .timeout(const Duration(seconds: 30));
    } on Object {
      return null;
    }
  }

  static List<ResultatRecherche> _depuisIdentifiants(
    List<CategoriePrestations> catalogue,
    List<int> ids,
  ) => <ResultatRecherche>[
    for (final int id in ids)
      for (final CategoriePrestations c in catalogue)
        for (final Prestation p in c.prestations)
          if (p.id == id)
            ResultatRecherche(categorie: c, prestation: p, score: 1),
  ];

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: <Widget>[
            PcHeader(
              titre: 'Assistant Parema',
              sousTitre: 'Diagnostic à partir de votre description',
              action: PcPill(
                _iaDisponible ? 'IA' : 'Hors ligne',
                couleur: _iaDisponible ? PcColors.tech : PcColors.coral,
                icone: Icons.auto_awesome_rounded,
              ),
            ),
            Expanded(
              child: ListView(
                controller: _defilement,
                padding: const EdgeInsets.fromLTRB(18, 4, 18, 18),
                children: <Widget>[
                  for (final _Bulle b in _fil) ...<Widget>[
                    _rendre(context, b),
                    const SizedBox(height: 12),
                  ],
                  if (_fil.length == 1)
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        for (final String e in _exemples)
                          PcChip(libelle: e, onTap: () => _envoyer(e)),
                      ],
                    ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 14),
              color: pc.bg,
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: TextField(
                      controller: _saisie,
                      textInputAction: TextInputAction.send,
                      onSubmitted: _envoyer,
                      minLines: 1,
                      maxLines: 4,
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14.5,
                        color: pc.ink,
                      ),
                      decoration: const InputDecoration(
                        hintText:
                            'Ex : la lumière de la cuisine ne marche plus',
                      ),
                    ),
                  ),
                  const SizedBox(width: 9),
                  PcPressable(
                    onTap: () => _envoyer(_saisie.text),
                    child: Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        gradient: pc.gradientAccent,
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: const Icon(
                        Icons.send_rounded,
                        color: Colors.white,
                      ),
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

  Widget _rendre(BuildContext context, _Bulle bulle) {
    final PcSkin pc = context.pc;

    Widget bulleDe(String texte, {required bool client}) => Align(
      alignment: client ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.8,
        ),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 12),
          decoration: BoxDecoration(
            color: client ? pc.accent : pc.card,
            boxShadow: client ? null : pc.ombre,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(20),
              topRight: const Radius.circular(20),
              bottomLeft: Radius.circular(client ? 20 : 6),
              bottomRight: Radius.circular(client ? 6 : 20),
            ),
          ),
          child: Text(
            texte,
            style: TextStyle(
              color: client ? Colors.white : pc.ink,
              fontSize: 14,
              height: 1.45,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ),
    );

    return switch (bulle) {
      _BulleClient(:final String texte) => bulleDe(texte, client: true),
      _Reflexion() => Align(
        alignment: Alignment.centerLeft,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            color: pc.card,
            borderRadius: BorderRadius.circular(20),
            boxShadow: pc.ombre,
          ),
          child: SizedBox(
            width: 34,
            height: 14,
            child: LinearProgressIndicator(
              color: pc.accent,
              backgroundColor: pc.soft,
              borderRadius: BorderRadius.circular(7),
            ),
          ),
        ),
      ),
      _BulleAssistant(
        :final String texte,
        :final List<ResultatRecherche> propositions,
      ) =>
        Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            bulleDe(texte, client: false),
            if (propositions.isEmpty && _fil.length > 2) ...<Widget>[
              const SizedBox(height: 10),
              Align(
                alignment: Alignment.centerLeft,
                child: PcChip(
                  libelle: 'Parcourir les métiers',
                  icone: Icons.grid_view_rounded,
                  onTap: () => context.pop(),
                ),
              ),
            ],
            for (int i = 0; i < propositions.length; i++) ...<Widget>[
              const SizedBox(height: 10),
              PcCard(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        PcIco(
                          PcMetier.de(propositions[i].categorie.code).icone,
                          couleur: PcMetier.de(propositions[i].categorie.code)
                              .couleur,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                propositions[i].prestation.nom,
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                              Text(
                                '${propositions[i].categorie.nom} · '
                                '${dureeTexte(propositions[i].prestation.dureeMin)}',
                                style: TextStyle(
                                  color: pc.muted,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                        PcMontant(
                          propositions[i].prestation.prixGnf,
                          prefixe: propositions[i].prestation.surDevis
                              ? 'dès'
                              : null,
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    PcButton(
                      petit: true,
                      variante: i == 0 ? PcVariante.accent : PcVariante.doux,
                      libelle: i == 0 ? 'Voir et choisir' : 'Plutôt celle-ci',
                      onPressed: () => ouvrirFichePrestation(
                        context,
                        ref,
                        prestation: propositions[i].prestation,
                        categorie: propositions[i].categorie,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
    };
  }
}
