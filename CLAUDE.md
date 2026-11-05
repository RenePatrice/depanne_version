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
| | **C3** — matching, temps réel mobile, notifications | ✅ terminée |
| | **C4** — chat, avis, litiges | ✅ terminée |
| | **C5** — paiement, séquestre, portefeuille, retraits | ✅ terminée |
| | **C6** — intégration bout en bout, durcissement, doc finale | ✅ terminée |
| D — Mobile Flutter | **D1** — design system, auth, navigation, client Dio | ✅ terminée |
| | **D2** — parcours client complet | ✅ terminée |
| | **D3** — parcours technicien | ✅ terminée |
| | **D4** — chat, notifications, temps réel, hors-ligne, réclamations | ✅ terminée (push FCM en attente de Firebase) |
| | **D5** — build, signature, publication | ⏳ suivante |

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

## 2 bis. Nouvelle orientation (5 novembre 2026)

Le client a revu la copie après la livraison de D4. Onze points, livrés par
lots, **sans casser l'existant** (chaque refonte remplace l'ADR concerné).

- **Marque → Parema Connect.** Rebrand complet du visible (back-office +
  mobile) et logo. Le nom de code interne reste `depanne_moi` (base, paquets,
  id Reverb) : le changer casserait l'environnement pour zéro valeur visible.
- **#1 Paiement direct + commission due.** On abandonne le séquestre
  obligatoire : le client paie le technicien directement (espèces ou Mobile
  Money), et la **commission 10 % devient une dette du technicien**, suivie au
  portefeuille et réglée périodiquement. Le séquestre Mobile Money reste une
  option. → remplacera l'ADR-0032 (le webhook n'est plus seule autorité).
- **#2 Place de marché.** Le prix catalogue devient **indicatif** ; chaque
  technicien propose son prix (service + distance) ; le client voit plusieurs
  techniciens (prix, note, distance) et **choisit**. → remplacera le matching
  séquentiel (ADR-0026/0027/0028). Photos : jusqu'à **3 pour un dépannage**,
  aucune pour une installation.
- **#3 Anti-« off ».** Parade au technicien qui demande d'annuler pour traiter
  hors plateforme (garantie liée à la plateforme, signalement, etc.).
- **#4 Garantie** après intervention (panne qui revient sous 24 h / X jours).
- **#5 BTP** : ouvrir le catalogue aux métiers du BTP (futur).
- **#6 Écrans** calqués sur les maquettes fournies (design adapté).
- **#7 Cartographie : OpenStreetMap**, pas de clé Google (Leaflet déjà en place
  côté back-office ; `flutter_map` côté mobile).
- **#8 Pas d'API SMS** : OTP par lien **WhatsApp** (`wa.me`) ou passerelle
  open-source, sans API Google.
- **#9 Back-office durci** : fin de l'admin en dur, changement de mot de passe
  au dashboard, CRUD utilisateurs, cloisonnement des modules admin, revue des
  failles (injection SQL, XSS, SSRF, path traversal, élévation de privilèges
  verticale/horizontale, RCE).
- **#10 IA locale** (sans API) pour des prédictions back-office et mobile.
- **#11 Durcissement APK** : obfuscation R8, aucun secret dans le binaire,
  en-têtes, vérification d'intégrité. (Le « chiffrer tout si dépaquetage »
  n'est pas un mécanisme standard réalisable ; on tient ce qui protège vraiment.)

Ordre retenu : **fondations d'abord** (rebrand ✅, écrans, OSM, sécurité
back-office), puis paiement + marketplace, puis garantie / anti-off / BTP / IA /
durcissement.

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
| `Matching` | `MatchAttempt`, `Candidat`, `TechnicianFinder`, `MatchScorer`, les actions de sollicitation et les deux jobs |
| `Payments` | `Payment`, `PaymentProvider` et ses deux pilotes, `InitiatePayment`, `HandlePaymentWebhook` |
| `Wallet` | `Transaction`, `Withdrawal`, `EscrowService`, `RequestWithdrawal`, `ProcessWithdrawal` |
| `Chat` | `Message`, `ContactMaskingFilter`, `SendMessage`, `MaskedCallProvider` |
| `Reviews` | `Review`, `SubmitReview` |
| `Disputes` | `Dispute`, ses trois énumérations, `OpenDispute` et `ResolveDispute` |
| `Notifications` | `AppNotification`, `NotificationType`, `SendNotification`, `PushProvider` et son pilote `log` |
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
- **Réassigner** (`Matching\Actions\ReassignTicket`, ADR-0040) remet en
  recherche un ticket ACCEPTEE ou EN_ROUTE dont le technicien ne vient pas. Il
  n'est jamais resollicité ; le prix redevient une estimation ; `accepted_at` et
  `en_route_at` sont effacés — **seule exception** à la règle du jalon, les
  dates d'origine restent dans l'événement. Imputé au technicien, le
  désistement passe en `DESISTE` et compte dans son taux d'annulation.
- **Forcer la clôture** libère le séquestre qu'elle trouve, et refuse un ticket
  en litige : c'est la décision du litige qui le ferme.
- **Rembourser** ne s'invente pas en marge : le bouton mène au litige ouvert,
  seul endroit où un remboursement se décide.

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
- **Trancher un litige règle l'argent** (ADR-0041), résolution comme rejet, dans
  la même transaction. Le remboursement sort **avant** la répartition (prorata,
  décision client du 4 novembre 2026) : la commission ne porte que sur ce qui
  reste facturé, au taux figé sur le ticket.
  - séquestre gelé → libéré diminué du remboursement, ticket CLOTUREE ;
  - fonds déjà libérés → le technicien rend le remboursement, la plateforme lui
    restitue la commission de la part remboursée : même résultat ;
  - rien encaissé → pas de remboursement possible, ticket ramené en TERMINEE.
