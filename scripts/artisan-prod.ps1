# =============================================================================
# Lance une commande artisan contre la base de PRODUCTION (Supabase), depuis le
# conteneur Docker local. Les accès sont lus dans backend-laravel/.env.production
# (ignoré par git) : ce script ne contient aucun secret.
#
#   .\scripts\artisan-prod.ps1 admin:create
#   .\scripts\artisan-prod.ps1 migrate --database=pgsql_migrations --force
# =============================================================================
param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Commande)

$racine = Split-Path -Parent $PSScriptRoot
$fichier = Join-Path $racine 'backend-laravel\.env.production'

if (-not (Test-Path $fichier)) {
    Write-Error "Fichier introuvable : $fichier"
    exit 1
}

# Seules ces clés sont transmises : la connexion à la base et le stockage.
$cles = '^(DB_[A-Z_]+|AWS_[A-Z_]+)$'
$env = @()
foreach ($ligne in Get-Content $fichier) {
    if ($ligne -match '^\s*#' -or $ligne -notmatch '=') { continue }
    $nom, $valeur = $ligne -split '=', 2
    $nom = $nom.Trim()
    if ($nom -match $cles -and $valeur.Trim() -ne '') {
        $env += @('-e', "$nom=$($valeur.Trim())")
    }
}

# Le pooler Supabase (mode transaction) impose ces deux réglages ; le cache en
# mémoire évite d'écrire dans la table de cache pendant une commande ponctuelle.
$env += @('-e', 'DB_SSLMODE=require', '-e', 'DB_DISABLE_NATIVE_PREPARES=true', '-e', 'CACHE_STORE=array')

Push-Location $racine
try {
    docker compose exec -it @env app php artisan @Commande
} finally {
    Pop-Location
}
