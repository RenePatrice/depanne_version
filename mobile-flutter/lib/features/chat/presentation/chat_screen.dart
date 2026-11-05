import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/temps_reel/temps_reel_providers.dart';
import '../../../design_system/components/dm_composants.dart';
import '../../../design_system/design_tokens.dart';
import '../application/chat_providers.dart';
import '../data/chat_repository.dart';

/// Le fil d'une intervention, entre le client et le technicien (§7.3).
///
/// Un seul écran pour les deux modes : la conversation est la même, seule la
/// couleur du thème change. Les messages arrivent par Reverb sur
/// `ticket.{id}` ; l'interrogation reste en repli.
///
/// Le filtre anti-contournement est côté serveur, et seulement là. Un message
/// contenant un numéro part quand même, masqué, et l'avertissement du serveur
/// est affiché tel quel : il explique *pourquoi*, ce qu'un refus ne ferait pas
/// (ADR-0030). Le rejouer ici donnerait deux jeux de règles, et c'est celui de
/// l'application qui finirait par se tromper.
class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({required this.ticketId, super.key});

  final int ticketId;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen>
    with InterrogationDeRepli<ChatScreen> {
  final TextEditingController _saisie = TextEditingController();

  bool _envoiEnCours = false;
  bool _appelEnCours = false;
  String? _avertissement;

  @override
  void initState() {
    super.initState();
    demarrerInterrogation(() {
      ref.invalidate(conversationProvider(widget.ticketId));
      return ref.read(conversationProvider(widget.ticketId)).value?.ouverte ??
          true;
    });
  }

  @override
  void dispose() {
    _saisie.dispose();
    super.dispose();
  }

  Future<void> _envoyer() async {
    final String contenu = _saisie.text.trim();
    if (contenu.isEmpty || _envoiEnCours) return;

    setState(() => _envoiEnCours = true);

    try {
      final MessageEnvoye envoye = await ref
          .read(chatRepositoryProvider)
          .envoyer(widget.ticketId, contenu);

      _saisie.clear();
      setState(() => _avertissement = envoye.avertissement);
      ref.invalidate(conversationProvider(widget.ticketId));
    } on ApiException catch (e) {
      // Le texte saisi est conservé : sur une coupure réseau, le retaper
      // serait la pire des réponses.
      _signaler(e.message);
    } finally {
      if (mounted) setState(() => _envoiEnCours = false);
    }
  }

  Future<void> _appeler() async {
    setState(() => _appelEnCours = true);

    try {
      _signaler(
        await ref.read(chatRepositoryProvider).appeler(widget.ticketId),
      );
    } on ApiException catch (e) {
      _signaler(e.message);
    } finally {
      if (mounted) setState(() => _appelEnCours = false);
    }
  }

  void _signaler(String message) {
    if (!mounted || message.isEmpty) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(signauxProvider('ticket.${widget.ticketId}'), (_, _) {
      ref.invalidate(conversationProvider(widget.ticketId));
    });

    final AsyncValue<Conversation> conversation = ref.watch(
      conversationProvider(widget.ticketId),
    );
    final bool ouverte = conversation.value?.ouverte ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Messages'),
        actions: <Widget>[
          if (ouverte)
            IconButton(
              tooltip: 'Appeler sans dévoiler mon numéro',
              onPressed: _appelEnCours ? null : _appeler,
              icon: const Icon(Icons.call_outlined),
            ),
        ],
      ),
      body: Column(
        children: <Widget>[
          Expanded(
            child: conversation.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (Object e, _) => Padding(
                padding: const EdgeInsets.all(DmSpace.x6),
                child: DmErreur(
                  message: messageErreur(e),
                  onReessayer: () =>
                      ref.invalidate(conversationProvider(widget.ticketId)),
                ),
              ),
              data: (Conversation c) => c.messages.isEmpty
                  ? DmVide(
                      icone: Icons.chat_bubble_outline,
                      titre: c.ouverte
                          ? 'Aucun message pour l\'instant'
                          : 'Conversation fermée',
                      texte: c.ouverte
                          ? 'Écris ici pour préciser la panne ou l\'accès '
                                'à ton domicile. Les numéros de téléphone sont '
                                'masqués : passe par l\'appel masqué.'
                          : 'Le fil n\'est ouvert que pendant l\'intervention.',
                    )
                  : _Fil(messages: c.messages),
            ),
          ),
          if (_avertissement != null)
            _Avertissement(
              texte: _avertissement!,
              onFermer: () => setState(() => _avertissement = null),
            ),
          if (ouverte)
            _Saisie(
              controleur: _saisie,
              enCours: _envoiEnCours,
              onEnvoyer: _envoyer,
            )
          else if (conversation.hasValue)
            Padding(
              padding: const EdgeInsets.all(DmSpace.x4),
              child: Text(
                'La conversation est fermée en dehors de l\'intervention.',
                style: Theme.of(context).textTheme.bodySmall,
                textAlign: TextAlign.center,
              ),
            ),
        ],
      ),
    );
  }
}

