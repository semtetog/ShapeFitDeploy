# 💪 Guia de Integração: Rotinas de Exercício com Tempo de Treino

## 🎯 Objetivo

Integrar o sistema de rotinas existente com o tracking de tempo de treino, permitindo que:
1. Usuário complete rotinas de exercício
2. Sistema pergunte quanto tempo durou
3. Tempo seja automaticamente somado em `workout_hours` ou `cardio_hours`
4. Tudo apareça na aba de progresso

---

## 📦 Arquivos Criados

### 1. **DATABASE_UPDATE_PROGRESS_FIXED.sql** ⭐
- **Corrige** o erro SQL da coluna ambígua
- Adiciona colunas para identificar exercícios em `sf_routine_items`
- Adiciona coluna para duração em `sf_user_routine_log`
- Cria **TRIGGERS automáticos** para somar/subtrair tempo
- Cria tabela de metas `sf_user_goals`

### 2. **actions/complete_routine_item_v2.php**
- Novo endpoint que detecta se é exercício
- Pede duração se necessário
- Registra duração no log

### 3. **assets/js/routine_with_exercise_time.js**
- JavaScript com modal de duração
- Lógica de completar exercícios
- Toast notifications

### 4. **assets/css/exercise_modal.css**
- Estilos do modal de duração
- Responsivo e moderno

---

## 🚀 Implementação (4 Passos)

### **PASSO 1: Banco de Dados (10 min)**

1. **Backup primeiro!**
   ```bash
   # No phpMyAdmin: Exportar → SQL
   ```

2. Execute `DATABASE_UPDATE_PROGRESS_FIXED.sql` no phpMyAdmin

3. Verifique se foi criado:
   ```sql
   -- Verificar novas colunas
   DESCRIBE sf_routine_items;
   -- Deve mostrar: is_exercise, exercise_type
   
   DESCRIBE sf_user_routine_log;
   -- Deve mostrar: exercise_duration_minutes
   
   DESCRIBE sf_user_daily_tracking;
   -- Deve mostrar: steps_daily, workout_hours, cardio_hours, sleep_hours
   
   -- Verificar triggers
   SHOW TRIGGERS LIKE 'sf_user_routine_log';
   ```

---

### **PASSO 2: Upload de Arquivos (5 min)**

Via FTP/Gerenciador de Arquivos:

```
├── actions/
│   └── complete_routine_item_v2.php  ← Upload
│
├── assets/
│   ├── js/
│   │   └── routine_with_exercise_time.js  ← Upload
│   └── css/
│       └── exercise_modal.css  ← Upload
│
├── progress_v2.php  ← Upload (do pacote anterior)
└── update_daily_tracking.php  ← Upload (do pacote anterior)
```

---

### **PASSO 3: Atualizar `routine.php` (2 min)**

Edite o arquivo `routine.php` e faça as seguintes alterações:

**A) No `<head>`, adicione o CSS do modal:**

```php
<?php
$page_title = "Sua Rotina";
$extra_css = ['exercise_modal.css'];  // ← ADICIONE ESTA LINHA
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>
```

**B) No final, antes de `</body>`, substitua o JavaScript:**

```php
<!-- ANTES (remova ou comente): -->
<!-- <script src="<?php echo BASE_APP_URL; ?>/assets/js/routine_logic.js"></script> -->

<!-- DEPOIS (adicione): -->
<script>
const BASE_URL = '<?php echo BASE_APP_URL; ?>';
</script>
<script src="<?php echo BASE_APP_URL; ?>/assets/js/routine_with_exercise_time.js"></script>
```

---

### **PASSO 4: Configurar Quais Rotinas São Exercícios (5 min)**

#### **Opção A: Via SQL (Rápido)**

Execute no phpMyAdmin:

