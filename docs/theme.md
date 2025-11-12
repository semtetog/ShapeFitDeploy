# Sistema de Design - Tema Claro

## Visão Geral

Este documento descreve o sistema de design implementado para o tema claro do painel administrativo. O sistema utiliza **tokens de design** para garantir consistência, manutenibilidade e acessibilidade.

## Estrutura de Arquivos

```
admin/assets/css/
├── tokens.css          # Tokens de design (cores, espaçamentos, sombras, etc.)
├── theme-light.css     # Implementação do tema claro usando tokens
├── admin_novo_style.css # Estilos base (tema escuro)
└── ...
```

## Tokens de Design

### Cores

#### Backgrounds
- `--bg`: Cor de fundo principal (`#FFFFFF` no tema claro)
- `--bg-elev`: Cor de fundo elevada para cards/nav (`#F8FAFC` no tema claro)
- `--card`: Cor de fundo para cards (`#FFFFFF` no tema claro)
- `--surface`: Cor de superfície (`#FFFFFF` no tema claro)

#### Textos
- `--text`: Cor de texto principal (`#0F172A` no tema claro)
- `--text-muted`: Cor de texto secundário (`#475569` no tema claro)

#### Bordas
- `--border`: Cor de borda padrão (`#E2E8F0` no tema claro)
- `--ring`: Cor do anel de foco (`#94A3B8` no tema claro)

#### Acento (Laranja)
- `--accent`: Cor de acento principal (`#FF6B00` - **MANTIDO DO TEMA ESCURO**)
- `--accent-hover`: Cor de acento no hover (`#E55D00`)
- `--accent-contrast`: Cor de contraste para acento (`#FFFFFF`)
- `--accent-gradient`: Gradiente laranja (`linear-gradient(45deg, #FFAE00, #F83600)`)

#### Cores Semânticas
- `--success`: Verde para sucesso (`#22C55E`)
- `--danger`: Vermelho para erro (`#EF4444`)
- `--warning`: Amarelo para aviso (`#F59E0B`)
- `--info`: Azul para informação (`#3B82F6`)

### Espaçamentos

```css
--space-1: 4px
--space-2: 8px
--space-3: 12px
--space-4: 16px
--space-5: 20px
--space-6: 24px
--space-7: 28px
--space-8: 32px
--space-10: 40px
--space-12: 48px
--space-16: 64px
```

### Bordas (Radius)

```css
--radius-xs: 4px
--radius-sm: 6px
--radius-md: 12px
--radius-lg: 16px
--radius-xl: 20px
--radius-2xl: 24px
--radius-full: 9999px
```

### Sombras

```css
--shadow-sm: 0 1px 2px rgba(var(--shadow-color), 0.06)
--shadow-md: 0 6px 16px rgba(var(--shadow-color), 0.08)
--shadow-lg: 0 12px 24px rgba(var(--shadow-color), 0.10)
--shadow-xl: 0 20px 40px rgba(var(--shadow-color), 0.12)
--shadow-2xl: 0 24px 48px rgba(var(--shadow-color), 0.15)
```

### Tipografia

```css
--font-family: 'Montserrat', sans-serif
--font-size-xs: 0.75rem (12px)
--font-size-sm: 0.875rem (14px)
--font-size-base: 1rem (16px)
--font-size-lg: 1.125rem (18px)
--font-size-xl: 1.25rem (20px)
--font-size-2xl: 1.5rem (24px)
--font-size-3xl: 1.875rem (30px)
--font-size-4xl: 2.25rem (36px)
```

## Uso dos Tokens

### ✅ Correto

```css
/* Use tokens para cores */
.meu-componente {
  background: var(--card);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-md);
}
```

### ❌ Incorreto

```css
/* NÃO use cores hardcoded */
.meu-componente {
  background: #FFFFFF;
  color: #1A202C;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
}
```

## Componentes

### Cards

```css
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md);
  padding: var(--space-6);
}
```

