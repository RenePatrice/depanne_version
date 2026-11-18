import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../design_system/pc.dart';
import '../../auth/application/session_controller.dart';
import '../../shared/data/modeles.dart';
import '../../shared/mode/selecteur_mode.dart';
import '../../shared/profil/profil_commun.dart';
import '../application/client_providers.dart';

/// Onglet « Profil » du client : identité, carnet d'adresses, compte.
///
/// Le carnet vit ici plutôt que dans le seul tunnel de commande : une adresse
/// se corrige au calme, pas au moment où l'on a une fuite d'eau.
class ProfilClientScreen extends ConsumerWidget {
  const ProfilClientScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PcSkin pc = context.pc;
    final Session session = ref.watch(sessionProvider);
    final AsyncValue<List<Adresse>> adresses = ref.watch(adressesProvider);
    final AsyncValue<List<Ticket>> tickets = ref.watch(ticketsProvider(false));
    final bool peutBasculer =
        (session.utilisateur?.modesDisponibles.length ?? 1) > 1;

    final List<Ticket> liste = tickets.value ?? const <Ticket>[];
    final int terminees = liste
        .where((Ticket t) => t.etat == 'CLOTUREE')
        .length;
    final int enCours = liste.where((Ticket t) => !t.etatFinal).length;

    return RefreshIndicator(
      color: pc.accent,
      onRefresh: () async {
        ref.invalidate(adressesProvider);
        ref.invalidate(ticketsProvider);
      },
      child: ListView(
        padding: EdgeInsets.fromLTRB(
          18,
          MediaQuery.of(context).padding.top + 18,
          18,
          PcTabBar.espaceReserve,
        ),
        children: <Widget>[
          const EnteteProfil(),
          const SizedBox(height: 18),

          // Chiffres réels du compte, comptés depuis ses propres demandes.
          Row(
            children: <Widget>[
              _Chiffre(valeur: '$enCours', libelle: 'En cours'),
              const SizedBox(width: 10),
              _Chiffre(valeur: '$terminees', libelle: 'Terminées'),
              const SizedBox(width: 10),
              _Chiffre(
                valeur: '${adresses.value?.length ?? 0}',
                libelle: 'Adresses',
              ),
            ],
          ),

          PcSection(
            'Mes adresses',
            lien: 'Ajouter',
            onLien: () => context.push('/client/adresse'),
          ),
          adresses.when(
            loading: () => const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
            error: (Object e, StackTrace _) => PcErreur(
              message: messageErreur(e),
              onReessayer: () => ref.invalidate(adressesProvider),
            ),
            data: (List<Adresse> liste) => liste.isEmpty
                ? PcCard(
                    onTap: () => context.push('/client/adresse'),
                    child: Row(
                      children: <Widget>[
                        PcIco(
                          Icons.add_location_alt_outlined,
                          couleur: pc.accent,
                          taille: 42,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Ajoutez une adresse maintenant : le jour de la '
                            'panne, vous n’aurez plus qu’à choisir.',
                            style: TextStyle(
                              color: pc.ink2,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                  )
                : GroupeProfil(
                    lignes: <Widget>[
                      for (final Adresse a in liste) _LigneAdresse(adresse: a),
                    ],
                  ),
          ),

          const PcSection('Activité'),
          GroupeProfil(
            lignes: <Widget>[
              LigneProfil(
                icone: Icons.report_problem_outlined,
                libelle: 'Mes réclamations',
                onTap: () => context.push('/client/reclamations'),
              ),
              LigneProfil(
                icone: Icons.notifications_none_rounded,
                libelle: 'Notifications',
                onTap: () => context.push('/notifications'),
              ),
              if (peutBasculer)
                LigneProfil(
                  icone: Icons.swap_horiz_rounded,
                  libelle: 'Passer à mon espace technicien',
                  couleur: PcColors.tech,
                  onTap: () => SelecteurMode.ouvrir(context),
                ),
            ],
          ),

          const LignesCompte(),
        ],
      ),
    );
  }
}

class _Chiffre extends StatelessWidget {
  const _Chiffre({required this.valeur, required this.libelle});

  final String valeur;
  final String libelle;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Expanded(
      child: PcCard(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Column(
          children: <Widget>[
            Text(
              valeur,
              style: TextStyle(
                fontFamily: PcFonts.display,
                fontWeight: FontWeight.w700,
                fontSize: 22,
                color: pc.ink,
              ),
            ),
            Text(
              libelle,
              style: TextStyle(
                color: pc.muted,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LigneAdresse extends ConsumerStatefulWidget {
  const _LigneAdresse({required this.adresse});

  final Adresse adresse;

  @override
  ConsumerState<_LigneAdresse> createState() => _LigneAdresseState();
}

class _LigneAdresseState extends ConsumerState<_LigneAdresse> {
  bool _enCours = false;

  Future<void> _supprimer() async {
    // Une adresse supprimée ne réécrit pas l'historique — le ticket en garde
    // un instantané (ADR-0013) — mais elle disparaît pour de bon du carnet.
    final bool? confirme = await showPcSheet<bool>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Supprimer « ${widget.adresse.libelle} » ?',
            style: Theme.of(ctx).textTheme.titleLarge,
          ),
          const SizedBox(height: 6),
          Text(
            'Elle ne sera plus proposée pour vos prochaines demandes. Vos '
            'interventions passées gardent l’adresse où elles ont eu lieu.',
            style: TextStyle(color: ctx.pc.muted, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 18),
          PcButton(
            libelle: 'Supprimer',
            variante: PcVariante.corail,
            onPressed: () => Navigator.of(ctx).pop(true),
          ),
          const SizedBox(height: 8),
          PcButton(
            libelle: 'Garder',
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(false),
          ),
        ],
      ),
    );

    if (confirme != true || _enCours) return;

    setState(() => _enCours = true);

    try {
      await ref
          .read(clientRepositoryProvider)
          .supprimerAdresse(widget.adresse.id);
      ref.invalidate(adressesProvider);
    } on ApiException catch (e) {
      if (mounted) {
        showPcToast(context, e.message, icone: Icons.error_outline_rounded);
      }
    } finally {
      if (mounted) setState(() => _enCours = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final Adresse a = widget.adresse;

    return LigneProfil(
      icone: a.libelle.toLowerCase().contains('bureau')
          ? Icons.work_outline_rounded
          : Icons.home_outlined,
      libelle: a.parDefaut ? '${a.libelle} · par défaut' : a.libelle,
      detail: a.estDesservie
          ? a.adresse
          : '${a.adresse}\nHors de notre zone de couverture',
      fin: IconButton(
        tooltip: 'Supprimer',
        onPressed: _enCours ? null : _supprimer,
        icon: _enCours
            ? const SizedBox(
                height: 18,
                width: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Icon(Icons.delete_outline_rounded, color: context.pc.muted),
      ),
    );
  }
}
