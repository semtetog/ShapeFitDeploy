@echo off
echo.
echo ========================================
echo   AUTO-DEPLOY SHAPEFITCURSOR
echo ========================================
echo.
echo Iniciando monitoramento automatico...
echo Todas as alteracoes serao enviadas automaticamente ao Git!
echo.
echo Pressione Ctrl+C para parar
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0auto-deploy.ps1"

pause

