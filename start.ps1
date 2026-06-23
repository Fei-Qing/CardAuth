# CardAuth One-Click Start Script
# Starts backend PHP server and frontend Vite dev server

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
$backendPath = Join-Path $projectRoot "backend"
$frontendPath = Join-Path $projectRoot "frontend"

function Test-PortInUse {
    param([int]$Port)
    $listener = $null
    try {
        $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Port)
        $listener.Start()
        $listener.Stop()
        return $false
    } catch {
        return $true
    } finally {
        if ($listener -ne $null) { $listener.Stop() }
    }
}

function Start-Backend {
    if (Test-PortInUse -Port 8080) {
        Write-Host "[Backend] Port 8080 already in use, skip" -ForegroundColor Yellow
        return
    }
    Write-Host "[Backend] Starting PHP server at http://127.0.0.1:8080 ..." -ForegroundColor Cyan
    $proc = Start-Process -FilePath "php" -ArgumentList "-S 127.0.0.1:8080 -t public" -WorkingDirectory $backendPath -WindowStyle Hidden -PassThru
    Write-Host "[Backend] Process ID: $($proc.Id)" -ForegroundColor Green
}

function Start-Frontend {
    if (Test-PortInUse -Port 3000) {
        Write-Host "[Frontend] Port 3000 already in use, skip" -ForegroundColor Yellow
        return
    }
    Write-Host "[Frontend] Starting Vite dev server at http://localhost:3000 ..." -ForegroundColor Cyan
    $proc = Start-Process -FilePath "cmd.exe" -ArgumentList "/c npm run dev" -WorkingDirectory $frontendPath -WindowStyle Hidden -PassThru
    Write-Host "[Frontend] Process ID: $($proc.Id)" -ForegroundColor Green
}

Write-Host "============================================" -ForegroundColor Blue
Write-Host "       CardAuth One-Click Start" -ForegroundColor Blue
Write-Host "============================================" -ForegroundColor Blue

Start-Backend
Start-Frontend

Write-Host ""
Write-Host "Services started. Please visit:" -ForegroundColor Green
Write-Host "  Frontend: http://localhost:3000/" -ForegroundColor White
Write-Host "  Admin:    http://localhost:3000/#/login" -ForegroundColor White
Write-Host "  Backend:  http://127.0.0.1:8080" -ForegroundColor White
Write-Host ""
Write-Host "Note: background processes keep running after this window closes" -ForegroundColor Gray
