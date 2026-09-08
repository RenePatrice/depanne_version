/// Écriture des montants, côté application.
///
/// Le serveur formate déjà tout ce qu'il expose — `total_formate`,
/// `montant_formate` — et **c'est cette version-là qu'on affiche** partout où
/// elle existe : deux mises en forme qui divergent finissent par faire douter
/// d'un chiffre.
///
/// Cette fonction ne sert qu'aux rares montants que l'application compose
/// elle-même : le net technicien, que toutes les réponses ne formatent pas, et
/// l'aperçu d'un supplément en cours de saisie. Elle reprend exactement
/// l'écriture de `App\Support\Money` — groupes de trois chiffres, séparateur
/// U+202F, suffixe GNF — pour que deux montants d'une même carte se lisent
/// pareil.
library;

/// Espace fine insécable, la `Money::SEPARATEUR` du serveur.
///
/// Insécable à dessein : un retour à la ligne au milieu de « 86 000 » se lit
/// comme deux nombres. Un test compare cette constante à celle du PHP, pour que
/// la dérive se voie avant qu'on la remarque à l'œil.
const String separateurMillier = '\u202F';

String formaterGnf(int montantGnf) {
  final String chiffres = montantGnf.abs().toString();
  final StringBuffer sortie = StringBuffer();

  for (int i = 0; i < chiffres.length; i++) {
    if (i > 0 && (chiffres.length - i) % 3 == 0) {
      sortie.write(separateurMillier);
    }

    sortie.write(chiffres[i]);
  }

  return '${montantGnf < 0 ? '-' : ''}$sortie${separateurMillier}GNF';
}
