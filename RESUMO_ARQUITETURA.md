# 📐 Resumo da Arquitetura - App Mobile Offline-First

## 🎯 Objetivo

Criar um app mobile nativo (iOS/Android) que:
- ✅ Funciona completamente offline
- ✅ Sincroniza automaticamente quando online
- ✅ Não inclui nada do painel admin
- ✅ Mantém controle total pelo painel admin na Hostinger

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    SERVIDOR (Hostinger)                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Admin Panel  │  │   API REST   │  │  Database    │      │
│  │  /admin/*    │  │   /api/*     │  │   MySQL      │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                          │ HTTPS
                          │
┌─────────────────────────▼───────────────────────────────────┐
│              APP MOBILE (Capacitor)                          │
│  ┌────────────────────────────────────────────────────┐     │
│  │  Web App (HTML/CSS/JS)                             │     │
│  │  - Service Worker (Cache)                          │     │
│  │  - IndexedDB (Dados Locais)                        │     │
│  │  - Sync Manager (Sincronização)                    │     │
│  └────────────────────────────────────────────────────┘     │
│                                                               │
│  ┌────────────────────────────────────────────────────┐     │
│  │  Capacitor Plugins                                  │     │
│  │  - Network (Detectar Conexão)                      │     │
│  │  - Storage (Persistência)                          │     │
│  │  - App (Lifecycle)                                 │     │
│  └────────────────────────────────────────────────────┘     │
└───────────────────────────────────────────────────────────────┘
```

## 📁 Estrutura de Arquivos

```
APPSHAPEFITCURSOR/
├── admin/                    # ❌ NÃO vai no app
│   └── ...                  # Painel admin completo
│
├── app/                      # ✅ Vai no app (webDir do Capacitor)
│   ├── index.html           # Entry point
│   ├── pages/               # Páginas do app
│   │   ├── login.html
│   │   ├── dashboard.html
│   │   ├── diary.html
│   │   └── ...
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   │   ├── db.js        # IndexedDB
│   │   │   ├── offline.js   # Gerenciamento offline
│   │   │   ├── sync.js      # Sincronização
│   │   │   └── app.js       # Lógica principal
│   │   └── images/
│   └── sw.js                # Service Worker
│
├── api/                      # ✅ API REST (acessada pelo app)
│   ├── auth.php
│   ├── user.php
│   ├── diary.php
│   ├── sync.php
│   └── ...
│
└── capacitor.config.json    # Configuração do Capacitor
```

## 🔄 Fluxo de Dados

### Online
1. Usuário faz ação no app
2. App faz requisição para API
3. API salva no banco
4. App atualiza IndexedDB
5. App atualiza UI

### Offline
1. Usuário faz ação no app
2. App salva no IndexedDB
3. App enfileira ação
4. App atualiza UI (com dados locais)
5. Quando volta online: sincroniza automaticamente

## 🔐 Segurança

1. **Autenticação JWT**
   - Token expira em 24h
   - Refresh token para renovar
   - Validação em todas as rotas da API

2. **HTTPS Obrigatório**
   - Nunca usar HTTP
   - Certificado SSL válido

3. **CORS Configurado**
   - Apenas domínio do app permitido
   - Headers apropriados

4. **Validação de Dados**
   - Sanitização de inputs
   - Validação no servidor
   - SQL Injection prevention

## 📱 Funcionalidades do App

### Funciona Offline
- ✅ Visualizar dashboard
- ✅ Adicionar refeições
- ✅ Editar refeições
- ✅ Buscar alimentos (cache)
- ✅ Ver receitas (cache)
- ✅ Ver histórico

### Requer Online
- ⚠️ Login inicial
- ⚠️ Sincronização
- ⚠️ Buscar novos alimentos
- ⚠️ Atualizar perfil

## 🚀 Próximos Passos

1. **Criar estrutura `app/`**
   - Copiar páginas do usuário
   - Remover referências ao admin
   - Adaptar para funcionar offline

2. **Implementar API REST**
   - Criar todos os endpoints necessários
   - Implementar autenticação JWT
   - Testar todos os endpoints

3. **Implementar Offline**
   - Service Worker
   - IndexedDB
   - Sincronização

4. **Testar**
   - Testar offline completo
   - Testar sincronização
   - Testar em dispositivos reais

5. **Build e Deploy**
   - Build iOS
   - Build Android
   - Submeter nas lojas

## ⚠️ Pontos de Atenção

1. **NUNCA incluir `/admin/*` no app**
   - Verificar todos os links
   - Verificar todas as requisições
   - Verificar imports

2. **Todas as requisições devem passar pela API**
   - Não fazer requisições diretas ao banco
   - Não acessar arquivos PHP diretamente
   - Usar apenas `/api/*`

3. **Testar offline extensivamente**
   - Desligar internet
   - Usar todas as funcionalidades
   - Verificar se dados são salvos
   - Verificar sincronização

4. **Performance**
   - Cache de imagens limitado
   - Limpar cache antigo
   - Otimizar queries do IndexedDB

## 📊 Monitoramento

Implementar:
- Logs de sincronização
- Erros de API
- Ações enfileiradas
- Tempo de resposta
- Uso de cache

## 🎉 Resultado Final

Um app mobile nativo que:
- Funciona offline
- Sincroniza automaticamente
- Parece app nativo
- Não inclui admin
- Mantém controle pelo admin

