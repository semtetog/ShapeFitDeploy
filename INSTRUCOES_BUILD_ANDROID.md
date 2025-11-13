# 🚀 Instruções Completas - Build Android

## 📋 Pré-requisitos

1. ✅ Node.js instalado (v16 ou superior)
2. ✅ Android Studio instalado
3. ✅ Java JDK 11 ou superior
4. ✅ Git instalado

## 🔧 Passo a Passo

### 1. Instalar Dependências

```bash
npm install
```

Isso vai instalar:
- @capacitor/core
- @capacitor/cli
- @capacitor/android
- @capacitor/app
- @capacitor/network
- @capacitor/splash-screen

### 2. Configurar API Base URL

Edite `app/config.js`:

```javascript
API_BASE_URL: 'https://SEU_DOMINIO.com/api',
```

**IMPORTANTE**: Substitua `SEU_DOMINIO.com` pelo seu domínio real da Hostinger!

### 3. Adicionar Plataforma Android

```bash
npx cap add android
```

### 4. Sincronizar Arquivos

```bash
npx cap sync
```

Este comando:
- Copia arquivos da pasta `app/` para `android/app/src/main/assets/public/`
- Atualiza configurações do Android
- Sincroniza plugins do Capacitor

### 5. Abrir no Android Studio

```bash
npx cap open android
```

Isso vai abrir o Android Studio automaticamente.

### 6. Configurar no Android Studio

#### 6.1. Aguardar Gradle Sync
- O Android Studio vai sincronizar automaticamente
- Aguarde até aparecer "Gradle sync finished"

#### 6.2. Configurar Assinatura (Opcional para teste)
- Para testar localmente, não precisa assinar
- Para publicar na Play Store, precisa criar keystore

#### 6.3. Configurar Nome e Ícone
- Nome: `android/app/src/main/res/values/strings.xml`
- Ícone: Substituir arquivos em `android/app/src/main/res/mipmap-*/`

### 7. Build APK

#### Opção A: Build direto (para teste)
1. No Android Studio: **Build > Build Bundle(s) / APK(s) > Build APK(s)**
2. Aguarde o build
3. APK estará em: `android/app/build/outputs/apk/debug/app-debug.apk`

#### Opção B: Build assinado (para Play Store)
1. **Build > Generate Signed Bundle / APK**
2. Selecione **APK**
3. Crie ou selecione keystore
4. Configure senha
5. Selecione **release**
6. Clique em **Finish**

### 8. Testar no Emulador/Dispositivo

1. Conecte dispositivo via USB OU inicie emulador
2. No Android Studio: **Run > Run 'app'**
3. Ou clique no botão ▶️ verde

## 🔐 Configurar Assinatura para Play Store

### Criar Keystore

```bash
keytool -genkey -v -keystore shapefit-release.keystore -alias shapefit -keyalg RSA -keysize 2048 -validity 10000
```

### Configurar no Android

Edite `android/app/build.gradle`:

```gradle
android {
    signingConfigs {
        release {
            storeFile file('shapefit-release.keystore')
            storePassword 'SUA_SENHA'
            keyAlias 'shapefit'
            keyPassword 'SUA_SENHA'
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
        }
    }
}
```

## 📱 Configurações Importantes

### AndroidManifest.xml

Verificar permissões em `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

### build.gradle

Verificar em `android/app/build.gradle`:

```gradle
android {
    defaultConfig {
        applicationId "com.shapefit.app"
        minSdkVersion 22
        targetSdkVersion 33
        versionCode 1
        versionName "1.0.0"
    }
}
```

## 🧪 Testar Offline

1. Abra o app no dispositivo
2. Faça login
3. Desligue WiFi/dados móveis
4. Tente usar todas as funcionalidades
5. Verifique se dados são salvos
6. Ligue internet novamente
7. Verifique se sincroniza automaticamente

## 🐛 Problemas Comuns

### Erro: "SDK location not found"
- Abra Android Studio
- **File > Settings > Appearance & Behavior > System Settings > Android SDK**
- Copie o caminho do SDK
- Crie arquivo `android/local.properties`:
```
sdk.dir=C:\\Users\\SEU_USUARIO\\AppData\\Local\\Android\\Sdk
```

### Erro: "Gradle sync failed"
- **File > Invalidate Caches / Restart**
- Ou: `cd android && ./gradlew clean`

### App não carrega
- Verifique `API_BASE_URL` no `app/config.js`
- Abra Chrome DevTools no dispositivo (chrome://inspect)
- Verifique console para erros

### Build falha
- Verifique se Java JDK está instalado
- Verifique versão do Android Studio (deve ser recente)
- Limpe projeto: `cd android && ./gradlew clean`

## ✅ Checklist Final

Antes de publicar na Play Store:

- [ ] Testar em dispositivo real
- [ ] Testar offline completo
- [ ] Testar sincronização
- [ ] Verificar se não há referências a `/admin/*`
- [ ] Configurar ícone do app
- [ ] Configurar nome do app
- [ ] Gerar APK assinado
- [ ] Testar APK assinado
- [ ] Preparar screenshots
- [ ] Preparar descrição
- [ ] Configurar política de privacidade

## 🎉 Pronto!

Agora você pode:
1. Testar o app no seu dispositivo
2. Fazer ajustes necessários
3. Publicar na Play Store quando estiver pronto!

