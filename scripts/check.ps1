Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$projectRoot = Resolve-Path (Join-Path $PSScriptRoot "..")

& (Join-Path $projectRoot "scripts\lint.ps1")
& (Join-Path $projectRoot "scripts\test.ps1")
& (Join-Path $projectRoot "scripts\audit.ps1")

