# 🚀 Configurar Groq API - Passo a Passo Rápido

## ✅ Passo 1: Criar Conta e Obter API Key

1. **Acesse:** https://console.groq.com
2. **Crie uma conta** (gratuita)
3. **Vá em "API Keys"** no menu
4. **Clique em "Create API Key"**
5. **Copie a chave** (ela só aparece uma vez!)

---

## ✅ Passo 2: Configurar no Sistema

### Opção A - Via Variável de Ambiente (Recomendado na Hostinger):
No painel da Hostinger, adicione:
- **Nome:** `GROQ_API_KEY`
- **Valor:** `sua-chave-aqui`

### Opção B - Editar config.php diretamente:
Edite `includes/config.php` e altere:
```php
define('GROQ_API_KEY', 'sua-chave-aqui');
```

---

## ✅ Passo 3: Pronto!

Agora o sistema vai usar Groq API automaticamente! 🎉

- ✅ **Gratuito** com limites generosos
- ✅ **Muito rápido** (respostas em segundos)
- ✅ **Muito inteligente** (modelo Llama 3.1 70B)
- ✅ **Funciona na Hostinger** sem precisar de VPS

---

## 🔍 Testar

1. Abra uma resposta de check-in
2. Clique na aba "Resumo"
3. Deve gerar automaticamente!

---

## ⚠️ Limites Gratuitos

- **~14,400 requests/dia** (gratuito)
- **Muito rápido** (processamento em GPU)
- **Sem necessidade de cartão de crédito**

---

## 🆘 Se não funcionar

1. Verifique se a API key está correta
2. Verifique os logs do PHP
3. Teste a API key em: https://console.groq.com/playground

---

## ✅ Pronto para usar!

Basta configurar a API key e está funcionando! 🚀