class _Fil extends StatelessWidget {
  const _Fil({required this.messages});

  final List<MessageChat> messages;

  @override
  Widget build(BuildContext context) {
    // Le plus récent en bas, sous le pouce, et la liste démarre là : c'est
    // l'ordre de lecture d'une messagerie, et celui que tout le monde attend.
    final List<MessageChat> inverses = messages.reversed.toList();

    return ListView.builder(
      reverse: true,
      padding: const EdgeInsets.all(DmSpace.x4),
      itemCount: inverses.length,
      itemBuilder: (_, int i) => _Bulle(message: inverses[i]),
    );
  }
}

class _Bulle extends StatelessWidget {
  const _Bulle({required this.message});

  final MessageChat message;

  static String _heure(DateTime moment) =>
      '${moment.hour.toString().padLeft(2, '0')}:'
      '${moment.minute.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool moi = message.deMoi;
    final Color fond = moi
        ? theme.colorScheme.primary
        : theme.colorScheme.surfaceContainerHighest;
    final Color texte = moi
        ? theme.colorScheme.onPrimary
        : theme.colorScheme.onSurface;

    return Align(
      alignment: moi ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: DmSpace.x1),
          padding: const EdgeInsets.symmetric(
            horizontal: DmSpace.x3,
            vertical: DmSpace.x2,
          ),
          decoration: BoxDecoration(color: fond, borderRadius: DmRadius.mdAll),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(message.contenu, style: TextStyle(color: texte)),
              const SizedBox(height: DmSpace.x1),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  if (message.masque) ...<Widget>[
                    Icon(Icons.shield_outlined, size: 12, color: texte),
                    const SizedBox(width: DmSpace.x1),
                    Text(
                      'Coordonnées masquées',
                      style: TextStyle(color: texte, fontSize: DmType.fs6),
                    ),
                    const SizedBox(width: DmSpace.x2),
                  ],
                  if (message.envoyeLe != null)
                    Text(
                      _heure(message.envoyeLe!),
                      style: TextStyle(
                        color: texte.withValues(alpha: 0.75),
                        fontSize: DmType.fs6,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Avertissement extends StatelessWidget {
  const _Avertissement({required this.texte, required this.onFermer});

  final String texte;
  final VoidCallback onFermer;

  @override
  Widget build(BuildContext context) => Material(
    color: DmColors.warning.withValues(alpha: 0.15),
    child: Padding(
      padding: const EdgeInsets.fromLTRB(
        DmSpace.x4,
        DmSpace.x2,
        DmSpace.x1,
        DmSpace.x2,
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.info_outline, color: DmColors.warning),
          const SizedBox(width: DmSpace.x3),
          Expanded(
            child: Text(texte, style: Theme.of(context).textTheme.bodySmall),
          ),
          IconButton(
            tooltip: 'Fermer',
            onPressed: onFermer,
            icon: const Icon(Icons.close, size: 18),
          ),
        ],
      ),
    ),
  );
}

class _Saisie extends StatelessWidget {
  const _Saisie({
    required this.controleur,
    required this.enCours,
    required this.onEnvoyer,
  });

  final TextEditingController controleur;
  final bool enCours;
  final VoidCallback onEnvoyer;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Padding(
      padding: const EdgeInsets.all(DmSpace.x3),
      child: Row(
        children: <Widget>[
          Expanded(
            child: TextField(
              controller: controleur,
              minLines: 1,
              maxLines: 4,
              // La borne du serveur (SendMessage::LONGUEUR_MAX).
              maxLength: 2000,
              textInputAction: TextInputAction.send,
              onSubmitted: (_) => onEnvoyer(),
              decoration: const InputDecoration(
                hintText: 'Écris ton message',
                counterText: '',
              ),
            ),
          ),
          const SizedBox(width: DmSpace.x2),
          IconButton.filled(
            tooltip: 'Envoyer',
            onPressed: enCours ? null : onEnvoyer,
            icon: enCours
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.send),
          ),
        ],
      ),
    ),
  );
}
