# Script para configurar o Ollama automaticamente
# Execute: .\configurar_ollama.ps1

Write-Host "🚀 Configurando Ollama..." -ForegroundColor Cyan
Write-Host ""

# Verificar se ollama está instalado
Write-Host "1️⃣ Verificando instalação do Ollama..." -ForegroundColor Yellow
try {
    $version = ollama --version 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Ollama está instalado!" -ForegroundColor Green
        Write-Host "   Versão: $version" -ForegroundColor Gray
    } else {
        Write-Host "   ❌ Ollama não encontrado no PATH" -ForegroundColor Red
        Write-Host "   💡 Solução: Feche e abra um NOVO terminal, ou reinicie o computador" -ForegroundColor Yellow
        exit 1
    }
} catch {
    Write-Host "   ❌ Ollama não encontrado no PATH" -ForegroundColor Red
    Write-Host "   💡 Solução: Feche e abra um NOVO terminal, ou reinicie o computador" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Verificar modelos instalados
Write-Host "2️⃣ Verificando modelos instalados..." -ForegroundColor Yellow
$models = ollama list 2>&1
Write-Host $models

if ($models -match "llama3.1") {
    Write-Host "   ✅ Modelo llama3.1 encontrado!" -ForegroundColor Green
} else {
    Write-Host "   ⚠️ Modelo llama3.1 não encontrado" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "3️⃣ Baixando modelo llama3.1:8b..." -ForegroundColor Yellow
    Write-Host "   ⏳ Isso pode demorar alguns minutos (~13GB)..." -ForegroundColor Gray
    
    $response = Read-Host "   Deseja baixar o modelo agora? (S/N)"
    if ($response -eq "S" -or $response -eq "s") {
        ollama pull llama3.1:8b
        if ($LASTEXITCODE -eq 0) {
            Write-Host "   ✅ Modelo baixado com sucesso!" -ForegroundColor Green
        } else {
            Write-Host "   ❌ Erro ao baixar modelo" -ForegroundColor Red
            Write-Host "   💡 Tente manualmente: ollama pull llama3.1:8b" -ForegroundColor Yellow
        }
    } else {
        Write-Host "   ⏭️ Pulando download. Execute depois: ollama pull llama3.1:8b" -ForegroundColor Yellow
    }
}

Write-Host ""

# Testar conexão
Write-Host "4️⃣ Testando conexão com Ollama..." -ForegroundColor Yellow
try {
    $testResponse = ollama run llama3.1:8b "teste" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✅ Ollama está funcionando!" -ForegroundColor Green
    } else {
        Write-Host "   ⚠️ Teste falhou, mas pode ser normal" -ForegroundColor Yellow
        Write-Host "   💡 Verifique se o modelo está instalado: ollama list" -ForegroundColor Yellow
    }
} catch {
    Write-Host "   ⚠️ Não foi possível testar automaticamente" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "✅ Configuração concluída!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 Próximos passos:" -ForegroundColor Cyan
Write-Host "   1. Teste no sistema: abra uma resposta de check-in e clique em 'Resumo'" -ForegroundColor White
Write-Host "   2. Se não funcionar, execute: ollama serve" -ForegroundColor White
Write-Host "   3. Verifique os modelos: ollama list" -ForegroundColor White
Write-Host ""