```sql
-- Exemplo: Marcar rotinas de exercício do onboarding
-- Substitua os IDs pelas rotinas reais do seu sistema

-- TREINO (musculação, crossfit, etc)
UPDATE sf_routine_items 
SET is_exercise = 1, exercise_type = 'workout'
WHERE title LIKE '%musculação%' 
   OR title LIKE '%academia%'
   OR title LIKE '%crossfit%'
   OR title LIKE '%treino%';

-- CARDIO (corrida, bike, natação, etc)
UPDATE sf_routine_items 
SET is_exercise = 1, exercise_type = 'cardio'
WHERE title LIKE '%corrida%' 
   OR title LIKE '%bike%'
   OR title LIKE '%natação%'
   OR title LIKE '%caminhada%'
   OR title LIKE '%cardio%';

-- Ver resultado
SELECT id, title, is_exercise, exercise_type 
FROM sf_routine_items 
WHERE is_exercise = 1;
```

#### **Opção B: Via Interface (Futuro - Opcional)**

Criar uma tela de admin para configurar as rotinas.

---

## 🔄 Como Funciona?

### **Fluxo Completo:**

```
1. Usuário completa rotina de exercício
   ↓
2. Sistema detecta que é exercício (is_exercise = 1)
   ↓
3. Modal aparece perguntando: "Quanto tempo durou?"
   ↓
4. Usuário informa: 45 minutos (ou clica botão rápido)
   ↓
5. Sistema salva em sf_user_routine_log com exercise_duration_minutes = 45
   ↓
6. TRIGGER automático dispara e:
   - Converte 45min → 0.75h
   - Se exercise_type = 'workout' → Soma em workout_hours
   - Se exercise_type = 'cardio' → Soma em cardio_hours
   ↓
7. Aparece na aba de progresso automaticamente!
```

### **Exemplo Visual:**

```
╔════════════════════════════════════════╗
║  Rotina: Treinou na academia hoje?     ║
║                                        ║
║  [Ignorar]  [✓ Completar]             ║
╚════════════════════════════════════════╝
         ↓ (usuário clica Completar)
         
╔════════════════════════════════════════╗
║  Treinou na academia hoje?             ║
║  ──────────────────────────────────    ║
║  💪 Informe quanto tempo durou o treino║
║                                        ║
║  Duração (minutos)                     ║
║  ┌──────────────────────────────────┐ ║
║  │          45                      │ ║
║  └──────────────────────────────────┘ ║
║                                        ║
║  [15min] [30min] [45min]               ║
║  [1h]    [1h30]  [2h]                  ║
║                                        ║
║  [Cancelar]           [Confirmar]      ║
╚════════════════════════════════════════╝
         ↓ (usuário confirma)
         
╔════════════════════════════════════════╗
║  ✅ Parabéns! 0.75h de treino          ║
║     registrado.                        ║
╚════════════════════════════════════════╝

→ sf_user_daily_tracking.workout_hours += 0.75
→ Aparece na aba de progresso!
```

---

## 🧪 Testando

### **Teste 1: Criar Rotina de Exercício**

```sql
-- Inserir uma rotina de teste
INSERT INTO sf_routine_items (title, icon_class, is_active, default_for_all_users, is_exercise, exercise_type)
VALUES 
('Treinou na academia hoje?', 'fa-dumbbell', 1, 1, 1, 'workout'),
('Fez cardio (corrida/bike)?', 'fa-running', 1, 1, 1, 'cardio');
```

### **Teste 2: Completar Rotina**

1. Acesse `routine.php`
2. Clique em "Completar" na rotina de exercício
3. **Deve aparecer o modal** pedindo duração
4. Informe 30 minutos
5. Clique "Confirmar"
6. Veja a mensagem de sucesso

### **Teste 3: Verificar se Salvou**

```sql
-- Ver log de rotinas
SELECT * FROM sf_user_routine_log 
WHERE user_id = 36  -- seu user_id
  AND date = CURDATE()
  AND exercise_duration_minutes IS NOT NULL;

-- Ver se somou no tracking
SELECT workout_hours, cardio_hours 
FROM sf_user_daily_tracking 
WHERE user_id = 36 
  AND date = CURDATE();
```

