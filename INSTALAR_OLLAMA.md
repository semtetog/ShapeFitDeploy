# 🚀 Guia de Instalação do Ollama no Windows

## Passo 1: Baixar e Instalar o Ollama

1. **Acesse o site oficial:** https://ollama.com/download
2. **Baixe o instalador para Windows** (arquivo `.exe`)
3. **Execute o instalador** e siga as instruções
4. **Aguarde a instalação** completar

## Passo 2: Verificar a Instalação

Abra o PowerShell ou CMD e execute:
```powershell
ollama --version
```

Se aparecer a versão, está instalado! ✅

## Passo 3: Baixar o Modelo de IA

Execute este comando para baixar o modelo recomendado (Llama 3.1 - 8B):
```powershell
ollama pull llama3.1:8b
```

**Modelos alternativos (se o 8B for muito pesado):**
- `ollama pull llama3.1` (versão menor, mais rápida)
- `ollama pull mistral` (modelo alternativo, menor)
- `ollama pull phi3` (modelo muito leve)

## Passo 4: Verificar Modelos Instalados

```powershell
ollama list
```

## Passo 5: Testar o Ollama

**Opção 1 - Teste rápido no terminal:**
```powershell
ollama run llama3.1:8b "Olá, como você está?"
```

Se responder, está tudo funcionando! ✅

**Opção 2 - Teste completo com script PHP:**
```powershell
php testar_ollama.php
```

Este script testa se o Ollama está acessível e funcionando corretamente com o sistema.

## Passo 6: Iniciar o Serviço Ollama

O Ollama deve iniciar automaticamente após a instalação. Se não estiver rodando:

1. **Procure por "Ollama" no menu Iniciar**
2. **Execute o aplicativo Ollama**
3. **Ou execute no terminal:**
```powershell
ollama serve
```

O servidor vai rodar em: `http://localhost:11434`

## ✅ Pronto!

Agora o sistema vai usar o Ollama automaticamente para gerar os resumos!

---

## 🎯 Melhorias Implementadas no Código

O código foi otimizado para usar **APENAS o Ollama** e garantir que ele leia **TUDO**:

✅ **Prompt ultra-inteligente** com instruções enfáticas para não perder informações  
✅ **5000 tokens** de saída para resumos completos  
✅ **Modelo padrão: llama3.1:8b** (mais inteligente)  
✅ **Timeout aumentado** para 180 segundos  
✅ **Múltiplas verificações** para garantir que todos os dados sejam incluídos  

O sistema agora:
- ⚠️ Lê TODA a conversa linha por linha
- ⚠️ Extrai TODOS os dados (valores, notas, sentimentos, etc.)
- ⚠️ NÃO esquece nenhuma informação
- ⚠️ Inclui TODAS as perguntas e respostas no resumo

---

## 🔧 Solução de Problemas

### Ollama não inicia automaticamente?
- Execute manualmente: `ollama serve`
- Ou adicione ao Inicialização do Windows

### Erro de memória?
- Use um modelo menor: `ollama pull llama3.1` (sem :8b)
- Ou: `ollama pull phi3` (muito leve)

### Porta 11434 já em uso?
- O Ollama já está rodando! Tudo certo ✅

### Quer usar outro modelo?
- Altere no arquivo `admin/ajax_checkin.php` na linha que diz `$model = 'llama3.1';`

