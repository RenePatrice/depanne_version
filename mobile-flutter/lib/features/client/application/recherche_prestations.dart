import '../../shared/data/modeles.dart';

/// Correspondance entre une description libre et le catalogue.
///
/// Elle tourne sur le téléphone : la recherche et l'assistant répondent même
/// sans réseau. Les mots du quotidien (« ça coule », « le courant saute »)
/// sont ramenés au vocabulaire du catalogue par une table de synonymes ; le
/// modèle de langue open source, côté serveur, viendra s'y ajouter sans que
/// cette base disparaisse — elle reste le repli quand le réseau manque.
class ResultatRecherche {
  const ResultatRecherche({
    required this.categorie,
    required this.prestation,
    required this.score,
  });

  final CategoriePrestations categorie;
  final Prestation prestation;
  final double score;
}

/// Minuscules, sans accents ni ponctuation.
String normaliser(String texte) {
  const Map<String, String> accents = <String, String>{
    'à': 'a',
    'â': 'a',
    'ä': 'a',
    'á': 'a',
    'é': 'e',
    'è': 'e',
    'ê': 'e',
    'ë': 'e',
    'î': 'i',
    'ï': 'i',
    'í': 'i',
    'ô': 'o',
    'ö': 'o',
    'ó': 'o',
    'ù': 'u',
    'û': 'u',
    'ü': 'u',
    'ú': 'u',
    'ç': 'c',
    'œ': 'oe',
    'æ': 'ae',
    '’': ' ',
    '\'': ' ',
  };

  final StringBuffer sortie = StringBuffer();
  for (final String c in texte.toLowerCase().split('')) {
    sortie.write(accents[c] ?? c);
  }

  return sortie.toString().replaceAll(RegExp(r'[^a-z0-9 ]'), ' ');
}

/// Mots courants → vocabulaire du catalogue.
const Map<String, List<String>> _synonymes = <String, List<String>>{
  'coule': <String>['fuite', 'eau'],
  'goutte': <String>['fuite', 'robinet'],
  'fuit': <String>['fuite'],
  'inonde': <String>['fuite', 'eau'],
  'bouche': <String>['debouchage', 'canalisation'],
  'evacuation': <String>['debouchage', 'canalisation'],
  'toilette': <String>['wc', 'chasse'],
  'toilettes': <String>['wc', 'chasse'],
  'douche': <String>['plomberie', 'debouchage'],
  'lavabo': <String>['plomberie', 'evier'],
  'courant': <String>['electricite', 'disjoncteur', 'prise'],
  'saute': <String>['disjoncteur'],
  'coupure': <String>['electricite', 'panne'],
  'etincelle': <String>['prise', 'electricite'],
  'etincelles': <String>['prise', 'electricite'],
  'lumiere': <String>['luminaire', 'eclairage', 'electricite'],
  'lampe': <String>['luminaire', 'eclairage'],
  'ampoule': <String>['luminaire', 'eclairage'],
  'ventilo': <String>['ventilateur'],
  'fissure': <String>['mur', 'maconnerie'],
  'lezarde': <String>['fissure', 'mur'],
  'cloture': <String>['mur', 'maconnerie'],
  'repeindre': <String>['peinture'],
  'peindre': <String>['peinture'],
  'salon': <String>['peinture', 'piece'],
  'chambre': <String>['peinture', 'piece'],
  'porte': <String>['menuiserie'],
  'fenetre': <String>['menuiserie'],
  'placard': <String>['menuiserie'],
  'armoire': <String>['menuiserie', 'placard'],
  'carreau': <String>['carrelage'],
  'carreaux': <String>['carrelage'],
  'sol': <String>['carrelage'],
  'faience': <String>['carrelage'],
};

const Set<String> _motsVides = <String>{
  'le',
  'la',
  'les',
  'un',
  'une',
  'des',
  'de',
  'du',
  'et',
  'ou',
  'mon',
  'ma',
  'mes',
  'est',
  'sont',
  'il',
  'elle',
  'y',
  'a',
  'au',
  'aux',
  'ne',
  'pas',
  'plus',
  'en',
  'dans',
  'sur',
  'sous',
  'qui',
  'que',
  'je',
  'veux',
  'ai',
  'mais',
  'avec',
  'pour',
  'par',
  'tres',
  'depuis',
  'ce',
  'cette',
  'ca',
};

List<String> _mots(String texte) =>
    normaliser(texte)
        .split(' ')
        .where((String m) => m.length > 1 && !_motsVides.contains(m))
        .toList();

/// Les prestations qui correspondent le mieux, de la plus probable à la moins.
List<ResultatRecherche> rechercherPrestations(
  List<CategoriePrestations> catalogue,
  String texte, {
  int limite = 20,
}) {
  final List<String> mots = _mots(texte);
  if (mots.isEmpty) return const <ResultatRecherche>[];

  final List<String> etendus = <String>[
    ...mots,
    for (final String m in mots) ...?_synonymes[m],
  ];

  final List<ResultatRecherche> resultats = <ResultatRecherche>[];

  for (final CategoriePrestations c in catalogue) {
    final String metier = normaliser(c.nom);

    for (final Prestation p in c.prestations) {
      final String nom = normaliser(p.nom);
      final String corps = normaliser(
        '${p.description ?? ''} ${p.inclus.join(' ')} $metier',
      );

      double score = 0;
      for (final String m in etendus) {
        final bool direct = mots.contains(m);
        if (nom.contains(m)) {
          score += direct ? 3 : 2;
        } else if (corps.contains(m)) {
          score += direct ? 1.2 : 0.8;
        } else if (m.length >= 5 &&
            nom.contains(m.substring(0, m.length - 1))) {
          // Pluriel ou terminaison approchée (« fuites », « prises »).
          score += 1;
        }
      }

      if (score > 0) {
        resultats.add(
          ResultatRecherche(categorie: c, prestation: p, score: score),
        );
      }
    }
  }

  resultats.sort(
    (ResultatRecherche a, ResultatRecherche b) => b.score.compareTo(a.score),
  );

  return resultats.take(limite).toList();
}

/// Confiance affichable (en %) du premier résultat face au second.
int confiance(List<ResultatRecherche> resultats) {
  if (resultats.isEmpty) return 0;
  final double premier = resultats.first.score;
  final double second = resultats.length > 1 ? resultats[1].score : 0;
  final double ecart = premier == 0 ? 0 : (premier - second) / premier;

  return (55 + (premier.clamp(0, 9) / 9) * 25 + ecart * 18).round().clamp(
    40,
    97,
  );
}
