import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/client_providers.dart';
import '../data/modeles.dart';

/// Adresse, description, prix, publication.
///
/// Le prix apparaît **avant** le bouton de publication, jamais après. C'est
/// toute la promesse de l'onboarding : le client sait ce qu'il engage au moment
/// où il l'engage.
///
/// Le devis est marqué « à partir de » tant qu'aucun technicien n'a accepté :
/// le déplacement dépend de la distance du technicien, qui n'existe pas encore
/// (ADR-0026). L'annoncer comme ferme serait le mensonge qui produit la
/// première réclamation.
class NouvelleDemandeScreen extends ConsumerStatefulWidget {
  const NouvelleDemandeScreen({super.key});

  @override
  ConsumerState<NouvelleDemandeScreen> createState() =>
      _NouvelleDemandeScreenState();
}

class _NouvelleDemandeScreenState extends ConsumerState<NouvelleDemandeScreen> {
  final TextEditingController _description = TextEditingController();

  Devis? _devis;
  bool _calculEnCours = false;
  bool _publicationEnCours = false;
  ApiException? _erreur;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  Future<void> _calculerLePrix() async {
    final BrouillonDemande brouillon = ref.read(brouillonProvider);

    if (!brouillon.estComplet) return;

    setState(() {
      _calculEnCours = true;
      _erreur = null;
      _devis = null;
    });

    try {
      final Devis devis = await ref
          .read(clientRepositoryProvider)
          .devis(
            prestationId: brouillon.prestation!.id,
            adresseId: brouillon.adresse!.id,
          );

      if (mounted) setState(() => _devis = devis);
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _calculEnCours = false);
    }
  }

  Future<void> _publier() async {
    final BrouillonDemande brouillon = ref.read(brouillonProvider);

    if (!brouillon.estComplet || _publicationEnCours) return;

    setState(() {
      _publicationEnCours = true;
      _erreur = null;
    });

    try {
      final Ticket ticket = await ref
          .read(clientRepositoryProvider)
          .publier(
            prestationId: brouillon.prestation!.id,
            adresseId: brouillon.adresse!.id,
            description: _description.text.trim().isEmpty
                ? null
                : _description.text.trim(),
          );

      ref.read(brouillonProvider.notifier).vider();
      ref.invalidate(ticketsProvider);

      if (mounted) context.go('/client/suivi/${ticket.id}');
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e);
    } finally {
      if (mounted) setState(() => _publicationEnCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final BrouillonDemande brouillon = ref.watch(brouillonProvider);
    final AsyncValue<List<Adresse>> adresses = ref.watch(adressesProvider);
    final ThemeData theme = Theme.of(context);

    final Prestation? prestation = brouillon.prestation;

    if (prestation == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Nouvelle demande')),
        body: DmVide(
          icone: Icons.help_outline,
          titre: 'Aucune prestation choisie',
          texte: 'Reviens au catalogue pour sélectionner ce dont tu as besoin.',
          action: DmBouton(
            libelle: 'Voir le catalogue',
            onPresse: () => context.go('/client/catalogue'),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Ta demande')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(DmSpace.x6),
          children: <Widget>[
            _Section(titre: 'Prestation'),
            DmCarte(
              enfant: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      prestation.nom,
                      style: theme.textTheme.titleMedium,
                    ),
                  ),
                  Text(
                    prestation.prixFormate,
                    style: theme.textTheme.titleMedium?.copyWith(
                      color: theme.colorScheme.primary,
                    ),
                  ),
                ],
              ),
            ),

            _Section(titre: 'Où intervenir ?'),
            adresses.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (Object e, _) => DmErreur(
                message: messageErreur(e),
                onReessayer: () => ref.invalidate(adressesProvider),
              ),
              data: (List<Adresse> liste) => _ChoixAdresse(
                adresses: liste,
                choisie: brouillon.adresse,
                onChoisir: (Adresse a) {
                  ref.read(brouillonProvider.notifier).choisirAdresse(a);
                  _calculerLePrix();
                },
              ),
            ),

            _Section(titre: 'Décris le problème'),
            DmChamp(
              libelle: 'Ce qui ne va pas',
              indication: 'Fuite sous l\'évier depuis hier soir…',
              controleur: _description,
            ),

            if (_erreur != null) ...<Widget>[
              const SizedBox(height: DmSpace.x4),
              DmErreur(
                message: _erreur!.message,
                onReessayer: _erreur!.reseau ? _calculerLePrix : null,
              ),
            ],

            if (_calculEnCours) ...<Widget>[
              const SizedBox(height: DmSpace.x6),
              const Center(child: CircularProgressIndicator()),
            ],

            if (_devis != null) ...<Widget>[
              _Section(titre: 'Prix'),
              _CarteDevis(devis: _devis!),
            ],

            const SizedBox(height: DmSpace.x6),
            DmBouton(
              libelle: 'Publier ma demande',
              enCours: _publicationEnCours,
              // Publier sans avoir vu le prix est possible techniquement, mais
              // contraire à la promesse : le bouton reste inactif tant que le
              // devis n'est pas affiché.
              onPresse: _devis == null ? null : _publier,
            ),
            const SizedBox(height: DmSpace.x8),
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.titre});

  final String titre;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(0, DmSpace.x6, 0, DmSpace.x3),
    child: Text(titre, style: Theme.of(context).textTheme.titleLarge),
  );
}

