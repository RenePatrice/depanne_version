# Décisions d'architecture

Une décision par entrée : le contexte, la décision, ses conséquences. Les
décisions sont datées et ne sont pas réécrites — une décision remplacée est
marquée « remplacée par ADR-XXXX ».

---

## ADR-0001 — Backend Laravel unique
**5 septembre 2026 · acceptée**

Le back-office web et l'API mobile ont besoin de la même logique : calcul de
prix, transitions de ticket, répartition financière. Deux applications
signifieraient deux implémentations à maintenir en cohérence.

**Décision.** Une seule application Laravel, deux jeux de routes, une seule
logique métier dans `app/Domain/`. Les contrôleurs sont des adaptateurs.

**Conséquence.** Le bloc B construit le domaine, le bloc C le consomme sans
réécriture. Corollaire strict : aucune règle métier dans un contrôleur.

---

## ADR-0002 — Montants en entiers de GNF
**5 septembre 2026 · acceptée**

Le franc guinéen n'a pas de subdivision utilisée. Un flottant introduirait des
erreurs d'arrondi sur la commission et le net technicien.

**Décision.** `bigInteger` en base, `int` en PHP, suffixe `_gnf` sur les
colonnes. Le formatage (`100 000 GNF`) est centralisé.

---

## ADR-0003 — Authentification par téléphone et argon2id
**5 septembre 2026 · acceptée**

Le cahier initial prévoyait une connexion par OTP SMS ; la décision retenue est
téléphone + mot de passe.

**Décision.** Le téléphone normalisé E.164 est l'identifiant unique. Mots de
passe hachés en argon2id. Access token Sanctum de 15 minutes, refresh token
rotatif hashé de 30 jours en table dédiée. L'OTP à 6 chiffres ne sert qu'à la
réinitialisation du mot de passe.

**Conséquence.** Le guard doit être surchargé pour authentifier sur `phone`.
L'extension `sodium` devient une dépendance dure.

---

## ADR-0004 — Le solde se calcule, il ne s'incrémente pas
**5 septembre 2026 · acceptée**

Un champ `solde` mis à jour à l'aveugle diverge silencieusement au premier
incident : job rejoué, transaction interrompue, correction manuelle.

**Décision.** Toute variation d'argent est un mouvement dans `transactions`. Le
solde est la somme des mouvements ; la colonne matérialisée est **recalculée**
dans la même transaction SQL que la répartition, jamais incrémentée.

---

## ADR-0005 — Chaque fournisseur externe derrière une interface
**5 septembre 2026 · acceptée**

Aucune clé tierce n'est disponible au démarrage du projet, et le pilote doit
pouvoir être développé et testé sans compte marchand.

**Décision.** `MapProvider`, `PaymentProvider`, `SmsProvider`, `PushProvider`,
`MaskedCallProvider`. Chaque interface a une implémentation simulée, choisie par
variable d'environnement.

| Interface | Implémentation simulée | Implémentation réelle |
|---|---|---|
| `MapProvider` | Haversine × 1,3 | Google Distance Matrix / Directions |
| `PaymentProvider` | `MockPaymentProvider` | Orange Money, puis MTN MoMo, Wave… |
| `PushProvider` | `laravel.log` + table `notifications` + Reverb | Firebase Cloud Messaging |
| `SmsProvider` | code écrit dans `laravel.log` | passerelle SMS |

**Conséquence.** Le repli Haversine n'est pas un artifice de développement :
c'est le comportement de secours prévu au §8.2 quand Google est indisponible.

---

## ADR-0006 — Deux connexions PostgreSQL
**5 septembre 2026 · acceptée**

Sur Supabase, l'application passe par le pooler en mode transaction (port 6543),
qui ne conserve pas les instructions préparées nommées d'une requête à l'autre
et ne sait pas exécuter certains DDL.

**Décision.** `pgsql` pour l'application, avec `DB_DISABLE_NATIVE_PREPARES` qui
bascule PDO en mode émulé ; `pgsql_migrations` pour les migrations, en connexion
directe. En local, les deux visent le même cluster.

---

## ADR-0007 — Blade comme socle, React en îlots
**5 septembre 2026 · acceptée**

