# Análise das Diferenças entre refnova.sql e Código Implementado

## Tabelas Relacionadas ao Sistema de Desafios

### ✅ Tabelas que estão corretas:

1. **sf_challenge_groups**
   - ✅ Tem `created_by` int(11) NOT NULL
   - ✅ Tem foreign key para `sf_admins`
   - ✅ Tem coluna `goals` como JSON
   - ✅ Estrutura correta

2. **sf_challenge_group_daily_progress**
   - ✅ Tem `points_breakdown` text DEFAULT NULL
   - ✅ Tem todas as colunas necessárias
   - ✅ Estrutura correta

3. **sf_challenge_group_members**
   - ✅ Estrutura correta
   - ✅ Foreign keys corretas

4. **sf_challenge_user_rank_snapshot**
   - ✅ Tem UNIQUE KEY `unique_challenge_user_snapshot` (`challenge_group_id`,`user_id`)
   - ✅ Estrutura correta

### ❌ Tabela que precisa ser ajustada:

**sf_challenge_notifications**

#### Estrutura no refnova.sql:
```sql
CREATE TABLE `sf_challenge_notifications` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,  -- ❌ Deveria ser challenge_group_id
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,  -- ❌ Não usado no código
  `message` text NOT NULL,
  `type` enum('info','warning','success','achievement') DEFAULT 'info',  -- ❌ Deveria ser notification_type
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
)
```

#### Estrutura esperada no código (includes/functions.php):
```sql
CREATE TABLE `sf_challenge_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_group_id` int(11) NOT NULL,  -- ✅ Nome correto
  `user_id` int(10) UNSIGNED NOT NULL,
  `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL,  -- ✅ Enum correto
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
)
```

## Script de Correção

Execute o arquivo `FIX_CHALLENGE_NOTIFICATIONS_STRUCTURE.sql` para ajustar a estrutura.

## Resumo

- **Tabelas OK**: 4 tabelas estão corretas
- **Tabelas a ajustar**: 1 tabela (sf_challenge_notifications)
- **Ação necessária**: Executar script de migração para ajustar estrutura

