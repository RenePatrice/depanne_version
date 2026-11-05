import 'package:flutter/foundation.dart';

import '../../../core/network/api_client.dart';

/// Un message du fil d'une intervention.
@immutable
class MessageChat {
  const MessageChat({
    required this.id,
    required this.contenu,
    required this.deMoi,
    required this.masque,
    this.expediteur,
    this.envoyeLe,
  });

  final int id;

  /// Le texte **tel que le serveur l'a filtré**. L'original d'un message
  /// masqué ne quitte jamais le serveur ; l'application n'a aucun moyen de le
  /// montrer, et c'est voulu.
  final String contenu;
  final bool deMoi;

  /// Un numéro, un e-mail ou un lien y a été masqué. Le message reste affiché
  /// comme tel : voir son propre message filtré dissuade mieux qu'un
  /// avertissement lu une fois.
  final bool masque;
  final String? expediteur;
  final DateTime? envoyeLe;

  factory MessageChat.depuisJson(Map<String, dynamic> json) => MessageChat(
    id: json['id'] as int,
    contenu: json['contenu'] as String? ?? '',
    deMoi: json['de_moi'] as bool? ?? false,
    masque: json['masque'] as bool? ?? false,
    expediteur: json['expediteur'] as String?,
    envoyeLe: DateTime.tryParse(json['envoye_le'] as String? ?? '')?.toLocal(),
  );
}

@immutable
class Conversation {
  const Conversation({required this.ouverte, required this.messages});

  /// Le serveur décide : avant l'acceptation et après la clôture, il n'y a
  /// plus rien à se dire, et rouvrir permettrait de reprendre contact hors de
  /// tout cadre des semaines plus tard.
  final bool ouverte;
  final List<MessageChat> messages;
}

/// Ce que renvoie un envoi : le message tel qu'il est parti, et
/// l'avertissement éventuel du filtre.
@immutable
class MessageEnvoye {
  const MessageEnvoye({required this.message, this.avertissement});

  final MessageChat message;
  final String? avertissement;
}

/// Appels de la messagerie. Les deux parties passent par les mêmes routes : ce
/// qui compte est d'être partie au ticket, pas d'être client ou technicien.
class ChatRepository {
  const ChatRepository(this._api);

  final ApiClient _api;

  Future<Conversation> conversation(int ticketId) async {
    final Map<String, dynamic> reponse = await _api.get(
      '/tickets/$ticketId/messages',
      repliHorsLigne: true,
    );

    return Conversation(
      ouverte: reponse['ouvert'] as bool? ?? false,
      messages: ((reponse['messages'] as List<dynamic>?) ?? const <dynamic>[])
          .map((dynamic m) => MessageChat.depuisJson(m as Map<String, dynamic>))
          .toList(),
    );
  }

  Future<MessageEnvoye> envoyer(int ticketId, String contenu) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/messages',
      corps: <String, dynamic>{'contenu': contenu},
    );

    return MessageEnvoye(
      message: MessageChat.depuisJson(
        reponse['message'] as Map<String, dynamic>,
      ),
      avertissement: reponse['avertissement'] as String?,
    );
  }

  /// Mise en relation par numéro masqué. Le message du serveur dit
  /// franchement si le service est encore simulé : un bouton qui fait
  /// semblant coûte plus cher en confiance qu'une fonction annoncée.
  Future<String> appeler(int ticketId) async {
    final Map<String, dynamic> reponse = await _api.post(
      '/tickets/$ticketId/appel',
    );

    return reponse['message'] as String? ?? '';
  }
}