- Avant ADR-0041, un litige tranché laissait ticket et argent gelés **pour
  toujours** — B5 et C5 n'avaient jamais été raccordés. Une intervention
  arbitrée ne peut plus être remise en litige depuis l'application.
- Le client est crédité au grand livre ; **le reversement sur son Mobile Money
  reste manuel** : `PaymentProvider` n'expose pas de remboursement.
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

### Comptes du back-office et revue de sécurité (#9)
- **Plus d'admin en dur en production.** `php artisan admin:create` crée les
  rôles (via `RolesPermissionsSeeder`, séparé de la démo) et le premier compte,
  mot de passe saisi masqué. Les comptes `@depanne-moi.gn` restent **de démo**.
- **Gestion des comptes** (`ManageAdminUsers`, module `/administrateurs`)
  réservée à la permission `administrateurs.gerer`, que seul l'ADMIN porte :
  cloisonnement, SUPPORT/FINANCE n'en voient ni le lien ni les routes. Création,
  modification, réinitialisation de mot de passe, activation — **jamais de
  suppression** (désactivation). Garde-fous : on ne se désactive ni ne se
  rétrograde soi-même, et le **dernier ADMIN actif** est protégé.
- **Mot de passe au dashboard** : chacun change le sien (`/profil`), l'actuel
  exigé, session régénérée.
