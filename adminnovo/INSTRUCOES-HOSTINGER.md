# 🚀 INSTRUÇÕES PARA HOSTINGER

## 📤 UPLOAD DOS ARQUIVOS

### 1. **UPLOAD DA PASTA `dist/`**
- Faça upload de **TODOS** os arquivos da pasta `dist/` para a pasta `admin/` no Hostinger
- **IMPORTANTE:** Use a pasta `dist/` (não a pasta `adminnovo/`)

### 2. **ESTRUTURA FINAL NO HOSTINGER:**
```
admin/
├── index.html
├── .htaccess
├── shapefit-logo.png
├── assets/
│   ├── index-Do72GXcR.css
│   └── index-qeomWcK8.js
└── api/
    ├── auth/
    │   └── login-dev.php
    ├── dashboard.php
    ├── users.php
    ├── foods.php
    ├── recipes.php
    ├── food-classification.php
    ├── diet-plans.php
    ├── challenges.php
    ├── content.php
    ├── rankings.php
    └── user-groups.php
```

## 🔧 CONFIGURAÇÃO

### 1. **VERIFICAR .htaccess**
- Certifique-se de que o arquivo `.htaccess` está na pasta `admin/`
- O arquivo deve ter as regras de rewrite corretas

### 2. **TESTAR ACESSO**
- URL: `https://www.appshapefit.com/admin/`
- Login: `admin` / `admin`

## 🐛 RESOLUÇÃO DE PROBLEMAS

### **Tela Branca:**
1. Verifique se todos os arquivos da pasta `dist/` foram enviados
2. Verifique se o `.htaccess` está correto
3. Verifique se as permissões estão corretas

### **Erro 404:**
1. Verifique se as APIs estão na pasta `api/`
2. Verifique se o `.htaccess` está funcionando

### **Erro de Login:**
1. Verifique se `api/auth/login-dev.php` está funcionando
2. Teste: `https://www.appshapefit.com/admin/api/auth/login-dev.php`

## ✅ CHECKLIST FINAL

- [ ] Upload da pasta `dist/` completo
- [ ] Arquivo `.htaccess` na pasta `admin/`
- [ ] Pasta `api/` com todas as APIs
- [ ] Logo `shapefit-logo.png` na pasta `admin/`
- [ ] Teste de acesso funcionando
- [ ] Login `admin/admin` funcionando

## 🎯 RESULTADO ESPERADO

- ✅ Tela de login com logo ShapeFit
- ✅ Login funcionando com `admin/admin`
- ✅ Dashboard com estatísticas
- ✅ Todas as páginas navegando
- ✅ Design moderno (preto, laranja, glassmorphism)
