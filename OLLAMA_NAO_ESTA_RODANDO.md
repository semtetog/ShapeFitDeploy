# ⚠️ Problema: Ollama não está rodando!

## 🔴 Erro encontrado:
```
Failed to connect to localhost port 11434: Connection refused
```

Isso significa que o **Ollama não está rodando** no seu computador.

---

## ✅ Solução Rápida:

### Opção 1: Script Automático (Recomendado)
Execute no PowerShell:
```powershell
.\iniciar_ollama.ps1
```

### Opção 2: Manual
1. Abra um **novo** PowerShell ou CMD
2. Execute:
```powershell
ollama serve
```
3. **MANTENHA A JANELA ABERTA!** ⚠️
   - Se fechar, o Ollama para de funcionar

---

## 🔍 Como verificar se está rodando:

Execute:
```powershell
php testar_ollama.php
```

Se aparecer "✅ SUCESSO!", está funcionando!

---

## 💡 Dica: Iniciar automaticamente

Para que o Ollama inicie automaticamente quando você ligar o computador:

1. Pressione `Win + R`
2. Digite: `shell:startup`
3. Crie um atalho para: `ollama serve`
4. Ou crie um arquivo `.bat` com:
```batch
@echo off
start "Ollama" ollama serve
```

---

## ⚠️ IMPORTANTE:

- O Ollama **PRECISA estar rodando** para gerar resumos
- Se fechar a janela do Ollama, ele para de funcionar
- Deixe a janela do Ollama aberta enquanto usar o sistema

---

## 🎯 Depois de iniciar:

1. Teste: `php testar_ollama.php`
2. Tente gerar um resumo no sistema
3. Deve funcionar! ✅

