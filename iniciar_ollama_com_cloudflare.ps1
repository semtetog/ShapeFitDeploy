# Script para iniciar Ollama e Cloudflare Tunnel
# Requisitos: cloudflared instalado

Write-Host "🚀 Iniciando Ollama + Cloudflare Tunnel..." -ForegroundColor Cyan
Write-Host ""

# Verificar se cloudflared está instalado
$cloudflaredPath = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cloudflaredPath) {
    Write-Host "❌ cloudflared não encontrado!" -ForegroundColor Red
    Write-Host "   Baixe em: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/" -ForegroundColor Yellow
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
Write-Host "🌐 Iniciando Cloudflare Tunnel..." -ForegroundColor Yellow
Write-Host "   ⚠️ Uma nova janela será aberta" -ForegroundColor Gray
Write-Host ""

# Iniciar cloudflared em nova janela
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cloudflared tunnel --url http://localhost:11434" -WindowStyle Normal

Write-Host "⏳ Aguardando tunnel iniciar..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

Write-Host ""
Write-Host "✅ Tudo iniciado!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 PRÓXIMOS PASSOS:" -ForegroundColor Cyan
Write-Host "   1. Na janela do Cloudflare, copie a URL (ex: https://abc123.trycloudflare.com)" -ForegroundColor White
Write-Host "   2. Na Hostinger, edite includes/config.php" -ForegroundColor White
Write-Host "   3. Altere: define('OLLAMA_URL', 'https://SUA_URL_CLOUDFLARE');" -ForegroundColor White
Write-Host ""
Write-Host "⚠️ IMPORTANTE:" -ForegroundColor Red
Write-Host "   - Mantenha as janelas do Ollama e Cloudflare abertas!" -ForegroundColor Yellow
Write-Host "   - A URL muda a cada reinício" -ForegroundColor Yellow
Write-Host ""
Write-Host "Pressione qualquer tecla para sair..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

