# CLAUDE.md — Dépanne-Moi

Mémoire de travail du projet : conventions, commandes, décisions. À tenir à jour
à chaque fin de phase.

---

## 1. État d'avancement

| Bloc | Phase | État |
|---|---|---|
| A — Fondations | **A0** — environnement, arborescence, installation | ✅ terminée |
| | **A1** — migrations, modèles, seeders de démo | ✅ terminée |
| B — Back-office | **B1** — auth admin, layout, thèmes | ✅ terminée |
| | **B2** — tableau de bord, KPI, Highcharts | ✅ terminée |
| | **B3** — tickets, clients, techniciens (DataTables) | ✅ terminée |
| | **B4** — catalogue, zones, configuration, audit | ✅ terminée |
| | **B5** — finances, retraits, litiges | ✅ terminée |
| | **B6** — carte live temps réel | ✅ terminée |
| C — API mobile | **C1** — auth mobile, Sanctum, refresh tokens | ✅ terminée |
| | **C2** — catalogue, adresses, tickets, PricingService | ✅ terminée |
| | **C3** — matching, temps réel mobile, notifications | ⏳ suivante |
| | C4 → C6 | à faire |
| D — Mobile Flutter | D1 → D5 | à faire |

## 2. Décisions du client (5 septembre 2026)

- **Pas de Docker, pas de WSL2.** Tout tourne en natif sur la machine Windows.
  L'infrastructure conteneurisée sera reprise au moment du déploiement.
- **PostgreSQL local d'abord**, bascule sur Supabase ensuite (les clés seront
  fournies plus tard). Les migrations sont écrites pour fonctionner sur les deux.
- **Orange Money** est le fournisseur de paiement du pilote. Les autres (MTN MoMo,
  Wave…) viendront plus tard **derrière la même interface `PaymentProvider`**.
- **Google Maps** pour la cartographie et le calcul de distance routière.
- **Firebase indisponible en local** : les notifications push passent par un
  pilote `log` (écriture dans `laravel.log` + table `notifications` + diffusion
  Reverb). Le pilote `fcm` sera activé par simple variable d'environnement.

## 3. Environnement local

Outils installés en **mode portable** dans `%USERPROFILE%\devtools` (aucun droit
administrateur nécessaire, aucun service Windows créé) :

| Outil | Version | Emplacement |
|---|---|---|
| PHP (NTS x64) | 8.4.25 | `devtools\php84` |
| Composer | 2.10.3 | `devtools\composer` |
| PostgreSQL | 17.11 | `devtools\pgsql` |
| PostGIS | 3.6.2 | intégré à `devtools\pgsql` |
| Node / npm | 22.22.2 / 10.9.7 | installation système |

Le cluster PostgreSQL écoute sur le **port 5433** (le 5432 est occupé par une
autre instance sur cette machine). Données dans `devtools\pgdata`.

Bases : `depanne_moi` (développement) et `depanne_moi_test` (Pest), toutes deux
avec l'extension PostGIS. Rôle applicatif `depanne`.

Détails et réinstallation : [docs/environnement-local.md](docs/environnement-local.md).

## 4. Commandes

```cmd
scripts\dev.cmd            :: tout démarrer (PG + web + queue + scheduler + Reverb + Vite)
scripts\pg-start.cmd       :: démarrer PostgreSQL seul
scripts\pg-stop.cmd        :: arrêter PostgreSQL
scripts\pg-psql.cmd        :: session psql sur depanne_moi
```

Depuis `backend-laravel/` :

```cmd
composer setup             :: installation initiale complète
composer dev               :: les cinq processus de développement
composer test              :: suite Pest
composer analyse           :: PHPStan / Larastan (niveau 6)
composer format            :: Laravel Pint

php artisan migrate --database=pgsql_migrations
php artisan db:seed
php artisan reverb:start
npm run build
```

> Les migrations passent **toujours** par la connexion `pgsql_migrations`
> (cf. ADR-0006) : sur Supabase, le pooler en mode transaction ne sait pas
> exécuter certains DDL ni `CREATE EXTENSION`.

