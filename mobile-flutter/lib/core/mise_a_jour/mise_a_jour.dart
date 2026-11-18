import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../network/api_exception.dart';
import '../providers.dart';

/// Ce que l'application sait de sa propre version et de la dernière publiée.
class EtatMiseAJour {
  const EtatMiseAJour({
    required this.installee,
    this.derniere,
    this.minimale,
    this.urlTelechargement,
  });

  final String installee;
  final String? derniere;
  final String? minimale;
  final String? urlTelechargement;

  /// Une version plus récente existe.
  bool get disponible =>
      derniere != null && comparerVersions(installee, derniere!) < 0;

  /// La version installée n'est plus prise en charge par le serveur.
  bool get obligatoire =>
      minimale != null && comparerVersions(installee, minimale!) < 0;
}

/// Compare deux versions « 1.4.0 » champ par champ. Le suffixe de build
/// (`+12`) et tout libellé (`-beta`) sont ignorés : seul compte ce que le
/// client publie comme numéro de version.
int comparerVersions(String a, String b) {
  List<int> champs(String v) => v
      .split('+')
      .first
      .split('-')
      .first
      .split('.')
      .map((String c) => int.tryParse(c.trim()) ?? 0)
      .toList();

  final List<int> x = champs(a);
  final List<int> y = champs(b);
  final int n = x.length > y.length ? x.length : y.length;

  for (int i = 0; i < n; i++) {
    final int gauche = i < x.length ? x[i] : 0;
    final int droite = i < y.length ? y[i] : 0;
    if (gauche != droite) return gauche < droite ? -1 : 1;
  }

  return 0;
}

/// Version installée, isolée pour pouvoir la remplacer en test.
final Provider<Future<String>> versionInstalleeProvider =
    Provider<Future<String>>(
      (Ref ref) async => (await PackageInfo.fromPlatform()).version,
    );

/// Vérifie s'il existe une version plus récente de l'application.
///
/// Une vérification qui échoue (réseau, serveur muet) rend simplement « rien
/// de nouveau » : ne pas savoir n'est pas une raison d'inquiéter quelqu'un.
final FutureProvider<EtatMiseAJour> miseAJourProvider =
    FutureProvider<EtatMiseAJour>((Ref ref) async {
      final String installee = await ref.watch(versionInstalleeProvider);

      try {
        final Map<String, dynamic> r = await ref
            .read(apiClientProvider)
            .get('/application/version');

        return EtatMiseAJour(
          installee: installee,
          derniere: r['derniere_version'] as String?,
          minimale: r['version_minimale'] as String?,
          urlTelechargement: r['url_telechargement'] as String?,
        );
      } on ApiException {
        return EtatMiseAJour(installee: installee);
      }
    });
