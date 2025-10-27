# 📊 Documentação Visual - Página de Progresso

## 🎯 Visão Geral da Página

A página `progress.php` é uma dashboard completa que mostra o progresso do usuário em diferentes áreas de saúde e fitness. Cada card busca dados de tabelas específicas do banco de dados.

---

## 🗄️ **TABELAS DO BANCO DE DADOS**

### **1. `sf_user_profiles`**
- **Função**: Dados pessoais do usuário
- **Campos usados**: `gender`, `weight_kg`, `height_cm`, `dob`, `objective`, `exercise_frequency`
- **Como é acessado**: `getUserProfileData($conn, $user_id)`

### **2. `sf_user_goals`** 
- **Função**: Metas do usuário (criadas automaticamente se não existirem)
- **Campos usados**: `target_kcal`, `target_protein_g`, `target_carbs_g`, `target_fat_g`, `target_water_cups`, `target_steps_daily`, `target_steps_weekly`, `target_workout_hours_weekly`, `target_cardio_hours_weekly`, `target_sleep_hours`
- **Como é acessado**: Query direta `SELECT * FROM sf_user_goals WHERE user_id = ? AND goal_type = 'nutrition'`

### **3. `sf_user_daily_tracking`**
- **Função**: Dados diários do usuário
- **Campos usados**: `kcal_consumed`, `protein_consumed_g`, `carbs_consumed_g`, `fat_consumed_g`, `water_consumed_cups`, `steps_daily`, `sleep_hours`, `workout_hours`, `cardio_hours`
- **Como é acessado**: 2 queries separadas (hoje e semana)

### **4. `sf_user_routine_log`**
- **Função**: Log de rotinas completadas
- **Campos usados**: `is_completed`
- **Como é acessado**: `SELECT COUNT(*) FROM sf_user_routine_log WHERE user_id = ? AND DATE(date) = ? AND is_completed = 1`

### **5. `sf_user_weight_history`**
- **Função**: Histórico de peso do usuário
- **Campos usados**: `date_recorded`, `weight_kg`
- **Como é acessado**: `SELECT date_recorded, weight_kg FROM sf_user_weight_history WHERE user_id = ? AND date_recorded >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)`

---

## 📋 **MAPEAMENTO VISUAL DOS CARDS**

### **🔥 SEÇÃO: NUTRIÇÃO**

#### **Card: Calorias**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['kcal_consumed'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_kcal'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Semana: $week_data['total_kcal'] / ($user_goals['target_kcal'] * 7)

📊 FONTES:
├── sf_user_daily_tracking.kcal_consumed (HOJE)
├── sf_user_goals.target_kcal (META)
└── SUM(sf_user_daily_tracking.kcal_consumed) (SEMANA)
```

#### **Card: Proteínas**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['protein_consumed_g'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_protein_g'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Semana: $week_data['total_protein'] / ($user_goals['target_protein_g'] * 7)

📊 FONTES:
├── sf_user_daily_tracking.protein_consumed_g (HOJE)
├── sf_user_goals.target_protein_g (META)
└── SUM(sf_user_daily_tracking.protein_consumed_g) (SEMANA)
```

#### **Card: Carboidratos**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['carbs_consumed_g'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_carbs_g'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Semana: $week_data['total_carbs'] / ($user_goals['target_carbs_g'] * 7)

📊 FONTES:
├── sf_user_daily_tracking.carbs_consumed_g (HOJE)
├── sf_user_goals.target_carbs_g (META)
└── SUM(sf_user_daily_tracking.carbs_consumed_g) (SEMANA)
```

#### **Card: Gorduras**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['fat_consumed_g'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_fat_g'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Semana: $week_data['total_fat'] / ($user_goals['target_fat_g'] * 7)

📊 FONTES:
├── sf_user_daily_tracking.fat_consumed_g (HOJE)
├── sf_user_goals.target_fat_g (META)
└── SUM(sf_user_daily_tracking.fat_consumed_g) (SEMANA)
```

---

### **💧 SEÇÃO: HIDRATAÇÃO**

#### **Card: Água Hoje**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['water_consumed_ml'] (convertido de copos * 250)
├── Meta: $water_goal_ml (calculado por getWaterIntakeSuggestion)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Fonte: sf_user_daily_tracking.water_consumed_cups

