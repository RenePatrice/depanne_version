import 'package:flutter/material.dart';

import '../../../design_system/components/dm_composants.dart';

/// La messagerie, sous son adresse définitive.
///
/// Elle est livrée en phase D4, avec le temps réel. La route existe déjà pour
/// la même raison qu'en back-office (ADR-0015) : le bouton « Contacter » du
/// suivi mène quelque part qui explique, plutôt que nulle part. Le jour où
/// l'écran arrive, aucune adresse ne change.
class ChatScreen extends StatelessWidget {
  const ChatScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Messages')),
    body: const DmVide(
      icone: Icons.chat_bubble_outline,
      titre: 'Messagerie en préparation',
      texte:
          'Le fil de discussion avec ton technicien arrive en phase D4, avec '
          'les notifications en temps réel. En attendant, son numéro reste '
          'affiché sur le suivi pendant toute l\'intervention.',
    ),
  );
}
