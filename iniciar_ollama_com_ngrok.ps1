# Script para iniciar Ollama e ngrok automaticamente
# Requisitos: ngrok instalado e configurado

Write-Host "🚀 Iniciando Ollama + ngrok..." -ForegroundColor Cyan
Write-Host ""

# Verificar se ngrok está instalado
$ngrokPath = Get-Command ngrok -ErrorAction SilentlyContinue
if (-not $ngrokPath) {
    Write-Host "❌ ngrok não encontrado!" -ForegroundColor Red
    Write-Host "   Baixe em: https://ngrok.com/download" -ForegroundColor Yellow
    Write-Host "   Configure seu token: ngrok config add-authtoken SEU_TOKEN" -ForegroundColor Yellow
    exit 1
}

# Verificar se Ollama está rodando
try {
    $test = Invoke-WebRequest -Uri "http://localhost:11434/api/tags" -TimeoutSec 2 -ErrorAction Stop
    Write-Host "✅ Ollama já está rodando!" -ForegroundColor Green
} catch {
    Write-Host "📦 Iniciando Ollama..." -ForegroundColor Yellow
    Start-Process powershell -ArgumentList "-NoExit", "-Command", "ollama serve" -WindowStyle Minimized
    Start-Sleep -Seconds 5
    Write-Host "   ✅ Ollama iniciado!" -ForegroundColor Green
}

Write-Host ""
Write-Host "🌐 Iniciando ngrok..." -ForegroundColor Yellow
Write-Host "   ⚠️ Uma nova janela do ngrok será aberta" -ForegroundColor Gray
Write-Host ""

# Iniciar ngrok em nova janela
Start-Process powershell -ArgumentList "-NoExit", "-Command", "ngrok http 11434" -WindowStyle Normal

Write-Host "⏳ Aguardando ngrok iniciar..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

Write-Host ""
Write-Host "✅ Tudo iniciado!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 PRÓXIMOS PASSOS:" -ForegroundColor Cyan
Write-Host "   1. Na janela do ngrok, copie a URL 'Forwarding' (ex: https://abc123.ngrok.io)" -ForegroundColor White
Write-Host "   2. Na Hostinger, edite includes/config.php" -ForegroundColor White
Write-Host "   3. Altere: define('OLLAMA_URL', 'https://SUA_URL_NGROK');" -ForegroundColor White
Write-Host ""
Write-Host "⚠️ IMPORTANTE:" -ForegroundColor Red
Write-Host "   - Mantenha as janelas do Ollama e ngrok abertas!" -ForegroundColor Yellow
Write-Host "   - A URL do ngrok muda a cada reinício (versão gratuita)" -ForegroundColor Yellow
Write-Host "   - Você precisará atualizar na Hostinger se reiniciar" -ForegroundColor Yellow
Write-Host ""
Write-Host "Pressione qualquer tecla para sair..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

