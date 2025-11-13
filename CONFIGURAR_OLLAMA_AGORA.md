# 🚀 Configurar Ollama - Passo a Passo

## ✅ Passo 1: Reiniciar o Terminal

Após instalar o Ollama, você precisa **fechar e abrir novamente** o PowerShell/CMD para que o comando `ollama` funcione.

**Faça isso agora:**
1. Feche este terminal
2. Abra um NOVO PowerShell ou CMD
3. Execute: `ollama --version`

Se aparecer a versão, está funcionando! ✅

---

## ✅ Passo 2: Baixar o Modelo

Execute este comando para baixar o modelo recomendado:

```powershell
ollama pull llama3.1:8b
```

**⚠️ IMPORTANTE:** Este modelo tem ~13GB. Vai demorar alguns minutos para baixar.

**Se tiver pouca RAM (< 16GB), use este modelo menor:**
```powershell
ollama pull llama3.1
```

---

## ✅ Passo 3: Verificar Modelos Instalados

```powershell
ollama list
```

Você deve ver o modelo que acabou de baixar na lista.

---

## ✅ Passo 4: Testar o Ollama

Teste rápido:
```powershell
ollama run llama3.1:8b "Olá, você está funcionando?"
```

Ou teste completo com o script:
```powershell
php testar_ollama.php
```

---

## ✅ Passo 5: Garantir que o Serviço está Rodando

O Ollama deve iniciar automaticamente. Se não estiver rodando:

1. **Procure "Ollama" no menu Iniciar** e execute
2. **Ou execute no terminal:**
```powershell
ollama serve
```

O servidor vai rodar em: `http://localhost:11434`

---

## ✅ Passo 6: Testar no Sistema

Agora abra uma resposta de check-in no sistema e clique na aba "Resumo". 
O sistema deve usar o Ollama automaticamente! 🎉

---

## 🔧 Problemas Comuns

### "ollama não é reconhecido"
- **Solução:** Feche e abra um NOVO terminal/PowerShell
- Ou reinicie o computador

### "Porta 11434 já em uso"
- **Solução:** O Ollama já está rodando! Tudo certo ✅

### "Modelo não encontrado"
- **Solução:** Execute `ollama pull llama3.1:8b` novamente

### Erro de memória ao usar o modelo
- **Solução:** Use modelo menor: `ollama pull llama3.1` (sem :8b)
- E altere no código: `admin/ajax_checkin.php` linha 1039 para `$model = 'llama3.1';`

