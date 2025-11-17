# Script para sincronizar código com Android
# Uso: .\sync-android.ps1

Write-Host "🔄 Sincronizando código com Android..." -ForegroundColor Cyan

# Copiar arquivos para www/ (exceto pastas que não devem ir)
Write-Host "📦 Copiando arquivos para www/..." -ForegroundColor Yellow
Remove-Item -Path "www" -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path "www" -Force | Out-Null

# Copiar tudo exceto pastas específicas
Get-ChildItem -Path "." -Exclude "node_modules","android","www","ios","*.log","package-lock.json" | 
    ForEach-Object {
        Copy-Item -Path $_.FullName -Destination "www\" -Recurse -Force -ErrorAction SilentlyContinue
    }

Write-Host "✅ Arquivos copiados para www/" -ForegroundColor Green

# Sincronizar com Capacitor
Write-Host "🔄 Sincronizando com Capacitor..." -ForegroundColor Yellow
npx cap sync

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Sincronização concluída!" -ForegroundColor Green
    Write-Host "💡 Para abrir no Android Studio, execute: npx cap open android" -ForegroundColor Cyan
} else {
    Write-Host "❌ Erro ao sincronizar!" -ForegroundColor Red
}

