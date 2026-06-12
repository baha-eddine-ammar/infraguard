@echo off
setlocal

cd /d "%~dp0"

set "INFRA_API_URL=http://pfe.test/api/server-metrics"

echo Starting InfraGuard PC Metrics Collector - LOCAL
echo URL: %INFRA_API_URL%
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0pc-metrics-collector.ps1" -ApiUrl "%INFRA_API_URL%"

echo.
echo Collector stopped.
pause
