# InfraGuard PC Metrics Collector

This Windows collector sends your real PC CPU, RAM, disk, and network metrics to InfraGuard every 5 seconds.

## Runners

Use one of these BAT files:

```text
start-local-pc-metrics-collector.bat
start-production-pc-metrics-collector.bat
```

The local runner sends to:

```text
http://pfe.test/api/server-metrics
```

The production runner sends to:

```text
https://infraguard.swissnexo.ch/api/server-metrics
```

The old `start-pc-metrics-collector.bat` still works and simply starts the local runner.

## Token Setup

Use the same token configured as `PC_METRICS_TOKEN` on the Laravel site you are sending to.

Do not commit a real token. The safest local setup is:

1. Copy `pc-metrics-token.local.example.txt`.
2. Rename the copy to `pc-metrics-token.local.txt`.
3. Replace the placeholder text with your real token.

`pc-metrics-token.local.txt` is ignored by git.

The collector looks for the token in this order:

1. `-Token` PowerShell parameter
2. Windows environment variable `PC_METRICS_TOKEN`
3. `pc-metrics-token.local.txt`
4. Laravel `.env` file in this project
5. Console prompt when no token is found

## Run Locally

Double-click:

```text
start-local-pc-metrics-collector.bat
```

Keep your local Laravel app reachable at:

```text
http://pfe.test
```

## Run In Production

Before double-clicking the production runner, make sure `pc-metrics-token.local.txt` contains the same token as the deployed website's `PC_METRICS_TOKEN`.

Then double-click:

```text
start-production-pc-metrics-collector.bat
```

The console will show the production URL and messages like:

```text
metrics sent successfully
```

## Stop

Click the collector console window and press:

```text
Ctrl+C
```

Then close the window if it stays open.

## Payload

The collector sends this JSON shape:

```json
{
  "cpu_percent": 35.5,
  "ram_used_mb": 8192,
  "ram_total_mb": 16384,
  "disk_used_gb": 240.3,
  "disk_total_gb": 512,
  "network_rx": 3.2,
  "network_tx": 1.1
}
```

Laravel stores each received sample in `server_metrics`, and `/servers/live-state` returns the newest stored row for the live card.
