# 📱 Build Android - ShapeFit

## ✅ Configuração Completa

A estrutura está configurada e funcionando! A pasta `android/` está dentro de `mobile-app/` (não na raiz).

## 🚀 Como Usar

### Após fazer mudanças no código:

**Windows (PowerShell):**
```powershell
cd mobile-app
.\sync-android.ps1
```

**Linux/Mac:**
```bash
cd mobile-app
chmod +x sync-android.sh
./sync-android.sh
```

**Ou manualmente:**
```bash
cd mobile-app
# 1. Copiar arquivos para www/
# (o script faz isso automaticamente)

# 2. Sincronizar
npx cap sync

# 3. Abrir Android Studio
npx cap open android
```

## 📁 Estrutura

```
mobile-app/
├── www/              # Cópia dos arquivos (não vai pro commit)
├── android/          # Projeto Android (não vai pro commit)
├── capacitor.config.json
└── ...
```

## ⚠️ Importante

- A pasta `www/` é gerada automaticamente (não commitar)
- A pasta `android/` é gerada pelo Capacitor (não commitar)
- Sempre rode `sync-android.ps1` ou `sync-android.sh` após mudanças no código
- Ou copie manualmente os arquivos para `www/` antes de `npx cap sync`

## 🎯 Próximos Passos

1. Abra o Android Studio (já deve ter aberto)
2. Aguarde o Gradle sincronizar
3. Build > Build Bundle(s) / APK(s) > Build APK(s)