### Botões

#### Botão Primário
```css
.btn-primary {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--accent-contrast);
  border-radius: var(--radius-lg);
  padding: var(--space-3) var(--space-5);
}
```

#### Botão Secundário
```css
.btn-secondary {
  background: var(--bg-elev);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: var(--radius-lg);
}
```

### Inputs

```css
input {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  color: var(--text);
  padding: var(--space-3) var(--space-4);
}

input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}
```

### Tabelas

```css
table {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
}

thead {
  background: var(--bg-elev);
  border-bottom: 1px solid var(--border);
}

th {
  color: var(--text);
  font-weight: var(--font-weight-semibold);
}

td {
  color: var(--text-muted);
  border-bottom: 1px solid var(--border);
}
```

## Acessibilidade

### Contraste

Todos os textos no tema claro atendem ao mínimo WCAG AA:
- Texto principal (`--text`): `#0F172A` sobre `#FFFFFF` = 15.8:1 ✅
- Texto secundário (`--text-muted`): `#475569` sobre `#FFFFFF` = 7.1:1 ✅

### Foco

Todos os elementos interativos têm estados de foco visíveis:

```css
button:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 2px;
}
```

### Reduced Motion

O sistema respeita `prefers-reduced-motion`:

```css
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

## Toggle de Tema

O tema é controlado via atributo `data-theme` no elemento `<html>`:

```html
<html data-theme="light">
  <!-- Conteúdo -->
</html>
```

### JavaScript (se necessário)

```javascript
// Alternar tema
function toggleTheme() {
  const html = document.documentElement;
  const currentTheme = html.getAttribute('data-theme');
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';
  html.setAttribute('data-theme', newTheme);
  localStorage.setItem('theme', newTheme);
}

// Carregar tema salvo
const savedTheme = localStorage.getItem('theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);
```

## Regras Importantes

### ⚠️ NÃO ALTERE O TEMA ESCURO

O tema escuro deve permanecer **absolutamente idêntico** ao que está agora. Todas as alterações devem ser feitas apenas no tema claro.

### 🎨 Mantenha o Laranja

A cor de acento laranja (`#FF6B00`) é **mantida exatamente igual** em ambos os temas. Não altere esta cor.

### 📐 Use Tokens

Sempre use tokens ao invés de valores hardcoded. Isso garante consistência e facilita manutenção.

### 🎯 Especificidade

Use `html[data-theme="light"]` como prefixo para todas as regras do tema claro para garantir especificidade adequada.

## Checklist de Implementação

Ao adicionar novos componentes ou páginas:

- [ ] Usar tokens de design ao invés de valores hardcoded
- [ ] Garantir contraste adequado (WCAG AA mínimo)
- [ ] Adicionar estados de foco visíveis
- [ ] Testar em diferentes tamanhos de tela (1280px - 1920px)
- [ ] Testar em diferentes níveis de zoom (90% - 125%)
- [ ] Verificar que elementos laranjas permanecem laranjas
- [ ] Garantir que não há textos brancos/cinzas em fundo claro
- [ ] Verificar que todas as bordas são visíveis
- [ ] Garantir sombras consistentes em cards similares

## Troubleshooting

### Elemento não está mudando de cor no tema claro

1. Verifique se o seletor está usando `html[data-theme="light"]`
2. Verifique se há regras mais específicas sobrescrevendo
3. Verifique se está usando tokens ao invés de cores hardcoded

### Texto branco aparecendo no tema claro

1. Adicione regra específica para o elemento:
   ```css
   html[data-theme="light"] .elemento {
     color: var(--text) !important;
   }
   ```

### Borda não aparecendo

1. Verifique se o elemento tem `border: 1px solid var(--border)`
2. Verifique se não há regras removendo bordas

## Recursos

- [WCAG Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [CSS Custom Properties (MDN)](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties)
- [Design Tokens (W3C)](https://www.w3.org/community/design-tokens/)

