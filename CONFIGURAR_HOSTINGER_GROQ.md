# 🚀 Configurar Groq API na Hostinger - RÁPIDO!

## ⚡ Passo a Passo (2 minutos)

### 1. Acesse o File Manager da Hostinger
- Entre no painel da Hostinger
- Vá em **File Manager**
- Navegue até: `public_html/includes/`

### 2. Edite o arquivo `config.php`
- Clique com botão direito em `config.php`
- Selecione **Edit**
- Procure a linha **168** (aproximadamente)

### 3. Cole sua API Key
Encontre esta linha:
```php
define('GROQ_API_KEY', ''); // Cole sua API key da Groq aqui
```

**Substitua por:**
```php
define('GROQ_API_KEY', 'sua-chave-aqui');
```

**Onde `sua-chave-aqui` é sua API key da Groq (começa com `gsk_`)**

### 4. Salve o arquivo
- Clique em **Save**
- Pronto! ✅

---

## ✅ Testar

1. Acesse seu site
2. Abra uma resposta de check-in
3. Clique na aba **"Resumo"**
4. Deve gerar automaticamente! 🎉

---

## 🔐 Como obter sua API Key:

1. Acesse: https://console.groq.com
2. Faça login
3. Vá em **API Keys**
4. Clique em **Create API Key**
5. Copie a chave (começa com `gsk_`)
6. Cole na linha 168 do `config.php` na Hostinger

---

## ⚠️ Importante:

- A chave está configurada localmente (seu PC)
- Na Hostinger, você precisa editar o arquivo diretamente
- Depois de configurar, vai funcionar automaticamente!

---

## 🆘 Se não funcionar:

1. Verifique se salvou o arquivo
2. Verifique se copiou a chave completa
3. Limpe o cache do navegador
4. Verifique os logs do PHP na Hostinger

