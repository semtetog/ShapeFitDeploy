#!/bin/bash

# Script para instalar Ollama na Hostinger VPS
# Execute: sudo ./instalar_ollama_hostinger.sh

echo "🚀 Instalando Ollama na Hostinger VPS..."
echo ""

# Verificar se é root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Por favor, execute como root (sudo)"
    exit 1
fi

# Instalar Ollama
echo "📦 Instalando Ollama..."
curl -fsSL https://ollama.com/install.sh | sh

if [ $? -ne 0 ]; then
    echo "❌ Erro ao instalar Ollama"
    exit 1
fi

echo "✅ Ollama instalado!"
echo ""

# Baixar modelo
echo "📦 Baixando modelo llama3.1:8b..."
echo "   ⚠️ Isso pode demorar vários minutos (~13GB)..."
ollama pull llama3.1:8b

if [ $? -ne 0 ]; then
    echo "⚠️ Erro ao baixar modelo, mas continuando..."
fi

echo ""
echo "⚙️ Configurando serviço systemd..."

# Criar serviço
cat > /etc/systemd/system/ollama.service <<'EOF'
[Unit]
Description=Ollama Service
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/local/bin/ollama serve
Restart=always
RestartSec=3
Environment="OLLAMA_HOST=127.0.0.1:11434"

[Install]
WantedBy=multi-user.target
EOF

# Habilitar serviço
systemctl daemon-reload
systemctl enable ollama
systemctl start ollama

# Aguardar iniciar
echo "⏳ Aguardando Ollama iniciar..."
sleep 5

# Verificar
if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo ""
    echo "✅ Ollama instalado e rodando!"
    echo "✅ Modelo: llama3.1:8b"
    echo "✅ Serviço configurado para iniciar automaticamente"
    echo ""
    echo "🎉 Pronto! O sistema vai usar o Ollama automaticamente."
else
    echo ""
    echo "⚠️ Ollama pode não ter iniciado corretamente"
    echo "   Verifique: systemctl status ollama"
    echo "   Logs: journalctl -u ollama -f"
fi

