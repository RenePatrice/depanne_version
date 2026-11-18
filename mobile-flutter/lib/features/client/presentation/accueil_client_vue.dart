import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../../auth/application/session_controller.dart';
import '../../notifications/application/notifications_providers.dart';
import '../../shared/data/modeles.dart';
import '../application/client_providers.dart';
import 'composants_client.dart';

/// Accueil de l'espace client (prototype v4).
///
/// Tout ce qui s'affiche vient du serveur : prénom, adresse, métiers,
/// prestations, demande en cours. Aucun chiffre n'est inventé — un compteur
/// « 2 300 pros » sans source serait le premier mensonge de l'application.
class AccueilClientVue extends ConsumerWidget {
  const AccueilClientVue({super.key, required this.onExplorer});

  /// Bascule vers l'onglet « Explorer ».
  final VoidCallback onExplorer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final Session session = ref.watch(sessionProvider);
    final AsyncValue<List<CategoriePrestations>> catalogue = ref.watch(
      catalogueProvider,
    );
    final AsyncValue<List<Ticket>> enCours = ref.watch(ticketsProvider(true));
    final AsyncValue<List<Adresse>> adresses = ref.watch(adressesProvider);
    final int nonLues = ref.watch(notificationsProvider).value?.nonLues ?? 0;

