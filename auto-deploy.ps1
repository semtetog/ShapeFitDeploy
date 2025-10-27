# Script de Auto-Deploy 100% Automático
# Monitora alterações e faz commit+push automaticamente

Write-Host "🚀 Auto-Deploy Ativado!" -ForegroundColor Green
Write-Host "📁 Monitorando: $PWD" -ForegroundColor Cyan
Write-Host "🔄 Qualquer alteração será automaticamente enviada ao Git" -ForegroundColor Yellow
Write-Host "⚠️  Pressione Ctrl+C para parar" -ForegroundColor Red
Write-Host ""

# Função para fazer commit e push
function Deploy-Changes {
    param($ChangeType, $FileName)
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    
    Write-Host "[$timestamp] 🔔 Detectada alteração: $ChangeType - $FileName" -ForegroundColor Yellow
    
    # Aguarda 2 segundos para garantir que o arquivo foi salvo completamente
    Start-Sleep -Seconds 2
    
    # Git add all
    git add -A
    
    # Verifica se há alterações para commitar
    $status = git status --porcelain
    
    if ($status) {
        # Cria mensagem de commit automática
        $commitMsg = "Auto-deploy: $ChangeType - $FileName - $timestamp"
        
        Write-Host "📝 Commitando: $commitMsg" -ForegroundColor Cyan
        git commit -m $commitMsg
        
        Write-Host "✅ Deploy realizado com sucesso!" -ForegroundColor Green
        Write-Host ""
    }
}

# Configurar o FileSystemWatcher
$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $PWD
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true

# Filtros - ignora arquivos do git, node_modules, etc
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName,LastWrite,DirectoryName'

# Pastas e arquivos a ignorar
$ignorePaths = @(
    ".git",
    "node_modules",
    "vendor",
    ".idea",
    ".vscode",
    "uploads",
    "sessions",
    "logs"
)

# Função para verificar se deve ignorar o arquivo
function Should-Ignore {
    param($path)
    
    foreach ($ignore in $ignorePaths) {
        if ($path -like "*\$ignore\*" -or $path -like "*/$ignore/*") {
            return $true
        }
    }
    
    # Ignora arquivos temporários
    if ($path -match '\.(tmp|temp|swp|swo|log)$') {
        return $true
    }
    
    return $false
}

# Debounce - evita múltiplos commits para a mesma alteração
$lastChange = @{}
$debounceSeconds = 3

# Evento: Arquivo Alterado
$onChanged = Register-ObjectEvent $watcher "Changed" -Action {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $changeType = $Event.SourceEventArgs.ChangeType
    
    # Ignora se estiver na lista de ignorados
    if (Should-Ignore $path) {
        return
    }
    
    # Debounce - só processa se passou tempo suficiente desde a última mudança
    $now = Get-Date
    if ($lastChange.ContainsKey($path)) {
        $timeSinceLastChange = ($now - $lastChange[$path]).TotalSeconds
        if ($timeSinceLastChange -lt $debounceSeconds) {
            return
        }
    }
    $lastChange[$path] = $now
    
    Deploy-Changes $changeType $name
}

# Evento: Arquivo Criado
$onCreated = Register-ObjectEvent $watcher "Created" -Action {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $changeType = $Event.SourceEventArgs.ChangeType
    
    if (Should-Ignore $path) {
        return
    }
    
    Deploy-Changes $changeType $name
}

# Evento: Arquivo Deletado
$onDeleted = Register-ObjectEvent $watcher "Deleted" -Action {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $changeType = $Event.SourceEventArgs.ChangeType
    
    if (Should-Ignore $path) {
        return
    }
    
    Deploy-Changes $changeType $name
}

# Evento: Arquivo Renomeado
$onRenamed = Register-ObjectEvent $watcher "Renamed" -Action {
    $path = $Event.SourceEventArgs.FullPath
    $name = $Event.SourceEventArgs.Name
    $oldName = $Event.SourceEventArgs.OldName
    $changeType = "Renamed"
    
    if (Should-Ignore $path) {
        return
    }
    
    Deploy-Changes "$changeType ($oldName -> $name)" $name
}

# Mantém o script rodando
try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    # Cleanup ao parar o script
    Unregister-Event -SourceIdentifier $onChanged.Name
    Unregister-Event -SourceIdentifier $onCreated.Name
    Unregister-Event -SourceIdentifier $onDeleted.Name
    Unregister-Event -SourceIdentifier $onRenamed.Name
    $watcher.Dispose()
    
    Write-Host ""
    Write-Host "🛑 Auto-Deploy Desativado" -ForegroundColor Red
}