class _ChoixAdresse extends StatelessWidget {
  const _ChoixAdresse({
    required this.adresses,
    required this.choisie,
    required this.onChoisir,
  });

  final List<Adresse> adresses;
  final Adresse? choisie;
  final ValueChanged<Adresse> onChoisir;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    if (adresses.isEmpty) {
      return DmCarte(
        onTape: () => context.push('/client/adresse'),
        enfant: Row(
          children: <Widget>[
            const Icon(Icons.add_location_alt_outlined),
            const SizedBox(width: DmSpace.x3),
            Expanded(
              child: Text(
                'Ajoute ta première adresse',
                style: theme.textTheme.titleMedium,
              ),
            ),
          ],
        ),
      );
    }

    return Column(
      children: <Widget>[
        for (final Adresse a in adresses) ...<Widget>[
          Opacity(
            // Une adresse hors zone reste visible mais grisée : la masquer
            // laisserait le client chercher pourquoi elle a disparu.
            opacity: a.estDesservie ? 1 : 0.5,
            child: DmCarte(
              onTape: a.estDesservie ? () => onChoisir(a) : null,
              enfant: Row(
                children: <Widget>[
                  Icon(
                    choisie?.id == a.id
                        ? Icons.radio_button_checked
                        : Icons.radio_button_unchecked,
                    color: choisie?.id == a.id
                        ? theme.colorScheme.primary
                        : theme.dividerColor,
                  ),
                  const SizedBox(width: DmSpace.x3),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(a.libelle, style: theme.textTheme.titleMedium),
                        Text(a.adresse, style: theme.textTheme.bodySmall),
                        if (!a.estDesservie)
                          Text(
                            'Hors de notre zone de couverture',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: DmColors.warning,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: DmSpace.x3),
        ],
        TextButton.icon(
          onPressed: () => context.push('/client/adresse'),
          icon: const Icon(Icons.add),
          label: const Text('Ajouter une adresse'),
        ),
      ],
    );
  }
}

class _CarteDevis extends StatelessWidget {
  const _CarteDevis({required this.devis});

  final Devis devis;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return DmCarte(
      enfant: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          for (final LigneDevis l in devis.lignes) ...<Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(l.libelle, style: theme.textTheme.bodyLarge),
                      if (l.detail != null)
                        Text(l.detail!, style: theme.textTheme.bodySmall),
                    ],
                  ),
                ),
                Text(l.montantFormate, style: theme.textTheme.bodyLarge),
              ],
            ),
            const SizedBox(height: DmSpace.x3),
          ],
          const Divider(),
          const SizedBox(height: DmSpace.x3),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  // Le mot compte : « à partir de » tant que le déplacement
                  // n'est pas connu, « total » une fois qu'il l'est.
                  devis.ferme ? 'Total' : 'À partir de',
                  style: theme.textTheme.titleMedium,
                ),
              ),
              Text(
                devis.totalFormate,
                style: theme.textTheme.titleLarge?.copyWith(
                  color: theme.colorScheme.primary,
                ),
              ),
            ],
          ),
          if (!devis.ferme) ...<Widget>[
            const SizedBox(height: DmSpace.x3),
            Text(
              'Le déplacement dépend de la distance du technicien qui acceptera. '
              'Le montant définitif te sera confirmé à ce moment-là.',
              style: theme.textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}
