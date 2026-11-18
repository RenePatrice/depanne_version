import 'package:depanne_moi/features/client/application/recherche_prestations.dart';
import 'package:depanne_moi/features/shared/data/modeles.dart';
import 'package:flutter_test/flutter_test.dart';

Prestation _p(
  int id,
  String nom, {
  String? description,
  String type = 'DEPANNAGE',
}) => Prestation(
  id: id,
  nom: nom,
  prixGnf: 75000,
  prixFormate: '75 000 GNF',
  description: description,
  type: type,
);

final List<CategoriePrestations> _catalogue = <CategoriePrestations>[
  CategoriePrestations(
    id: 1,
    code: 'PLOMBERIE',
    nom: 'Plomberie',
    prestations: <Prestation>[
      _p(1, 'Fuite sous évier', description: 'Joint, siphon ou raccord'),
      _p(2, 'Débouchage canalisation', description: 'Évier, douche, lavabo'),
      _p(3, 'Pose de WC', type: 'INSTALLATION'),
    ],
  ),
  CategoriePrestations(
    id: 2,
    code: 'ELECTRICITE',
    nom: 'Électricité',
    prestations: <Prestation>[
      _p(
        4,
        'Prise défectueuse',
        description: 'Prise qui chauffe ou ne marche plus',
      ),
      _p(5, 'Disjoncteur qui saute', description: 'Recherche de court-circuit'),
    ],
  ),
];

void main() {
  group('correspondance entre une description et le catalogue', () {
    test('ignore accents et majuscules', () {
      expect(normaliser('Évier À CÔTÉ'), 'evier a cote');
    });

    test('comprend les mots du quotidien grâce aux synonymes', () {
      final List<ResultatRecherche> r = rechercherPrestations(
        _catalogue,
        'L’eau coule sous mon évier',
      );

      expect(r.first.prestation.nom, 'Fuite sous évier');
    });

    test('relie « le courant saute » au disjoncteur', () {
      final List<ResultatRecherche> r = rechercherPrestations(
        _catalogue,
        'le courant saute tout le temps',
      );

      expect(r.first.prestation.nom, 'Disjoncteur qui saute');
    });

    test('classe d’abord ce qui figure dans le nom', () {
      final List<ResultatRecherche> r = rechercherPrestations(
        _catalogue,
        'prise',
      );

      expect(r.first.prestation.nom, 'Prise défectueuse');
    });

    test('ne renvoie rien pour une phrase sans mot utile', () {
      expect(rechercherPrestations(_catalogue, 'le la les de'), isEmpty);
      expect(rechercherPrestations(_catalogue, 'xylophone'), isEmpty);
    });

    test('borne la confiance affichée', () {
      final List<ResultatRecherche> r = rechercherPrestations(
        _catalogue,
        'fuite évier',
      );

      expect(confiance(r), inInclusiveRange(40, 97));
      expect(confiance(const <ResultatRecherche>[]), 0);
    });
  });
}
