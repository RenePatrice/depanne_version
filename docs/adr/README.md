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

---

## ADR-0024 — Le déplacement se mesure depuis le centre de la zone
**5 septembre 2026 · remplacée par l'ADR-0026 le jour même**

Le §8.2 fait entrer une `distance_km` dans les frais de déplacement, sans dire
d'où elle part. Or au moment où le client voit son prix, **aucun technicien n'est
assigné** : le matching n'a pas encore tourné. Il n'existe donc pas de position
de départ réelle à cet instant, et le prix doit pourtant être ferme (ADR-0013).

Trois lectures étaient possibles : mesurer depuis le technicien (impossible,
il n'existe pas encore), mesurer depuis un dépôt fictif (arbitraire), ou mesurer
depuis la zone elle-même.

**Décision.** La distance part du **centroïde de la zone** qui contient
l'adresse, calculé par PostGIS et mis en cache jusqu'à la prochaine
modification de l'emprise.

**Conséquence.** Deux clients de la même rue paient exactement le même
déplacement, ce qui est défendable au téléphone. Le prix croît vers les bords de
la zone, là où le technicien roule effectivement plus loin. En revanche il ne
reflète pas le trajet réellement parcouru par le technicien retenu : c'est un
tarif, pas un compteur. Si le pilote montre un écart systématique entre les
frais encaissés et la peine réelle des techniciens, la variable d'ajustement est
le découpage des zones, pas la formule.

---

## ADR-0025 — Les classes d'état lisent la table de l'énumération
**5 septembre 2026 · acceptée**

Le §8.1 impose `spatie/laravel-model-states` : une classe par statut, des
transitions déclarées, une exception sur toute transition illégale. La phase A1
avait livré la même table de transitions sous forme d'énumération, faute de
pouvoir mettre de la logique métier dans A1 (ADR-0014).

Recopier la table dans les classes d'état aurait créé deux sources de vérité.
Elles auraient divergé, et la divergence se serait payée en tickets bloqués dans
un état sans issue.

**Décision.** `TicketStatus::config()` **lit** `TicketState::transitionsPossibles()`
pour déclarer les transitions au paquet. L'énumération reste la source unique et
garde les libellés, les couleurs et les listes d'états ; les classes d'état
délèguent tout cela et n'apportent que la garde du paquet. Le nom de chaque
classe est la valeur déjà stockée en base : le basculement n'a demandé aucune
migration, comme annoncé en A1.

**Conséquence.** Un cast maison, `TicketStatusCaster`, accepte indifféremment
l'énumération, la classe d'état ou la valeur brute — sans lui, écrire
`$ticket->state = TicketState::PAYEE` échouait sur une erreur de type opaque, à
l'exécution seulement. Les trois écritures donnent désormais le même résultat.


---

## ADR-0026 — Le déplacement se facture au technicien réel, à partir de 3 km
**5 septembre 2026 · acceptée · remplace l'ADR-0024**

Précision du client sur le §8.2 : la distance qui compte est celle **entre le
client et le technicien**, elle n'est monétisée qu'au-delà de 3 km, le
technicien ne doit avoir aucun moyen de saisir ce montant, et sous 3 km la
prestation est majorée de 1 % qui lui revient.

Cette règle a une conséquence que le §8.2 ne mentionne pas : le prix **ne peut
pas être ferme à la publication**, puisque aucun technicien n'est encore
assigné. L'ADR-0024, qui mesurait depuis le centroïde pour obtenir un prix ferme
avant publication, ne tient plus.

**Décision.**

1. Deux calculs, une seule formule. `estimation()` part du centroïde de la zone
   et se déclare **provisoire** ; `pourTechnicien()` part de la position réelle
   du technicien et est **ferme**. Le devis porte un drapeau `ferme` que
   l'application mobile doit refléter — « à partir de », puis « total ».
2. Le verrouillage se déplace de la publication à l'**acceptation**. C'est là
   que le montant définitif est figé sur le ticket.
3. Sous le seuil, le déplacement vaut zéro et la prestation est majorée du taux
   de proximité. La majoration est **retirée de l'assiette de commission** puis
   rendue au technicien : elle lui revient en entier, puisqu'elle remplace un
   déplacement qu'on ne lui facture pas.
4. Le seuil est le `included_km` de la zone, à 3 km pour les trois zones du
   pilote. Le forfait de zone passe à **zéro** sans disparaître : un forfait non
   nul recréerait une marche au passage du seuil — 15 000 GNF de plus pour
   200 mètres — mais le levier reste en back-office.
5. `short_trip_uplift_gnf` est figée sur le ticket plutôt que redérivée. Le taux
   et le seuil sont pilotables ; rejouer le calcul dans six mois, avec les
   réglages de six mois plus tard, donnerait un autre chiffre que celui facturé.

**Conséquence.** Le client ne connaît plus son total exact au moment où il
confirme : il voit le prix de la prestation et une estimation du déplacement.
C'est le prix à payer pour une règle qui dépend du technicien. L'écran mobile
devra le dire sans ambiguïté, et notifier le montant ferme à l'acceptation —
sans quoi la première facture surprise deviendra le premier litige.

Le barème reste continu au passage du seuil : 85 850 GNF à 2,9 km, 86 000 GNF à
3,1 km. C'est ce que garantit le forfait de zone à zéro, et un test le vérifie.


---

## ADR-0027 — L'attribution est protégée trois fois plutôt qu'une
**5 septembre 2026 · acceptée · précise l'ADR-0008**

Deux techniciens attribués au même ticket, ce sont deux personnes qui roulent
vers la même adresse et une seule qui sera payée. L'ADR-0008 prévoyait un
verrou ; à l'écriture, le verrou seul s'est révélé insuffisant à raisonner
dessus — il expire, et un worker tué le laisse tomber.

**Décision.** Trois protections superposées, du plus commode au plus sûr :

1. Un **verrou** par ticket, court (10 s) et pris pendant l'acceptation
   seulement — pas pendant les 45 secondes de la fenêtre, qu'aucun verrou ne
   devrait avoir à couvrir.
2. Une **mise à jour conditionnelle** `WHERE state = 'PUBLIEE' AND
   technician_id IS NULL`. Zéro ligne touchée signifie que quelqu'un a été plus
   rapide. C'est la garantie réelle : elle est atomique en SQL et ne dépend
   d'aucun verrou.
3. La **machine à états**, qui refuserait de toute façon une seconde transition
   vers ACCEPTEE.

**Conséquence.** Le second technicien reçoit un 409 avec un message explicite
plutôt qu'une erreur de contrainte. Le verrou reste utile — il évite de faire
travailler deux transactions pour rien — mais aucune correction ne doit plus
partir du principe qu'il suffit.

---

## ADR-0028 — Un technicien sollicité ne l'est jamais deux fois
**5 septembre 2026 · acceptée**

Le §8.3 élargit le rayon de 5 à 15 km sur trois cycles. La table
`match_attempts` porte une clé unique sur *(ticket, technicien, cycle)*, ce qui
autorisait à re-solliciter au cycle suivant quelqu'un qui n'avait pas répondu.

**Décision.** L'exclusion porte sur le **ticket entier**, quelle qu'ait été la
réponse. Élargir le rayon sert à trouver des gens nouveaux, pas à insister
auprès des mêmes.

**Conséquence.** Un technicien momentanément indisponible ne sera pas
re-sollicité même s'il redevient libre pendant la recherche. C'est assumé : le
client attend, et brûler 45 secondes de plus sur quelqu'un qui a déjà ignoré la
demande, c'est du temps pris sur lui. La clé unique par cycle reste en place
comme filet.

---

## ADR-0029 — Le prix ferme relit l'instantané du ticket, jamais le catalogue
**5 septembre 2026 · acceptée · précise les ADR-0013 et 0026**

À l'acceptation, `pourTechnicien()` recompose le prix. Sa première écriture
prenait un `Service` et lisait `base_price_gnf` — c'est-à-dire le tarif **du
jour**, pas celui promis au client. Un prix modifié en back-office entre la
publication et l'acceptation se serait répercuté sur une demande déjà engagée.
Un test l'a révélé.

**Décision.** `pourTechnicien()` prend le prix de la prestation **en entier**,
et l'appelant lui passe `$ticket->base_price_gnf`. Elle ne reçoit délibérément
pas de `Service`, contrairement à `estimation()`, qui a bien vocation à lire le
catalogue du jour.

**Conséquence.** L'asymétrie des deux signatures documente la règle : ce qui
est estimé lit le catalogue, ce qui est ferme lit l'instantané. Seul le
déplacement change entre les deux calculs, et c'est bien le seul terme qui
dépende du technicien.


---

## ADR-0030 — Le masquage filtre et signale, il ne bloque pas
**5 septembre 2026 · acceptée**

Le §7.3 impose de masquer les coordonnées échangées dans le chat, et le §11
qualifie le contournement de risque business n°1. Restait à décider ce qu'on
fait d'un message qui en contient : le refuser, ou le laisser passer masqué.

**Décision.** Le message **part quand même**, masqué, et son auteur reçoit un
avertissement qui explique *pourquoi* — la garantie, le support, l'historique.
Le message est marqué `is_flagged` avec sa raison, et le texte d'origine est
conservé pour le support seul.

**Conséquence.** Refuser aurait poussé à recommencer autrement — en épelant,
en photographiant un papier — jusqu'à trouver la faille, et sans laisser de
trace. Laisser passer masqué rend la tentative visible : une paire
client/technicien qui revient dans la liste des messages signalés est un signal
exploitable, ce qu'un refus silencieux n'aurait jamais produit.

Le filtre n'est **pas étanche** et ne prétend pas l'être : un numéro en soussou,
à l'envers, ou dans une photo passera. Le but est de rendre le contournement
assez pénible pour ne pas être le réflexe. Toute évolution doit être jugée sur
ce critère, pas sur un taux de détection.

---

## ADR-0031 — Le masquage épargne les montants et les références
**5 septembre 2026 · acceptée**

Un numéro guinéen fait neuf chiffres. Mais à Conakry un montant courant —
100 000 GNF — en fait six, un million en fait sept, et une référence
`DM-2026-000123` en porte dix.

Un filtre qui masque les prix rendrait le chat inutilisable **dans les
conversations qui portent précisément sur le prix**, et pousserait les deux
parties dehors : exactement l'inverse du but.

**Décision.** Le seuil est à huit chiffres — assez bas pour attraper un numéro
amputé d'un chiffre, assez haut pour laisser passer les montants. En plus, une
suite suivie d'une mention monétaire (`GNF`, `fg`, `francs`) ou précédée d'un
préfixe de référence (`DM`, `LIT`, `ticket`) est explicitement épargnée.

**Conséquence.** Un numéro écrit sans aucun contexte et long de sept chiffres
passerait. C'est le prix d'un chat utilisable, et la moitié « ce qu'il ne faut
pas masquer » de la suite de tests est aussi fournie que l'autre — elle protège
contre la tentation de durcir le filtre sans mesurer ce qu'on casse.


---

## ADR-0032 — Le webhook est la seule autorité sur un paiement
**5 septembre 2026 · acceptée**

Un encaissement Mobile Money a deux témoins possibles : le navigateur du client
qui revient de la page de l'opérateur, et la notification serveur à serveur.

**Décision.** Seule la **notification** décide. La route de retour du client
n'écrit rien : elle affiche un message et renvoie vers le suivi. Le ticket ne
passe en PAYEE que sur webhook signé.

**Conséquence.** Si la route de retour tranchait, il suffirait de l'ouvrir à la
main pour se déclarer payé. Le prix à payer est une attente : la notification
peut arriver après le retour du client dans l'application, d'où la route de
suivi que le mobile interroge — l'ordre des deux n'est jamais garanti.

Quatre protections gardent le webhook, dans cet ordre : signature vérifiée
avant toute lecture du corps, référence qui doit **exister** en base (une
référence inconnue est ignorée, jamais créée), verrou par référence, et état du
paiement revérifié dans la transaction. La réponse reste un 200 même sur un
rejeu : répondre en erreur ferait rejouer l'opérateur indéfiniment sur un cas
qui ne se résoudra jamais.

---

## ADR-0033 — Un écart de montant est tracé, pas refusé
**5 septembre 2026 · acceptée**

L'opérateur peut notifier un montant différent de celui attendu — frais
prélevés, arrondi, ou anomalie réelle.

**Décision.** La capture se fait quand même, et l'écart est journalisé dans
`activity_log` sous le nom `finances`, avec les deux montants.

**Conséquence.** L'argent est déjà parti de chez le client : refuser la
notification le laisserait débité sans intervention payée, ce qui est pire que
l'écart. Le rapprochement devient un travail de support, ce qui est sa place —
mais il faut donc que quelqu'un regarde ce journal. C'est un indicateur à mettre
au tableau de bord si le pilote en produit.

---

## ADR-0034 — Orange Money est écrit mais jamais branché tant que les clés manquent
**5 septembre 2026 · acceptée**

Aucun compte marchand n'est ouvert. Deux tentations : ne rien écrire et
découvrir l'intégration au dernier moment, ou écrire du code fictif qui donne
l'illusion d'être prêt.

**Décision.** `OrangeMoneyProvider` est écrit — jeton OAuth2 mis en cache,
initiation, vérification de signature à temps constant, lecture de la
notification — et porte en tête un avertissement disant qu'**il n'a jamais parlé
à l'API réelle**. Chaque champ à confirmer contre la documentation Orange est
marqué `À CONFIRMER` à l'endroit exact où il est utilisé. Quatre points sont
listés nommément : noms de champs de la réponse, algorithme de signature, unité
du montant, comportement en cas de rejeu.

Le conteneur ne le lie que si `PAYMENT_PROVIDER=orange_money` **et** que les
quatre clés sont renseignées. En local, `MockPaymentProvider` prend la main.

**Conséquence.** Le parcours complet — paiement, séquestre, libération,
portefeuille, retrait — est jouable et testé dès maintenant, sans compte
marchand. Le pilote simulé **signe ses notifications de la même façon** que le
fera l'opérateur, si bien que le code de vérification est exercé en local et ne
sera pas découvert le jour du branchement.

Ce qui reste devant nous est nommé plutôt que caché : le jour où les clés
arrivent, il faudra relire ce fichier ligne à ligne contre la documentation, et
seule cette classe bougera.


---

## ADR-0035 — La Policy garde la porte, le domaine garde la règle
**5 septembre 2026 · acceptée**

Le §10 demande des Policies Laravel. Restait à décider ce qu'on y met : la
règle d'accès seule, ou aussi les règles métier — le chat n'est ouvert
qu'entre l'acceptation et la clôture, on ne note qu'une intervention terminée.

**Décision.** La Policy répond à « as-tu le droit d'essayer ? », l'action du
domaine à « ton essai a-t-il un sens ? ». Les gardes métier restent donc dans
les actions.

La raison est simple : le back-office n'appelle pas les Policies de l'API. Une
règle métier logée dans une Policy deviendrait invisible depuis les actions
support, et un administrateur pourrait faire par le back-office ce que l'API
interdit.

**Conséquence.** Les refus de Policy sont rendus en `404` — `denyAsNotFound()`
— et non en `403` : confirmer l'existence d'un ticket à qui devine sa référence
en dirait déjà trop. Les refus métier restent en `422`, avec un message lisible,
parce qu'ils s'adressent à quelqu'un qui est bien partie prenante et qui s'est
simplement trompé de bouton.

La règle « être partie au ticket » était recopiée à **six endroits** — trois
contrôleurs, trois actions. Elle vit désormais dans
`Ticket::estPartiePrenante()`, appelée aussi bien par la Policy que par le
domaine. Deux versions d'une règle d'accès finissent par diverger, et la
divergence donne accès aux données de quelqu'un d'autre.

---

## ADR-0036 — Les pièces d'identité passent par une route signée, jamais par une URL publique
**5 septembre 2026 · acceptée**

Le §10 impose des URL signées à durée limitée pour les pièces justificatives.
La phase B4 s'appuyait sur `temporaryUrl()` du disque — ce qui ne fonctionne
qu'avec un stockage objet, et rendait la file de validation **indémontrable en
local**, où l'interface affichait un encart d'indisponibilité.

**Décision.** Une route signée (`documents.piece`) diffuse le fichier depuis
l'application. Trois gardes se cumulent : la signature valable quinze minutes,
le guard `admin`, et la permission `techniciens.voir`. Quand le disque sait
signer lui-même, on lui laisse la main — le fichier ne transite alors pas par
l'application.

**Conséquence.** Aucune adresse publique ne pointe vers une carte d'identité, et
le disque de stockage peut rester entièrement privé. La réponse porte
`Cache-Control: no-store` : une pièce d'identité n'a rien à faire dans le cache
d'un navigateur partagé. Et la validation de dossier se démontre en local, ce
qui n'était pas le cas.

---

## ADR-0037 — L'itinéraire passe par l'application de cartographie du téléphone
**8 septembre 2026 · acceptée**

Le technicien doit se rendre chez le client. La réponse réflexe est une carte
embarquée dans l'application — `google_maps_flutter`, un marqueur, un tracé.
Elle coûte une clé Google Maps, une facturation active, et le poids du SDK dans
le binaire.

**Décision.** L'écran d'intervention ouvre le schéma `geo:` et laisse
l'application de cartographie installée prendre la main. Aucune clé n'est
requise. Le **point de repère écrit** — « portail vert, en face de la
pharmacie » — est affiché en grand, au-dessus de tout le reste.

**Conséquence.** L'application guide mieux qu'elle ne le ferait elle-même :
Google Maps ou Waze connaissent le trafic, savent recalculer, et fonctionnent
hors ligne si leurs cartes sont téléchargées. Le manifeste déclare la requête
`geo:` — sans elle, Android 11 et suivants répondent « aucune application »
même quand Maps est installé. Si rien ne peut ouvrir le lien, l'écran le dit :
un bouton silencieux coûte plus cher en confiance qu'un message
d'indisponibilité.

À Conakry, une coordonnée à dix mètres près n'amène personne devant la bonne
porte ; c'est le repère écrit qui le fait. La carte embarquée reste possible le
jour où une clé existe, mais elle ne remplacera pas le repère.

---

## ADR-0038 — Le suivi GPS est lié à l'intervention, jamais à un interrupteur
**8 septembre 2026 · acceptée**

La position du technicien alimente le matching (§8.3) et la carte live du
back-office (§10). Il faut donc la remonter — mais un GPS actif vide une
batterie en quelques heures et collecte une donnée personnelle.

**Décision.** Le flux démarre quand une intervention active apparaît et
s'arrête avec elle. Deux cadences : huit secondes pendant une intervention, où
le client regarde le point avancer ; deux minutes en simple disponibilité, où
seule la zone compte. La permission est demandée au moment où elle sert, pas au
premier lancement.

**Conséquence.** Personne n'est suivi sans raison. Un interrupteur laisserait un
technicien pisté toute la nuit pour avoir oublié de le couper — et le reproche
serait justifié. Demander la permission au lancement, avant d'avoir montré à
quoi elle sert, produit un refus définitif difficile à rattraper.

Corollaire : **un échec d'envoi ne remonte jamais à l'écran**. Le GPS met
parfois trente secondes à accrocher, le réseau coupe. Une position perdue est un
désagrément ; une exception au milieu d'un écran d'intervention est une panne.
Deux envois ne se chevauchent jamais — sur un réseau lent, la file grossirait
plus vite qu'elle ne se vide et le serveur recevrait des positions périmées dans
le désordre.

---

## ADR-0039 — Le SDK Android est installé par le binaire `android`, jamais par AGP
**8 septembre 2026 · acceptée**

Sur cette machine, le `sdkmanager` livré avec les *command-line tools* est
déprécié et **plante en sortie** (NTSTATUS 0xC0000409) : l'installation
aboutit, le processus meurt après. AGP l'appelle pour installer ce qui manque,
prend le plantage pour un échec, et fait échouer le build sur une erreur qui ne
nomme pas la pièce absente. Il installe au passage une plateforme corrompue —
`platforms/android-37.0` avec un `ApiLevel` invalide.

**Décision.** Toutes les pièces du SDK sont installées explicitement, par le
binaire `android`, avant le premier build :

```
android sdk install platforms/android-36 build-tools/36.1.0 ndk/28.2.13676358
```

Le NDK y figure bien qu'aucune ligne de C ne soit écrite : le plugin Gradle de
Flutter impose sa version.

**Conséquence.** `flutter_secure_storage` **11.0.0 compile contre l'API 37**,
qui n'existe au dépôt Google que sous des noms mineurs — `android-37.0`,
`37.1`… — qu'AGP ne sait pas résoudre depuis `compileSdk = 37`. La dépendance
est fixée à `^10.3.1`, qui compile contre 36 ; l'API Dart utilisée est
identique. À revoir quand AGP saura résoudre les versions mineures.

---

## ADR-0040 — Le support peut réassigner un ticket tant que le technicien n'est pas sur place
**4 novembre 2026 · acceptée**

Un technicien accepte puis ne vient pas : injoignable, panne de moto. Jusqu'ici
le support n'avait qu'une issue, l'annulation, qui renvoie le client à zéro et
l'oblige à republier la même panne.

**Décision.** Deux transitions nouvelles, `ACCEPTEE → PUBLIEE` et
`EN_ROUTE → PUBLIEE`, empruntées par la seule action `ReassignTicket`. Le
technicien est retiré du ticket et le matching reprend **sans lui** : il a déjà
été sollicité, il ne le sera pas une seconde fois (ADR-0028). Au-delà de
SUR_PLACE, l'intervention a commencé chez le client : c'est une annulation ou un
litige, pas un remplacement.

Le prix ferme dépendait de la position du technicien retiré. Il redevient une
estimation depuis le point de référence de la zone, sur le prix de prestation de
l'instantané (ADR-0029) ; le suivant le refixera en acceptant.

**Conséquence.** C'est la seule exception à « un jalon horodaté n'est jamais
réécrit » : `accepted_at` et `en_route_at` sont effacés, sans quoi
l'application annoncerait un prix ferme et un technicien en route qui
n'existent plus. Les dates d'origine sont conservées dans les métadonnées de
l'événement de transition.

L'imputation est une décision explicite du support, comme pour l'annulation. À
ses torts, la sollicitation passe en `DESISTE` et compte dans le taux
d'annulation — sans quoi ne pas venir coûterait moins cher qu'annuler. Elle
reste comptée comme une acceptation : le technicien n'est pas puni deux fois.

---

## ADR-0041 — Trancher un litige règle le séquestre, au prorata du remboursement
**4 novembre 2026 · acceptée**

Les litiges (B5) ont été écrits avant le séquestre (C5) et n'avaient jamais été
raccordés. Un litige tranché laissait le ticket en LITIGE_OUVERT et l'argent
capturé **pour toujours** : le job de libération était passé pendant le gel et
ne repassait pas. Un remboursement reprenait en outre au technicien une somme
qu'il n'avait jamais reçue, et son solde devenait négatif.

**Décision.** `ResolveDispute` règle l'argent dans la même transaction que la
décision, résolution comme rejet. Le remboursement sort **avant** la
répartition (décision du client, 4 novembre 2026) : la commission ne porte que
sur ce qui reste facturé, et un remboursement total laisse zéro des deux côtés.

| Où en était l'argent | Ce que fait la décision |
|---|---|
| en séquestre | libération de `total − remboursement`, commission sur ce reste ; paiement `LIBEREE`, ou `REMBOURSEE` si tout est rendu ; ticket CLOTUREE |
| déjà libéré | le technicien rend le remboursement, la plateforme lui restitue la commission prélevée sur la part remboursée ; même résultat qu'en séquestre |
| jamais encaissé | aucun remboursement possible ; ticket ramené en TERMINEE (transition nouvelle) pour que le client paie |

Le taux appliqué est celui **figé sur le ticket**, jamais le taux du jour. La
majoration de proximité reste hors assiette.

**Conséquence.** Une intervention déjà arbitrée ne peut plus être remise en
litige par l'application : rouvrir gèlerait les fonds de nouveau, sans fin.
« Forcer la clôture » libère désormais le séquestre qu'elle trouve, et refuse un
ticket en litige — fermer en marge laisserait la réclamation ouverte et l'argent
gelé.

Le client est crédité au grand livre ; **le reversement sur son compte Mobile
Money reste manuel**, `PaymentProvider` n'exposant pas de remboursement. L'écran
le dit.
