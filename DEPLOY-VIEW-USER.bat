@echo off
echo ========================================
echo   DEPLOY VIEW_USER
echo ========================================
echo.

cd /d "C:\Users\Brenno\Desktop\APPSHAPEFITCURSOR"

echo Adicionando arquivos...
git add admin/view_user.php
git add admin/assets/css/view_user_addon.css

echo.
echo Fazendo commit...
git commit -m "feat: CSS moderno e responsivo para view_user"

echo.
echo Fazendo push...
git push origin main

echo.
echo ========================================
echo   DEPLOY CONCLUIDO!
echo ========================================
echo.
pause

