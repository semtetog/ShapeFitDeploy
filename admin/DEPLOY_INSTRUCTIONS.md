# Instruções de Deploy - Aba Rotina

## ✅ Checklist de Arquivos Modificados/Criados

### Arquivos Modificados
- ✅ `admin/view_user.php` - Aba Rotina completamente refatorada

### Arquivos Criados
- ✅ `admin/api/routine_missions_crud.php` - Backend CRUD para missões
- ✅ `admin/migrations/create_routine_missions_table.sql` - Script SQL para criar tabela
- ✅ `admin/ROTINA_TAB_README.md` - Documentação completa
- ✅ `admin/DEPLOY_INSTRUCTIONS.md` - Este arquivo

## 📋 Passos para Deploy

### 1. Backup do Banco de Dados
```bash
mysqldump -u seu_usuario -p seu_banco > backup_antes_rotina_$(date +%Y%m%d).sql
```

### 2. Executar Script SQL

**Opção A: Via linha de comando**
```bash
mysql -u seu_usuario -p seu_banco < admin/migrations/create_routine_missions_table.sql
```

**Opção B: Via phpMyAdmin**
1. Acesse phpMyAdmin
2. Selecione o banco de dados
3. Vá em "SQL"
4. Cole o conteúdo de `create_routine_missions_table.sql`
5. Execute

### 3. Verificar Criação da Tabela

Execute no MySQL:
```sql
DESCRIBE sf_routine_missions;
SELECT * FROM sf_routine_missions;
```

Você deve ver 7 missões padrão já inseridas.

### 4. Fazer Upload dos Arquivos

**Estrutura no servidor:**
```
/public_html/admin/
├── view_user.php (substituir o existente)
├── api/
│   └── routine_missions_crud.php (novo)
└── migrations/
    └── create_routine_missions_table.sql (novo - opcional no servidor)
```

**Via FTP/SFTP:**
```bash
# Fazer backup do arquivo original
cp view_user.php view_user.php.backup

# Upload dos novos arquivos
# Usar seu cliente FTP preferido (FileZilla, WinSCP, etc.)
```

**Via SSH (se disponível):**
```bash
# Conectar ao servidor
ssh usuario@seu-servidor.com

# Navegar até o diretório
cd /caminho/para/admin

# Fazer backup
cp view_user.php view_user.php.backup

# Upload via scp (do seu computador local)
scp view_user.php usuario@servidor:/caminho/para/admin/
scp -r api usuario@servidor:/caminho/para/admin/
```

### 5. Configurar Permissões

```bash
# No servidor, via SSH
chmod 644 view_user.php
chmod 644 api/routine_missions_crud.php
```

### 6. Verificar config.php

Certifique-se de que o arquivo `config.php` existe e tem as credenciais corretas:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'seu_banco');
?>
```

O arquivo `routine_missions_crud.php` usa:
```php
require_once __DIR__ . '/../config.php';
```

### 7. Testar a Aplicação

1. **Acessar o painel admin**
   - URL: `https://seu-dominio.com/admin/view_user.php?id=ID_DO_USUARIO`

2. **Abrir a aba "Rotina"**
   - Verificar se o card de resumo carrega
   - Verificar se o calendário é renderizado
   - Clicar em um dia do calendário
   - Verificar se os detalhes do dia aparecem

3. **Testar CRUD de Missões**
   - Rolar até "Gerenciar Missões de Rotina"
   - Verificar se as 7 missões padrão aparecem
   - Clicar em "Adicionar Missão"
   - Preencher o formulário e salvar
   - Verificar se a nova missão aparece na lista
   - Testar editar uma missão
   - Testar excluir uma missão

4. **Verificar Console do Navegador**
   - Pressione F12 para abrir DevTools
   - Vá em "Console"
   - Não deve haver erros JavaScript em vermelho
   - Verifique as requisições AJAX na aba "Network"

## 🔍 Verificações de Segurança

### Validar Autenticação
```php
// Testar acessando diretamente a API sem estar logado
// Deve retornar erro 403
curl https://seu-dominio.com/admin/api/routine_missions_crud.php?action=list
```

Resposta esperada:
```json
{"success":false,"message":"Acesso negado"}
```

### Validar Permissões de Usuário

Certifique-se de que apenas usuários com `user_role = 'admin'` podem acessar.

## 🐛 Resolução de Problemas Comuns

### Erro: "Table 'sf_routine_missions' doesn't exist"
**Solução**: Execute o script SQL novamente.

### Erro: "Call to undefined function json_encode()"
**Solução**: Instale/ative a extensão JSON do PHP.
```bash
# Ubuntu/Debian
sudo apt-get install php-json
sudo service apache2 restart
```

### Erro 500 ao acessar a API
**Soluções**:
1. Verificar logs do Apache/Nginx
   ```bash
   tail -f /var/log/apache2/error.log
   ```
2. Verificar se o arquivo `config.php` existe
3. Verificar credenciais do banco de dados

### Calendário não renderiza
**Soluções**:
1. Abrir Console do navegador (F12)
2. Verificar se há erros JavaScript
3. Verificar se os dados PHP estão sendo passados:
   ```javascript
   console.log(routineLogData);
   console.log(exerciseData);
   console.log(sleepData);
   ```

### Estilos CSS não aplicados
**Solução**: Limpar cache do navegador (Ctrl+Shift+R ou Cmd+Shift+R)

## 📊 Validação Final

Execute este checklist após o deploy:

- [ ] Tabela `sf_routine_missions` criada no banco
- [ ] 7 missões padrão inseridas
- [ ] Arquivo `view_user.php` atualizado no servidor
- [ ] Arquivo `routine_missions_crud.php` criado em `admin/api/`
- [ ] Aba "Rotina" acessível no painel
- [ ] Card de resumo exibindo dados
- [ ] Calendário renderizando corretamente
- [ ] Clique em dia do calendário funciona
- [ ] Detalhes do dia aparecem (missões, atividades, sono)
- [ ] Botão "Adicionar Missão" funciona
- [ ] Modal de missão abre corretamente
- [ ] Criação de missão funciona via AJAX
- [ ] Edição de missão funciona
- [ ] Exclusão de missão funciona
- [ ] Tabela de missões atualiza após operações CRUD
- [ ] Sem erros no console do navegador
- [ ] Sem erros nos logs do servidor

## 🔄 Rollback (Se Necessário)

Se algo der errado:

1. **Restaurar arquivo view_user.php**
   ```bash
   cp view_user.php.backup view_user.php
   ```

2. **Remover tabela criada**
   ```sql
   DROP TABLE sf_routine_missions;
   ```

3. **Restaurar backup do banco**
   ```bash
   mysql -u seu_usuario -p seu_banco < backup_antes_rotina_YYYYMMDD.sql
   ```

## 📞 Suporte

Em caso de problemas:
1. Verificar logs do servidor
2. Verificar console do navegador
3. Revisar o arquivo `ROTINA_TAB_README.md` para mais detalhes
4. Verificar se todas as dependências estão instaladas

## ✨ Próximos Passos

Após o deploy bem-sucedido:
1. Treinar nutricionistas no uso da nova aba
2. Monitorar uso e performance
3. Coletar feedback dos usuários
4. Implementar melhorias futuras conforme necessário

---

**Deploy realizado em**: _____/_____/_____
**Por**: ___________________________
**Status**: ⬜ Sucesso  ⬜ Falha  ⬜ Parcial

**Notas adicionais**:
_____________________________________________
_____________________________________________
_____________________________________________

