Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$devDir = Join-Path $projectRoot "dev"

if (-not (Test-Path $devDir)) {
    throw "dev directory not found: $devDir"
}

Push-Location $devDir
try {
    composer test
}
finally {
    Pop-Location
}

