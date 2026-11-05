# Application mobile — bloc D

Application Flutter **Client / Technicien** : un binaire, deux modes. Elle
consomme l'API `/api/v1` du backend Laravel, sans jamais toucher à la base.

## État

| Phase | Contenu | État |
|---|---|---|
| **D1** | Design system, thèmes, client Dio + refresh, auth, navigation, sélecteur de mode | ✅ terminée |
| **D2** | Parcours client : catalogue → adresse → prix → publication → suivi → supplément → paiement → avis → historique | ✅ terminée |
| **D3** | Parcours technicien : disponibilité, réception, intervention, supplément, portefeuille, position GPS | ✅ terminée |
| **D4** | Chat, notifications, temps réel Reverb, lecture hors-ligne, réclamations | ✅ terminée — push FCM en attente de Firebase |
| D5 | Build, signature, publication | à faire |

## Outillage

Flutter **3.47.2** (Dart 3.13.2) et le **SDK Android** (plateforme 36,
build-tools 36.1.0, NDK 28.2.13676358), installés en portable dans
`%USERPROFILE%\devtools`. Aucun droit administrateur, aucune variable
d'environnement système, pas d'Android Studio.

```cmd
scripts\flutter.cmd --version      :: n'importe quelle commande flutter
scripts\flutter.cmd build apk --debug
scripts\mobile-verifier.cmd        :: format + analyse + tests
```

> **Les chemins des SDK passent par le nom court 8.3.** L'outillage de Flutter
> et celui d'Android compilent leurs greffons natifs en appelant `dart` et
> `aapt2` sans guillemets : le profil utilisateur de cette machine contient un
> espace, et la compilation échoue si `FLUTTER_ROOT` ou `ANDROID_HOME`
> l'exposent. Les deux scripts s'en chargent ; en cas d'appel direct, prévoir
> `for %%I in ("%USERPROFILE%\devtools\flutter") do set FLUTTER_ROOT=%%~sI`.


### Réinstaller le SDK Android

Les *command-line tools* seules suffisent. Trois pièges, dans l'ordre où on les
rencontre :

1. **`sdkmanager` est déprécié et plante en sortie** sur cette machine
   (NTSTATUS 0xC0000409). L'installation aboutit, le processus meurt après —
   et Gradle, lui, prend le plantage pour un échec. Tout passe donc par le
   binaire `android` :

   ```cmd
   android sdk install platforms/android-36 build-tools/36.1.0 ndk/28.2.13676358
   ```

   Laisser AGP installer ce qui manque échoue sur une erreur qui ne nomme pas
   la pièce absente, et installe au passage une plateforme corrompue.
2. **Le NDK est obligatoire** alors qu'aucune ligne de C n'est écrite : le
   plugin Gradle de Flutter impose sa version. 2,5 Go, une fois.
3. **Télécharger avec `curl.exe`**, jamais `Invoke-WebRequest` : en PowerShell
   5.1, celui-ci bufferise toute la réponse en mémoire et met vingt minutes là
   où curl en met quarante secondes.

`flutter analyze` et `flutter test` tournent sans le SDK Android — c'est ce que
vérifie `scripts\mobile-verifier.cmd`. Il n'est nécessaire que pour produire un
APK ou lancer sur un appareil.

## Lancer

```cmd
scripts\flutter.cmd run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Avec le temps réel (D4), ajouter l'adresse de Reverb et sa clé publique —
`REVERB_APP_KEY` du `.env` du backend. Sans elles, l'application interroge le
serveur comme avant :

```cmd
scripts\flutter.cmd run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 --dart-define=REVERB_URL=ws://10.0.2.2:8080 --dart-define=REVERB_APP_KEY=...
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
    temps_reel/            client Reverb (protocole Pusher) et repli
    hors_ligne/            cache des lectures et bandeau « hors connexion »
    providers.dart         câblage des dépendances
  design_system/
    design_tokens.dart     miroir de resources/scss/_tokens.scss
    app_theme.dart         thèmes clair/sombre × mode client/technicien
    components/            composants réutilisables
  features/
    onboarding/            trois écrans d'introduction
    auth/                  inscription, connexion, mot de passe oublié, dossier
    shared/
      data/                modèles partagés par les deux parcours
      ...                  démarrage, accueil, sélecteur de mode
    client/
      data/                ClientRepository
      application/         fournisseurs Riverpod et brouillon de demande
      presentation/        catalogue, demande, suivi, paiement, avis, réclamation, profil
    technicien/
      data/                TechnicienRepository, sollicitation, portefeuille
      application/         fournisseurs, disponibilité, service de position
      presentation/        demandes, intervention, diagnostic, portefeuille
    chat/                  messagerie de l'intervention
    notifications/         centre de notifications et cloche
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

**Le technicien nomme un geste, jamais un état.** « Je pars », « je suis
arrivé » : c'est la machine à états du serveur qui décide si le geste est
possible. Une liste d'états recopiée dans l'application finirait par diverger de
la sienne, et l'écran proposerait des boutons qui échouent.

**Une seule route mobile envoie un montant : le supplément après diagnostic.**
Et elle propose — le total ne bouge qu'après accord du client. Les trois gardes
(plafond au double de la prestation, diagnostic écrit, décision du client) sont
annoncées par l'écran et tenues par le serveur. Le retrait fait exception à la
règle sans la contredire : c'est l'argent du technicien, pas un prix
d'intervention.

**Le GPS ne tourne que pendant une intervention.** Le flux démarre quand une
intervention active apparaît et s'arrête avec elle — huit secondes de cadence
pendant, deux minutes en simple disponibilité. Lier le suivi à un interrupteur
laisserait quelqu'un pisté toute la nuit pour avoir oublié de le couper. Un
échec d'envoi est avalé : une position perdue est un désagrément, une exception
au milieu d'une intervention est une panne.

**Les messages d'erreur viennent du serveur.** L'API les rédige en français pour
être affichés tels quels. `ApiException` ne fabrique un texte que lorsque le
serveur n'a rien pu dire.

## Reste à fournir

| Élément | Nécessaire pour |
|---|---|
| Compte Apple Developer + machine macOS ou CI | build iOS — **impossible depuis Windows** |
| Polices Poppins et Inter dans `assets/fonts/` | typographie de la maquette |
| Clés Google Maps Android et iOS | carte embarquée, et remplacer la liste de quartiers par un sélecteur sur carte. **Aucune clé n'a été créée** : cela demande un compte Google Cloud et une facturation active. Le câblage attend : `MAPS_API_KEY` dans `android/local.properties` (non versionné) ou en variable d'environnement |
| Projet Firebase | notifications push |
