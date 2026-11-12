<?php

declare(strict_types=1);

/*
 * Point d'entrée de la fonction Vercel. Vercel n'exécute que ce qui se trouve
 * dans `api/` : on y rend simplement la main au contrôleur frontal de Laravel.
 * Voir vercel.json et docs/deploiement-vercel-supabase.md.
 *
 * Le script s'appelle ici `/api/index.php`. Laissé tel quel, Symfony en déduit
 * que l'application est installée sous `/api` et retire ce préfixe des
 * adresses : `/api/v1/catalogue` devenait `/v1/catalogue`, et toute l'API
 * mobile répondait 404. On présente donc le script comme s'il était à la
 * racine, ce qu'il est du point de vue des visiteurs.
 */
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/../public/index.php';
