import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';

/// Un quartier de la zone pilote et son point de référence.
///
/// La position n'a pas à être exacte au mètre : elle sert à trouver la zone et
/// à mesurer une distance au technicien, tous deux à l'échelle du kilomètre.
/// Le point de repère écrit, lui, est ce qui amène réellement quelqu'un devant
/// la bonne porte — à Conakry, une adresse postale ne le fait pas.
class _Quartier {
  const _Quartier(this.nom, this.commune, this.latitude, this.longitude);

  final String nom;
  final String commune;
  final double latitude;
  final double longitude;
}

/// Ajouter une adresse au carnet.
///
/// Le choix du quartier remplace, pour l'instant, le sélecteur sur carte : il
/// demande une clé Google Maps que le pilote n'a pas encore, et l'écran ne peut
/// pas attendre. Les neuf quartiers couvrent exactement les trois zones
/// ouvertes ; le jour où la carte arrive, seul cet écran change.
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
  static const List<_Quartier> _quartiers = <_Quartier>[
    _Quartier('Kipé', 'Ratoma', 9.5960, -13.6420),
    _Quartier('Nongo', 'Ratoma', 9.6050, -13.6480),
    _Quartier('Taouyah', 'Ratoma', 9.5800, -13.6540),
    _Quartier('Kaporo', 'Ratoma', 9.6220, -13.6300),
    _Quartier('Lambanyi', 'Ratoma', 9.6300, -13.6200),
    _Quartier('Sonfonia', 'Ratoma', 9.6480, -13.6100),
    _Quartier('Hamdallaye', 'Ratoma', 9.5620, -13.6380),
    _Quartier('Cosa', 'Ratoma', 9.5700, -13.6450),
    _Quartier('Koloma', 'Ratoma', 9.5760, -13.6300),
  ];

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

  _Quartier? _quartier;
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
    final _Quartier? quartier = _quartier;

    if (quartier == null || _enCours || !_estComplet) return;

    setState(() {
      _enCours = true;
      _erreur = null;
    });

    try {
      final Adresse creee = await ref
          .read(clientRepositoryProvider)
          .ajouterAdresse(
            libelle: _libelle.text.trim(),
            adresse: _adresse.text.trim(),
            latitude: quartier.latitude,
            longitude: quartier.longitude,
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

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Nouvelle adresse')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(DmSpace.x6),
          children: <Widget>[
            Text('Quel quartier ?', style: theme.textTheme.titleLarge),
            const SizedBox(height: DmSpace.x3),
            Wrap(
              spacing: DmSpace.x2,
              runSpacing: DmSpace.x2,
              children: <Widget>[
                for (final _Quartier q in _quartiers)
                  ChoiceChip(
                    label: Text(q.nom),
                    selected: _quartier?.nom == q.nom,
                    onSelected: (_) => setState(() => _quartier = q),
                  ),
              ],
            ),
            const SizedBox(height: DmSpace.x2),
            Text(
              'Nous intervenons aujourd\'hui dans la commune de Ratoma.',
              style: theme.textTheme.bodySmall,
            ),

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
