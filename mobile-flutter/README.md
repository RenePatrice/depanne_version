# Application mobile — bloc D

Application Flutter **Client / Technicien** : un binaire, deux modes. Elle
consomme l'API `/api/v1` du backend Laravel, sans jamais toucher à la base.

## État

| Phase | Contenu | État |
|---|---|---|
| **D1** | Design system, thèmes, client Dio + refresh, auth, navigation, sélecteur de mode | ✅ terminée |
| **D2** | Parcours client : catalogue → adresse → prix → publication → suivi → supplément → paiement → avis → historique | ✅ terminée |
| D3 | Parcours technicien : disponibilité, réception, intervention, portefeuille | à faire |
| D4 | Chat, push, temps réel, mode hors-ligne | à faire |
| D5 | Build, signature, publication | à faire |

## Outillage

Flutter **3.47.2** (Dart 3.13.2), installé en portable dans
`%USERPROFILE%\devtools\flutter`. Aucun droit administrateur, aucune variable
d'environnement système.

```cmd
scripts\flutter.cmd --version      :: n'importe quelle commande flutter
scripts\mobile-verifier.cmd        :: format + analyse + tests
```

> **Le chemin du SDK passe par le nom court 8.3.** L'outillage de Flutter
> compile ses greffons natifs en appelant `dart` sans guillemets : le profil
> utilisateur de cette machine contient un espace, et la compilation échoue si
> `FLUTTER_ROOT` l'expose. Les deux scripts s'en chargent ; en cas d'appel
> direct, prévoir `for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI`.

Le SDK Android **n'est pas installé** : il n'est nécessaire que pour lancer
l'application sur un appareil ou produire un APK. `flutter analyze` et
`flutter test` tournent sans lui, et c'est ce que vérifie
`scripts\mobile-verifier.cmd`.

## Lancer

```cmd
scripts\flutter.cmd run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` est l'adresse de la machine hôte vue depuis l'émulateur Android ;
`localhost` y désignerait l'émulateur lui-même. Rien de sensible n'est écrit
dans le code : tout passe par `--dart-define`.

## Structure

```
lib/
  core/
    app_mode.dart          les deux casquettes
    config/                configuration d'exécution
    auth/                  couple de jetons, coffre sécurisé
    network/               client Dio, intercepteur de refresh, erreurs
    router/                go_router et sa règle de redirection unique
    providers.dart         câblage des dépendances
  design_system/
    design_tokens.dart     miroir de resources/scss/_tokens.scss
    app_theme.dart         thèmes clair/sombre × mode client/technicien
    components/            composants réutilisables
  features/
    onboarding/            trois écrans d'introduction
    auth/                  inscription, connexion, mot de passe oublié, dossier
    shared/                démarrage, accueil, sélecteur de mode
    client/
      data/                modèles JSON et ClientRepository
      application/         fournisseurs Riverpod et brouillon de demande
      presentation/        catalogue, demande, suivi, paiement, avis, profil
    chat/                  messagerie — annoncée, livrée en D4
```

## Ce qu'il faut savoir avant de toucher au code

**Les jetons de design sont en double.** `design_tokens.dart` et
`backend-laravel/resources/scss/_tokens.scss` décrivent le même système : toute
modification doit être portée des deux côtés. Un test le vérifie sur les trois
couleurs de marque.

**Le refresh token tourne à chaque usage.** Deux rafraîchissements concurrents
seraient vus par le serveur comme un rejeu et révoqueraient toutes les sessions
du compte. `AuthInterceptor` les sérialise — un seul en vol, les autres
attendent son résultat. C'est la raison d'être de sa complexité, et c'est
couvert par onze tests.

**`validateStatus` s'arrête à 400, volontairement.** L'élargir aux 4xx pour lire
le corps plus commodément ferait passer les 401 à côté de l'intercepteur, et
tout le mécanisme de rafraîchissement deviendrait silencieusement inopérant.

**Le prix se lit avant de s'engager.** Le bouton « Publier ma demande » reste
inactif tant que le devis n'est pas affiché, et le mot employé suit le drapeau
`ferme` du serveur : « à partir de » avant qu'un technicien n'ait accepté,
« total » après. Le déplacement dépend de la position du technicien, qui
n'existe pas encore au moment de la publication (ADR-0026) ; annoncer un total
à ce stade produirait la première facture surprise, donc la première
réclamation.

**Aucun montant ne part vers le serveur.** Le prix est calculé côté Laravel, à
partir de la grille de la zone et de la position réelle du technicien. Un test
énumère tous les appels mutants du parcours client et refuse qu'une clé de
requête contienne « montant », « prix », « gnf » ou « km ». Si un jour un écran
doit envoyer un chiffre, c'est ce test qu'il faudra convaincre — et l'exception
devra être nommée, pas glissée.

**L'application ne décide pas qu'un paiement a réussi.** Elle lance
l'encaissement puis interroge `/paiements/{reference}` jusqu'à ce que le serveur
tranche : le webhook est la seule autorité (ADR-0032). Une interrogation qui
échoue est ignorée — le réseau a hoqueté, la suivante réessaiera.

**Les messages d'erreur viennent du serveur.** L'API les rédige en français pour
être affichés tels quels. `ApiException` ne fabrique un texte que lorsque le
serveur n'a rien pu dire.

## Reste à fournir

| Élément | Nécessaire pour |
|---|---|
| SDK Android | build de l'APK, lancement sur appareil |
| Compte Apple Developer + machine macOS ou CI | build iOS — **impossible depuis Windows** |
| Polices Poppins et Inter dans `assets/fonts/` | typographie de la maquette |
| Clés Google Maps Android et iOS | carte, suivi, et remplacer la liste de quartiers par un sélecteur sur carte |
| Projet Firebase | notifications push |
