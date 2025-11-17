#!/bin/bash
# Script para sincronizar código com Android
# Uso: ./sync-android.sh

echo "🔄 Sincronizando código com Android..."

# Copiar arquivos para www/ (exceto pastas que não devem ir)
echo "📦 Copiando arquivos para www/..."
rm -rf www
mkdir -p www

# Copiar tudo exceto pastas específicas
rsync -av --exclude='node_modules' \
          --exclude='android' \
          --exclude='www' \
          --exclude='ios' \
          --exclude='*.log' \
          --exclude='package-lock.json' \
          ./ www/

echo "✅ Arquivos copiados para www/"

# Sincronizar com Capacitor
echo "🔄 Sincronizando com Capacitor..."
npx cap sync

if [ $? -eq 0 ]; then
    echo "✅ Sincronização concluída!"
    echo "💡 Para abrir no Android Studio, execute: npx cap open android"
else
    echo "❌ Erro ao sincronizar!"
    exit 1
fi

