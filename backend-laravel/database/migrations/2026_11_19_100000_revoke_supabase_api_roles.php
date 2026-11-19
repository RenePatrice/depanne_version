<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Fermer la porte de l'API REST de Supabase
|--------------------------------------------------------------------------
|
| Supabase expose le schéma `public` par son API REST (PostgREST), au nom des
| rôles `anon` et `authenticated` — la clé `anon` est publique par nature.
| L'application ne s'en sert pas : Laravel parle à Postgres directement. Ces
| deux rôles n'ont donc rien à faire dans nos tables.
|
| `spatial_ref_sys` appartient à PostGIS : on n'y active PAS la RLS. Les
| fonctions géographiques (ST_DWithin, ST_Covers) y lisent le sphéroïde, et une
| RLS sans politique les casserait pour un rôle qui ne la contourne pas. On
| retire seulement les droits des rôles de l'API — en écriture, un anonyme
| pouvait y corrompre une projection.
|
| Sans les rôles Supabase (base locale, tests), la migration ne fait rien.
| Un droit qu'on ne peut pas retirer (objet d'un autre propriétaire) est
| signalé sans faire échouer la migration.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                r text;
                f record;
            BEGIN
                FOREACH r IN ARRAY ARRAY['anon', 'authenticated'] LOOP
                    CONTINUE WHEN NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r);

                    BEGIN
                        EXECUTE format('REVOKE ALL ON TABLE public.spatial_ref_sys FROM %I', r);
                    EXCEPTION WHEN insufficient_privilege OR undefined_table THEN
                        RAISE NOTICE 'spatial_ref_sys : droits de % non retirés (%)', r, SQLERRM;
                    END;

                    FOR f IN
                        SELECT p.oid::regprocedure AS sig
                        FROM pg_proc p
                        JOIN pg_namespace n ON n.oid = p.pronamespace
                        WHERE n.nspname = 'public'
                          AND p.proname IN ('rls_auto_enable', 'st_estimatedextent')
                    LOOP
                        BEGIN
                            EXECUTE format('REVOKE EXECUTE ON FUNCTION %s FROM %I', f.sig, r);
                        EXCEPTION WHEN insufficient_privilege THEN
                            RAISE NOTICE '% : exécution de % non retirée (%)', f.sig, r, SQLERRM;
                        END;
                    END LOOP;
                END LOOP;

                -- Ces fonctions sont aussi exécutables par PUBLIC, donc par
                -- l'API. L'application ne les appelle pas ; un déclencheur
                -- d'événement ne vérifie pas EXECUTE au déclenchement.
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
                    FOR f IN
                        SELECT p.oid::regprocedure AS sig
                        FROM pg_proc p
                        JOIN pg_namespace n ON n.oid = p.pronamespace
                        WHERE n.nspname = 'public'
                          AND p.proname IN ('rls_auto_enable', 'st_estimatedextent')
                    LOOP
                        BEGIN
                            EXECUTE format('REVOKE EXECUTE ON FUNCTION %s FROM PUBLIC', f.sig);
                        EXCEPTION WHEN insufficient_privilege THEN
                            RAISE NOTICE '% : exécution de PUBLIC non retirée (%)', f.sig, SQLERRM;
                        END;
                    END LOOP;
                END IF;
            END
            $$;
            SQL);
    }

    public function down(): void
    {
        // Rendre ces droits rouvrirait l'accès anonyme : rien à défaire.
    }
};
