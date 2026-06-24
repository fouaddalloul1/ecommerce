$ProjectPath = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectPath

Write-Host "Starting invoice / notification worker..." -ForegroundColor Green
$normal = Start-Process php -ArgumentList @(
    "artisan", "queue:work", "redis",
    "--queue=invoices,notifications,default",
    "--sleep=1", "--tries=3", "--timeout=240"
) -PassThru -NoNewWindow

Write-Host "Starting long-running reports worker..." -ForegroundColor Green
$reports = Start-Process php -ArgumentList @(
    "artisan", "queue:work", "redis-reports",
    "--queue=reports",
    "--sleep=1", "--tries=3", "--timeout=1800"
) -PassThru -NoNewWindow

Write-Host "Workers started. Press Ctrl+C to stop." -ForegroundColor Cyan
try {
    Wait-Process -Id $normal.Id, $reports.Id
}
finally {
    Stop-Process -Id $normal.Id, $reports.Id -ErrorAction SilentlyContinue
}