📊 FONTES:
├── sf_user_daily_tracking.water_consumed_cups (HOJE)
└── getWaterIntakeSuggestion($user_profile_data['weight_kg']) (META)
```

#### **Card: Água Semana**
```
📊 DADOS EXIBIDOS:
├── Valor: ($week_data['total_water'] * 250) (convertido de copos)
├── Meta: ($water_goal_ml * 7) (meta diária * 7)
├── Progresso: calculateProgressPercentage(consumido, meta)
└── Fonte: SUM(sf_user_daily_tracking.water_consumed_cups)

📊 FONTES:
├── SUM(sf_user_daily_tracking.water_consumed_cups) (SEMANA)
└── getWaterIntakeSuggestion($user_profile_data['weight_kg']) * 7 (META)
```

---

### **👟 SEÇÃO: ATIVIDADE FÍSICA**

#### **Card: Passos Hoje**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['steps_daily'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_steps_daily'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(passos, meta)
├── Distância: ($today_data['steps_daily'] * $step_length_cm) / 100000
└── Cálculo: Passos × Comprimento do passo (76cm homem / 66cm mulher)

📊 FONTES:
├── sf_user_daily_tracking.steps_daily (HOJE)
├── sf_user_goals.target_steps_daily (META)
└── sf_user_profiles.gender (para calcular distância)
```

#### **Card: Passos Semana**
```
📊 DADOS EXIBIDOS:
├── Valor: $week_data['total_steps'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_steps_weekly'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(passos, meta)
├── Distância: ($week_data['total_steps'] * $step_length_cm) / 100000
└── Meta Distância: ($user_goals['target_steps_weekly'] * $step_length_cm) / 100000

📊 FONTES:
├── SUM(sf_user_daily_tracking.steps_daily) (SEMANA)
├── sf_user_goals.target_steps_weekly (META)
└── sf_user_profiles.gender (para calcular distância)
```

---

### **💪 SEÇÃO: EXERCÍCIOS**

#### **Card: Treino** (se usuário tem exercícios)
```
📊 DADOS EXIBIDOS:
├── Valor: $today_workout_data['workout_hours'] (de sf_user_daily_tracking)
├── Meta: $daily_workout_target (calculado: target_workout_hours_weekly / 7)
├── Progresso: calculateProgressPercentage(horas, meta)
├── Semanal: $user_goals['target_workout_hours_weekly']
└── Mensal: $user_goals['target_workout_hours_monthly']

📊 FONTES:
├── sf_user_daily_tracking.workout_hours (HOJE)
├── sf_user_goals.target_workout_hours_weekly / 7 (META DIÁRIA)
├── sf_user_goals.target_workout_hours_weekly (META SEMANAL)
└── sf_user_goals.target_workout_hours_monthly (META MENSAL)
```

#### **Card: Cardio** (se usuário tem exercícios)
```
📊 DADOS EXIBIDOS:
├── Valor: $today_workout_data['cardio_hours'] (de sf_user_daily_tracking)
├── Meta: $daily_cardio_target (calculado: target_cardio_hours_weekly / 7)
├── Progresso: calculateProgressPercentage(horas, meta)
├── Semanal: $user_goals['target_cardio_hours_weekly']
└── Mensal: $user_goals['target_cardio_hours_monthly']

📊 FONTES:
├── sf_user_daily_tracking.cardio_hours (HOJE)
├── sf_user_goals.target_cardio_hours_weekly / 7 (META DIÁRIA)
├── sf_user_goals.target_cardio_hours_weekly (META SEMANAL)
└── sf_user_goals.target_cardio_hours_monthly (META MENSAL)
```

#### **Card: Sedentário** (se exercise_frequency = 'sedentary')
```
📊 DADOS EXIBIDOS:
├── Valor: "--" (sem dados)
├── Meta: "Sem metas de exercício"
├── Progresso: 0%
└── Fonte: sf_user_profiles.exercise_frequency = 'sedentary'

📊 FONTES:
└── sf_user_profiles.exercise_frequency (VERIFICAÇÃO)
```

#### **Card: Configurar** (se não tem exercícios configurados)
```
📊 DADOS EXIBIDOS:
├── Valor: "--" (sem dados)
├── Meta: "Adicione exercícios"
├── Progresso: 0%
└── Fonte: Verificação se $user_has_exercises = false

📊 FONTES:
└── sf_user_profiles.exercise_frequency (VERIFICAÇÃO)
```

---

### **😴 SEÇÃO: SONO**

#### **Card: Sono Hoje**
```
📊 DADOS EXIBIDOS:
├── Valor: $today_data['sleep_hours'] (de sf_user_daily_tracking)
├── Meta: $user_goals['target_sleep_hours'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(horas, meta)
└── Formato: formatHours() (ex: "8h", "7h30", "45min")

📊 FONTES:
├── sf_user_daily_tracking.sleep_hours (HOJE)
└── sf_user_goals.target_sleep_hours (META)
```