## 5. Organisation du domaine

`app/Domain/` compte **14 contextes**. Chacun expose des actions invocables ;
les contrôleurs web et API ne font que valider, appeler, présenter.

| Contexte | Contient aujourd'hui |
|---|---|
| `Accounts` | `User`, `AdminUser`, `ClientProfile`, `TechnicianProfile`, `Address`, `RefreshToken`, `PasswordResetCode` |
| `Catalog` | `ServiceCategory`, `Service`, énumération `Specialty` |
| `Zones` | `Zone`, `ZoneService` (rattachement et point de référence) |
| `Tickets` | `Ticket`, `TicketEvent`, énumérations `TicketState` et `ActorType`, les 16 classes d'état |
| `Pricing` | `PricingService`, `MapProvider` et ses deux pilotes, `Devis`, `Distance` |
| `Matching` | `MatchAttempt`, énumération `MatchResponse` |
| `Payments` | `Payment`, énumérations `PaymentStatus` et `PaymentMethod` |
| `Wallet` | `Transaction`, `Withdrawal`, énumérations associées |
| `Chat` | `Message` |
| `Reviews` | `Review` |
| `Disputes` | `Dispute` et ses trois énumérations |
| `Notifications` | *(vide — phase C3)* |
| `Settings` | `AppSetting` et ses clés de configuration |
| `Reporting` | `Periode`, `DashboardService` — toutes les agrégations du tableau de bord |

Les fournisseurs externes sont configurés dans **`config/depanne.php`** : c'est
le seul fichier autorisé à appeler `env()`, ailleurs la valeur disparaîtrait dès
que la configuration est mise en cache.

### État du ticket
La colonne `tickets.state` est castée vers `Tickets\States\TicketStatus`, la
classe de base de `spatie/laravel-model-states`. Les 16 états sont des classes
dont le `$name` est la valeur déjà stockée en base : le passage aux classes
d'état n'a demandé **aucune migration**, comme annoncé en A1.

La table des transitions n'est pas recopiée : `TicketStatus::config()` lit
`TicketState::transitionsPossibles()` (ADR-0025). L'énumération reste la source
unique et garde libellés, couleurs et listes d'états ; les classes délèguent.

`TicketStatusCaster` accepte indifféremment l'énumération, la classe d'état ou
la valeur brute. Sans lui, `$ticket->state = TicketState::PAYEE` échouait sur
une erreur de type opaque, à l'exécution seulement.

## 6. Back-office

