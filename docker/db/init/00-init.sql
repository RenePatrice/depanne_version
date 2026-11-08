-- Initialisation du cluster PostgreSQL + PostGIS pour Parema Connect.
-- L'image postgis active déjà PostGIS sur la base par défaut (depanne_moi).
-- Ici on crée en plus la base de test et on y active PostGIS.

CREATE EXTENSION IF NOT EXISTS postgis;

SELECT 'CREATE DATABASE depanne_moi_test OWNER depanne'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'depanne_moi_test')\gexec

\connect depanne_moi_test
CREATE EXTENSION IF NOT EXISTS postgis;