#### **Card: Média Semanal**
```
📊 DADOS EXIBIDOS:
├── Valor: $week_data['avg_sleep'] (média de sf_user_daily_tracking)
├── Meta: $user_goals['target_sleep_hours'] (de sf_user_goals)
├── Progresso: calculateProgressPercentage(média, meta)
└── Fonte: AVG(sf_user_daily_tracking.sleep_hours)

📊 FONTES:
├── AVG(sf_user_daily_tracking.sleep_hours) (SEMANA)
└── sf_user_goals.target_sleep_hours (META)
```

---

### **⚖️ SEÇÃO: PESO**

#### **Card: Peso Atual**
```
📊 DADOS EXIBIDOS:
├── Valor: $weight_history[último]['weight_kg'] (de sf_user_weight_history)
├── Mudança: $weight_change (último - primeiro em 30 dias)
├── Período: "em 30 dias"
└── Fonte: sf_user_weight_history (últimos 30 dias)

📊 FONTES:
├── sf_user_weight_history.weight_kg (ÚLTIMO REGISTRO)
└── Cálculo: último peso - primeiro peso (30 dias)
```

#### **Card: Registros**
```
📊 DADOS EXIBIDOS:
├── Valor: count($weight_history) (quantidade de registros)
├── Período: "pesagens em 30 dias"
└── Fonte: sf_user_weight_history (últimos 30 dias)

📊 FONTES:
└── COUNT(sf_user_weight_history) WHERE date_recorded >= 30 dias
```

#### **Gráfico: Evolução do Peso**
```
📊 DADOS EXIBIDOS:
├── Eixo X: Datas dos últimos 30 dias
├── Eixo Y: Peso em kg
├── Dados: $weight_history (array de registros)
└── Biblioteca: Chart.js

📊 FONTES:
└── sf_user_weight_history (últimos 30 dias ordenados por data)
```

---

## 🔄 **FLUXO DE DADOS**

### **1. Inicialização**
```
1. Buscar perfil do usuário (sf_user_profiles)
2. Buscar metas do usuário (sf_user_goals)
3. Se não tem metas → Criar automaticamente baseado no perfil
```

### **2. Dados do Dia**
```
1. Query: sf_user_daily_tracking WHERE date = hoje
2. Query: sf_user_daily_tracking (workout_hours, cardio_hours) WHERE date = hoje
3. Query: sf_user_routine_log WHERE date = hoje AND is_completed = 1
```

### **3. Dados da Semana**
```
1. Query: SUM/AVG(sf_user_daily_tracking) WHERE date BETWEEN início_semana AND fim_semana
```

### **4. Histórico de Peso**
```
1. Query: sf_user_weight_history WHERE date_recorded >= 30 dias ORDER BY date_recorded
```

---

## ⚠️ **PONTOS IMPORTANTES**

### **🔧 Dependências de Colunas**
- **Colunas novas**: `workout_hours`, `cardio_hours`, `sleep_hours`, `steps_daily`
- **Fallback**: Se não existirem, usa 0
- **Try-catch**: Implementado para não quebrar a página

### **📊 Cálculos Automáticos**
- **Meta de água**: Baseada no peso do usuário
- **Distância**: Passos × Comprimento do passo (gênero)
- **Metas de exercício**: Baseadas na frequência de exercícios
- **Progresso**: (consumido / meta) × 100

### **🎨 Formatação**
- **Horas**: formatHours() (1h20, 45min)
- **Números**: formatNumber() (1.234,56)
- **Cores**: getProgressColor() (verde/amarelo/laranja/vermelho)

### **📱 Responsividade**
- **Desktop**: 4 colunas (nutrição), 2 colunas (outros)
- **Mobile**: 2 colunas (nutrição), 1 coluna (outros)
- **Gráficos**: Responsivos com Chart.js

---

## 🚀 **RESUMO TÉCNICO**

A página `progress.php` é uma **dashboard completa** que:

1. **📊 Busca dados** de 5 tabelas diferentes
2. **🔄 Calcula progressos** automaticamente
3. **📱 Adapta-se** a diferentes perfis de usuário
4. **⚡ Otimiza** consultas com fallbacks
5. **🎨 Apresenta** dados de forma visual e intuitiva

Cada card tem uma **fonte específica** de dados e **cálculos próprios**, garantindo que todas as informações sejam **precisas e atualizadas** em tempo real.
