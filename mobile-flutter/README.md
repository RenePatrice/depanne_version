# Application mobile — bloc D

Ce dossier accueillera l'application Flutter **Client / Technicien** (un binaire,
deux modes). Il est volontairement vide tant que les blocs B (back-office) et C
(API mobile) ne sont pas livrés : l'application consomme l'API, elle ne peut pas
être écrite avant.

## Prérequis, à installer au moment du bloc D

| Outil | Nécessaire pour |
|---|---|
| Flutter SDK 3.x (Dart 3) | tout le bloc D |
| Android Studio + SDK Android | build de l'APK signé |
| Compte Apple Developer + machine macOS (ou CI type Codemagic) | build de l'IPA — **impossible depuis Windows** |
| Clés Google Maps SDK Android et iOS | carte, adresse, suivi |
| Projet Firebase (`google-services.json`, `GoogleService-Info.plist`) | notifications push |

Aucun de ces outils n'est installé sur la machine actuelle.

## Structure prévue

```
lib/
  core/            client Dio + interceptor de refresh, routeur, erreurs, stockage sécurisé
  design_system/   design_tokens.dart (miroir de resources/scss/_tokens.scss),
                   thèmes clair/sombre, composants réutilisables
  features/
    auth/          inscription 3 étapes, connexion téléphone + mot de passe
    shared/        sélecteur de mode Client/Technicien, chat, notifications
    client/        catalogue, adresse, prix, publication, suivi, paiement, évaluation
    technician/    disponibilité, réception de demande, intervention, portefeuille
```

## Rappels du cahier des charges

- Le fichier `design_tokens.dart` et `backend-laravel/resources/scss/_tokens.scss`
  décrivent le **même** système : toute modification doit être portée des deux côtés.
- Le mode Client est bleu (`#1B6FF3`), le mode Technicien orange (`#FF7A1A`) : la
  bascule doit être visible immédiatement, couleur d'accentuation et navigation.
- Réseau lent et instable : file de requêtes hors-ligne, cache des tickets
  acceptés, images optimisées, messages d'erreur en français clair.
- Positions GPS toutes les 8 secondes **uniquement pendant une intervention active**.
