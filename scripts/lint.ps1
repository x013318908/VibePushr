Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$entrypoint = Join-Path $projectRoot "public_html\vp.php"

if (-not (Test-Path $entrypoint)) {
    throw "vp.php not found: $entrypoint"
}

php -l $entrypoint

