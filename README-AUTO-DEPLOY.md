# 🚀 Auto-Deploy 100% Automático

## Como Usar

### Método 1: Duplo Clique (Mais Fácil)
1. Dê **duplo clique** no arquivo `INICIAR-AUTO-DEPLOY.bat`
2. Pronto! Agora qualquer alteração que você fizer será automaticamente enviada ao Git

### Método 2: PowerShell
```powershell
.\auto-deploy.ps1
```

### Método 3: Executar em Background (Invisível)
```powershell
Start-Process powershell -ArgumentList "-ExecutionPolicy Bypass -WindowStyle Hidden -File auto-deploy.ps1"
```

## O Que Acontece Automaticamente

✅ **Monitora** todas as alterações em arquivos PHP, JSX, JS, CSS, SQL, etc.  
✅ **Ignora** automaticamente: `.git`, `node_modules`, `uploads`, `sessions`, `logs`  
✅ **Cooldown** de 5 segundos entre commits para evitar spam  
✅ **Commita** automaticamente com mensagem descritiva  
✅ **Faz Push** automaticamente para o GitHub (via hook post-commit)  

## Proteções Inteligentes

🛡️ **Ignora mudanças na pasta `.git`** - Evita loop infinito de commits  
🛡️ **Cooldown de 5 segundos** - Agrupa múltiplas mudanças em um único commit  
🛡️ **Verifica mudanças reais** - Só commita se houver alterações de fato  

## Exemplo de Commits Automáticos

```
Auto-deploy: Changed - progress.php - 2025-10-27 14:32:15
Auto-deploy: Created - new-feature.php - 2025-10-27 14:35:22
Auto-deploy: Deleted - old-file.php - 2025-10-27 14:38:45
```

## Para Parar o Auto-Deploy

**Opção 1:** Pressione `Ctrl+C` na janela do PowerShell  
**Opção 2:** Feche a janela do PowerShell  
**Opção 3:** Execute: `Get-Process powershell | Where-Object {$_.MainWindowTitle -like "*AUTO-DEPLOY*"} | Stop-Process`

## Dica Pro

Para iniciar o auto-deploy automaticamente quando ligar o computador:
1. Pressione `Win+R`
2. Digite: `shell:startup`
3. Crie um atalho do `INICIAR-AUTO-DEPLOY.bat` nessa pasta

---

⚠️ **Importante:** O auto-deploy commitará suas mudanças após salvar os arquivos. Certifique-se de que seu código está funcionando antes de salvar!

## Solução de Problemas

### Auto-deploy não para de commitar
- Certifique-se de fechar a janela do PowerShell completamente
- Execute: `Get-Process powershell | Stop-Process -Force` (cuidado: fecha TODOS os PowerShell)

### Commits muito frequentes
- O sistema tem um cooldown de 5 segundos entre commits
- Múltiplas alterações rápidas serão agrupadas automaticamente
