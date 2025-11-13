# 🚀 Instalar Ollama Diretamente na Hostinger

## ✅ É POSSÍVEL? Depende do seu plano!

### 📋 Tipos de Hospedagem Hostinger:

#### 1. **Hospedagem Compartilhada** ❌
- **NÃO permite** instalar Ollama
- Sem acesso SSH/root
- Sem permissão para executar binários customizados
- **Solução:** Precisa usar VPS ou servidor externo

#### 2. **VPS Hostinger** ✅
- **PERMITE** instalar Ollama!
- Acesso SSH completo
- Controle total do servidor
- **Solução:** Instale diretamente no VPS

#### 3. **Cloud Hosting** ❌
- Geralmente **NÃO permite** (similar ao compartilhado)
- Sem acesso root
- **Solução:** Precisa usar VPS ou servidor externo

---

## 🔍 Como Verificar seu Plano

1. Acesse o painel da Hostinger
2. Veja qual é seu plano de hospedagem
3. Se for **VPS**, você pode instalar Ollama!
4. Se for **Compartilhado/Cloud**, não é possível diretamente

---

## ✅ Se Você Tem VPS Hostinger - Instalação

### Passo 1: Acessar via SSH
```bash
ssh usuario@seu-servidor-hostinger.com
```

### Passo 2: Instalar Ollama
```bash
# Baixar e instalar Ollama
curl -fsSL https://ollama.com/install.sh | sh
```

### Passo 3: Baixar Modelo
```bash
ollama pull llama3.1:8b
```

### Passo 4: Iniciar Ollama como Serviço
```bash
# Criar serviço systemd
sudo tee /etc/systemd/system/ollama.service > /dev/null <<EOF
[Unit]
Description=Ollama Service
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/local/bin/ollama serve
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

# Habilitar e iniciar
sudo systemctl daemon-reload
sudo systemctl enable ollama
sudo systemctl start ollama
```

### Passo 5: Verificar se está rodando
```bash
curl http://localhost:11434/api/tags
```

### Passo 6: Configurar no código
No `includes/config.php` da Hostinger, já está configurado para usar localhost:
```php
define('OLLAMA_URL', 'http://localhost:11434'); // Já é o padrão!
```

✅ **Pronto! Funciona automaticamente!**

---

## ❌ Se Você NÃO Tem VPS - Alternativas

### Opção 1: Upgrade para VPS Hostinger
- Mais caro, mas permite instalar Ollama
- Controle total do servidor
- Melhor performance

### Opção 2: Usar Ollama no Seu PC (via ngrok/cloudflare)
- Gratuito
- Use os scripts que criamos
- Funciona perfeitamente

### Opção 3: VPS Externo Barato
- DigitalOcean, Linode, etc.
- ~$5-10/mês
- Instala Ollama lá e configura URL remota

### Opção 4: API Externa de IA
- OpenAI API
- Anthropic Claude
- Google Gemini
- (Mas não é gratuito)

---

## 🎯 Recomendação por Situação

### Se você tem VPS Hostinger:
✅ **Instale Ollama diretamente no VPS!**
- Mais rápido
- Sem dependências externas
- Funciona 24/7

### Se você tem Hospedagem Compartilhada:
✅ **Use Ollama no seu PC + ngrok/cloudflare**
- Gratuito
- Funciona bem
- Só precisa manter PC ligado quando usar

### Se você quer solução profissional:
✅ **Upgrade para VPS ou VPS externo**
- Melhor performance
- Sempre disponível
- Mais controle

---

## 📝 Script de Instalação Completo (VPS)

Crie um arquivo `instalar_ollama_hostinger.sh`:

```bash
#!/bin/bash

echo "🚀 Instalando Ollama na Hostinger VPS..."

# Instalar Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Baixar modelo
echo "📦 Baixando modelo llama3.1:8b (isso pode demorar)..."
ollama pull llama3.1:8b

# Criar serviço
sudo tee /etc/systemd/system/ollama.service > /dev/null <<EOF
[Unit]
Description=Ollama Service
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/local/bin/ollama serve
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

# Habilitar serviço
sudo systemctl daemon-reload
sudo systemctl enable ollama
sudo systemctl start ollama

# Verificar
sleep 3
if curl -s http://localhost:11434/api/tags > /dev/null; then
    echo "✅ Ollama instalado e rodando!"
    echo "✅ Modelo: llama3.1:8b"
    echo "✅ Serviço configurado para iniciar automaticamente"
else
    echo "❌ Erro ao iniciar Ollama"
fi
```

Execute:
```bash
chmod +x instalar_ollama_hostinger.sh
sudo ./instalar_ollama_hostinger.sh
```

---

## ⚠️ Requisitos do VPS

- **RAM:** Mínimo 8GB (recomendado 16GB para llama3.1:8b)
- **Disco:** Mínimo 20GB livre (modelo ocupa ~13GB)
- **CPU:** Quanto mais, melhor (processamento de IA é pesado)

---

## 🔒 Segurança (VPS)

Se instalar no VPS:
- ✅ Ollama roda em localhost (não exposto externamente)
- ✅ Apenas seu código PHP acessa
- ✅ Seguro por padrão

---

## ✅ Resumo

**Tem VPS Hostinger?** → Instale Ollama diretamente! ✅

**Tem Hospedagem Compartilhada?** → Use Ollama no PC + ngrok ✅

**Quer solução profissional?** → Upgrade para VPS ✅

