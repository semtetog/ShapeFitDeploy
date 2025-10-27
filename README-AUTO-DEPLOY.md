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
✅ **Commita** automaticamente com mensagem descritiva  
✅ **Faz Push** automaticamente para o GitHub  

## Exemplo de Commits Automáticos

```
Auto-deploy: Changed - progress.php - 2025-10-27 14:32:15
Auto-deploy: Created - new-feature.php - 2025-10-27 14:35:22
Auto-deploy: Deleted - old-file.php - 2025-10-27 14:38:45
```

## Para Parar o Auto-Deploy

Pressione `Ctrl+C` na janela do PowerShell ou simplesmente feche a janela.

## Dica Pro

Para iniciar o auto-deploy automaticamente quando ligar o computador:
1. Pressione `Win+R`
2. Digite: `shell:startup`
3. Crie um atalho do `INICIAR-AUTO-DEPLOY.bat` nessa pasta

---

⚠️ **Importante:** O auto-deploy commitará TUDO que você alterar. Certifique-se de que seu código está funcionando antes de salvar!

