# ⚡ CONFIGURAR API KEY GROQ - AGORA!

## 🚀 Passo 1: Obter API Key (2 minutos)

1. **Acesse:** https://console.groq.com
2. **Faça login** ou crie conta (gratuito)
3. **Clique em "API Keys"** no menu lateral
4. **Clique em "Create API Key"**
5. **Dê um nome** (ex: "ShapeFit")
6. **COPIE A CHAVE** ⚠️ (ela só aparece uma vez!)

---

## ⚡ Passo 2: Configurar no Código (30 segundos)

### Edite o arquivo: `includes/config.php`

**Encontre a linha 160:**
```php
define('GROQ_API_KEY', ''); // Deixe vazio se não tiver ainda
```

**Substitua por:**
```php
define('GROQ_API_KEY', 'SUA_CHAVE_AQUI'); // Cole sua chave aqui
```

**Exemplo:**
```php
define('GROQ_API_KEY', 'gsk_abc123xyz456...'); // Sua chave real
```

---

## ✅ Passo 3: Salvar e Testar

1. **Salve o arquivo**
2. **Teste no sistema:** Abra uma resposta de check-in → Aba "Resumo"
3. **Deve funcionar!** 🎉

---

## 🔍 Verificar se Funcionou

Execute este comando para testar:
```powershell
php testar_groq.php
```

---

## ⚠️ IMPORTANTE:

- **NÃO compartilhe** sua API key publicamente
- **NÃO commite** a chave no Git (já está no .gitignore)
- A chave é **gratuita** e tem limites generosos

---

## 🆘 Se não funcionar:

1. Verifique se copiou a chave completa
2. Verifique se não tem espaços extras
3. Verifique se salvou o arquivo
4. Veja os logs do PHP para mais detalhes