### **Teste 4: Verificar no Progresso**

1. Acesse `progress_v2.php`
2. Vá até a seção "💪 Treino (Exercícios)"
3. Deve mostrar o tempo que você registrou!

---

## 🔗 Integração com Onboarding

### **Como funciona atualmente:**

No onboarding (`onboarding/onboarding_physicalactivity.php`), o usuário seleciona quais atividades físicas pratica (ex: musculação, corrida, yoga).

### **O que precisa ser feito:**

**Opção 1: Criar rotinas automaticamente (Recomendado)**

Quando o usuário completar o onboarding com atividades selecionadas, criar rotinas personalizadas para ele:

```php
// Em onboarding/process_onboarding.php ou similar

// Depois de salvar as atividades físicas
$selected_activities = $_POST['physical_activities'] ?? [];

foreach ($selected_activities as $activity) {
    // Determinar tipo de exercício
    $exercise_type = in_array($activity, ['corrida', 'bike', 'natação', 'caminhada']) 
        ? 'cardio' 
        : 'workout';
    
    // Criar rotina personalizada para este usuário
    $stmt = $conn->prepare("
        INSERT INTO sf_routine_items (title, icon_class, is_active, default_for_all_users, user_id_creator, is_exercise, exercise_type)
        VALUES (?, ?, 1, 0, ?, 1, ?)
    ");
    
    $title = "Praticou {$activity} hoje?";
    $icon = getActivityIcon($activity);  // Função para mapear ícones
    
    $stmt->bind_param("ssis", $title, $icon, $user_id, $exercise_type);
    $stmt->execute();
}
```

**Opção 2: Marcar rotinas padrão como exercício**

Atualizar as rotinas existentes que são sobre exercícios:

```sql
UPDATE sf_routine_items 
SET is_exercise = 1, exercise_type = 'workout'
WHERE title LIKE '%exercício%' OR title LIKE '%treino%';
```

---

## ⚙️ Configurações Avançadas

### **Ajustar Metas Padrão**

Edite o arquivo SQL antes de executar:

```sql
-- No DATABASE_UPDATE_PROGRESS_FIXED.sql, linha ~90
-- Ajuste os valores padrão:

2000 as target_kcal,          -- Calorias diárias
120.0 as target_protein_g,    -- Proteínas
200.0 as target_carbs_g,      -- Carboidratos
60.0 as target_fat_g,         -- Gorduras
8 as target_water_cups,       -- Água
10000 as target_steps_daily,  -- Passos/dia
70000 as target_steps_weekly, -- Passos/semana
3.0 as target_workout_hours_weekly,   -- Treino/semana
12.0 as target_workout_hours_monthly,  -- Treino/mês
2.5 as target_cardio_hours_weekly,    -- Cardio/semana
10.0 as target_cardio_hours_monthly,   -- Cardio/mês
8.0 as target_sleep_hours     -- Sono
```

### **Personalizar por Usuário**

```sql
-- Exemplo: Usuário 36 tem metas diferentes
UPDATE sf_user_goals 
SET 
  target_workout_hours_weekly = 5.0,  -- 5h de treino/semana
  target_cardio_hours_weekly = 3.0,   -- 3h de cardio/semana
  target_steps_daily = 12000          -- 12k passos/dia
WHERE user_id = 36;
```

---

## 🔍 Troubleshooting

### **Problema 1: Modal não aparece**

**Causa:** JavaScript não foi incluído ou BASE_URL não está definido

**Solução:**
```php
<!-- No routine.php, antes do script: -->
<script>
const BASE_URL = '<?php echo BASE_APP_URL; ?>';
</script>
<script src="<?php echo BASE_APP_URL; ?>/assets/js/routine_with_exercise_time.js"></script>
```

---

### **Problema 2: Tempo não soma automaticamente**

**Causa:** TRIGGERs não foram criados

**Verificar:**
```sql
SHOW TRIGGERS LIKE 'sf_user_routine_log';
```

**Solução:** Re-executar a parte dos TRIGGERs do SQL