- Posture sur les failles listées :
  - **Hachage** argon2id + salt (ADR-0003), cast `hashed` — plus fort que bcrypt.
  - **Élévation de privilèges** : permissions spatie côté back-office ; Policies
    + 404 (jamais 403) côté API (ADR-0035). Un lien affiché est toujours ouvrable.
  - **Injection SQL** : tout passe par Eloquent / requêtes liées, aucune
    concaténation ; `filterColumn` neutre sur les colonnes calculées.
  - **XSS** : Blade échappe par défaut ; les îlots React reçoivent des données
    typées, pas du HTML.
  - **RCE** : aucun `eval`/`exec`/désérialisation de données externes.
  - **SSRF** : les seuls appels sortants (carte, paiement) visent des hôtes
    **configurés**, jamais une URL fournie par l'utilisateur.
  - **Path traversal** : la route des pièces liste blanche la pièce et sert
    depuis la racine du disque (Flysystem), chemin issu de la base, pas de l'URL.

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
- **Les photos suivent le type de prestation (#2).** `CreateTicket` tronque au
  `photosAutorisees()` du `ServiceType` : trois pour un dépannage (elles aident
  à estimer la panne avant le déplacement), **zéro pour une installation** où il
  n'y a encore rien à photographier. Le catalogue expose `type` et
  `photos_autorisees`, et l'application n'affiche le bouton que quand il y a lieu.
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

### Sélection par le client — place de marché (ADR-0042)
> **Changement de modèle (5 novembre 2026).** Le push séquentiel « un
> technicien à la fois » a été remplacé par une **place de marché** : le client
> voit plusieurs techniciens et choisit. `SolicitNextTechnician`,
> `StartMatchingJob` et `AbandonMatching` sont supprimés ; `TechnicianFinder` et
> `MatchScorer` sont réutilisés.

Deux pièces : `ListAvailableTechnicians` présente, `SelectTechnician` sollicite
le technicien choisi.

- **La publication ne sollicite plus personne.** `CreateTicket` publie, et le
  ticket reste PUBLIEE, ouvert à la sélection.
- `ListAvailableTechnicians` renvoie les techniciens disponibles
  (`TechnicianFinder` : en ligne, validés, bonne spécialité, dans le rayon,
  libres), **chacun avec son prix déjà ferme** — son tarif (#2) + le déplacement
  depuis sa position — sa note et sa distance. L'ordre est donné par
  `MatchScorer` (meilleurs d'abord), mais le client reste libre de choisir.
- `SelectTechnician` ouvre au technicien choisi la **fenêtre de 45 s**
  (maquette « Accepter / Refuser »). Trois gardes : une seule sélection vivante
  à la fois, disponibilité revérifiée, et **un technicien déjà sollicité n'est
  pas re-choisi** (ADR-0028 conservé). L'expiration reste un job différé
  idempotent (`HandleMatchTimeoutJob`).
- **Refus ou expiration ne poussent plus** vers un autre : le ticket revient à
  PUBLIEE et le client en choisit un autre. Les deux parties sont prévenues
  (`TECHNICIEN_INDISPONIBLE`).
- Le filtrage et le tri par distance restent **en SQL** (`ST_DWithin`, index
  GiST de A1). La position retenue est la dernière connue si elle a moins de
  15 minutes, sinon le point de rattachement.

### L'attribution
`RespondToMatch::accepter()` superpose trois protections (ADR-0027, conservé) :
un verrou court, une mise à jour conditionnelle `WHERE state = 'PUBLIEE'` — c'est
elle qui garantit réellement l'unicité — et la machine à états. Un second
technicien reçoit un 409 explicite.

C'est aussi là que **le prix devient ferme** : le prix de la prestation est le
**tarif propre du technicien** qui accepte (ADR-0043), celui que le client a vu
en le choisissant ; sans tarif fixé, le prix catalogue sert de défaut. Le
déplacement est calculé depuis la position réelle du technicien. L'ancien
ADR-0029 (relire l'instantané plutôt que le catalogue) est remplacé : en place
de marché, le prix est celui du technicien choisi, pas un instantané figé à la
publication.

Sans position connue pour le technicien, l'estimation est conservée telle
quelle : mieux vaut facturer l'estimation que refuser l'attribution et laisser
le client sans personne.

### Notifications
Trois chemins, dans cet ordre : **la base toujours**, Reverb pour une
application ouverte, le push pour une application fermée. Aucun ne peut faire
échouer l'appelant — une notification perdue est un désagrément, un matching
interrompu est une panne. C'est pourquoi `PushProvider`, comme `MapProvider`, a
l'interdiction de lever.

Tant que Firebase n'existe pas, le pilote `log` écrit dans `laravel.log` ; la
persistance et la diffusion Reverb, elles, fonctionnent réellement. Le centre de
notifications de l'application lit la table, pas le push : sur le réseau de
Conakry, un message perdu est un cas courant.

Les textes vus par le client viennent des paramètres `notif_*` et se corrigent
en back-office. Une variable oubliée est **effacée** plutôt que laissée en
clair : mieux vaut une phrase incomplète qu'un « {technicien} » affiché.

### Ce que le technicien ne peut pas faire
- **Saisir un montant.** Le kilométrage est calculé depuis sa position ; aucune
  route ne l'expose. Deux tests le vérifient.
- **Se mettre en ligne sans dossier validé**, ni **hors ligne pendant une
  intervention** — se déconnecter en route, c'est laisser un client sans
  nouvelle et sans position sur la carte du support.
- **Voir l'adresse exacte avant d'accepter** : seuls le quartier et la distance.
  Sans cela, refuser systématiquement les demandes deviendrait un moyen de
  collecter des adresses.
- **Sauter une étape** : il nomme un geste — « je pars », « je suis arrivé » —
  et c'est la machine à états qui décide s'il est possible.

### Statistiques de matching
`RecalculateTechnicianStats` **recalcule**, n'incrémente jamais — même
raisonnement que pour le solde (ADR-0004). Un compteur incrémenté dérive au
premier job rejoué, et une dérive du taux d'acceptation change qui reçoit les
courses, donc qui gagne sa vie. Un technicien sans historique part à 1,00 :
sinon il ne serait jamais sollicité, donc n'aurait jamais d'historique.

### Chat et anti-contournement
Le §11 appelle le contournement « risque business n°1 ». `ContactMaskingFilter`
cherche trois choses — numéros, e-mails, liens — et normalise avant de chercher :
un `620123456` se repère en une expression régulière, mais « six deux zéro », les
chiffres espacés un à un et « nom arobase gmail point com » demandent une analyse
par jetons.

- **Le message part quand même**, masqué, et son auteur reçoit un avertissement
  qui explique *pourquoi* (ADR-0030). Le refuser pousserait à recommencer
  autrement jusqu'à trouver la faille, sans laisser de trace ; le laisser passer
  masqué rend la tentative visible au back-office.
- Le filtre **n'est pas étanche** et ne prétend pas l'être. Toute évolution doit
  être jugée sur « le contournement est-il resté pénible ? », pas sur un taux de
  détection.
- **Les montants et les références sont épargnés** (ADR-0031) : le seuil est à
  huit chiffres, une suite suivie de `GNF` ou précédée de `DM` est ignorée. Un
  filtre qui masque les prix dans une conversation sur le prix pousse les gens
  dehors — l'inverse du but. La moitié « ce qu'il ne faut pas masquer » de la
  suite de tests est aussi fournie que l'autre.
- Le chat n'est ouvert **qu'entre l'acceptation et la clôture**. Avant, les deux
  parties n'ont rien à se dire ; après, rouvrir permettrait de reprendre contact
  des semaines plus tard hors de tout cadre.
- L'original n'est exclu qu'**à un seul endroit** — `Message::$hidden` — plutôt
  qu'à chaque sérialisation. C'est la ligne qu'on oublie.
- L'appel masqué a son interface et un pilote simulé qui **annonce qu'il l'est**.
  Un bouton qui ne marche pas coûte plus cher en confiance qu'une fonction
  annoncée comme à venir.

### Avis
L'avis pèse 30 % du score de matching : il décide de qui reçoit les courses
suivantes. Seul le client du ticket note, une seule fois, et seulement une
intervention réellement effectuée. La moyenne est **recalculée** depuis la table,
jamais ajustée à l'incrément (ADR-0004).

La note affichée reste la moyenne réelle ; c'est le **scoring** qui substitue une
note neutre aux nouveaux venus. Confondre les deux afficherait 4,0 sur le profil
d'un technicien jamais noté, ce qui serait faux.

Seul le prénom du client apparaît sur un avis public : dans une ville où tout le
monde se connaît, un avis négatif ne doit pas être un risque social.

### Réclamations côté mobile
`OpenDispute` ouvre la porte que le back-office traite depuis B5. Le ticket passe
en LITIGE_OUVERT, ce qui **suspend la libération des fonds** — un litige déposé
après le versement n'aurait plus de levier.

La priorité est **déduite** du motif et de l'enjeu, jamais déclarée : chacun
estimerait son propre litige urgent. Elle n'est pas exposée au déclarant, pas
plus que l'échéance interne — les afficher inviterait à négocier son rang dans la
file.

### Garantie après intervention (#4)
Une panne qui revient dans les `warranty_days` suivant la clôture (7 jours par
défaut, réglable, 0 désactive) ouvre droit au **retour du même technicien, sans
nouveau paiement**. `OpenWarranty` crée un **ticket à part** rattaché à
l'origine (`parent_ticket_id`, `is_warranty`), qui démarre directement en
ACCEPTEE — la place de marché ne joue pas, le technicien est déjà désigné — et
suit ensuite la même machine à états. `CloseWarranty` le clôt quand le client
confirme la réparation : c'est le seul usage de la transition TERMINEE →
CLOTUREE.

- La fenêtre est **figée dans `warranty_until`** à la clôture de l'intervention
  payante, dans `TransitionTicket` — le seul entonnoir que toutes les clôtures
  traversent. Raccourcir le délai en back-office ne retire pas une garantie
  déjà promise.
- Un retour sous garantie **n'ouvre pas lui-même de garantie** (gratuit, total
  0, commission 0) : sinon un technicien de mauvaise foi enchaînerait les
  retours. Un seul retour vivant à la fois par intervention.
- Les deux actions de paiement (`InitiatePayment`, `DeclareCashPayment`)
  **refusent** un ticket `is_warranty` : c'est gratuit, il n'y a rien à encaisser.
- Réservé au client (Policy `garantir`, 404 sinon) : c'est lui qui constate le
  retour de la panne, puis sa réparation.

### Anti-contournement « hors plateforme » (#3)
Le §11 appelle le contournement « risque business n°1 ». Trois leviers, dans la
continuité de l'ADR-0030 (*filtrer et signaler, ne pas bloquer*) :

- **La garantie (#4) est le premier rempart** : elle n'existe que dans
  l'application. Un technicien qui pousse à régler « en dehors » prive le client
  de ce recours, et le client le sait — les CGU et le message de clôture le
  rappellent.
- **Le masquage du chat** (`ContactMaskingFilter`) reste la parade au partage de
  coordonnées.
- **Le signalement explicite** (`ReportOffPlatform`, route
  `/tickets/{t}/signaler-hors-plateforme`) : le client ou le technicien nomme la
  tentative, qui atterrit dans le journal **`activity_log` sous `securite`**,
  imputée à l'autre partie. On **ne bloque rien, on trace** : au support de
  rapprocher les signalements et d'agir sur un compte. Ouvert aux deux parties
  (Policy `reclamer`), refusé sur un brouillon (personne n'est encore en
  relation).

### Paiement direct et commission due (#1, place de marché)
Depuis le 5 novembre 2026, le client paie le technicien **directement**. Deux
moyens, une seule règle de commission :

- **Espèces** : double confirmation (`DeclareCashPayment` côté technicien,
  `ConfirmCashReceipt` côté client — seule la confirmation du client engage). Le
  technicien a tout encaissé : on inscrit **uniquement** la commission en
  négatif (sa dette), sans `EARNING`. Le ticket va direct en CLOTUREE, sans
  séquestre.
- **Mobile Money in-app** : flux séquestre existant (ci-dessous), la commission
  est **retenue à la source** (EARNING + COMMISSION, net retirable).

`CommissionLedger` : la **dette** est la part négative du solde
(`max(0, −solde)`). Les gains Mobile Money la compensent. Au-delà du plafond
(`commission_debt_cap_gnf`, 100 000 par défaut), `RespondToMatch::accepter`
**refuse** toute nouvelle course tant que le technicien n'a pas réglé
(`SettleCommission`, mouvement `SETTLEMENT`). Le portefeuille expose
`commission_due_gnf`, `commission_plafond_gnf`, `bloque`.

> **Pilote** : le règlement de commission est simulé (succès immédiat). En
> production il passera par `PaymentProvider` et son webhook (ADR-0032).

### Séquestre (Mobile Money in-app)
`PaymentProvider` a deux pilotes. **`MockPaymentProvider` est celui qui tourne
aujourd'hui** : aucun compte marchand n'est ouvert, et le parcours complet —
paiement, séquestre, libération, portefeuille, retrait — se joue et se teste
sans lui. Il est fidèle sur ce qui compte : référence unique, **signature
HMAC-SHA256 identique** à celle attendue de l'opérateur, et capacité à échouer
(une référence contenant `ECHEC` produit un refus).

`OrangeMoneyProvider` est écrit mais **n'a jamais parlé à l'API réelle**
(ADR-0034). Chaque champ à confirmer contre la documentation Orange porte la
mention `À CONFIRMER` à l'endroit où il est utilisé. Le conteneur ne le lie que
si `PAYMENT_PROVIDER=orange_money` **et** que les quatre clés sont là : il ne
peut pas être atteint par accident.

> **Avant toute mise en ligne**, relire `OrangeMoneyProvider` ligne à ligne
> contre la documentation Orange : noms de champs de la réponse d'initiation,
> algorithme et en-tête de signature, unité du montant, comportement en cas de
> rejeu. Seule cette classe bougera.

**Le webhook est la seule autorité** (ADR-0032). La route de retour du client
n'écrit rien : si elle tranchait, il suffirait de l'ouvrir à la main pour se
déclarer payé. Quatre protections, dans cet ordre :

1. Signature vérifiée **avant** toute lecture du corps.
2. Référence qui doit **exister** en base — une référence inconnue est ignorée,
   jamais créée.
3. Verrou par référence : les opérateurs rejouent volontiers quand la première
   réponse tarde.
4. État du paiement revérifié dans la transaction.

La réponse reste un **200 même sur un rejeu**. Répondre en erreur ferait rejouer
l'opérateur indéfiniment sur un cas qui ne se résoudra jamais.

Un écart de montant est **tracé, pas refusé** (ADR-0033) : l'argent est déjà
parti de chez le client, refuser le laisserait débité sans intervention payée.
L'écart part dans `activity_log` sous `finances` — il faut donc que quelqu'un
regarde ce journal.

### Supplément après diagnostic
Le seul endroit où le total d'un ticket accepté peut augmenter, et **la seule
route de l'API qui accepte un montant en entrée**. Trois gardes le rendent
acceptable :

- le technicien **propose**, il ne décide pas. Le ticket passe en
  DIAGNOSTIC_EN_ATTENTE et `total_gnf` ne bouge pas tant que le client n'a pas
  répondu. Le montant proposé vit dans `proposed_extra_fee_gnf`, distinct de
  `extra_fee_gnf` qui est le supplément **appliqué** ;
- il est **plafonné au double de la prestation** — assez large pour une pièce
  coûteuse, assez ferme pour transformer une dérive en refus explicite plutôt
  qu'en négociation sur le pas de la porte ;
- le **diagnostic écrit est obligatoire**, vingt caractères au minimum. Un
  supplément sans explication est un litige à venir dont le support n'aurait
  rien à examiner.

À l'acceptation, la répartition est **entièrement recalculée** : ajouter le
supplément au total sans reventiler laisserait la commission porter sur
l'ancien montant. Un refus n'annule rien — le technicien est sur place et fait
ce qui était prévu, d'où la transition DIAGNOSTIC_REFUSE → EN_COURS.

> Cette route a fait tomber le garde-fou « aucune route ne prend de montant ».
> C'était son rôle : le test liste désormais l'exception, la nomme, et un
> second test vérifie que le montant saisi n'est pas appliqué sans accord.

### Le grand livre
Entre la capture et la libération, l'argent est **encaissé mais pas acquis**.
C'est ce qui donne au client un levier réel : une réclamation ouverte suspend
une libération qui n'a pas eu lieu. Deux chemins de libération — le client
valide, ou vingt-quatre heures passent. Le second n'est pas une commodité : sans
lui, un client qui n'ouvre plus l'application bloquerait l'argent d'un
technicien qui a fait son travail.

- `EscrowService::liberer()` est **rejouable** : job différé, validation du
  client et reprise manuelle peuvent arriver dans n'importe quel ordre.
- **Deux mouvements, pas un** : `EARNING` du total puis `COMMISSION` en négatif.
  Le solde fait bien le net, mais le grand livre garde trace du chiffre
  d'affaires *et* du prélèvement. Un mouvement net unique rendrait le CA
  irrécupérable.
- `balance_after_gnf` est une **commodité de lecture**, pas une source de vérité
  (ADR-0004). Le solde reste la somme des mouvements.
- Le **retirable** n'est pas le solde : il en retranche ce qui est engagé dans
  une demande non versée. Sans cela, un technicien déposerait trois demandes de
  la totalité de son solde.

> ⚠️ **En test, `QUEUE_CONNECTION=sync` ignore les délais.** Un test qui veut
> observer le séquestre doit appeler `Queue::fake()`, sinon la libération
> s'exécute dans la foulée de la capture et le séquestre n'existe jamais.

### Limites de débit
Les limites de route protègent l'infrastructure et restent **plus larges** que
les gardes métier : c'est `AuthenticateUser` qui verrouille au bout de cinq
essais, avec un message lisible en français. Un 429 muet à sa place serait une
régression d'expérience.

### Téléphone
`App\Support\Telephone` normalise en E.164 avant toute validation : sans cela,
« 620 12 34 56 », « +224620123456 » et « 00224620123456 » créeraient trois
comptes pour la même personne.

## 7 bis. Durcissement et observabilité

### Autorisation
`TicketPolicy` et `AddressPolicy` gardent la porte ; les actions du domaine
gardent la règle (ADR-0035). Une règle métier logée dans une Policy deviendrait
invisible depuis le back-office, qui n'appelle pas les Policies de l'API.

- Refus de Policy → **404**, par `denyAsNotFound()`. Confirmer l'existence d'un
  ticket à qui devine sa référence en dirait déjà trop.
- Refus métier → **422** avec un message lisible : il s'adresse à quelqu'un qui
  est bien partie prenante et s'est trompé de bouton.
- `valider` — qui libère le séquestre — est réservé au **client**, pas « aux
  parties » : l'ouvrir au technicien lui permettrait de débloquer ses propres
  fonds.
- La règle « être partie au ticket » vit dans `Ticket::estPartiePrenante()`, un
  seul exemplaire pour la Policy et pour le domaine. Elle était recopiée six
  fois.

Nos modèles ne sont pas dans `App\Models` : les Policies sont **déclarées
explicitement** dans `AppServiceProvider`, la découverte automatique ne les
trouverait pas.

### Pièces d'identité
Route signée `documents.piece`, quinze minutes, cumulée au guard `admin` et à la
permission `techniciens.voir` (ADR-0036). Aucune adresse publique ne pointe vers
une carte d'identité, et la réponse porte `Cache-Control: no-store`.

Quand le disque sait signer lui-même, on lui laisse la main. Sinon l'application
diffuse le fichier — ce qui rend la file de validation **démontrable en local**,
ce qu'elle n'était pas.

### CORS
`config/cors.php` restreint les origines à `CORS_ALLOWED_ORIGINS`. Sans ce
fichier, Laravel laisse passer tout le monde. L'application Flutter n'est pas
concernée : un client natif n'envoie pas d'en-tête `Origin`.

`supports_credentials` reste à **false** — l'API s'authentifie par jeton Bearer,
jamais par cookie ; l'activer ouvrirait le CSRF sur des routes qui n'en ont
aucune protection.

### Traçabilité
Chaque réponse porte un `X-Request-Id`, repris de l'appelant s'il en fournit un
de forme correcte, régénéré sinon — accepter une valeur arbitraire laisserait
injecter des sauts de ligne dans les journaux. L'identifiant est partagé dans le
contexte de tous les logs de la requête, ce qui relie la requête HTTP, les jobs
différés et le webhook de paiement.

Le canal `json` produit une ligne par objet ; il se sélectionne par
`LOG_STACK=json` au déploiement. En local le texte reste plus lisible.

### Ce que le parcours complet vérifie
`tests/Feature/ParcoursCompletTest.php` traverse l'application **par l'API
seule**, comme le fera Flutter, de l'inscription au retrait. Il attrape ce que
les tests unitaires ne voient pas : un champ renommé d'un côté et pas de
l'autre, une transition possible en théorie mais inaccessible par les routes,
une donnée attendue par l'écran suivant et jamais renvoyée. Il a trouvé trois
écarts à sa première exécution.

## 7 ter. Application mobile (bloc D)

Flutter **3.47.2** / Dart 3.13.2 et le **SDK Android** (plateforme 36,
build-tools 36.1.0, NDK 28.2.13676358), tous deux en portable dans
`%USERPROFILE%\devtools`. Riverpod pour l'état, go_router pour la navigation,
Dio pour le réseau, geolocator pour la position, url_launcher pour l'itinéraire.

```cmd
scripts\flutter.cmd run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
scripts\flutter.cmd build apk --debug
scripts\mobile-verifier.cmd        :: format + analyse + tests
```

> ⚠️ **Les chemins des SDK passent par le nom court 8.3.** L'outillage Flutter
> et celui d'Android compilent les greffons natifs en appelant `dart` et
> `aapt2` sans guillemets ; le profil utilisateur de cette machine contient un
> espace, et la compilation échoue si `FLUTTER_ROOT` ou `ANDROID_HOME`
> l'exposent. Les deux scripts s'en chargent.

### Le SDK Android, et ses trois pièges
Installé par les **command-line tools** dans
`%USERPROFILE%\devtools\android-sdk`, sans Android Studio ni droits
administrateur.

- Le `sdkmanager` est **déprécié et plante en sortie** sur cette machine
  (NTSTATUS 0xC0000409) — l'installation aboutit, le processus meurt après.
  Tout passe donc par le binaire `android` :
  `android sdk install platforms/android-36 build-tools/36.1.0 ndk/28.2.13676358`.
  Laisser AGP l'installer lui-même échoue sur une erreur qui ne nomme pas la
  pièce manquante, et il installe au passage une plateforme corrompue.
- Le **NDK est obligatoire** bien qu'aucune ligne de C ne soit écrite : le
  plugin Gradle de Flutter impose sa version. 2,5 Go, une fois.
- `flutter_secure_storage` **11.0.0 compile contre l'API 37**, qui n'existe au
  dépôt Google que sous des noms mineurs — `android-37.0`, `37.1`… — qu'AGP ne
  sait pas résoudre depuis `compileSdk = 37`. La dépendance est donc **fixée à
  `^10.3.1`**, qui compile contre 36 ; l'API Dart utilisée est identique. À
  revoir quand AGP saura résoudre les versions mineures.

Téléchargements : utiliser `curl.exe`, jamais `Invoke-WebRequest` — en
PowerShell 5.1 celui-ci bufferise toute la réponse en mémoire et met plus de
vingt minutes là où curl met quarante secondes.

### La clé Google Maps
**Aucune clé n'est fournie et aucune n'a été créée** : cela demande un compte
Google Cloud, une facturation active et l'accord du client. Le câblage est en
place et n'attend qu'elle :

- côté mobile, `MAPS_API_KEY` se pose dans `android/local.properties` — non
  versionné — ou dans la variable d'environnement du même nom ; Gradle
  l'injecte dans le manifeste. Vide, l'application compile et tourne, car rien
  du parcours n'en dépend aujourd'hui ;
- côté serveur, `GOOGLE_MAPS_SERVER_KEY` active le calcul de distance routière
  réel ; sans elle le conteneur lie directement le pilote Haversine.

L'itinéraire du technicien passe par le schéma `geo:` et l'application de
cartographie du téléphone : elle guide mieux qu'une carte embarquée, connaît le
trafic, et ne demande aucune clé.

### Le design system est en double, à dessein
`mobile-flutter/lib/design_system/design_tokens.dart` et
`backend-laravel/resources/scss/_tokens.scss` décrivent **le même** système.
Toute modification se porte des deux côtés ; un test compare les trois couleurs
de marque, pour que la dérive se voie avant qu'on la remarque à l'œil.

Le mode Client est bleu, le mode Technicien orange. Ce n'est pas décoratif :
c'est ce qui permet de savoir dans quel mode on se trouve sans lire. Un
technicien qui se croit en mode client cherche un bouton qui n'existe pas.

### Le rafraîchissement des jetons est sérialisé
Le refresh token tourne à chaque usage (ADR-0022). Une application mobile lance
volontiers cinq requêtes en parallèle au retour sur un écran : si le jeton a
expiré entre-temps, cinq rafraîchissements partiraient avec le même refresh
token, le serveur y verrait un rejeu, et **le mécanisme censé maintenir la
session la détruirait**.

`AuthInterceptor` n'en laisse partir qu'un : les autres attendent son résultat
et repartent avec le jeton neuf. Onze tests couvrent ce fichier, dont celui de
la concurrence.

Deux détails qui ont l'air anodins :

- `validateStatus` s'arrête à **400**. L'élargir aux 4xx — réflexe courant pour
  lire le corps plus commodément — ferait passer les 401 à côté de
  l'intercepteur, et le rafraîchissement deviendrait silencieusement inopérant.
- Une coupure réseau pendant le rafraîchissement **n'efface pas** les jetons.
  Seul un 401 sur le refresh lui-même est sans appel. Sans cette nuance, toute
  perte de couverture déconnecterait l'utilisateur.

### Le parcours client (D2)
Catalogue, adresse, prix, publication, recherche, suivi, supplément, paiement,
avis, historique — le parcours entier du §7.2, par l'API seule.

`ClientRepository` est **la seule** classe qui connaisse la forme du JSON.
Au-dessus, on ne manipule que des objets typés ; le jour où un champ est
renommé côté serveur, la correspondance saute aux yeux à un seul endroit.

Trois écrans portent l'essentiel des décisions :

- **`nouvelle_demande_screen.dart`** — le bouton de publication reste inactif
  tant que le devis n'est pas affiché. Publier sans avoir vu le prix serait
  possible techniquement et contraire à toute la promesse. Le mot employé suit
  `devis.ferme` : « à partir de » avant qu'un technicien n'ait accepté, « total »
  après (ADR-0026). Trois tests de widget gardent ces deux règles.
- **`suivi_ticket_screen.dart`** — un seul écran pour les seize états, piloté par
  l'état renvoyé, jamais par une pile d'écrans propre à chaque étape. Il
  interroge le ticket toutes les dix secondes et **s'arrête sur un état final** :
  une interrogation qui continue après la clôture consomme le forfait de
  quelqu'un. Depuis D4, Reverb signale chaque changement et la boucle
  n'est plus que le repli.
- **`paiement_feuille.dart`** — l'application ne conclut jamais qu'un paiement a
  réussi ; elle interroge `/paiements/{reference}` jusqu'à ce que le serveur le
  dise (ADR-0032). Une interrogation qui échoue est ignorée : le réseau a
  hoqueté, la suivante réessaiera, et afficher une erreur ferait croire au
  client que son argent est perdu.

**Aucun montant ne remonte vers le serveur.** Un test énumère tous les appels
mutants du parcours et refuse qu'une clé contienne « montant », « prix », « gnf »
ou « km » — le pendant mobile du garde-fou de l'API. C'est la porte d'entrée
d'une facture négociée dans l'application, et elle reste fermée des deux côtés.

Le carnet d'adresses passe par une liste de **neuf quartiers** de Ratoma plutôt
que par un sélecteur sur carte : celui-ci demande une clé Google Maps que le
pilote n'a pas encore. La position n'a pas à être exacte au mètre — elle sert à
trouver la zone et à mesurer une distance, tous deux à l'échelle du kilomètre —
et c'est le **point de repère écrit** qui amène réellement quelqu'un devant la
bonne porte. Le jour où la carte arrive, seul `adresse_form_screen.dart` change.

### Le parcours technicien (D3)
Disponibilité, sollicitation, intervention, supplément, portefeuille, retrait —
le §7.3 en entier, par l'API seule. Quatre onglets : Demandes, Intervention,
Portefeuille, Profil.

- **`demandes_screen.dart`** — l'interrupteur en ligne / hors ligne et la
  sollicitation vivante. Une seule à la fois, jamais une file : le matching est
  séquentiel (§8.3), et afficher plusieurs demandes concurrentes serait un
  mensonge sur ce que le technicien peut réellement accepter. Le compte à
  rebours part de l'`expire_dans_s` **recalculé par le serveur à chaque
  lecture** et s'y recale : l'horloge d'un téléphone peut être décalée de
  plusieurs minutes, et un chrono fondé dessus se tromperait de fenêtre.
- **`intervention_screen.dart`** — le technicien nomme un geste, « je pars »,
  « je suis arrivé », et jamais un état. Une liste d'états recopiée dans
  l'application finirait par diverger de la machine à états du serveur, et
  l'écran proposerait des boutons qui échouent.
- **`diagnostic_feuille.dart`** — la **seule** route de l'application mobile
  qui envoie un montant. Elle propose : le total ne bouge qu'après accord du
  client. Les trois gardes — plafond au double de la prestation, diagnostic
  écrit de vingt caractères, décision du client — sont **annoncées** ici et
  **tenues** par le serveur. Les rejouer donnerait deux jeux de règles, et
  c'est celui de l'application qui finirait par se tromper.
- **`portefeuille_screen.dart`** — deux chiffres, jamais un : le solde est ce
  qui a été gagné, le retirable est ce qui peut sortir aujourd'hui. Le second
  retranche ce qui est déjà engagé dans une demande non versée.

Le refus du serveur — dossier non validé, mise hors ligne pendant une
intervention — est affiché **tel quel** : « ton dossier est en cours de
validation » est une phrase utile, « échec » ne l'est pas.

### La position remonte, enfin
`PositionService` émet exactement l'événement que `php artisan demo:positions`
simulait depuis B6 : la carte live du back-office reçoit désormais de vraies
positions, et la simulation peut s'arrêter sans que rien change côté
supervision.

- **On ne suit que quand il y a une raison de suivre.** Le flux démarre à
  l'apparition d'une intervention active et s'arrête avec elle. Lier le suivi à
  un interrupteur laisserait un technicien pisté toute la nuit pour avoir
  oublié de le couper.
- **Le rythme suit l'enjeu** : huit secondes pendant une intervention, où le
  client regarde le point avancer ; deux minutes en simple disponibilité, où
  seule la zone compte.
- **Un échec ne remonte jamais à l'écran.** Le GPS met parfois trente secondes
  à accrocher et le réseau coupe : une position perdue est un désagrément, une
  exception au milieu d'une intervention est une panne. Deux envois ne se
  chevauchent jamais — sur un réseau lent, la file grossirait plus vite qu'elle
  ne se vide et le serveur recevrait des positions périmées dans le désordre.
- La permission est demandée **au moment où elle sert**, pas au lancement :
  quelqu'un à qui on demande sa position avant de lui avoir montré pourquoi
  refuse, et un refus définitif est difficile à rattraper.

### Temps réel, messagerie, hors-ligne (D4)
**Le WebSocket ne transporte que des signaux.** `TicketEtatChange` part sur
`ticket.{id}` à chaque transition et ne dit que l'état : l'application relit
`/tickets/{id}`, seule source de vérité. Y mettre le total obligerait à
recopier les règles de visibilité de chaque partie, et la recopie fuirait.

- L'application a sa route d'autorisation, **`/api/v1/broadcasting/auth`**
  derrière Sanctum : celle du back-office s'appuie sur la session web et ne
  reconnaît pas un jeton Bearer. Les règles restent dans `routes/channels.php`.
- Le client Reverb (`core/temps_reel/temps_reel_service.dart`) est **écrit à la
  main** sur `web_socket_channel` : six messages du protocole Pusher 7, une
  reconnexion qui double son délai jusqu'à une minute, un réabonnement à chaque
  reconnexion — une signature est liée à un socket. Aucune erreur n'en sort.
- `InterrogationDeRepli` garde la boucle : dix secondes sans WebSocket, trente
  avec. Elle ne s'arrête jamais tout à fait — un WebSocket peut se croire ouvert
  bien après que la ligne est tombée.
- Lancement : `--dart-define=REVERB_URL=ws://10.0.2.2:8080
  --dart-define=REVERB_APP_KEY=…`. La clé Reverb est publique par nature ; sans
  elle, le temps réel est simplement absent et l'application interroge.

**Messagerie.** Le filtre est côté serveur et seulement là ; l'avertissement
s'affiche tel que le serveur l'a rédigé, le message masqué porte « Coordonnées
masquées ». Un envoi échoué **garde le texte saisi**. L'appel masqué annonce
franchement qu'il est simulé.

**Notifications.** Cloche sur l'accueil, relue à chaque notification poussée
sur `utilisateur.{id}`. La destination suit **le type** avant le mode : un
technicien qui a aussi la casquette client doit arriver sur le suivi client.

**Réclamations.** « Signaler un problème » sur tout ticket terminé ; le serveur
tient la fenêtre et l'unicité. Aucun montant ne part : le client décrit, le
support chiffre. Les motifs et la décision motivée sont **servis par l'API**.

**Hors-ligne, en lecture seule** (`core/hors_ligne/`). Un `get` qui déclare
`repliHorsLigne: true` mémorise sa réponse et la rend sur coupure réseau ; un
bandeau dit de quand datent les données. Le choix est **par chemin** : jamais
pour `/technicien/sollicitations` ni `/paiements/*` — une sollicitation d'il y a
dix minutes a expiré, un paiement a peut-être abouti. Les écritures ne sont
**jamais mises en file** : un « je suis arrivé » rejoué vingt minutes plus tard
ferait mentir l'historique. Le cache est vidé à chaque changement de compte.

### Ce que D4 ne fait pas
- **Push FCM** : Firebase n'existe pas pour le pilote. Le serveur écrit dans
  `laravel.log` ; l'application reçoit tout par Reverb quand elle est ouverte
  et par le centre de notifications sinon. Brancher FCM demande
  `google-services.json` et `firebase_messaging`.
- **Dépôt des pièces du dossier technicien** : appareil photo et téléversement
  restent à faire. L'écran dit où en est la validation.
- Profil (nom, mot de passe), seconde casquette et avis publics d'un technicien
  ont leurs routes API mais pas encore d'écran.

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
- **390 tests passent** (1 508 assertions) ; `composer analyse` (PHPStan niveau 6) ne remonte rien.
- Ne jamais lancer deux suites en même temps, ni `pest --parallel` : elles
  partagent `depanne_moi_test`, et les bases parallèles n'ont pas PostGIS.
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
| 0027 | **Attribution protégée trois fois** — verrou, mise à jour conditionnelle, machine à états |
| 0028 | **Un technicien sollicité ne l'est jamais deux fois** — élargir le rayon sert à trouver des gens nouveaux |
| 0029 | **Le prix ferme relit l'instantané du ticket** — jamais le catalogue du jour |
| 0030 | **Le masquage filtre et signale, il ne bloque pas** — un refus pousse à contourner sans laisser de trace |
| 0031 | **Le masquage épargne montants et références** — un filtre qui masque les prix pousse les gens dehors |
| 0032 | **Le webhook est la seule autorité sur un paiement** — le retour du client n'écrit rien |
| 0033 | **Un écart de montant est tracé, pas refusé** — l'argent est déjà parti de chez le client |
| 0034 | **Orange Money est écrit mais jamais branché sans ses clés** — chaque champ à confirmer est marqué |
| 0035 | **La Policy garde la porte, le domaine garde la règle** — 404 pour l'accès, 422 pour le métier |
| 0036 | **Les pièces d'identité passent par une route signée** — aucune adresse publique vers une carte d'identité |
| 0037 | **L'itinéraire passe par l'application de cartographie du téléphone** — elle guide mieux, connaît le trafic, et ne demande aucune clé |
| 0038 | **Le suivi GPS est lié à l'intervention, jamais à un interrupteur** — personne n'est suivi sans raison |
| 0039 | **Le SDK Android s'installe par le binaire `android`, jamais par AGP** — le sdkmanager déprécié plante et laisse une plateforme corrompue |
| 0040 | **Le support réassigne tant que le technicien n'est pas sur place** — jamais resollicité, prix ramené à l'estimation |
| 0041 | **Trancher un litige règle le séquestre, au prorata du remboursement** — plus aucun ticket gelé pour toujours |
| 0042 | **Place de marché : le client choisit parmi les techniciens disponibles** — remplace le push séquentiel (0026/0027/0028 ajustés) |
| 0043 | **Le prix ferme est le tarif propre du technicien choisi** — le catalogue n'est qu'un défaut ; remplace l'ADR-0029 |
| 0044 | **Garantie liée à la plateforme** — un retour sous garantie est un ticket gratuit rattaché à l'origine, fenêtre figée à la clôture ; c'est aussi le premier rempart anti-contournement |
| 0045 | **Le contournement se trace, il ne se bloque pas** — signalement « hors plateforme » au journal `securite`, dans la lignée de l'ADR-0030 |
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
