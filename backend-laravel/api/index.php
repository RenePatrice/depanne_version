<?php

declare(strict_types=1);

/*
 * Point d'entrée de la fonction Vercel. Vercel n'exécute que ce qui se trouve
 * dans `api/` : on y rend simplement la main au contrôleur frontal de Laravel.
 * Voir vercel.json et docs/deploiement-vercel-supabase.md.
 */
require __DIR__.'/../public/index.php';
