# 📱 ShapeFit Mobile App - Guia de Build

## ✅ O que foi criado

Estrutura completa do app mobile na pasta `app/` com:
- ✅ Sistema offline-first com IndexedDB
- ✅ Service Worker para cache
- ✅ Sincronização automática
- ✅ Páginas principais (Dashboard, Diary, Recipes, Progress, Settings)
- ✅ Sistema de navegação
- ✅ Autenticação
- ✅ Configuração do Capacitor

## 🚀 Como fazer o build para Android

### Passo 1: Instalar dependências

```bash
npm install
```

### Passo 2: Configurar API Base URL

Edite `app/config.js` e altere:
```javascript
API_BASE_URL: 'https://SEU_DOMINIO.com/api',
```

### Passo 3: Adicionar plataforma Android

```bash
npx cap add android
```

### Passo 4: Sincronizar arquivos

```bash
npx cap sync
```

### Passo 5: Abrir no Android Studio

```bash
npx cap open android
```

### Passo 6: Build no Android Studio

1. Abra o Android Studio
2. Aguarde o Gradle sync
3. Clique em **Build > Build Bundle(s) / APK(s) > Build APK(s)**
4. Ou clique em **Run > Run 'app'** para testar no emulador/dispositivo

## 📋 Checklist antes de publicar

- [ ] Configurar `API_BASE_URL` no `app/config.js`
- [ ] Testar login
- [ ] Testar offline (desligar internet)
- [ ] Testar sincronização
- [ ] Configurar ícone do app
- [ ] Configurar nome do app
- [ ] Gerar assinatura para Play Store
- [ ] Testar em dispositivo real

## 🔧 Configurações importantes

### Android (android/app/build.gradle)

```gradle
android {
    defaultConfig {
        applicationId "com.shapefit.app"
        minSdkVersion 22
        targetSdkVersion 33
    }
}
```

### Ícone do App

Coloque os ícones em:
- `android/app/src/main/res/mipmap-*/ic_launcher.png`
- Tamanhos: 48x48, 72x72, 96x96, 144x144, 192x192

### Nome do App

Edite `android/app/src/main/res/values/strings.xml`:
```xml
<string name="app_name">ShapeFit</string>
```

## 🌐 API REST Necessária

O app precisa dos seguintes endpoints:

- `POST /api/auth/login` - Login
- `GET /api/user/profile` - Dados do usuário
- `GET /api/dashboard?date=YYYY-MM-DD` - Dados do dashboard
- `GET /api/diary/meals?date=YYYY-MM-DD` - Refeições do dia
- `POST /api/diary/meals` - Adicionar refeição
- `PUT /api/diary/meals/:id` - Editar refeição
- `DELETE /api/diary/meals/:id` - Deletar refeição
- `GET /api/recipes?search=termo` - Buscar receitas
- `POST /api/water` - Adicionar água
- `POST /api/routine/complete` - Completar rotina
- `POST /api/sync` - Sincronização em lote

## ⚠️ Importante

1. **NUNCA incluir `/admin/*` no app**
2. Todas as requisições devem passar pela API REST
3. Testar offline extensivamente
4. Usar HTTPS sempre
5. Implementar autenticação JWT na API

## 🐛 Troubleshooting

### Erro: "Cannot find module"
- Execute `npx cap sync` novamente

### App não carrega
- Verifique se `API_BASE_URL` está correto
- Verifique console do navegador (Chrome DevTools)

### Offline não funciona
- Verifique se Service Worker está registrado
- Verifique console para erros

### Build falha
- Verifique se Android Studio está atualizado
- Verifique se Java JDK está instalado
- Limpe o projeto: `cd android && ./gradlew clean`

## 📞 Próximos Passos

1. Implementar todos os endpoints da API
2. Adicionar mais páginas (Progress, Profile, etc)
3. Testar em dispositivos reais
4. Preparar para Play Store
5. Configurar assinatura
6. Publicar!

