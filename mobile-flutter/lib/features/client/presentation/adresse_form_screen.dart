import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'selecteur_carte.dart';

/// Un quartier de la zone pilote et son point de référence.
///
/// La position n'a pas à être exacte au mètre : elle sert à trouver la zone et
/// à mesurer une distance au technicien, tous deux à l'échelle du kilomètre.
/// Le point de repère écrit, lui, est ce qui amène réellement quelqu'un devant
/// la bonne porte — à Conakry, une adresse postale ne le fait pas.
/// Ajouter une adresse au carnet.
///
/// Le client choisit sa commune, puis son quartier : la liste vient du
/// back-office (`/zones`, une zone = un quartier), si bien qu'un quartier
/// ouvert là-bas apparaît ici sans nouvelle version de l'application. Le
/// quartier centre la carte OpenStreetMap, sur laquelle le client affine le
/// point exact.
///
/// Le serveur reste seul juge de la couverture — il interroge PostGIS. On ne
/// devine pas ici qu'une adresse est desservie : la liste ci-dessous n'est pas
/// une frontière, c'est une commodité de saisie.
class AdresseFormScreen extends ConsumerStatefulWidget {
  const AdresseFormScreen({super.key});

  @override
  ConsumerState<AdresseFormScreen> createState() => _AdresseFormScreenState();
}

class _AdresseFormScreenState extends ConsumerState<AdresseFormScreen> {
  /// Les libellés que tout le monde utilise, proposés d'emblée. Le champ reste
  /// libre : imposer une liste fermée obligerait à appeler « Autre » la maison
  /// de sa mère.
  static const List<String> _libellesCourants = <String>[
    'Maison',
    'Bureau',
    'Chez mes parents',
  ];

  final TextEditingController _libelle = TextEditingController();
  final TextEditingController _adresse = TextEditingController();
  final TextEditingController _repere = TextEditingController();

  /// Commune sélectionnée : elle filtre les quartiers proposés. Sans elle,
  /// trente-huit pastilles d'un coup noieraient le client.
  String? _commune;
  QuartierCouvert? _quartier;

  /// Point affiné sur la carte OSM (#7). Part du centre du quartier choisi,
  /// puis suit chaque toucher sur la carte.
  LatLng? _point;

  bool _parDefaut = false;
  bool _enCours = false;
  ApiException? _erreur;

  @override
  void initState() {
    super.initState();

    // Le bouton d'enregistrement dépend de deux champs de texte : sans ces
    // écoutes il resterait inactif jusqu'à ce qu'un autre geste provoque un
    // rendu, et le formulaire paraîtrait cassé.
    _libelle.addListener(_rafraichir);
    _adresse.addListener(_rafraichir);
  }

  void _rafraichir() => setState(() {});

  @override
  void dispose() {
    _libelle
      ..removeListener(_rafraichir)
      ..dispose();
    _adresse
      ..removeListener(_rafraichir)
      ..dispose();
    _repere.dispose();
    super.dispose();
  }

  bool get _estComplet =>
      _quartier != null &&
      _libelle.text.trim().isNotEmpty &&
      _adresse.text.trim().isNotEmpty;