---

### **Problema 3: Erro "Coluna ambígua"**

**Causa:** Usando o SQL antigo

**Solução:** Use `DATABASE_UPDATE_PROGRESS_FIXED.sql` (versão corrigida)

---

### **Problema 4: Rotina não detectada como exercício**

**Verificar:**
```sql
SELECT id, title, is_exercise, exercise_type 
FROM sf_routine_items 
WHERE id = 123;  -- ID da rotina problemática
```

**Solução:**
```sql
UPDATE sf_routine_items 
SET is_exercise = 1, exercise_type = 'workout'  -- ou 'cardio'
WHERE id = 123;
```

---

## 📊 Relatório de Mudanças no Banco

### **Tabelas Alteradas:**

| Tabela | Mudança | Descrição |
|--------|---------|-----------|
| `sf_user_daily_tracking` | +4 colunas | steps_daily, workout_hours, cardio_hours, sleep_hours |
| `sf_routine_items` | +2 colunas | is_exercise, exercise_type |
| `sf_user_routine_log` | +1 coluna | exercise_duration_minutes |

### **Tabelas Criadas:**

| Tabela | Descrição |
|--------|-----------|
| `sf_user_goals` | Metas personalizadas dos usuários |

### **Triggers Criados:**

| Trigger | Quando | O que faz |
|---------|--------|-----------|
| `after_routine_complete_add_workout_time` | INSERT em sf_user_routine_log | Soma tempo em workout_hours ou cardio_hours |
| `after_routine_uncomplete_subtract_workout_time` | DELETE em sf_user_routine_log | Subtrai tempo quando desfazer |

---

## ✅ Checklist de Implementação

- [ ] Backup do banco de dados feito
- [ ] Executado `DATABASE_UPDATE_PROGRESS_FIXED.sql` sem erros
- [ ] Verificado que colunas foram adicionadas
- [ ] Verificado que triggers foram criados
- [ ] Upload de `complete_routine_item_v2.php`
- [ ] Upload de `routine_with_exercise_time.js`
- [ ] Upload de `exercise_modal.css`
- [ ] Atualizado `routine.php` com novo CSS e JS
- [ ] Configuradas rotinas como exercícios
- [ ] Testado completar rotina de exercício
- [ ] Verificado que modal aparece
- [ ] Verificado que tempo é salvo
- [ ] Verificado na aba de progresso
- [ ] Testado desfazer rotina (tempo é subtraído)

---

## 🎯 Próximos Passos (Opcional)

### **Melhorias Futuras:**

1. **Tela de Admin de Rotinas**
   - Listar todas as rotinas
   - Marcar como exercício
   - Definir tipo (workout/cardio)

2. **Rotinas Personalizadas por Usuário**
   - Cada usuário tem suas próprias rotinas
   - Baseadas nas atividades do onboarding

3. **Histórico de Exercícios**
   - Ver todos os treinos registrados
   - Gráfico de evolução

4. **Metas de Treino Personalizadas**
   - Usuário define suas próprias metas
   - Tela de configuração

---

## 📞 Suporte

Se encontrar problemas:

1. **Leia este guia completo**
2. **Verifique o Troubleshooting**
3. **Teste com os SQLs de verificação**
4. **Veja os logs do navegador (F12 → Console)**
5. **Veja os logs do PHP** (error_log)

---

## 🎉 Resultado Final

Quando tudo estiver implementado:

✅ Água já vem do sistema existente automaticamente  
✅ Rotinas de exercício perguntam duração ao completar  
✅ Tempo é somado automaticamente via TRIGGERS  
✅ Tudo aparece na aba de progresso  
✅ Usuário pode registrar manualmente em `update_daily_tracking.php`  
✅ Gráficos mostram evolução semanal e mensal  
✅ Sistema totalmente integrado! 🚀  

---

**Desenvolvido para ShapeFit - Outubro 2025**
**Versão: 2.0 com Integração de Rotinas**

Boa implementação! 💪






