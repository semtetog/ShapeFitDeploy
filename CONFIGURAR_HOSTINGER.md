# 🌐 Configurar Ollama na Hostinger

## ⚠️ IMPORTANTE: Situação na Hostinger

Na Hostinger, você tem **2 opções**:

### Opção 1: Ollama no Servidor da Hostinger (Recomendado se possível)
Se a Hostinger permitir instalar o Ollama no servidor:
1. Instale o Ollama no servidor da Hostinger
2. Configure para rodar como serviço
3. O sistema vai funcionar automaticamente (usa localhost)

### Opção 2: Ollama em Servidor Remoto (Recomendado)
Se não puder instalar na Hostinger, use um servidor separado:

1. **Instale o Ollama em outro servidor** (VPS, servidor próprio, etc.)
2. **Configure o Ollama para aceitar conexões externas:**
   ```bash
   # No servidor do Ollama, configure para aceitar conexões externas
   export OLLAMA_HOST=0.0.0.0:11434
   ollama serve
   ```

3. **Na Hostinger, configure a URL do Ollama:**
   
   **Opção A - Via variável de ambiente (recomendado):**
   - No painel da Hostinger, adicione variável de ambiente:
     - Nome: `OLLAMA_URL`
     - Valor: `http://seu-servidor-ollama.com:11434`
   
   **Opção B - Editar config.php diretamente:**
   - Edite `includes/config.php`
   - Altere a linha:
     ```php
     define('OLLAMA_URL', 'http://seu-servidor-ollama.com:11434');
     ```

---

## 🔒 Segurança (IMPORTANTE!)

Se usar servidor remoto:
- ⚠️ **NÃO exponha o Ollama publicamente sem autenticação!**
- Use firewall para permitir apenas IPs da Hostinger
- Ou configure autenticação no Ollama
- Considere usar VPN ou túnel SSH

---

## ✅ Verificar se está funcionando

Após configurar, teste:
1. Acesse o painel admin
2. Abra uma resposta de check-in
3. Clique na aba "Resumo"
4. Deve gerar o resumo automaticamente

---

## 🆘 Se não funcionar

1. **Verifique os logs do PHP** na Hostinger
2. **Teste a conexão:**
   - Crie um arquivo `test_ollama_hostinger.php`:
   ```php
   <?php
   require_once 'includes/config.php';
   $url = defined('OLLAMA_URL') ? OLLAMA_URL : 'http://localhost:11434';
   echo "Tentando conectar em: $url\n";
   $ch = curl_init($url . '/api/tags');
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_TIMEOUT, 5);
   $response = curl_exec($ch);
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);
   echo "HTTP Code: $http_code\n";
   echo "Response: " . substr($response, 0, 200);
   ?>
   ```
3. **Execute e veja o resultado**

---

## 💡 Alternativa: Usar API Externa

Se não conseguir usar Ollama na Hostinger, você pode:
- Usar uma API de IA externa (OpenAI, Anthropic, etc.)
- Ou criar um serviço intermediário que chama o Ollama

---

## 📝 Resumo

**Localmente (seu PC):**
- ✅ Ollama em localhost funciona automaticamente
- ✅ Basta rodar `ollama serve`

**Na Hostinger:**
- ⚠️ Precisa configurar URL do Ollama (se remoto)
- ⚠️ Ou instalar Ollama no servidor da Hostinger
- ⚠️ Verificar se Hostinger permite executar Ollama

