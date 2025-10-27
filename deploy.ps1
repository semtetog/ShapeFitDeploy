# Deploy Script
Set-Location "C:\Users\Brenno\Desktop\APPSHAPEFITCURSOR"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  DEPLOY VIEW_USER CSS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "1. Verificando status..." -ForegroundColor Yellow
git status --short

Write-Host ""
Write-Host "2. Adicionando arquivos..." -ForegroundColor Yellow
git add admin/view_user.php
git add admin/assets/css/view_user_addon.css
git add DEPLOY-VIEW-USER.bat

Write-Host ""
Write-Host "3. Fazendo commit..." -ForegroundColor Yellow
git commit -m "feat: CSS moderno e responsivo completo para view_user"

Write-Host ""
Write-Host "4. Fazendo push..." -ForegroundColor Yellow
git push origin main

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  DEPLOY CONCLUIDO!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

Read-Host "Pressione Enter para sair"

