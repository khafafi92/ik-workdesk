param(
    [string] $PhpPath = 'C:\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Push-Location $projectDirectory
try {
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw 'Asset build failed; runtime caches were not changed.' }

    $hotFile = Join-Path $projectDirectory 'public\hot'
    if (Test-Path -LiteralPath $hotFile) {
        Copy-Item -LiteralPath $hotFile -Destination (Join-Path $projectDirectory 'storage\framework\vite-hot-before-performance.txt') -Force
        Remove-Item -LiteralPath $hotFile
    }

    & $PhpPath artisan optimize
    if ($LASTEXITCODE -ne 0) { throw 'Laravel cache optimization failed.' }
    $configCache = Join-Path $projectDirectory 'bootstrap\cache\config.php'
    if (Test-Path -LiteralPath $configCache) {
        Set-Acl -LiteralPath $configCache -AclObject (Get-Acl -LiteralPath (Join-Path $projectDirectory '.env'))
    }

    & $PhpPath artisan filament:optimize
    if ($LASTEXITCODE -ne 0) { throw 'Filament cache optimization failed.' }

    Write-Output 'Built assets and Laravel/Filament caches are ready. Rebuild after deployment; clear config cache before changing environment settings.'
} finally {
    Pop-Location
}