  Future<void> _enregistrer() async {
    final QuartierCouvert? quartier = _quartier;

    if (quartier == null || _enCours || !_estComplet) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    // Le point affiné sur la carte prime sur le centre du quartier ; sans
    // affinage, le centre du quartier reste un repère valable.
    final LatLng point =
        _point ?? LatLng(quartier.latitude, quartier.longitude);

    try {
      final Adresse creee = await ref
          .read(clientRepositoryProvider)
          .ajouterAdresse(
            libelle: _libelle.text.trim(),
            adresse: _adresse.text.trim(),
            latitude: point.latitude,
            longitude: point.longitude,
            repere: _repere.text.trim().isEmpty ? null : _repere.text.trim(),
            parDefaut: _parDefaut,
          );

      ref.invalidate(adressesProvider);

      // L'adresse tout juste créée devient celle de la demande en cours : le
      // client vient de l'ajouter pour elle, lui demander de la re-choisir
      // ensuite serait lui faire refaire un geste qu'il a déjà fait.
      ref.read(brouillonProvider.notifier).choisirAdresse(creee);

      if (!mounted) return;

      if (context.canPop()) {
        context.pop();
      } else {
        context.go('/accueil');
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  /// Commune, puis quartier. La liste vient du serveur ; elle est gardée pour
  /// le hors-ligne par le dépôt.
  Widget _choixQuartier(ThemeData theme) {
    final AsyncValue<List<QuartierCouvert>> quartiers = ref.watch(
      quartiersProvider,
    );

    return quartiers.when(
      loading: () => const Padding(
        padding: EdgeInsets.symmetric(vertical: DmSpace.x4),
        child: Center(child: CircularProgressIndicator()),
      ),
      error: (Object e, StackTrace _) => DmErreur(
        message: messageErreur(e),
        onReessayer: () => ref.invalidate(quartiersProvider),
      ),
      data: (List<QuartierCouvert> liste) {
        if (liste.isEmpty) {
          return Text(
            'Aucun quartier n\'est encore ouvert. Reviens bientôt !',
            style: theme.textTheme.bodyMedium,
          );
        }

        // Le serveur range déjà par commune : on garde son ordre.
        final List<String> communes = <String>{
          for (final QuartierCouvert q in liste)
            if (q.commune.isNotEmpty) q.commune,
        }.toList();

        final List<QuartierCouvert> duQuartier = <QuartierCouvert>[
          for (final QuartierCouvert q in liste)
            if (q.commune == _commune) q,
        ];

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Commune', style: theme.textTheme.titleSmall),
            const SizedBox(height: DmSpace.x2),
            Wrap(
              spacing: DmSpace.x2,
              runSpacing: DmSpace.x2,
              children: <Widget>[
                for (final String c in communes)
                  ChoiceChip(
                    label: Text(c),
                    selected: _commune == c,
                    onSelected: (_) => setState(() {
                      // Changer de commune efface le quartier : il appartenait
                      // à l'ancienne.
                      if (_commune != c) {
                        _quartier = null;
                        _point = null;
                      }
                      _commune = c;
                    }),
                  ),
              ],
            ),
            if (_commune != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              Text('Quartier', style: theme.textTheme.titleSmall),
              const SizedBox(height: DmSpace.x2),
              Wrap(
                spacing: DmSpace.x2,
                runSpacing: DmSpace.x2,
                children: <Widget>[
                  for (final QuartierCouvert q in duQuartier)
                    ChoiceChip(
                      label: Text(q.nom),
                      selected: _quartier?.id == q.id,
                      onSelected: (_) => setState(() {
                        _quartier = q;
                        _point = LatLng(q.latitude, q.longitude);
                      }),
                    ),
                ],
              ),
            ] else ...<Widget>[
              const SizedBox(height: DmSpace.x2),
              Text(
                'Choisis d\'abord ta commune pour voir ses quartiers.',
                style: theme.textTheme.bodySmall,
              ),
            ],
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Nouvelle adresse')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(DmSpace.x6),
          children: <Widget>[
            Text('Où habites-tu ?', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x3),
            _choixQuartier(theme),

            // Carte OSM (#7) : affine le point une fois le quartier choisi.
            if (_quartier != null && _point != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              Text('Affine l\'emplacement', style: theme.textTheme.titleMedium),
              const SizedBox(height: DmSpace.x2),
              Text(
                'Touche la carte pour poser le repère au plus près de ta porte. '
                'La position sert à trouver un technicien proche ; le point de '
                'repère écrit, plus bas, reste le plus utile.',
                style: theme.textTheme.bodySmall,
              ),
              const SizedBox(height: DmSpace.x3),
              SelecteurCarte(
                point: _point!,
                onChange: (LatLng p) => setState(() => _point = p),
              ),
            ],

            const SizedBox(height: DmSpace.x6),
            Text('Comment l\'appeler ?', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x3),
            Wrap(
              spacing: DmSpace.x2,
              children: <Widget>[
                for (final String l in _libellesCourants)
                  ActionChip(
                    label: Text(l),
                    onPressed: () => setState(() {
                      _libelle.text = l;
                    }),
                  ),
              ],
            ),
            const SizedBox(height: DmSpace.x3),
            DmChamp(
              libelle: 'Libellé',
              indication: 'Maison',
              controleur: _libelle,
              action: TextInputAction.next,
            ),

            const SizedBox(height: DmSpace.x6),
            DmChamp(
              libelle: 'Adresse',
              indication: 'Kipé, carrefour Constantin, 2e ruelle à droite',
              controleur: _adresse,
              action: TextInputAction.next,
            ),
            const SizedBox(height: DmSpace.x4),
            DmChamp(
              libelle: 'Point de repère (facultatif)',
              indication: 'Portail vert, en face de la pharmacie',
              controleur: _repere,
            ),
            const SizedBox(height: DmSpace.x2),
            Text(
              'C\'est ce repère qui amènera le technicien devant ta porte. '
              'Prends le temps de l\'écrire.',
              style: theme.textTheme.bodySmall,
            ),

            const SizedBox(height: DmSpace.x4),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _parDefaut,
              onChanged: (bool v) => setState(() => _parDefaut = v),
              title: const Text('Adresse par défaut'),
              subtitle: const Text('Proposée en premier à chaque demande.'),
            ),

            if (_erreur != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              DmErreur(
                message: _erreur!.message,
                onReessayer: _erreur!.reseau ? _enregistrer : null,
              ),
            ],

            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Enregistrer',
              enCours: _enCours,
              onPresse: _estComplet ? _enregistrer : null,
            ),
            const SizedBox(height: DmSpace.x8),
          ],
        ),
      ),
    );
  }
}
