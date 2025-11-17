# 📴 Funcionamento Offline - ShapeFit App

## Como Funciona

### ✅ Páginas (HTML/PHP)
- **Primeira visita (online)**: Página é carregada do servidor e cacheada automaticamente
- **Visitas seguintes (offline)**: Página é servida do cache do Service Worker
- **Atualização**: Quando volta online, cache é atualizado em background

### 🌐 APIs (AJAX/PHP)
- **Sempre remotas**: APIs sempre apontam para `https://appshapefit.com`
- **Offline**: Retorna erro JSON informando que está offline
- **Nunca cacheadas**: APIs não são cacheadas para garantir dados atualizados

## Estratégia de Cache

1. **Precache**: Páginas críticas (login, register, dashboard) são precacheadas na instalação
2. **Cache Dinâmico**: Outras páginas são cacheadas quando visitadas
3. **Network First**: Tenta buscar da rede primeiro, usa cache se offline
4. **Background Update**: Quando online, atualiza cache em background

## Páginas Críticas Precacheadas

- `index.html` / `index.php`
- `auth/login.php`
- `auth/register.php`
- `main_app.php`
- `onboarding/onboarding.php`
- `diary.php`, `progress.php`, `ranking.php`, etc.

## Como Testar

1. **Primeira vez (online)**:
   - Abra o app
   - Navegue pelas páginas principais
   - Service Worker cacheia automaticamente

2. **Offline**:
   - Desligue a internet
   - Páginas já visitadas funcionam normalmente
   - Páginas não visitadas mostram página offline
   - APIs retornam erro JSON

3. **Voltar online**:
   - Cache é atualizado automaticamente
   - Páginas ficam atualizadas

## ⚠️ Importante

- **Páginas PHP precisam ser processadas no servidor** (por isso `server.url` está configurado)
- **Service Worker cacheia o HTML renderizado** (não o PHP)
- **APIs sempre remotas** para garantir dados atualizados
- **Páginas não visitadas não funcionam offline** (mas mostram fallback)

