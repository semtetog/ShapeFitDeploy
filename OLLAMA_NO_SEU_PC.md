# 💻 Rodar Ollama no Seu PC e Hostinger Acessar

## ✅ Sim, é possível!

Você pode rodar o Ollama no seu PC e a Hostinger acessar. Existem 2 formas principais:

---

## 🌐 Opção 1: ngrok (Mais Fácil - Recomendado)

### Passo 1: Instalar ngrok
1. Baixe: https://ngrok.com/download
2. Crie conta gratuita (tem limite, mas funciona)
3. Instale e configure seu token

### Passo 2: Iniciar Ollama
```powershell
ollama serve
```

### Passo 3: Criar túnel com ngrok
Em outro terminal:
```powershell
ngrok http 11434
```

Isso vai gerar uma URL tipo: `https://abc123.ngrok.io`

### Passo 4: Configurar na Hostinger
No `includes/config.php` da Hostinger, altere:
```php
define('OLLAMA_URL', 'https://abc123.ngrok.io');
```

⚠️ **IMPORTANTE:**
- A URL do ngrok muda a cada vez que você reinicia (na versão gratuita)
- Você precisa atualizar na Hostinger sempre que reiniciar
- Versão paga do ngrok tem URL fixa

---

## 🔧 Opção 2: IP Público + Port Forwarding (Mais Complexo)

### Requisitos:
- IP público fixo (ou usar serviço como No-IP)
- Acesso ao roteador para fazer port forwarding
- Firewall configurado

### Passo 1: Configurar Ollama para aceitar conexões externas
No seu PC, configure variável de ambiente:
```powershell
$env:OLLAMA_HOST="0.0.0.0:11434"
ollama serve
```

### Passo 2: Configurar Port Forwarding no Roteador
- Acesse o roteador (geralmente 192.168.1.1)
- Configure port forwarding:
  - Porta externa: 11434 (ou outra)
  - IP interno: IP do seu PC na rede local
  - Porta interna: 11434

### Passo 3: Descobrir seu IP público
Acesse: https://whatismyipaddress.com

### Passo 4: Configurar na Hostinger
No `includes/config.php`:
```php
define('OLLAMA_URL', 'http://SEU_IP_PUBLICO:11434');
```

⚠️ **IMPORTANTE:**
- Seu IP público pode mudar (a menos que seja fixo)
- Precisa abrir porta no firewall do Windows
- ⚠️ **RISCO DE SEGURANÇA:** Ollama ficará exposto na internet sem autenticação!

---

## 🔒 Opção 3: Cloudflare Tunnel (Mais Seguro)

### Passo 1: Instalar cloudflared
Baixe: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/

### Passo 2: Criar túnel
```powershell
cloudflared tunnel --url http://localhost:11434
```

Isso gera uma URL tipo: `https://abc123.trycloudflare.com`

### Passo 3: Configurar na Hostinger
```php
define('OLLAMA_URL', 'https://abc123.trycloudflare.com');
```

✅ **Vantagens:**
- Gratuito
- Mais seguro que IP público
- URL muda, mas é mais estável que ngrok free

---

## ⚠️ IMPORTANTE - Segurança

**NUNCA exponha o Ollama diretamente na internet sem proteção!**

Se usar IP público:
- Configure firewall para permitir apenas IPs da Hostinger
- Ou use autenticação (se o Ollama suportar)
- Considere usar VPN

---

## 🎯 Recomendação

**Para desenvolvimento/teste:** Use ngrok ou Cloudflare Tunnel
**Para produção:** Use servidor dedicado ou VPS

---

## 📝 Script Automático (ngrok)

Crie um arquivo `iniciar_ollama_com_ngrok.ps1`:

```powershell
# Iniciar Ollama
Start-Process powershell -ArgumentList "-NoExit", "-Command", "ollama serve"

# Aguardar Ollama iniciar
Start-Sleep -Seconds 3

# Iniciar ngrok
Write-Host "Iniciando ngrok..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "ngrok http 11434"

Write-Host ""
Write-Host "✅ Ollama e ngrok iniciados!" -ForegroundColor Green
Write-Host "📋 Copie a URL do ngrok e configure na Hostinger" -ForegroundColor Cyan
Write-Host ""
Write-Host "Pressione qualquer tecla para sair..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
```

---

## ✅ Testar

Após configurar, teste na Hostinger:
1. Acesse o painel admin
2. Abra uma resposta de check-in
3. Clique em "Resumo"
4. Deve funcionar!