### Accès
- Guard `admin` (guard par défaut de l'application), table `admin_users`,
  sessions en base.
- Comptes de démonstration : `admin@`, `support@` et `finance@depanne-moi.gn`,
  mot de passe `DepanneMoi2026` — **à changer avant toute mise en ligne**.
- Verrouillage : 5 tentatives par fenêtre de 15 minutes et par couple e-mail +
  IP, porté par l'action `AuthenticateAdmin`, jamais par le contrôleur.
- Connexions et déconnexions sont tracées dans `activity_log`.

### Navigation
`config/backoffice.php` est la source unique de la barre latérale, du fil
d'Ariane et des écrans « module en préparation ». Chaque entrée déclare la
permission qui la garde et la phase qui la livre.

Un module non livré **répond quand même**, sous son URL et son nom de route
définitifs, en annonçant ce qu'il contiendra. Quand la phase arrive, il suffit
de basculer `disponible` et de brancher le vrai contrôleur : aucun lien ne
change. `Navigation::forAdmin()` filtre les entrées sur les permissions, si bien
qu'un lien affiché est toujours un lien ouvrable.

### Apparence
- Le thème et l'état de la barre latérale sont mémorisés dans `localStorage`
  (`depanne.theme`, `depanne.sidebar`), avec repli silencieux si le stockage est
  bloqué. Le thème est posé par un script en ligne **avant le premier rendu** :
  sans cela une page sombre clignote en clair au chargement.
- Highcharts et React sont chargés à la demande par `import()` dynamique — une
  page sans graphique ne télécharge pas les 280 ko de Highcharts. Bundle
  d'entrée : 210 ko, 66 ko compressés.
- Les écrans de connexion et de diagnostic ont leur propre gabarit, hors coquille.

### Tableau de bord
- Toutes les agrégations vivent dans `Reporting\Services\DashboardService`, une
  requête groupée par bloc. Le contrôleur choisit la période et présente.
- `Reporting\Data\Periode` porte la fenêtre d'analyse et sa comparable
  précédente. Le décalage se calcule **en jours entiers** : raisonner en
  secondes déborde d'un jour à cause de la fraction de seconde de `endOfDay`.
- Les graphiques sont déclaratifs : Blade pose
  `<div data-graphique="ligne|camembert|colonnes|heatmap|jauge" data-serie="{…}">`
  et `resources/js/theme/graphiques.js` fait le reste. Aucune donnée n'est
  calculée en JavaScript.
- Les cartes KPI portent leur valeur finale dans le HTML ; l'animation ne fait
  que la faire monter depuis zéro, si bien qu'un lecteur d'écran, une impression
  ou un navigateur sans JavaScript lisent le bon chiffre.
- Le flux d'activité démarre avec les événements rendus par Blade puis
  s'actualise toutes les 20 s sur `/flux-activite`. En B6, Reverb poussera les
  mêmes événements et cette interrogation deviendra le repli.

### Tables et exports
- Une classe par table dans `app/Http/DataTables/`. Elle expose `requete()`,
  `json()` et `ligneExport()` : **la même requête filtrée sert l'écran et
  l'export**.
- Les exports sont calculés **côté serveur** (ADR-0016). Les boutons d'export de
  DataTables n'exporteraient que la page affichée — vingt-cinq lignes sur des
  milliers — donc un fichier silencieusement faux.
- Toute colonne composée ou calculée (badge, montant formaté, sous-requête)
  reçoit un `filterColumn` neutre. Sans lui, une requête qui la déclarerait
  cherchable ferait générer un `LOWER(users.interventions)` inexistant et
  l'écran renverrait 500.
- Le composant `<x-table-serveur>` porte la coquille, la barre d'export et les
  filtres ; `resources/js/theme/tables.js` l'anime et recopie les filtres sur
  les liens d'export.

### Actions support
- `TransitionTicket` est le **seul** chemin autorisé pour changer l'état d'un
  ticket : il vérifie la légalité de la transition contre
  `TicketState::transitionsPossibles()`, horodate le jalon et journalise
  l'événement, le tout dans une transaction. Écrire `$ticket->state = …`
  ailleurs contournerait la garde.
- Un jalon déjà horodaté n'est jamais réécrit : une reprise après incident ne
  doit pas effacer la date d'origine.
- `SetUserStatus` refuse de suspendre un compte ayant une intervention en cours,
  et met immédiatement un technicien suspendu hors ligne.
- `ReviewTechnicianApplication` refuse de trancher deux fois le même dossier et
  exige un motif au rejet.

### Administration
- Le catalogue, les zones et la configuration sont modifiables par qui détient
  la permission `.modifier` ; tout le monde d'autre les consulte.
- Un prix modifié ne s'applique qu'aux demandes à venir (ADR-0013). Une
  prestation ou une zone ne se supprime jamais : elle se désactive, sans quoi
  l'historique des tickets deviendrait illisible.
- `UpdateSettings` valide chaque paramètre selon son type **et** ses bornes,
  puis vérifie les cohérences croisées — un rayon initial supérieur au rayon
  maximum bloquerait le matching en silence. Les pondérations du score doivent
  totaliser 1,00 et la pénalité d'annulation rester négative : inverser ce signe
  récompenserait les techniciens qui annulent le plus.
- `Service`, `ServiceCategory` et `Zone` portent `LogsActivity` : chaque
  modification laisse un avant/après dans `activity_log`. Depuis spatie v5, ce
  delta est dans la colonne **`attribute_changes`**, plus dans `properties`.
- Le journal d'audit est en lecture seule : aucune route d'écriture n'existe.

### Opérations financières
- **Le solde ne bouge qu'au versement effectif.** Approuver un retrait, c'est
  décider ; le payer, c'est sortir l'argent. Un retrait approuvé mais jamais
  versé n'ampute pas le portefeuille du technicien — il est seulement *engagé*,
  et déduit du montant dû affiché.
- Le solde est **revérifié au moment du paiement**, dans la même transaction SQL
  que l'écriture du mouvement : entre la demande et le versement, un
  remboursement de litige a pu passer.
- Un remboursement de litige écrit **deux mouvements symétriques** — le client
  est crédité, la part correspondante est reprise au technicien. Le grand livre
  reste équilibré. Le versement vers Mobile Money relève du fournisseur de
  paiement (phase C5) ; l'écran le dit explicitement.
- La file des litiges est triée par **échéance de traitement**, pas par ordre
  d'arrivée : ce qui est hors délai remonte.
- `dispute_messages` est un fil distinct de `messages` : celui-ci est entre le
  client et le technicien, celui-là est tenu par le support et peut viser l'un,
  l'autre, ou les deux. Une note interne n'est jamais renvoyée aux parties.

### Temps réel
- Canal privé **`back-office`**, ouvert aux seuls comptes actifs ayant
  `carte-live.voir` : une position GPS est une donnée personnelle, le canal se
  ferme aussi soigneusement qu'une route.
- `TicketTransitioned` est diffusé **après le commit** (`DB::afterCommit`) : un
  abonné ne doit jamais recevoir un état qu'une transaction annulée effacerait.
- `TechnicianPositionUpdated` implémente `ShouldBroadcastNow` et ne transporte
  **aucun modèle Eloquent** : il part jusqu'à toutes les 8 secondes par
  technicien, et une position mise en file arriverait après la suivante.
- Chaque écran temps réel reçoit d'abord un **instantané complet**, puis suit les
  messages. Sans WebSocket, il bascule sur une interrogation périodique **et le
  dit** — bandeau « Mode dégradé ». Le réseau de Conakry coupe ; la supervision
  ne doit pas s'arrêter.
- `php artisan demo:positions` émet **exactement le même événement** que
  l'application mobile émettra en phase C3 : la carte se démontre aujourd'hui,
  et le jour où le mobile prend le relais, rien ne change côté back-office.
- Démonstration : `composer demo` lance Reverb et la simulation ensemble.

### Cartographie du back-office
L'éditeur d'emprises utilise **Leaflet et OpenStreetMap** (ADR-0017), sans clé
d'API : le back-office n'a besoin que d'un support pour tracer. Google Maps
reste réservé au calcul de distance routière et à l'application mobile, où il
est indispensable.

### Sécurité
- `SecurityHeaders` sur toutes les routes web : `X-Frame-Options: DENY`,
  `nosniff`, `Referrer-Policy: same-origin`, `noindex`.
- `/health` reste public mais ne renvoie que des booléens à un visiteur anonyme ;
  le détail — versions, message d'erreur — n'apparaît que pour un administrateur
  connecté. `/systeme` est entièrement derrière le guard.

## 7. API mobile

Préfixe `/api/v1`, documentation OpenAPI générée par Scramble sur **`/docs/api`**
(JSON : `/docs/api.json`).

### Jetons
- Access token Sanctum de **15 minutes**, refresh token de **30 jours** stocké
  hashé et **rotatif** : chaque rafraîchissement en renvoie un neuf et révoque
  l'ancien.
- Présenter deux fois le même refresh token révoque **toutes** les sessions du
  compte : c'est le signe qu'une copie circule. Mieux vaut une reconnexion
  qu'une session volée qui perdure.
- Changer de mot de passe — par le profil ou par code SMS — coupe les autres
  sessions.

### Ce que l'API ne dit pas
- Une connexion échouée renvoie le **même message** que le numéro existe ou non,
  et le hachage est calculé dans les deux cas pour que le temps de réponse ne
  trahisse rien.
- « Mot de passe oublié » répond la même chose pour un numéro inconnu : l'API ne
  doit pas servir à savoir qui utilise Dépanne-Moi.

### Tarification
`Pricing\Services\PricingService` est le **seul** endroit du projet qui produit
un montant à payer. Back-office, API mobile et jeux de démonstration l'appellent
tous : deux implémentations du même barème finiraient par diverger, et la
divergence se lirait dans la caisse.

La distance qui compte est celle **entre le technicien et le client**, et le
seuil est le `included_km` de la zone — 3 km pour le pilote :

    sous le seuil    majoration  = prix_prestation × taux_proximité
                     déplacement = 0

    au-delà          majoration  = 0
                     déplacement = arrondi_sup( forfait_zone
                                     + (distance − seuil) × prix_par_km )

    total = prix_prestation + majoration + déplacement + supplément

- **Le technicien ne saisit jamais un montant.** Le kilométrage est calculé
  depuis sa position et la grille de la zone ; aucune route de l'API ne permet
  de l'influencer, et deux tests le vérifient — l'un tente de glisser un
  montant dans la requête, l'autre énumère les routes qui touchent un ticket.
- **La majoration de proximité revient en entier au technicien** : elle est
  retirée de l'assiette de commission avant calcul, puis rendue. Elle compense
  un déplacement qu'on ne lui facture pas.
- Le net technicien est obtenu par **soustraction**, jamais par un second
  produit : `total × (1 − taux)` et `total − total × taux` ne donnent pas
  toujours le même entier, et l'écart d'un franc irait au grand livre.
- Le forfait de zone vaut **zéro** pour le pilote sans disparaître du
  back-office : un forfait non nul recréerait une marche au passage du seuil —
  15 000 GNF de plus pour 200 mètres. Le barème est aujourd'hui continu,
  85 850 GNF à 2,9 km contre 86 000 GNF à 3,1 km, et un test le garde.
- Un taux de commission ou de proximité aberrant est borné à [0, 1] plutôt que
  de produire un net négatif.
- Aucune valeur n'est codée en dur : taux de commission, taux de proximité et
  pas d'arrondi viennent des paramètres, la grille de déplacement de la zone, le
  prix de la prestation du catalogue.

### Le prix est ferme à l'acceptation, pas à la publication
Puisque le déplacement dépend de la position du technicien, il ne peut pas être
ferme tant qu'aucun technicien n'a accepté (ADR-0026). D'où deux calculs et une
seule formule :

| Méthode | Point de départ | Quand | `ferme` |
|---|---|---|---|
| `estimation()` | centroïde de la zone | avant publication | `false` |
| `pourTechnicien()` | position réelle du technicien | à l'acceptation (C3) | `true` |

Le devis et le ticket portent tous deux ce drapeau. L'application mobile doit
afficher « à partir de » puis « total », et **notifier le montant ferme à
l'acceptation** : sans cela, la première facture surprise sera le premier litige.

`MapProvider` a deux pilotes. `GoogleDistanceMatrixProvider` mesure la vraie
distance routière, met en cache sur des coordonnées arrondies — à Conakry les
demandes se concentrent sur quelques quartiers et chaque appel est facturé — et
**ne lève jamais d'exception** : toute anomalie rend la main à
`HaversineMapProvider`, qui applique le facteur de sinuosité paramétré. Un devis
ne peut pas échouer parce qu'un tiers a hoqueté ; il se dégrade et le dit, par
le drapeau `distance_is_estimated` conservé sur le ticket.

Sans clé Google, le conteneur lie directement le pilote Haversine : tenter
l'appel renverrait `REQUEST_DENIED` sur chaque devis et le repli se ferait après
un aller-retour réseau inutile.

### Publication d'une demande
- `CreateTicket` crée le ticket en BROUILLON puis le fait passer en PUBLIEE
  **par la machine à états**. Ce détour garantit qu'un ticket publié a toujours
  sa ligne `ticket_events` et son `published_at` : l'historique n'a pas de trou
  au point de départ.
- Un client ne peut avoir qu'une demande ouverte. Sans cette garde, un client
  agacé par l'attente republie la même panne : deux techniciens se déplacent, un
  seul est payé, et le second impute l'annulation à son propre taux.
- La référence vient d'une **séquence PostgreSQL**. Un compteur calculé en PHP
  ne tient pas la concurrence : deux publications simultanées liraient la même
  valeur et la seconde échouerait sur l'unicité, au pire moment.
- L'adresse est figée en instantané : le client peut la supprimer, l'historique
  reste lisible.
- Les frais d'annulation ne démarrent qu'à EN_ROUTE — tant que personne n'a
  bougé, annuler ne coûte rien — et sont **figés sur le ticket** au moment de
  l'annulation, pas relus à l'encaissement.

### Ce que l'API ne montre pas
- Un ticket ou une adresse qui n'est pas à soi répond **404**, jamais 403 :
  répondre « interdit » confirmerait son existence.
- Le numéro de l'autre partie n'est en clair que pendant une intervention
  active ; masqué avant et après.
- La part technicien n'apparaît pas dans la réponse servie au client, et la
  commission ne figure pas dans le détail du devis : le client paie un total.

### Limites de débit
Les limites de route protègent l'infrastructure et restent **plus larges** que
les gardes métier : c'est `AuthenticateUser` qui verrouille au bout de cinq
essais, avec un message lisible en français. Un 429 muet à sa place serait une
régression d'expérience.

### Téléphone
`App\Support\Telephone` normalise en E.164 avant toute validation : sans cela,
« 620 12 34 56 », « +224620123456 » et « 00224620123456 » créeraient trois
comptes pour la même personne.

## 8. Conventions de code

### PHP
- `declare(strict_types=1);` en tête de **chaque** fichier. Pint l'applique.
- Classes finales par défaut, typage complet des propriétés, paramètres et retours.
- **Aucune logique métier dans un contrôleur.** Un contrôleur valide (Form
  Request), appelle une action de `app/Domain/`, et présente (vue ou Resource).
- Une action = une classe, une méthode publique `execute()` (ou `__invoke()`),
  dans `app/Domain/<Contexte>/Actions/`.
- Les montants sont des **entiers de GNF** (`bigInteger` en base, `int` en PHP).
  Aucun flottant ne touche l'argent. Suffixe `_gnf` sur les colonnes.
- Les dates sont des `CarbonImmutable` (imposé dans `AppServiceProvider`).
- Le chargement paresseux des relations lève une exception hors production :
  toute relation doit être chargée explicitement (`with()`).

### Nommage
- Code, classes et commentaires : **français** pour le métier, anglais pour les
  termes techniques consacrés (`Controller`, `Action`, `Job`).
- Tout le contenu **visible** est en français : libellés, erreurs, notifications,
  emails, colonnes DataTables, légendes Highcharts. Traductions dans `lang/fr`.
- Téléphones normalisés en **E.164** (`+224XXXXXXXXX`), identifiant unique du compte.

### Front-office web (back-office)
- Blade est le socle. React n'intervient qu'en **îlots** montés sur
  `<div data-react-component="Nom" data-props="{…}">`, enregistrés dans
  `resources/js/react/registry.js`. Pas de SPA.
- Bootstrap 5.3 personnalisé : les jetons de design vivent dans
  `resources/scss/_tokens.scss` et surchargent les variables Bootstrap dans
  `_variables.scss`. **Aucune couleur en dur** dans un composant.
- Tous les tableaux passent par DataTables en **mode serveur** (yajra).
- Tous les graphiques passent par le thème global
  `resources/js/theme/highcharts-theme.js`.

### Tests (Pest)
- `tests/Unit` ne démarre pas l'application : logique pure uniquement.
- `tests/Feature` tourne sur la vraie base `depanne_moi_test` avec PostGIS.
- **196 tests passent** (931 assertions) ; `composer analyse` (PHPStan niveau 6) ne remonte rien.
- `phpstan.neon` active `parseModelCastsMethod: true` — sans elle, Larastan lit
  le type de retour déclaré de `casts()` et prend une date castée pour une
  chaîne. Les tests Pest sont exclus de l'analyse : leurs closures liées
  dynamiquement ne sont pas résolues sans extension dédiée.
- `HasFactory` n'est déclaré que sur les modèles qui ont réellement une factory
  (aujourd'hui `User`) : sinon PHPStan réclame un générique qui n'existe pas.
- Couverture obligatoire : calcul de prix, répartition financière, machine à
  états, scoring du matching, webhooks (signature, idempotence, rejeu).

## 9. Décisions d'architecture (ADR)

| # | Décision |
|---|---|
| 0001 | **Backend Laravel unique** — web et API partagent `app/Domain/`, jamais de logique dupliquée |
| 0002 | **Montants en entiers GNF** — `bigInteger`, jamais de flottant |
| 0003 | **Authentification par téléphone + argon2id** — access token 15 min, refresh rotatif hashé 30 j ; OTP réservé au mot de passe oublié |
| 0004 | **Solde calculé, jamais incrémenté** — le portefeuille est la somme des mouvements `transactions` |
| 0005 | **Fournisseurs derrière des interfaces** — `MapProvider`, `PaymentProvider`, `SmsProvider`, `PushProvider`, avec implémentations simulées activables par `.env` |
| 0006 | **Deux connexions PostgreSQL** — `pgsql` (applicative, pooler) et `pgsql_migrations` (directe) |
| 0007 | **Blade + îlots React** — pas de SPA, pas d'Inertia |
| 0008 | **Verrou Redis/base sur l'attribution** — `Cache::lock()` par ticket pendant la fenêtre de 45 s |
| 0009 | **Fuseau `Africa/Conakry`**, stockage UTC, `lang/fr` par défaut |
| 0010 | **Pas de Docker en développement** — installation portable, scripts `scripts\*.cmd` |
| 0011 | **Files d'attente et cache sur PostgreSQL en local** — le pilote `database` fournit des verrous atomiques ; bascule sur Redis + Horizon au déploiement |
| 0012 | **React 19** au lieu du 18 mentionné au cahier — version stable courante, API `createRoot` identique |
| 0015 | **Modules en préparation servis sous leur URL définitive** — un lien de la barre latérale ne renvoie jamais un 404, et le passage au module réel ne change aucune adresse |
| 0024 | ~~Déplacement mesuré depuis le centre de la zone~~ — **remplacée par l'ADR-0026** |
| 0025 | **Les classes d'état lisent la table de l'énumération** — une seule source de vérité pour les transitions |
| 0026 | **Déplacement facturé au technicien réel au-delà de 3 km** — prix ferme à l'acceptation, majoration de 1 % en deçà |
| 0013 | **Instantanés sur le ticket** — adresse et prix recopiés à la publication, pour qu'une suppression d'adresse ou un changement de grille ne réécrive pas l'historique |

Chaque ADR est détaillé dans [docs/adr/](docs/adr/).

## 10. Écarts assumés par rapport au cahier des charges

| Point du cahier | Écart | Raison |
|---|---|---|
| `docker-compose` en A0 | Remplacé par `scripts\*.cmd` | Décision client : pas de Docker |
| Redis + Horizon | Reportés au déploiement | Ni Redis ni Docker en local ; Horizon exige `ext-pcntl`, absente sous Windows |
| React 18 | React 19.2 | Version stable courante, îlots identiques |
| `app/Domain/` à 10 contextes | 14 contextes | Ajout de `Pricing/` (§8.2 impose un `PricingService` testé), `Settings/` (accès typé et caché à `app_settings`) et `Reviews/` (l'avis a son propre cycle de vie et alimente le scoring) |
| Machine à états dès A1 | Énumération `TicketState` en A1, spatie/model-states livré en C2 | A1 ne devait contenir aucune logique métier ; le basculement n'a demandé aucune migration |
| Supabase dès A1 | PostgreSQL local d'abord | Décision client ; bascule par simple changement de `.env` |
