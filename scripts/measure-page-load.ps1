param(
    [string] $PhpPath = 'C:\php\php.exe',
    [ValidateRange(3, 20)]
    [int] $Rounds = 3,
    [string] $OutputDirectory = 'output/performance-review-20261020',
    [switch] $AllPages,
    [string[]] $Paths = @(
        '/panel', '/panel/service-desk', '/panel/service-desk/create', '/panel/work-tasks',
        '/panel/it-devices', '/panel/it-infrastructure-reports', '/panel/users', '/panel/users/create',
        '/panel/attendance-report-center', '/panel/attendance-results', '/panel/activity-reports',
        '/panel/daily-activities', '/panel/meeting-room-calendar', '/panel/meeting-bookings',
        '/panel/vehicle-bookings', '/panel/reminders', '/panel/it-inspections',
        '/panel/atk-dashboard', '/panel/ltro-daily-reports', '/panel/reports/service-desk',
        '/panel/reports/work-logs', '/panel/reports/workload', '/panel/reports/sla-ticket-aging'
    )
)

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path

function Get-Median($Values) {
    $ordered = @($Values | Sort-Object)
    $middle = [int] [math]::Floor($ordered.Count / 2)
    if ($ordered.Count % 2) { return $ordered[$middle] }
    return [math]::Round(($ordered[$middle - 1] + $ordered[$middle]) / 2, 2)
}

Push-Location $projectDirectory
try {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
    if ($AllPages) {
        $Paths = Get-Content (Join-Path $PSScriptRoot 'profile-pages.json') -Raw | ConvertFrom-Json
    }
    $responseRules = Get-Content (Join-Path $PSScriptRoot 'profile-expected-statuses.json') -Raw | ConvertFrom-Json
    $samples = @()
    foreach ($round in 0..$Rounds) {
        foreach ($pagePath in $Paths) {
            $raw = & $PhpPath (Join-Path $PSScriptRoot 'profile-page.php') "--path=$pagePath" --details
            if ($LASTEXITCODE -ne 0) { throw "Profiler failed for $pagePath in round $round." }
            $sample = $raw | ConvertFrom-Json
            if ($null -eq $sample.total_ms) { throw "Profiler returned no timing for $pagePath." }
            $sample | Add-Member -NotePropertyName round -NotePropertyValue $round
            $sample | Add-Member -NotePropertyName warmup -NotePropertyValue ($round -eq 0)
            $responseRule = $responseRules.PSObject.Properties[$pagePath]
            $expectedStatus = if ($null -eq $responseRule) { 200 } else { $responseRule.Value.status }
            $sample | Add-Member -NotePropertyName expected_status -NotePropertyValue $expectedStatus
            $sample | Add-Member -NotePropertyName expected_status_passed -NotePropertyValue ($sample.status -eq $expectedStatus)
            $samples += $sample
            $samples | ConvertTo-Json -Depth 12 | Set-Content -Encoding utf8 (Join-Path $OutputDirectory 'continued-samples.json')
            Write-Host "$round $pagePath status=$($sample.status) total=$($sample.total_ms)ms sql=$($sample.sql_ms)ms"
        }
    }

    $summary = @($samples | Where-Object { -not $_.warmup } | Group-Object path | ForEach-Object {
        $group = $_.Group
        [pscustomobject]@{
            path = $_.Name
            samples = $group.Count
            statuses = @($group.status | Sort-Object -Unique)
            expected_status = $group[0].expected_status
            expected_status_passed = @($group | Where-Object { -not $_.expected_status_passed }).Count -eq 0
            median_total_ms = Get-Median $group.total_ms
            min_total_ms = ($group.total_ms | Measure-Object -Minimum).Minimum
            max_total_ms = ($group.total_ms | Measure-Object -Maximum).Maximum
            median_bootstrap_ms = Get-Median $group.bootstrap_ms
            median_render_ms = Get-Median $group.render_ms
            median_sql_ms = Get-Median $group.sql_ms
            max_queries = ($group.queries | Measure-Object -Maximum).Maximum
            html_bytes = $group[-1].html_bytes
            payload = $group[-1].payload
        }
    })
    [pscustomobject]@{
        measured_at = [DateTimeOffset]::Now.ToString('o')
        method = 'Local PHP CLI kernel GET; one excluded warmup per path; sequential samples; array session/cache; read-only database transaction. Excludes browser and network timing.'
        rounds = $Rounds
        pages = $summary
    } | ConvertTo-Json -Depth 12 | Set-Content -Encoding utf8 (Join-Path $OutputDirectory 'continued-summary.json')
    $summary | Sort-Object median_total_ms -Descending | Format-Table path, median_total_ms, median_sql_ms, max_queries
    if ($samples | Where-Object { -not $_.expected_status_passed }) { throw 'At least one page returned an unexpected status; inspect continued-samples.json.' }
} finally {
    Pop-Location
}
