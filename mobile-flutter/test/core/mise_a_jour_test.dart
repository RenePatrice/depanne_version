import 'package:depanne_moi/core/mise_a_jour/mise_a_jour.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('comparaison de versions', () {
    test('compare champ par champ, pas comme du texte', () {
      // « 1.10.0 » < « 1.9.0 » en ordre alphabétique : le piège classique.
      expect(comparerVersions('1.9.0', '1.10.0'), -1);
      expect(comparerVersions('2.0.0', '1.99.9'), 1);
      expect(comparerVersions('1.2.0', '1.2.0'), 0);
    });

    test('ignore le numéro de build et les libellés', () {
      expect(comparerVersions('1.2.0+7', '1.2.0'), 0);
      expect(comparerVersions('1.2.0-beta', '1.2.0'), 0);
    });

    test('complète les champs manquants par zéro', () {
      expect(comparerVersions('1.2', '1.2.0'), 0);
      expect(comparerVersions('1.2', '1.2.1'), -1);
    });
  });

  group('état de mise à jour', () {
    test('propose une version plus récente', () {
      const EtatMiseAJour e = EtatMiseAJour(
        installee: '1.0.0',
        derniere: '1.1.0',
      );
      expect(e.disponible, isTrue);
      expect(e.obligatoire, isFalse);
    });

    test('rend la mise à jour obligatoire sous la version minimale', () {
      const EtatMiseAJour e = EtatMiseAJour(
        installee: '1.0.0',
        derniere: '1.3.0',
        minimale: '1.2.0',
      );
      expect(e.obligatoire, isTrue);
    });

    test('ne prétend rien quand le serveur ne sait rien', () {
      const EtatMiseAJour e = EtatMiseAJour(installee: '1.0.0');
      expect(e.disponible, isFalse);
      expect(e.obligatoire, isFalse);
    });
  });
}
