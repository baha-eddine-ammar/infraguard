param (
    [string] $ApiUrl = 'http://pfe.test/api/server-metrics',
    [string] $Token = '',
    [int] $IntervalSeconds = 5,
    [string] $DiskDrive = '',
    [switch] $Once
)

$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($DiskDrive)) {
    $DiskDrive = if ($env:SystemDrive) { $env:SystemDrive } else { 'C:' }
}

$LocalTokenPath = Join-Path $PSScriptRoot 'pc-metrics-token.local.txt'

function Get-ProjectEnvValue {
    param (
        [string] $Name
    )

    $envPath = Join-Path $PSScriptRoot '..\..\.env'

    if (-not (Test-Path $envPath)) {
        return ''
    }

    $line = Get-Content -Path $envPath |
        Where-Object { $_ -match "^\s*$([regex]::Escape($Name))\s*=" } |
        Select-Object -First 1

    if (-not $line) {
        return ''
    }

    $value = ($line -replace "^\s*$([regex]::Escape($Name))\s*=\s*", '').Trim()
    return $value.Trim('"').Trim("'")
}

function Resolve-Token {
    if (-not [string]::IsNullOrWhiteSpace($Token)) {
        return [pscustomobject]@{
            Value = $Token.Trim()
            Source = 'script parameter'
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($env:PC_METRICS_TOKEN)) {
        return [pscustomobject]@{
            Value = $env:PC_METRICS_TOKEN.Trim()
            Source = 'Windows PC_METRICS_TOKEN environment variable'
        }
    }

    if (Test-Path $LocalTokenPath) {
        $localToken = Get-Content -Path $LocalTokenPath |
            Where-Object {
                -not [string]::IsNullOrWhiteSpace($_) -and
                -not $_.TrimStart().StartsWith('#')
            } |
            Select-Object -First 1

        if (-not [string]::IsNullOrWhiteSpace($localToken)) {
            return [pscustomobject]@{
                Value = $localToken.Trim()
                Source = 'pc-metrics-token.local.txt'
            }
        }
    }

    $envToken = (Get-ProjectEnvValue -Name 'PC_METRICS_TOKEN').Trim()

    if (-not [string]::IsNullOrWhiteSpace($envToken)) {
        return [pscustomobject]@{
            Value = $envToken
            Source = 'Laravel .env'
        }
    }

    return [pscustomobject]@{
        Value = ''
        Source = 'not configured'
    }
}

function Convert-LinkSpeedToMbps {
    param (
        $Adapter
    )

    if ($Adapter.PSObject.Properties.Name -contains 'Speed' -and $Adapter.Speed) {
        return [int][math]::Round(([double] $Adapter.Speed) / 1000000)
    }

    $linkSpeed = [string] $Adapter.LinkSpeed

    if ($linkSpeed -match '([\d.]+)\s*Gbps') {
        return [int][math]::Round(([double] $matches[1]) * 1000)
    }

    if ($linkSpeed -match '([\d.]+)\s*Mbps') {
        return [int][math]::Round([double] $matches[1])
    }

    return $null
}

function Get-ActiveNetworkAdapters {
    $excludePattern = 'Loopback|Teredo|isatap|Pseudo|Virtual|Hyper-V|Bluetooth'

    return Get-NetAdapter -ErrorAction Stop |
        Where-Object {
            $_.Status -eq 'Up' -and
            $_.InterfaceDescription -notmatch $excludePattern -and
            $_.Name -notmatch $excludePattern
        } |
        Sort-Object -Property Speed -Descending
}

function Get-NetworkSnapshot {
    try {
        $adapters = @(Get-ActiveNetworkAdapters)

        if ($adapters.Count -eq 0) {
            return [pscustomobject]@{
                Connected = $false
                RxBytes = 0
                TxBytes = 0
                Name = $null
                SpeedMbps = $null
                IPv4 = $null
                Timestamp = Get-Date
            }
        }

        $rxBytes = [int64] 0
        $txBytes = [int64] 0

        foreach ($adapter in $adapters) {
            $stats = Get-NetAdapterStatistics -Name $adapter.Name -ErrorAction Stop
            $rxBytes += [int64] $stats.ReceivedBytes
            $txBytes += [int64] $stats.SentBytes
        }

        $primary = $adapters | Select-Object -First 1
        $ipv4 = $null

        try {
            $ipConfig = Get-NetIPConfiguration -InterfaceIndex $primary.ifIndex -ErrorAction Stop
            $ipv4 = $ipConfig.IPv4Address.IPAddress | Select-Object -First 1
        } catch {
            $ipv4 = $null
        }

        return [pscustomobject]@{
            Connected = $true
            RxBytes = $rxBytes
            TxBytes = $txBytes
            Name = $primary.Name
            SpeedMbps = Convert-LinkSpeedToMbps -Adapter $primary
            IPv4 = $ipv4
            Timestamp = Get-Date
        }
    } catch {
        return [pscustomobject]@{
            Connected = $false
            RxBytes = 0
            TxBytes = 0
            Name = $null
            SpeedMbps = $null
            IPv4 = $null
            Timestamp = Get-Date
        }
    }
}

function Get-NetworkRates {
    param (
        $PreviousSnapshot
    )

    $current = Get-NetworkSnapshot

    if (-not $PreviousSnapshot -or -not $current.Connected) {
        return [pscustomobject]@{
            Snapshot = $current
            RxMbps = 0
            TxMbps = 0
        }
    }

    $elapsed = [math]::Max(1, ($current.Timestamp - $PreviousSnapshot.Timestamp).TotalSeconds)
    $rxDiff = [math]::Max(0, [double] ($current.RxBytes - $PreviousSnapshot.RxBytes))
    $txDiff = [math]::Max(0, [double] ($current.TxBytes - $PreviousSnapshot.TxBytes))

    return [pscustomobject]@{
        Snapshot = $current
        RxMbps = [math]::Round((($rxDiff * 8) / $elapsed) / 1000000, 2)
        TxMbps = [math]::Round((($txDiff * 8) / $elapsed) / 1000000, 2)
    }
}

function Get-CpuPercent {
    $cpu = Get-CimInstance -ClassName Win32_PerfFormattedData_PerfOS_Processor -Filter "Name='_Total'"
    return [math]::Round([double] $cpu.PercentProcessorTime, 1)
}

function Get-MemoryMetrics {
    $os = Get-CimInstance -ClassName Win32_OperatingSystem
    $totalMb = [int] [math]::Round(([double] $os.TotalVisibleMemorySize) / 1024)
    $freeMb = [int] [math]::Round(([double] $os.FreePhysicalMemory) / 1024)

    return [pscustomobject]@{
        UsedMb = [math]::Max(0, $totalMb - $freeMb)
        TotalMb = $totalMb
    }
}

function Get-DiskMetrics {
    $disk = Get-CimInstance -ClassName Win32_LogicalDisk -Filter "DeviceID='$DiskDrive'"

    if (-not $disk -or -not $disk.Size) {
        throw "Could not read disk metrics for drive $DiskDrive"
    }

    $totalGb = [math]::Round(([double] $disk.Size) / 1GB, 2)
    $freeGb = [math]::Round(([double] $disk.FreeSpace) / 1GB, 2)

    return [pscustomobject]@{
        UsedGb = [math]::Round([math]::Max(0, $totalGb - $freeGb), 2)
        TotalGb = $totalGb
        FreeGb = $freeGb
    }
}

function New-MetricsPayload {
    param (
        $NetworkRates
    )

    $memory = Get-MemoryMetrics
    $disk = Get-DiskMetrics
    return [ordered]@{
        cpu_percent = Get-CpuPercent
        ram_used_mb = $memory.UsedMb
        ram_total_mb = $memory.TotalMb
        disk_used_gb = $disk.UsedGb
        disk_total_gb = $disk.TotalGb
        network_rx = $NetworkRates.RxMbps
        network_tx = $NetworkRates.TxMbps
    }
}

$ResolvedTokenInfo = Resolve-Token
$ResolvedToken = $ResolvedTokenInfo.Value

Write-Host 'InfraGuard PC Metrics Collector'
Write-Host "API URL: $ApiUrl"
Write-Host "Interval: $IntervalSeconds seconds"
Write-Host "Disk drive: $DiskDrive"

if ([string]::IsNullOrWhiteSpace($ResolvedToken)) {
    Write-Host ''
    Write-Host 'PC_METRICS_TOKEN is missing.' -ForegroundColor Red
    Write-Host 'Use one of these options:'
    Write-Host '1. Paste the token into tools\pc-metrics-collector\pc-metrics-token.local.txt'
    Write-Host '2. Set a Windows PC_METRICS_TOKEN environment variable'
    Write-Host '3. Pass -Token when running the PowerShell script'
    Write-Host ''
    $ResolvedToken = (Read-Host 'Paste PC_METRICS_TOKEN for this run').Trim()

    if ([string]::IsNullOrWhiteSpace($ResolvedToken)) {
        Write-Host 'No token entered. Collector cannot start.' -ForegroundColor Red
        Read-Host 'Press Enter to close'
        exit 1
    }

    $ResolvedTokenInfo = [pscustomobject]@{
        Value = $ResolvedToken
        Source = 'console prompt'
    }
}

Write-Host "Token: configured from $($ResolvedTokenInfo.Source)"
Write-Host 'Press Ctrl+C to stop.'
Write-Host ''

$headers = @{
    'Accept' = 'application/json'
    'X-Server-Token' = $ResolvedToken
}

$previousNetwork = Get-NetworkSnapshot

while ($true) {
    try {
        $networkRates = Get-NetworkRates -PreviousSnapshot $previousNetwork
        $previousNetwork = $networkRates.Snapshot

        $payload = New-MetricsPayload -NetworkRates $networkRates
        $json = $payload | ConvertTo-Json -Depth 4

        Invoke-RestMethod `
            -Method Post `
            -Uri $ApiUrl `
            -Headers $headers `
            -Body $json `
            -ContentType 'application/json' `
            -TimeoutSec 15 | Out-Null

        $message = '[{0}] metrics sent successfully | CPU {1}% | RAM {2}/{3} MB | Disk {4}/{5} GB | Net RX {6} Mbps / TX {7} Mbps' -f `
            (Get-Date -Format 'HH:mm:ss'),
            $payload.cpu_percent,
            $payload.ram_used_mb,
            $payload.ram_total_mb,
            $payload.disk_used_gb,
            $payload.disk_total_gb,
            $payload.network_rx,
            $payload.network_tx

        Write-Host $message -ForegroundColor Green
    } catch {
        Write-Host ("[{0}] send failed: {1}" -f (Get-Date -Format 'HH:mm:ss'), $_.Exception.Message) -ForegroundColor Yellow
        Write-Host 'The collector will retry on the next interval.'
    }

    if ($Once) {
        break
    }

    Start-Sleep -Seconds $IntervalSeconds
}
