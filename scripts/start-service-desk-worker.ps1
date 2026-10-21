param(
    [string] $PhpPath = 'C:\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$artisanPath = Join-Path $projectDirectory 'artisan'
$phpExecutable = (Get-Command -Name $PhpPath -ErrorAction Stop).Source
$existingWorker = Get-CimInstance Win32_Process -Filter "name = 'php.exe'" |
    Where-Object {
        $_.CommandLine -like "*$artisanPath*" -and
        $_.CommandLine -match 'queue:work' -and
        $_.CommandLine -match '--queue=service-desk-notifications(?:\s|$)'
    } | Select-Object -First 1

if ($existingWorker) {
    Write-Output "Service Desk notification worker is running (PID $($existingWorker.ProcessId))."
    exit 0
}

$worker = Start-Process -FilePath $phpExecutable -ArgumentList @(
    "`"$artisanPath`"", 'queue:work', '--queue=service-desk-notifications',
    '--sleep=3', '--tries=3', '--timeout=90', '--backoff=30'
) -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $projectDirectory 'storage\logs\service-desk-worker.log') `
    -RedirectStandardError (Join-Path $projectDirectory 'storage\logs\service-desk-worker-error.log')

Start-Sleep -Seconds 1
if ($worker.HasExited) {
    throw 'Service Desk worker exited. Check storage/logs/service-desk-worker-error.log.'
}

Write-Output "Service Desk notification worker started (PID $($worker.Id))."
