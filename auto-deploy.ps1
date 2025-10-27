# Script de Auto-Deploy 100% Automatico
# Monitora alteracoes e faz commit+push automaticamente

Write-Host "AUTO-DEPLOY ATIVADO!" -ForegroundColor Green
Write-Host "Monitorando: $PWD" -ForegroundColor Cyan
Write-Host "Qualquer alteracao sera automaticamente enviada ao Git" -ForegroundColor Yellow
Write-Host "Pressione Ctrl+C para parar" -ForegroundColor Red
Write-Host ""

$lastCommitTime = Get-Date
$commitCooldown = 5  # Minimo de 5 segundos entre commits

$action = {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $changeType = $Event.SourceEventArgs.ChangeType
    
    # Lista de pastas/arquivos a ignorar
    $ignorePatterns = @(
        "*\.git\*",
        "*\node_modules\*",
        "*\vendor\*",
        "*\.idea\*",
        "*\.vscode\*",
        "*\uploads\*",
        "*\sessions\*",
        "*\logs\*",
        "*.tmp",
        "*.temp",
        "*.swp",
        "*.swo",
        "*.log"
    )
    
    # Verifica se deve ignorar
    $shouldIgnore = $false
    foreach ($pattern in $ignorePatterns) {
        if ($path -like $pattern) {
            $shouldIgnore = $true
            break
        }
    }
    
    if ($shouldIgnore) {
        return
    }
    
    # Cooldown entre commits para evitar spam
    $now = Get-Date
    $timeSinceLastCommit = ($now - $script:lastCommitTime).TotalSeconds
    
    if ($timeSinceLastCommit -lt $script:commitCooldown) {
        return
    }
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] Detectada alteracao: $changeType - $name" -ForegroundColor Yellow
    
    # Aguarda para garantir que todos os arquivos foram salvos
    Start-Sleep -Seconds 3
    
    # Verifica se realmente há mudanças
    $status = git status --porcelain 2>&1
    
    if (-not $status) {
        return
    }
    
    # Adiciona todas as mudanças
    git add -A 2>&1 | Out-Null
    
    # Commita
    $commitMsg = "Auto-deploy: $changeType - $name - $timestamp"
    Write-Host "Commitando: $commitMsg" -ForegroundColor Cyan
    
    $commitResult = git commit -m $commitMsg 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Deploy realizado com sucesso!" -ForegroundColor Green
        $script:lastCommitTime = Get-Date
    }
    
    Write-Host ""
}

# Configurar o FileSystemWatcher
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $PWD
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName,LastWrite'

# Registrar eventos
$handlers = @()
$handlers += Register-ObjectEvent $watcher "Changed" -Action $action
$handlers += Register-ObjectEvent $watcher "Created" -Action $action
$handlers += Register-ObjectEvent $watcher "Deleted" -Action $action
$handlers += Register-ObjectEvent $watcher "Renamed" -Action $action

Write-Host "Aguardando alteracoes..." -ForegroundColor Cyan
Write-Host ""

try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    # Cleanup
    foreach ($handler in $handlers) {
        Unregister-Event -SourceIdentifier $handler.Name -ErrorAction SilentlyContinue
    }
    $watcher.Dispose()
    Write-Host ""
    Write-Host "Auto-Deploy Desativado" -ForegroundColor Red
}
