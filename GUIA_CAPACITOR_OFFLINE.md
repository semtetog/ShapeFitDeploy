# 🚀 Guia Completo: Capacitor + App Offline-First

## 📋 Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│                    HOSTINGER (Servidor)                  │
├─────────────────────────────────────────────────────────┤
│  ┌──────────────────┐      ┌──────────────────┐        │
│  │  Painel Admin    │      │   API REST       │        │
│  │  (PHP/React)     │      │   (PHP)          │        │
│  │  /admin/*        │      │   /api/*         │        │
│  └──────────────────┘      └──────────────────┘        │
│         │                           │                   │
│         └───────────┬───────────────┘                   │
│                     │                                   │
│              ┌──────▼──────┐                            │
│              │  Database   │                            │
│              │  (MySQL)    │                            │
│              └─────────────┘                            │
└─────────────────────────────────────────────────────────┘
                        │
                        │ HTTPS
                        │
┌───────────────────────▼─────────────────────────────────┐
│              APP MOBILE (Capacitor)                      │
├─────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────┐     │
│  │  App Web (HTML/CSS/JS) - Funciona Offline    │     │
│  │  - Service Worker (Cache)                     │     │
│  │  - IndexedDB (Dados Locais)                   │     │
│  │  - Sincronização em Background                │     │
│  └──────────────────────────────────────────────┘     │
│                                                          │
│  ┌──────────────────────────────────────────────┐     │
│  │  Capacitor Plugins                            │     │
│  │  - Network (Detectar Conexão)                │     │
│  │  - Storage (Persistência Local)               │     │
│  │  - Background Sync                            │     │
│  └──────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘
```

## 🎯 Estratégia de Implementação

### 1. **Separação de Rotas**

O app mobile NUNCA deve acessar `/admin/*`. Tudo deve passar pela API REST.

### 2. **Arquitetura Offline-First**

- **IndexedDB**: Armazena todos os dados do usuário localmente
- **Service Worker**: Cache de assets (HTML, CSS, JS, imagens)
- **Sincronização**: Quando online, sincroniza dados com servidor
- **Queue de Ações**: Ações offline são enfileiradas e executadas quando online

### 3. **API REST Necessária**

Endpoints que o app precisa:
- `POST /api/auth/login` - Autenticação
- `GET /api/user/profile` - Dados do usuário
- `GET /api/diary/meals?date=YYYY-MM-DD` - Refeições
- `POST /api/diary/meals` - Adicionar refeição
- `PUT /api/diary/meals/:id` - Editar refeição
- `DELETE /api/diary/meals/:id` - Deletar refeição
- `GET /api/foods/search?q=termo` - Buscar alimentos
- `GET /api/recipes` - Receitas
- `POST /api/sync` - Sincronização em lote

## 📦 Estrutura de Pastas Recomendada

```
APPSHAPEFITCURSOR/
├── admin/                    # Painel Admin (NÃO vai no app)
├── api/                      # API REST (vai no app)
├── app/                      # App Mobile (NOVO)
│   ├── index.html           # Entry point do app
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   │   ├── app.js       # Lógica principal
│   │   │   ├── offline.js   # Gerenciamento offline
│   │   │   ├── sync.js      # Sincronização
│   │   │   └── db.js        # IndexedDB
│   │   └── images/
│   ├── pages/               # Páginas do app
│   │   ├── login.html
│   │   ├── dashboard.html
│   │   ├── diary.html
│   │   └── ...
│   └── sw.js                # Service Worker melhorado
├── www/                      # Build do Capacitor (gerado)
└── capacitor.config.json
```

## 🔧 Passos de Implementação

### Passo 1: Configurar Capacitor

```bash
npm install @capacitor/core @capacitor/cli
npm install @capacitor/ios @capacitor/android
npx cap init
```

### Passo 2: Criar Estrutura do App Mobile

Separar as páginas do usuário em uma pasta `app/` que será o `webDir` do Capacitor.

### Passo 3: Implementar Service Worker Avançado

Cache estratégico:
- Assets estáticos: Cache permanente
- Dados da API: Cache com validação
- Imagens: Cache com limite de tamanho

### Passo 4: Implementar IndexedDB

Armazenar:
- Dados do usuário
- Refeições
- Alimentos
- Receitas
- Histórico

### Passo 5: Sistema de Sincronização

- Detectar quando volta online
- Sincronizar dados pendentes
- Resolver conflitos (última modificação vence)

### Passo 6: Build e Deploy

```bash
# Build do app
npm run build

# Adicionar plataformas
npx cap add ios
npx cap add android

# Sync
npx cap sync

# Abrir no Xcode/Android Studio
npx cap open ios
npx cap open android
```

## ⚠️ Pontos Importantes

1. **NUNCA incluir `/admin/*` no app**
2. **Todas as requisições devem passar pela API REST**
3. **Validar autenticação em todas as rotas da API**
4. **Implementar rate limiting na API**
5. **Usar HTTPS sempre**
6. **Criptografar dados sensíveis no IndexedDB**

## 🔐 Segurança

- Tokens JWT para autenticação
- Refresh tokens
- Validação de origem (CORS)
- Sanitização de inputs
- SQL Injection prevention

## 📱 Testes

1. Testar offline completo
2. Testar sincronização
3. Testar conflitos de dados
4. Testar performance
5. Testar em dispositivos reais

