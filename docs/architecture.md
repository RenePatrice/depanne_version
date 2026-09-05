# Architecture

## Vue d'ensemble

Une seule application Laravel sert **à la fois** le back-office web et l'API
mobile : un seul ORM, un seul jeu de migrations, une seule logique métier, un
seul déploiement.

```mermaid
flowchart TB
    subgraph clients[Clients]
        FL["Flutter — Client &amp; Technicien<br/>Android + iOS"]
        NAV["Navigateur — Back-office<br/>Blade + Bootstrap 5 + DataTables<br/>+ Highcharts + îlots React"]
    end

    subgraph app["Application Laravel 13 — PHP 8.4"]
        API["routes/api.php<br/>Sanctum, JSON"]
        WEB["routes/web.php<br/>guard admin"]
        DOM["app/Domain/<br/>Accounts · Catalog · Zones · Tickets<br/>Matching · Pricing · Payments · Wallet<br/>Chat · Disputes · Notifications · Settings"]
        API --> DOM
        WEB --> DOM
    end

    subgraph infra[Infrastructure]
        PG[("PostgreSQL 17 + PostGIS<br/>local, puis Supabase")]
        Q["File d'attente et verrous<br/>database en local, Redis en production"]
        RV["Reverb — WebSocket"]
    end

    subgraph ext[Fournisseurs externes]
        GM["Google Maps<br/>Distance Matrix · Directions"]
        OM["Orange Money<br/>autres agrégateurs à venir"]
        FCM["Firebase Cloud Messaging<br/>simulé en local"]
    end

    FL --> API
    NAV --> WEB
    DOM --> PG
    DOM --> Q
    DOM --> RV
    RV -.->|positions, statuts, chat| FL
    RV -.->|carte live, KPI| NAV
    DOM --> GM
    DOM --> OM
    DOM --> FCM
```

## Règle structurante

La logique métier vit dans des classes d'action et de service sous
`app/Domain/<Contexte>/`. Les contrôleurs web et les contrôleurs API sont de
simples adaptateurs qui appellent **les mêmes actions**.

C'est ce qui rend cohérent l'ordre d'exécution du projet : le bloc B
(back-office) construit les actions du domaine, le bloc C (API mobile) les
réutilise telles quelles.

```mermaid
flowchart LR
    W["Controllers/Web/<br/>TicketController"] --> A["Domain/Tickets/Actions/<br/>CancelTicket"]
    M["Controllers/Api/V1/<br/>TicketController"] --> A
    A --> S["machine à états<br/>+ ticket_events<br/>+ notifications"]
```

## Cycle de vie d'un ticket

```mermaid
stateDiagram-v2
    [*] --> BROUILLON
    BROUILLON --> PUBLIEE
    PUBLIEE --> ACCEPTEE
    PUBLIEE --> SANS_REPONSE : 3 cycles épuisés
    ACCEPTEE --> EN_ROUTE
    EN_ROUTE --> SUR_PLACE
    SUR_PLACE --> DIAGNOSTIC_EN_ATTENTE
    DIAGNOSTIC_EN_ATTENTE --> DIAGNOSTIC_VALIDE
    DIAGNOSTIC_EN_ATTENTE --> DIAGNOSTIC_REFUSE
    SUR_PLACE --> EN_COURS
    DIAGNOSTIC_VALIDE --> EN_COURS
    EN_COURS --> TERMINEE
    TERMINEE --> PAYEE
    PAYEE --> CLOTUREE
    CLOTUREE --> [*]

    PUBLIEE --> ANNULEE_CLIENT
    ACCEPTEE --> ANNULEE_CLIENT
    ACCEPTEE --> ANNULEE_TECHNICIEN
    TERMINEE --> LITIGE_OUVERT : sous 72 h
    PAYEE --> LITIGE_OUVERT : sous 72 h
```

Le modèle de données détaillé et son diagramme entité-relation seront produits
en **phase A1**, dans `docs/data-model.md`.
