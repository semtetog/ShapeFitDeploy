# 📋 Instruções para Conversão PHP → HTML

## ⚠️ IMPORTANTE: Manter Margens e Safe Areas

Ao converter as páginas PHP para HTML, é **ESSENCIAL** manter os seguintes espaçamentos:

### 1. 📱 **Margem Superior nas Páginas** (Para não ficar colado na status bar)

**O container principal (`.app-container` ou `.container`) PRECISA ter:**

```css
.app-container,
.container {
    padding-top: env(safe-area-inset-top) 24px calc(60px + 20px + env(safe-area-inset-bottom)) 24px;
    /* OU separado: */
    padding-top: env(safe-area-inset-top);
    padding-left: 24px;
    padding-right: 24px;
    padding-bottom: calc(60px + 20px + env(safe-area-inset-bottom));
}
```

**Por quê?**
- `env(safe-area-inset-top)` cria espaço no topo para não ficar colado na status bar do iPhone/Android
- `24px` nas laterais para margens laterais
- `calc(60px + 20px + env(safe-area-inset-bottom))` embaixo para espaço do bottom nav + safe area

### 2. 📱 **Espaço Embaixo do Bottom Nav** (Para o menu do iPhone/Android)

**O `.bottom-nav` PRECISA ter:**

```css
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    
    /* ESSENCIAL: Espaço embaixo para o menu do iPhone/Android */
    padding-bottom: calc(12px + env(safe-area-inset-bottom));
    
    /* Outros estilos... */
    padding: 12px 0 calc(12px + env(safe-area-inset-bottom)) 0;
    background: rgba(24, 24, 24, 0.85);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1000;
}
```

**Por quê?**
- `env(safe-area-inset-bottom)` cria espaço extra embaixo para não ficar colado no menu de gestos do iPhone/Android
- No iPhone X+ e Android moderno, há um menu de gestos na parte inferior que precisa de espaço
- Sem isso, o bottom nav fica colado no menu do sistema

### 3. 📱 **Meta Tag Viewport (OBRIGATÓRIA)**

**O `<head>` PRECISA ter:**

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no">
```

**Por quê?**
- `viewport-fit=cover` é ESSENCIAL para que `env(safe-area-inset-*)` funcione
- Sem isso, as safe areas não funcionam e o layout fica errado

### 4. 📱 **Regras Adicionais para Android**

**Se detectar Android (classe `.android-mobile` no body), adicionar:**

```css
/* No arquivo CSS global ou inline */
.android-mobile .app-container,
.android-mobile .container {
    padding-top: calc(env(safe-area-inset-top) + 20px) !important;
}

.android-mobile .bottom-nav {
    padding-bottom: calc(12px + env(safe-area-inset-bottom) + 20px) !important;
}
```

### 📝 **Resumo Rápido**

✅ **Container principal:**
```css
padding-top: env(safe-area-inset-top);
padding-bottom: calc(60px + 20px + env(safe-area-inset-bottom));
```

✅ **Bottom nav:**
```css
padding-bottom: calc(12px + env(safe-area-inset-bottom));
```

✅ **Viewport meta tag:**
```html
<meta name="viewport" content="..., viewport-fit=cover, ...">
```

### ❌ **O QUE NÃO FAZER**

- ❌ NÃO remover `env(safe-area-inset-bottom)` do `.bottom-nav`
- ❌ NÃO remover `env(safe-area-inset-top)` do `.app-container`
- ❌ NÃO esquecer `viewport-fit=cover` no meta viewport
- ❌ NÃO usar valores fixos (ex: `padding-bottom: 20px`) - sempre usar `calc()` com `env()`

---

**Referência:** Veja os arquivos originais:
- `includes/layout_bottom_nav.php` (linha 32)
- `assets/css/base/_global.css` (linha 79)
- `assets/css/base/_android.css` (linhas 7-22)

