import 'dart:convert';
import 'dart:io';

import 'package:path_provider/path_provider.dart';

/// Dernière réponse reçue pour une lecture, et l'heure où elle l'a été.
class LectureEnCache {
  const LectureEnCache({required this.donnees, required this.recueLe});

  final Map<String, dynamic> donnees;
  final DateTime recueLe;
}

/// Mémoire des dernières lectures, pour l'affichage hors connexion (D4).
///
/// Le mode hors-ligne de Dépanne-Moi est **en lecture seule**, à dessein.
/// Afficher la dernière version connue d'un ticket ou du portefeuille, en le
/// disant, rend service à quelqu'un qui a perdu le réseau dans un couloir. Mettre
/// en file un « je suis arrivé » pour le rejouer vingt minutes plus tard, en
/// revanche, ferait mentir l'historique — et un paiement rejoué serait pire.
/// Les écritures échouent donc franchement, comme avant.
abstract interface class CacheLecture {
  Future<LectureEnCache?> lire(String cle);

  Future<void> ecrire(String cle, Map<String, dynamic> donnees);

  /// À la déconnexion : le compte suivant sur le même téléphone ne doit rien
  /// voir de celui d'avant.
  Future<void> vider();
}

/// Pour les tests, et tant que le stockage n'est pas prêt.
class CacheEnMemoire implements CacheLecture {
  final Map<String, LectureEnCache> _lectures = <String, LectureEnCache>{};

  @override
  Future<LectureEnCache?> lire(String cle) async => _lectures[cle];

  @override
  Future<void> ecrire(String cle, Map<String, dynamic> donnees) async {
    _lectures[cle] = LectureEnCache(donnees: donnees, recueLe: DateTime.now());
  }

  @override
  Future<void> vider() async => _lectures.clear();
}

/// Un fichier JSON par lecture, dans le dossier privé de l'application.
///
/// Rien de ce qui y est écrit n'est un secret — les jetons restent dans le
/// coffre chiffré — mais tout y est personnel, d'où le dossier de support de
/// l'application, inaccessible aux autres, et le vidage à la déconnexion.
/// Toute erreur d'entrée-sortie est avalée : un cache qui échoue rend
/// l'application un peu moins utile hors réseau, jamais inutilisable.
class CacheFichiers implements CacheLecture {
  Directory? _dossier;

  Future<Directory> _racine() async {
    final Directory? connu = _dossier;
    if (connu != null) return connu;

    final Directory support = await getApplicationSupportDirectory();
    final Directory dossier = Directory('${support.path}/lectures');
    await dossier.create(recursive: true);

    return _dossier = dossier;
  }

  Future<File> _fichier(String cle) async => File(
    '${(await _racine()).path}/${base64Url.encode(utf8.encode(cle))}.json',
  );

  @override
  Future<LectureEnCache?> lire(String cle) async {
    try {
      final File fichier = await _fichier(cle);
      if (!await fichier.exists()) return null;

      final Map<String, dynamic> brut =
          jsonDecode(await fichier.readAsString()) as Map<String, dynamic>;

      return LectureEnCache(
        donnees: brut['d'] as Map<String, dynamic>,
        recueLe: DateTime.parse(brut['t'] as String),
      );
    } on Object {
      return null;
    }
  }

  @override
  Future<void> ecrire(String cle, Map<String, dynamic> donnees) async {
    try {
      await (await _fichier(cle)).writeAsString(
        jsonEncode(<String, dynamic>{
          't': DateTime.now().toIso8601String(),
          'd': donnees,
        }),
      );
    } on Object {
      return;
    }
  }

  @override
  Future<void> vider() async {
    try {
      final Directory dossier = await _racine();
      if (await dossier.exists()) await dossier.delete(recursive: true);
      _dossier = null;
    } on Object {
      return;
    }
  }
}
