import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/chat_repository.dart';

final Provider<ChatRepository> chatRepositoryProvider =
    Provider<ChatRepository>(
      (Ref ref) => ChatRepository(ref.watch(apiClientProvider)),
    );

/// Le fil d'une intervention. Libéré dès que l'écran se ferme : la lecture
/// marque les messages comme lus, et une lecture en arrière-plan accuserait
/// réception de messages que personne n'a vus.
final conversationProvider = FutureProvider.autoDispose
    .family<Conversation, int>(
      (Ref ref, int ticketId) =>
          ref.watch(chatRepositoryProvider).conversation(ticketId),
    );