    final String prenom = session.utilisateur?.prenom ?? '';
    final Adresse? adresse = adresses.value?.isNotEmpty ?? false
        ? adresses.value!.firstWhere(
            (Adresse a) => a.parDefaut,
            orElse: () => adresses.value!.first,
          )
        : null;
    final Ticket? actif = enCours.value
        ?.where((Ticket t) => t.etatActif)
        .firstOrNull;

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async {
        ref.invalidate(catalogueProvider);
        ref.invalidate(ticketsProvider(true));
        ref.invalidate(adressesProvider);
        ref.invalidate(notificationsProvider);
      },
      child: ListView(
        padding: EdgeInsets.fromLTRB(
          18,
          MediaQuery.of(context).padding.top + 10,
          18,
          PcTabBar.espaceReserve,
        ),
        children: <Widget>[
          Row(
            children: <Widget>[
              PcAvatar(
                nom: session.utilisateur?.nomComplet ?? prenom,
                image: session.utilisateur?.avatarUrl,
                couleurs: const <Color>[
                  PcColors.coralLight,
                  PcColors.coralDeep,
                ],
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      prenom.isEmpty ? 'Bonjour 👋' : 'Bonjour $prenom 👋',
                      style: TextStyle(
                        color: pc.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                    GestureDetector(
                      onTap: () => context.push('/client/adresse'),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          const Icon(
                            Icons.place_rounded,
                            size: 16,
                            color: PcColors.coral,
                          ),
                          const SizedBox(width: 3),
                          Flexible(
                            child: Text(
                              adresse?.libelle ?? 'Ajouter mon adresse',
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          Icon(
                            Icons.keyboard_arrow_down_rounded,
                            size: 18,
                            color: pc.ink,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              PcIconButton(
                icone: Icons.notifications_none_rounded,
                pastille: nonLues > 0,
                libelle: 'Notifications',
                onTap: () => context.push('/notifications'),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Text.rich(
            TextSpan(
              children: <InlineSpan>[
                const TextSpan(text: 'Qu’est-ce qu’on\nrépare '),
                TextSpan(
                  text: 'aujourd’hui',
                  style: TextStyle(color: pc.accent),
                ),
                const TextSpan(text: ' ?'),
              ],
            ),
            style: Theme.of(context).textTheme.displaySmall,
          ),
          const SizedBox(height: 16),
          _BarreRecherche(
            onRecherche: () => context.push('/client/recherche'),
            onAssistant: () => context.push('/client/assistant'),
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                flex: 11,
                child: _Tuile(
                  degrade: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: <Color>[
                      PcColors.coralLight,
                      PcColors.coral,
                      PcColors.coralDeep,
                    ],
                  ),
                  pastille: 'Maintenant',
                  titre: 'Dépannage\nexpress',
                  texte: 'Une panne à réparer vite',
                  icone: Icons.bolt_rounded,
                  onTap: onExplorer,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 10,
                child: _Tuile(
                  degrade: const LinearGradient(
                    begin: Alignment.topRight,
                    end: Alignment.bottomLeft,
                    colors: <Color>[
                      Color(0xFF3A5BFF),
                      Color(0xFF1A2240),
                      Color(0xFF0D1220),
                    ],
                  ),
                  pastille: 'Assistant',
                  titre: 'Décrivez,\non identifie',
                  texte: 'Trouver la bonne prestation',
                  icone: Icons.auto_awesome_rounded,
                  onTap: () => context.push('/client/assistant'),
                ),
              ),
            ],
          ),
          PcSection('Métiers', lien: 'Tout voir', onLien: onExplorer),
          catalogue.when(
            loading: () => const _Attente(hauteur: 96),
            error: (Object e, _) => PcErreur(
              message: 'Le catalogue n’a pas pu être chargé.',
              onReessayer: () => ref.invalidate(catalogueProvider),
            ),
            data: (List<CategoriePrestations> categories) => SizedBox(
              height: 100,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                clipBehavior: Clip.none,
                itemCount: categories.length,
                separatorBuilder: (_, _) => const SizedBox(width: 8),
                itemBuilder: (BuildContext context, int i) =>
                    _MetierRond(categorie: categories[i]),
              ),
            ),
          ),
          if (actif != null) ...<Widget>[
            const SizedBox(height: 16),
            _DemandeEnCours(ticket: actif),
          ],
          catalogue.maybeWhen(
            data: (List<CategoriePrestations> categories) =>
                _Courantes(categories: categories),
            orElse: () => const SizedBox.shrink(),
          ),
          const SizedBox(height: 22),
          PcCard(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[PcColors.techDeep, Color(0xFF064E3B)],
            ),
            padding: const EdgeInsets.all(18),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'GARANTIE PAREMA',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.75),
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'La panne revient ? Le même pro revient gratuitement.',
                        style: TextStyle(
                          fontFamily: PcFonts.display,
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                          height: 1.25,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Uniquement pour les interventions passées par l’app.',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.75),
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(
                  Icons.verified_user_rounded,
                  size: 54,
                  color: Colors.white.withValues(alpha: 0.25),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BarreRecherche extends StatelessWidget {
  const _BarreRecherche({required this.onRecherche, required this.onAssistant});

  final VoidCallback onRecherche;
  final VoidCallback onAssistant;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return PcPressable(
      onTap: onRecherche,
      child: Container(
        height: 58,
        padding: const EdgeInsets.only(left: 16, right: 8),
        decoration: BoxDecoration(
          color: pc.card,
          borderRadius: BorderRadius.circular(20),
          boxShadow: pc.ombre,
        ),
        child: Row(
          children: <Widget>[
            Icon(Icons.search_rounded, color: pc.muted),
            const SizedBox(width: 11),
            Expanded(
              child: Text(
                'Fuite, prise, carrelage…',
                style: TextStyle(
                  color: pc.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 14.5,
                ),
              ),
            ),
            Semantics(
              button: true,
              label: 'Assistant',
              child: GestureDetector(
                onTap: onAssistant,
                child: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    gradient: const LinearGradient(
                      colors: <Color>[
                        PcColors.coralLight,
                        PcColors.coral,
                        PcColors.coralDeep,
                      ],
                    ),
                  ),
                  child: const Icon(
                    Icons.auto_awesome_rounded,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Tuile extends StatelessWidget {
  const _Tuile({
    required this.degrade,
    required this.pastille,
    required this.titre,
    required this.texte,
    required this.icone,
    required this.onTap,
  });

  final Gradient degrade;
  final String pastille;
  final String titre;
  final String texte;
  final IconData icone;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PcPressable(
      onTap: onTap,
      child: Container(
        height: 140,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: degrade,
          borderRadius: PcRadius.lgAll,
        ),
        child: Stack(
          clipBehavior: Clip.hardEdge,
          children: <Widget>[
            Positioned(
              right: -24,
              bottom: -24,
              child: Icon(
                icone,
                size: 104,
                color: Colors.white.withValues(alpha: 0.18),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 9,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(99),
                  ),
                  child: Text(
                    pastille,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const Spacer(),
                Text(
                  titre,
                  style: const TextStyle(
                    fontFamily: PcFonts.display,
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                    height: 1.15,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  texte,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.85),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _MetierRond extends StatelessWidget {
  const _MetierRond({required this.categorie});

  final CategoriePrestations categorie;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final PcMetier metier = PcMetier.de(categorie.code);

    return PcPressable(
      echelle: 0.92,
      onTap: () => context.push('/client/categorie/${categorie.code}'),
      child: SizedBox(
        width: 78,
        child: Column(
          children: <Widget>[
            Container(
              width: 66,
              height: 66,
              decoration: BoxDecoration(
                color: pc.teinte(metier.couleur),
                borderRadius: BorderRadius.circular(22),
                boxShadow: pc.ombre,
              ),
              child: Icon(metier.icone, color: metier.couleur, size: 28),
            ),
            const SizedBox(height: 8),
            Text(
              categorie.nom,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: pc.ink2,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DemandeEnCours extends StatelessWidget {
  const _DemandeEnCours({required this.ticket});

  final Ticket ticket;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;
    final bool aChoisir = ticket.etat == 'PUBLIEE';
    final String? technicien = ticket.technicien?.nomComplet;

    return PcCard(
      onTap: () => context.push(
        aChoisir ? '/client/choix/${ticket.id}' : '/client/suivi/${ticket.id}',
      ),
      child: Row(
        children: <Widget>[
          if (technicien != null)
            PcAvatar(nom: technicien, taille: 40)
          else
            PcIco(Icons.search_rounded, couleur: pc.accent, taille: 40),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'EN COURS · ${ticket.reference}',
                  style: TextStyle(
                    color: pc.muted,
                    fontWeight: FontWeight.w800,
                    fontSize: 11,
                    letterSpacing: 0.3,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  aChoisir
                      ? 'Choisissez votre technicien'
                      : '${ticket.prestation?.nom ?? 'Intervention'} · ${ticket.etatLibelle}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ],
            ),
          ),
          PcPill(aChoisir ? 'À choisir' : 'Suivre', couleur: pc.accent),
        ],
      ),
    );
  }
}

/// Les prestations de dépannage les plus courantes : la première de chaque
/// métier, dans l'ordre du catalogue fixé en back-office.
class _Courantes extends ConsumerWidget {
  const _Courantes({required this.categories});

  final List<CategoriePrestations> categories;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final List<(CategoriePrestations, Prestation)> lignes =
        <(CategoriePrestations, Prestation)>[
          for (final CategoriePrestations c in categories)
            if (c.prestations.any((Prestation p) => p.type == 'DEPANNAGE'))
              (
                c,
                c.prestations.firstWhere(
                  (Prestation p) => p.type == 'DEPANNAGE',
                ),
              ),
        ];

    if (lignes.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        PcSection(
          'Pannes courantes',
          lien: 'Rechercher',
          onLien: () => context.push('/client/recherche'),
        ),
        for (final (CategoriePrestations c, Prestation p) in lignes.take(4))
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: LignePrestation(
              prestation: p,
              metier: PcMetier.de(c.code),
              categorie: c.nom,
              onTap: () => ouvrirFichePrestation(
                context,
                ref,
                prestation: p,
                categorie: c,
              ),
            ),
          ),
      ],
    );
  }
}

class _Attente extends StatelessWidget {
  const _Attente({required this.hauteur});

  final double hauteur;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: hauteur,
    child: const Center(child: CircularProgressIndicator(strokeWidth: 2.4)),
  );
}