Seules quelques zones du back-office ont besoin de temps réel : carte live,
widgets KPI, file de validation, visionneuse de chat de litige.

**Décision.** Blade rend les pages. React est monté par `createRoot` sur
`<div data-react-component="…">`, à partir du registre
`resources/js/react/registry.js`. Pas de SPA, pas d'Inertia.

---

## ADR-0008 — Verrou atomique sur l'attribution d'un ticket
**5 septembre 2026 · acceptée**

Le matching est séquentiel, mais deux réponses peuvent se croiser : un
technicien accepte au moment précis où le job de délai dépassé passe au suivant.

**Décision.** `Cache::lock("ticket:{id}:attribution")` encadre toute
attribution. Les magasins `database` et `redis` fournissent l'un comme l'autre
des verrous atomiques : le comportement est identique en local et en production.

---

## ADR-0009 — Fuseau Africa/Conakry, français par défaut
**5 septembre 2026 · acceptée**

**Décision.** `APP_TIMEZONE=Africa/Conakry` (UTC+0 toute l'année, pas d'heure
d'été), horodatages stockés en UTC, `lang/fr` comme seule locale du pilote,
structure i18n prête pour d'autres langues.

---

## ADR-0010 — Pas de Docker en développement
**5 septembre 2026 · acceptée · décision client**

**Décision.** Installation portable dans `%USERPROFILE%\devtools`, processus
lancés par `scripts\dev.cmd`. L'infrastructure conteneurisée sera reprise au
moment du déploiement.

**Conséquence.** L'environnement local diverge de la cible de production : le
guide de déploiement devra décrire explicitement sa reconstitution — Nginx +
PHP-FPM, Redis, Horizon, supervision des workers.

---

## ADR-0011 — Files d'attente et cache sur PostgreSQL en local
**5 septembre 2026 · acceptée**

Redis ne s'installe pas simplement sous Windows sans Docker ni WSL, et Horizon
exige `ext-pcntl`, absente des builds Windows.

**Décision.** En local, `QUEUE_CONNECTION`, `CACHE_STORE` et `SESSION_DRIVER`
valent `database`. Horizon n'est pas installé ; il le sera au déploiement.

**Conséquence.** Matching séquentiel, libération du séquestre et verrous
fonctionnent à l'identique. Ce qui manque en local : la supervision Horizon et
les performances de Redis en charge.

---

## ADR-0012 — React 19 plutôt que React 18
**5 septembre 2026 · acceptée**

Le cahier mentionne React 18. React 19.2 est la version stable courante et
l'API de montage des îlots (`createRoot`) est identique.

**Décision.** React 19.2 avec `@vitejs/plugin-react` 5.x, compatible Vite 7.

---

## ADR-0013 — Instantanés du prix et de l'adresse sur le ticket
**5 septembre 2026 · acceptée**

Le catalogue et les grilles tarifaires sont modifiables en back-office, et un
client peut supprimer une adresse enregistrée. Si le ticket ne faisait que
référencer ces objets, l'historique changerait sous les pieds du support.

**Décision.** À la publication, le ticket recopie le prix de la prestation, les
trois composantes du calcul et l'adresse complète (`address_snapshot`). Les clés
étrangères sont conservées pour la navigation, mais ce sont les copies qui font foi.

**Conséquence.** Le prix annoncé au client avant publication est celui qui sera
facturé, quoi qu'il arrive ensuite à la grille tarifaire.

---

## ADR-0014 — Énumération `TicketState` avant spatie/model-states
**5 septembre 2026 · acceptée**

La phase A1 ne doit contenir aucune logique métier, or les modèles et les
seeders ont besoin d'une représentation de l'état du ticket.

**Décision.** La colonne `tickets.state` est castée vers l'énumération
`App\Domain\Tickets\Data\TicketState`, dont les valeurs sont déjà les noms
d'états définitifs. En phase C2, `spatie/laravel-model-states` reprend la même
colonne avec une classe par état et des transitions déclarées.

**Conséquence.** Aucune migration ne sera nécessaire lors du remplacement. En
revanche, tant que C2 n'est pas livrée, **rien n'empêche techniquement une
transition illégale** : les seeders et le back-office doivent passer par les
actions du domaine dès qu'elles existent.

---

## ADR-0015 — Les modules non livrés répondent sous leur URL définitive
**5 septembre 2026 · acceptée**

La barre latérale du back-office annonce onze modules dont un seul est livré en
phase B1. Trois options : masquer les liens, les désactiver, ou les faire
répondre.

**Décision.** Chaque module déclaré dans `config/backoffice.php` a dès B1 sa
route, son URL et sa permission définitives. Tant que sa phase n'est pas livrée,
il rend un écran qui annonce son contenu et sa phase. Le passage au module réel
consiste à brancher un contrôleur — l'adresse ne change pas.

**Conséquence.** Personne ne tombe sur un 404 ni sur une page blanche, les
permissions sont éprouvées dès B1 (un rôle FINANCE reçoit bien un 403 sur
`/configuration`), et les liens partagés dans l'équipe restent valables après
chaque livraison de phase.

---

## ADR-0016 — Les exports sont calculés côté serveur
**5 septembre 2026 · acceptée**

Toutes les tables du back-office tournent en mode serveur : le navigateur ne
détient jamais plus que les vingt-cinq lignes affichées. Les boutons d'export de
DataTables travaillent, eux, sur les données présentes côté client.

**Décision.** Les exports CSV, Excel et PDF passent par une route serveur qui
rejoue **la requête filtrée de l'écran**. Les filtres affichés sont recopiés sur
les liens d'export par le JavaScript, si bien que le fichier téléchargé
correspond exactement à ce que l'utilisateur a sous les yeux.

**Conséquence.** Un export de tickets clôturés contient les 85 lignes réelles,
pas les 25 de la page courante. Les montants y restent des entiers, pour qu'un
tableur puisse les additionner. Le PDF est plafonné à 2 000 lignes et le dit
explicitement en pied de document, au lieu de tronquer en silence.

---

## ADR-0017 — OpenStreetMap pour l'éditeur de zones du back-office
**5 septembre 2026 · acceptée**

Le §6 demande des polygones dessinables sur carte. La bibliothèque Google Maps
exige une clé facturée que le projet n'a pas encore, et le tracé d'une emprise
n'a besoin d'aucune des fonctions pour lesquelles Google est indispensable :
ni routage, ni matrice de distance, ni recherche d'adresse.

**Décision.** L'éditeur de zones utilise Leaflet avec un fond OpenStreetMap.
Google Maps reste le fournisseur du calcul de distance routière (§8.2) et de la
cartographie de l'application mobile, derrière l'interface `MapProvider`.

**Conséquence.** L'écran des zones fonctionne dès aujourd'hui, sans clé et sans
facturation. Les deux usages ne se recouvrent pas : le back-office trace, le
domaine calcule. La feuille de style et le code de Leaflet ne sont téléchargés
que par la page qui affiche une carte.

---

## ADR-0018 — Le solde ne bouge qu'au versement effectif d'un retrait
**5 septembre 2026 · acceptée**

Le workflow des retraits compte trois états avant le versement : demandé,
approuvé, payé. Écrire le mouvement de portefeuille à l'approbation aurait été
plus simple, mais un retrait approuvé peut encore être rejeté, ou rester des
jours sans être versé chez l'opérateur.

**Décision.** Le mouvement de sortie est écrit **au moment où le versement est
constaté**, avec la référence de la transaction opérateur. Un retrait approuvé
est seulement *engagé* : il est déduit du montant dû affiché aux finances, sans
amputer le portefeuille du technicien.

**Conséquence.** Le solde revérifié au moment du paiement, dans la même
transaction SQL que l'écriture — entre l'approbation et le versement, un
remboursement de litige a pu passer et vider le compte. Sans cette
revérification, le solde deviendrait négatif en silence.

---

## ADR-0019 — Une décision de litige écrit deux mouvements symétriques
**5 septembre 2026 · acceptée**

Rembourser un client sur un litige, c'est déplacer de l'argent d'une poche à une
autre. N'écrire que le crédit du client déséquilibrerait le grand livre et
laisserait au technicien une part qu'il ne détient plus.

**Décision.** Un remboursement écrit un mouvement `REFUND` positif pour le
client et un mouvement `REFUND` négatif du même montant pour le technicien. La
somme des mouvements de la plateforme reste nulle sur l'opération.

**Conséquence.** Le versement effectif vers le Mobile Money du client relève du
fournisseur de paiement (phase C5) : décider n'est pas rembourser, et l'écran le
dit. Les deux étapes restent distinctes et traçables séparément.

---

## ADR-0020 — Le temps réel dégrade au lieu de tomber
**5 septembre 2026 · acceptée**

La carte live et le flux d'activité reposent sur un WebSocket. À Conakry, le
réseau coupe ; en développement, Reverb n'est pas toujours lancé. Un écran de
supervision qui reste vide ou figé sans le dire est pire qu'un écran lent.

**Décision.** Chaque écran temps réel reçoit d'abord un **instantané complet**
par HTTP, puis suit les messages. Quand la connexion WebSocket n'est pas établie,
il bascule sur une interrogation périodique et affiche un bandeau « Mode
dégradé » indiquant la cadence de rafraîchissement.

**Conséquence.** Deux points d'entrée JSON existent en parallèle du canal : ils
servent au premier chargement, au repli, et aux tests. Les événements diffusés
transportent tout ce qu'il faut afficher, de sorte qu'un abonné n'ait pas à
requêter la base à chaque message.

---

## ADR-0021 — La simulation de positions émet l'événement définitif
**5 septembre 2026 · acceptée**

La carte live a besoin de positions de techniciens, que seule l'application
mobile produira (phase C3). Attendre le bloc D pour éprouver le temps réel
aurait laissé toute la chaîne — canal, autorisation, sérialisation, rendu — non
vérifiée pendant des mois.

**Décision.** La commande `demo:positions` émet `TechnicianPositionUpdated`,
**le même événement** que l'API mobile émettra, avec la même charge utile. Elle
met aussi à jour `last_known_location` en base, comme le fera l'API.

**Conséquence.** Le back-office est complet et testable dès aujourd'hui ; le
jour où le mobile prend le relais, il remplace le producteur sans qu'une ligne
du consommateur ne change. C'est la même logique que le pilote `log` des
notifications push (ADR-0005).

---

## ADR-0022 — Rotation des jetons et révocation en cascade au rejeu
**5 septembre 2026 · acceptée**

Un téléphone se perd, se prête, se revend. Un jeton de session longue durée qui
resterait valable trente jours sur un appareil compromis donnerait à l'intrus
autant de temps que le porteur légitime.

**Décision.** Le refresh token **tourne à chaque usage** : l'échange en délivre
un neuf et révoque immédiatement l'ancien. Si un jeton déjà échangé se
représente, c'est qu'une copie circule : toute la lignée est révoquée, y compris
la session légitime.

**Conséquence.** Le porteur légitime devra parfois se reconnecter sans raison
apparente — un rafraîchissement interrompu par une coupure réseau peut produire
ce cas. C'est le prix accepté : une reconnexion vaut mieux qu'une session volée
qui perdure. Le client Flutter devra sérialiser ses rafraîchissements pour ne
pas se déconnecter lui-même en lançant deux requêtes concurrentes.

---

## ADR-0023 — L'API ne révèle jamais si un numéro est inscrit
**5 septembre 2026 · acceptée**

L'identifiant de connexion est un numéro de téléphone. Une API qui répondrait
« ce compte n'existe pas » deviendrait un outil pour savoir qui utilise
Dépanne-Moi — information commercialement sensible sur un marché où les
concurrents se comptent sur une main.

**Décision.** Une connexion échouée renvoie le même message dans les deux cas, et
le hachage du mot de passe est calculé même quand le compte n'existe pas, pour
que le temps de réponse ne trahisse rien. La demande de réinitialisation répond
« si un compte existe avec ce numéro… », sans distinction.

**Conséquence.** Un utilisateur qui se trompe de numéro ne l'apprendra pas de
l'API ; l'écran mobile devra donc afficher le numéro saisi, en toutes lettres, à
côté du message d'erreur.
