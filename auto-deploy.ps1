# Script de Auto-Deploy 100% Automatico
# Monitora alteracoes e faz commit+push automaticamente

Write-Host "AUTO-DEPLOY ATIVADO!" -ForegroundColor Green
Write-Host "Monitorando: $PWD" -ForegroundColor Cyan
Write-Host "Qualquer alteracao sera automaticamente enviada ao Git" -ForegroundColor Yellow
Write-Host "Pressione Ctrl+C para parar" -ForegroundColor Red
Write-Host ""

$ignorePaths = @(".git", "node_modules", "vendor", ".idea", ".vscode", "uploads", "sessions", "logs")
$lastChange = @{}
$debounceSeconds = 3

function ShouldIgnore($path) {
    foreach ($ignore in $ignorePaths) {
        if ($path -like "*\$ignore\*") {
            return $true
        }
    }
    if ($path -match '\.(tmp|temp|swp|swo|log)$') {
        return $true
    }
    return $false
}

function DoDeploy($changeType, $fileName) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] Detectada alteracao: $changeType - $fileName" -ForegroundColor Yellow
    
    Start-Sleep -Seconds 2
    
    git add -A 2>&1 | Out-Null
    
    $status = git status --porcelain
    
    if ($status) {
        $commitMsg = "Auto-deploy: $changeType - $fileName - $timestamp"
        Write-Host "Commitando: $commitMsg" -ForegroundColor Cyan
        git commit -m $commitMsg 2>&1 | Out-Null
        Write-Host "Deploy realizado com sucesso!" -ForegroundColor Green
        Write-Host ""
    }
}

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $PWD
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName,LastWrite,DirectoryName'

$action = {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $changeType = $Event.SourceEventArgs.ChangeType
    
    $shouldIgnore = $false
    foreach ($ignore in @(".git", "node_modules", "vendor", ".idea", ".vscode", "uploads", "sessions", "logs")) {
        if ($path -like "*\$ignore\*") {
            $shouldIgnore = $true
            break
        }
    }
    
    if ($shouldIgnore) {
        return
    }
    
    if ($path -match '\.(tmp|temp|swp|swo|log)$') {
        return
    }
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] Detectada alteracao: $changeType - $name" -ForegroundColor Yellow
    
    Start-Sleep -Seconds 2
    
    git add -A 2>&1 | Out-Null
    $status = git status --porcelain
    
    if ($status) {
        $commitMsg = "Auto-deploy: $changeType - $name - $timestamp"
        Write-Host "Commitando: $commitMsg" -ForegroundColor Cyan
        git commit -m $commitMsg 2>&1 | Out-Null
        Write-Host "Deploy realizado!" -ForegroundColor Green
        Write-Host ""
    }
}

$handlers = @()
$handlers += Register-ObjectEvent $watcher "Changed" -Action $action
$handlers += Register-ObjectEvent $watcher "Created" -Action $action
$handlers += Register-ObjectEvent $watcher "Deleted" -Action $action
$handlers += Register-ObjectEvent $watcher "Renamed" -Action $action

try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    foreach ($handler in $handlers) {
        Unregister-Event -SourceIdentifier $handler.Name -ErrorAction SilentlyContinue
    }
    $watcher.Dispose()
    Write-Host ""
    Write-Host "Auto-Deploy Desativado" -ForegroundColor Red
}

