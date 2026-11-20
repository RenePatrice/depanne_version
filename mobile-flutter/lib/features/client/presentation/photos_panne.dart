import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../design_system/pc.dart';
import '../application/client_providers.dart';

/// Une photo de panne : choisie sur l'appareil, puis déposée sur le serveur.
class PhotoPanne {
  PhotoPanne(this.local);

  final String local;
  String? chemin;
  bool enCours = true;
  String? erreur;
}

/// Photos de la panne (lot 3) : jusqu'à [maximum], chacune déposée dès
/// qu'elle est choisie pour que la publication n'attende pas.
class PhotosPanne extends ConsumerStatefulWidget {
  const PhotosPanne({required this.maximum, required this.onChange, super.key});

  final int maximum;

  /// Rappelé à chaque changement : chemins déposés, et dépôt en cours ou non.
  final void Function(List<String> chemins, bool enCours) onChange;

  @override
  ConsumerState<PhotosPanne> createState() => _PhotosPanneState();
}

class _PhotosPanneState extends ConsumerState<PhotosPanne> {
  final List<PhotoPanne> _photos = <PhotoPanne>[];

  void _signaler() => widget.onChange(<String>[
    for (final PhotoPanne p in _photos)
      if (p.chemin != null) p.chemin!,
  ], _photos.any((PhotoPanne p) => p.enCours));

  Future<void> _ajouter() async {
    final ImageSource? source = await showPcSheet<ImageSource>(
      context,
      builder: (BuildContext ctx) => Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Photo de la panne', style: Theme.of(ctx).textTheme.titleLarge),
          const SizedBox(height: 16),
          PcButton(
            libelle: 'Prendre une photo',
            icone: Icons.photo_camera_rounded,
            onPressed: () => Navigator.of(ctx).pop(ImageSource.camera),
          ),
          const SizedBox(height: 10),
          PcButton(
            libelle: 'Choisir dans la galerie',
            icone: Icons.photo_library_rounded,
            variante: PcVariante.doux,
            onPressed: () => Navigator.of(ctx).pop(ImageSource.gallery),
          ),
        ],
      ),
    );
    if (source == null) return;

    final XFile? fichier = await ImagePicker().pickImage(
      source: source,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 82,
    );
    if (fichier == null || !mounted) return;

    final PhotoPanne photo = PhotoPanne(fichier.path);
    setState(() => _photos.add(photo));
    _signaler();
    await _deposer(photo);
  }

  Future<void> _deposer(PhotoPanne photo) async {
    setState(() {
      photo.enCours = true;
      photo.erreur = null;
    });
    _signaler();

    try {
      photo.chemin = await ref
          .read(clientRepositoryProvider)
          .deposerPhoto(photo.local);
    } on ApiException catch (e) {
      photo.erreur = e.message;
    }

    if (!mounted) return;
    setState(() => photo.enCours = false);
    _signaler();
  }

  void _retirer(PhotoPanne photo) {
    setState(() => _photos.remove(photo));
    _signaler();
  }

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: <Widget>[
            for (final PhotoPanne p in _photos)
              _Vignette(
                photo: p,
                onRetirer: () => _retirer(p),
                onReessayer: () => _deposer(p),
              ),
            if (_photos.length < widget.maximum)
              Semantics(
                button: true,
                label: 'Ajouter une photo de la panne',
                child: PcPressable(
                  onTap: _ajouter,
                  child: Container(
                    width: 92,
                    height: 92,
                    decoration: BoxDecoration(
                      color: pc.soft,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: pc.line),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: <Widget>[
                        Icon(Icons.add_a_photo_rounded, color: pc.accent),
                        const SizedBox(height: 6),
                        Text(
                          '${_photos.length}/${widget.maximum}',
                          style: TextStyle(
                            color: pc.muted,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          'Les photos aident le technicien à préparer son intervention. '
          'Seul le technicien choisi les voit.',
          style: TextStyle(color: pc.muted, fontSize: 12),
        ),
      ],
    );
  }
}

class _Vignette extends StatelessWidget {
  const _Vignette({
    required this.photo,
    required this.onRetirer,
    required this.onReessayer,
  });

  final PhotoPanne photo;
  final VoidCallback onRetirer;
  final VoidCallback onReessayer;

  @override
  Widget build(BuildContext context) {
    final PcSkin pc = context.pc;

    return SizedBox(
      width: 92,
      height: 92,
      child: Stack(
        children: <Widget>[
          Positioned.fill(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: Image.file(File(photo.local), fit: BoxFit.cover),
            ),
          ),
          if (photo.enCours || photo.erreur != null)
            Positioned.fill(
              child: Container(
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Center(
                  child: photo.enCours
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.4,
                            color: Colors.white,
                          ),
                        )
                      : IconButton(
                          tooltip: photo.erreur,
                          onPressed: onReessayer,
                          icon: const Icon(
                            Icons.refresh_rounded,
                            color: Colors.white,
                          ),
                        ),
                ),
              ),
            ),
          Positioned(
            top: 4,
            right: 4,
            child: Semantics(
              button: true,
              label: 'Retirer la photo',
              child: GestureDetector(
                onTap: onRetirer,
                child: Container(
                  width: 26,
                  height: 26,
                  decoration: BoxDecoration(
                    color: pc.card,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.close_rounded, size: 16, color: pc.ink),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
