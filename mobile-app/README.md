# 📱 ShapeFit Mobile App

App mobile do ShapeFit empacotado com Capacitor.

## 🚀 Instalação e Uso

### 1. Instalar Dependências

```bash
npm install
```

### 2. Sincronizar com Capacitor

```bash
npx cap sync
```

### 3. Abrir no Android Studio

```bash
npx cap open android
```

### 4. Build e Deploy

No Android Studio:
- Conecte um dispositivo ou inicie um emulador
- Clique em "Run" (▶️)
- O app será instalado e executado

## 📦 Gerar APK

### Para Teste (Debug)
```bash
cd android
./gradlew assembleDebug
```
APK estará em: `android/app/build/outputs/apk/debug/`

### Para Produção (Release)
No Android Studio:
- Build > Generate Signed Bundle / APK
- Escolha AAB (recomendado para Play Store) ou APK
- Configure sua keystore
- Build

## ⚙️ Configuração

- **capacitor.config.json** - Configuração do Capacitor
- **package.json** - Dependências npm
- **mobile-app/** - Código do app (webDir)

## 📝 Estrutura

```
mobile-app/
├── index.html           # Entry point
├── sw.js                # Service Worker (offline)
├── auth/                # Autenticação
├── includes/            # PHP compartilhado
├── assets/              # CSS, JS, imagens
├── api/                 # Endpoints API
├── actions/             # Processamento de ações
└── [páginas PHP]       # Páginas do app
```

## 🔧 Comandos Úteis

```bash
# Sincronizar mudanças
npx cap sync

# Abrir Android Studio
npx cap open android

# Abrir iOS (quando necessário)
npx cap open ios

# Copiar arquivos
npx cap copy

# Ver logs
npx cap run android
```

## ⚠️ Importante

- O app sempre aponta para `https://appshapefit.com`
- Painel admin NÃO está incluído
- Funciona offline com sincronização automática
- Teste sempre em dispositivo real antes de publicar

## 🐛 Troubleshooting

### App não carrega
```bash
npx cap sync --force
```

### Limpar cache
```bash
npx cap sync
cd android
./gradlew clean
```

## 📚 Documentação

Veja `MOBILE_APP_README.md` na pasta raiz para documentação completa.





