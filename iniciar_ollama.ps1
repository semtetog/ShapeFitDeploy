# Script para iniciar o Ollama
# Execute: .\iniciar_ollama.ps1

Write-Host "🚀 Iniciando Ollama..." -ForegroundColor Cyan
Write-Host ""

# Verificar se já está rodando
Write-Host "1️⃣ Verificando se Ollama já está rodando..." -ForegroundColor Yellow
try {
    $test = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -TimeoutSec 2 -ErrorAction Stop
    Write-Host "   ✅ Ollama já está rodando!" -ForegroundColor Green
    Write-Host "   Você pode fechar esta janela." -ForegroundColor Gray
    exit 0
} catch {
    Write-Host "   ⚠️ Ollama não está rodando, iniciando..." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "2️⃣ Iniciando servidor Ollama..." -ForegroundColor Yellow
Write-Host "   ⚠️ IMPORTANTE: Mantenha esta janela aberta!" -ForegroundColor Red
Write-Host "   O Ollama precisa estar rodando para gerar resumos." -ForegroundColor Gray
Write-Host ""

# Iniciar Ollama
Start-Process -FilePath "ollama" -ArgumentList "serve" -WindowStyle Normal

Write-Host "   ⏳ Aguardando Ollama iniciar..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

# Verificar novamente
Write-Host ""
Write-Host "3️⃣ Verificando se iniciou corretamente..." -ForegroundColor Yellow
try {
    $test = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -TimeoutSec 5 -ErrorAction Stop
    Write-Host "   ✅ Ollama está rodando!" -ForegroundColor Green
    Write-Host ""
    Write-Host "✅ Tudo pronto! Agora você pode gerar resumos." -ForegroundColor Green
    Write-Host ""
    Write-Host "⚠️ IMPORTANTE: Mantenha a janela do Ollama aberta!" -ForegroundColor Red
    Write-Host "   Se fechar, o Ollama para de funcionar." -ForegroundColor Gray
} catch {
    Write-Host "   ❌ Erro ao iniciar Ollama" -ForegroundColor Red
    Write-Host "   💡 Tente executar manualmente: ollama serve" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Pressione qualquer tecla para sair..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

