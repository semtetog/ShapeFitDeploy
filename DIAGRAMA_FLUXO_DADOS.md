# 🔄 Diagrama de Fluxo de Dados - Progress.php

## 📊 **FLUXO PRINCIPAL DE DADOS**

```
┌─────────────────────────────────────────────────────────────────┐
│                        PROGRESS.PHP                             │
│                     (Dashboard Principal)                      │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     INICIALIZAÇÃO                               │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ sf_user_profiles│  │  sf_user_goals   │  │ Verificar metas │ │
│  │ (dados pessoais)│  │ (metas do user)  │  │ (criar se não   │ │
│  └─────────────────┘  └─────────────────┘  │  existir)        │ │
│           │                   │              │              │
│           ▼                   ▼              ▼              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ gender, weight, │  │ target_kcal,    │  │ Calcular metas  │ │
│  │ height, dob,    │  │ target_protein, │  │ baseadas no     │ │
│  │ objective,      │  │ target_carbs,   │  │ perfil do user  │ │
│  │ exercise_freq   │  │ target_fat,     │  │                 │ │
│  └─────────────────┘  │ target_water,   │  └─────────────────┘ │
│                       │ target_steps,   │                      │
│                       │ target_workout, │                      │
│                       │ target_cardio,  │                      │
│                       │ target_sleep    │                      │
│                       └─────────────────┘                      │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DADOS DO DIA ATUAL                          │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              sf_user_daily_tracking                         │ │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────┐ │ │
│  │  │ kcal_      │ │ protein_   │ │ carbs_      │ │ fat_    │ │ │
│  │  │ consumed   │ │ consumed_g │ │ consumed_g  │ │ consumed│ │ │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────┘ │ │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────┐ │ │
│  │  │ water_     │ │ steps_      │ │ sleep_      │ │ workout │ │ │
│  │  │ consumed_  │ │ daily       │ │ hours       │ │ hours   │ │ │
│  │  │ cups       │ │             │ │             │ │         │ │ │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────┘ │ │
│  │  ┌─────────────┐                                            │ │
│  │  │ cardio_     │                                            │ │
│  │  │ hours       │                                            │ │
│  │  └─────────────┘                                            │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DADOS DA SEMANA                              │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              sf_user_daily_tracking                         │ │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────┐ │ │
│  │  │ SUM(kcal_   │ │ SUM(protein │ │ SUM(carbs_  │ │ SUM(fat │ │ │
│  │  │ consumed)   │ │ _consumed_g)│ │ _consumed_g)│ │ _consumed│ │ │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────┘ │ │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐            │ │
│  │  │ SUM(water_  │ │ SUM(steps_  │ │ AVG(sleep_  │            │ │
│  │  │ consumed_   │ │ daily)      │ │ hours)      │            │ │
│  │  │ cups)       │ │             │ │             │            │ │
│  │  └─────────────┘ └─────────────┘ └─────────────┘            │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DADOS DE EXERCÍCIOS                          │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              sf_user_daily_tracking                         │ │
│  │  ┌─────────────┐ ┌─────────────┐                          │ │
│  │  │ workout_    │ │ cardio_     │                          │ │
│  │  │ hours       │ │ hours       │                          │ │
│  │  └─────────────┘ └─────────────┘                          │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              sf_user_routine_log                           │ │
│  │  ┌─────────────┐                                          │ │
│  │  │ COUNT(*)    │ (rotinas completadas hoje)               │ │
│  │  │ WHERE       │                                          │ │
│  │  │ is_completed│                                          │ │
│  │  │ = 1         │                                          │ │
│  │  └─────────────┘                                          │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    HISTÓRICO DE PESO                            │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │              sf_user_weight_history                        │ │
│  │  ┌─────────────┐ ┌─────────────┐                          │ │
│  │  │ date_       │ │ weight_kg   │ (últimos 30 dias)        │ │
│  │  │ recorded    │ │             │                          │ │
│  │  └─────────────┘ └─────────────┘                          │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        CÁLCULOS                                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  • Progresso = (consumido / meta) × 100                    │ │
│  │  • Distância = passos × comprimento_passo                  │ │
│  │  • Meta água = baseada no peso                            │ │
│  │  • Meta exercício = baseada na frequência                 │ │
│  │  • Meta sono = 8h (fixo)                                  │ │
│  │  • Meta passos = 10.000/dia, 70.000/semana                │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        EXIBIÇÃO                                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  📊 NUTRIÇÃO (4 cards)                                     │ │
│  │  ├── Calorias (kcal_consumed vs target_kcal)              │ │
│  │  ├── Proteínas (protein_consumed_g vs target_protein_g)   │ │
│  │  ├── Carboidratos (carbs_consumed_g vs target_carbs_g)    │ │
│  │  └── Gorduras (fat_consumed_g vs target_fat_g)            │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  💧 HIDRATAÇÃO (2 cards)                                   │ │
│  │  ├── Água Hoje (water_consumed_ml vs meta_água)            │ │
│  │  └── Água Semana (total_water vs meta_água × 7)           │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  👟 ATIVIDADE (2 cards)                                    │ │
│  │  ├── Passos Hoje (steps_daily vs target_steps_daily)       │ │
│  │  └── Passos Semana (total_steps vs target_steps_weekly)   │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  💪 EXERCÍCIOS (2 cards - condicionais)                   │ │
│  │  ├── Treino (workout_hours vs meta_treino)                │ │
│  │  └── Cardio (cardio_hours vs meta_cardio)                 │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  😴 SONO (2 cards)                                         │ │
│  │  ├── Sono Hoje (sleep_hours vs target_sleep_hours)         │ │
│  │  └── Média Semanal (avg_sleep vs target_sleep_hours)       │ │
│  └─────────────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  ⚖️ PESO (2 cards + gráfico)                              │ │
│  │  ├── Peso Atual (último weight_kg)                        │ │
│  │  ├── Registros (count weight_history)                     │ │
│  │  └── Gráfico (Chart.js com weight_history)                │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## 🔍 **DETALHAMENTO POR CARD**

### **📊 NUTRIÇÃO - 4 Cards**

```
┌─────────────────────────────────────────────────────────────────┐
│  🔥 CALORIAS                                                    │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.kcal_consumed           │ │
│  │  ├── Meta: sf_user_goals.target_kcal                       │ │
│  │  ├── Progresso: (consumido / meta) × 100                   │ │
│  │  └── Semana: SUM(kcal_consumed) vs (target_kcal × 7)      │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🥩 PROTEÍNAS                                                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.protein_consumed_g       │ │
│  │  ├── Meta: sf_user_goals.target_protein_g                  │ │
│  │  ├── Progresso: (consumido / meta) × 100                   │ │
│  │  └── Semana: SUM(protein_consumed_g) vs (target_protein_g × 7) │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🍞 CARBOIDRATOS                                               │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.carbs_consumed_g        │ │
│  │  ├── Meta: sf_user_goals.target_carbs_g                    │ │
│  │  ├── Progresso: (consumido / meta) × 100                   │ │
│  │  └── Semana: SUM(carbs_consumed_g) vs (target_carbs_g × 7) │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🥑 GORDURAS                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.fat_consumed_g          │ │
│  │  ├── Meta: sf_user_goals.target_fat_g                     │ │
│  │  ├── Progresso: (consumido / meta) × 100                   │ │
│  │  └── Semana: SUM(fat_consumed_g) vs (target_fat_g × 7)    │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **💧 HIDRATAÇÃO - 2 Cards**

```
┌─────────────────────────────────────────────────────────────────┐
│  💧 ÁGUA HOJE                                                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.water_consumed_cups × 250│ │
│  │  ├── Meta: getWaterIntakeSuggestion(weight_kg)             │ │
│  │  ├── Progresso: (consumido / meta) × 100                   │ │
│  │  └── Conversão: copos × 250ml = ml                        │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📊 ÁGUA SEMANA                                                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: SUM(water_consumed_cups) × 250                │ │
│  │  ├── Meta: getWaterIntakeSuggestion(weight_kg) × 7        │ │
│  │  ├── Progresso: (consumido / meta) × 100                 │ │
│  │  └── Conversão: copos × 250ml = ml                        │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **👟 ATIVIDADE FÍSICA - 2 Cards**

```
┌─────────────────────────────────────────────────────────────────┐
│  👟 PASSOS HOJE                                                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.steps_daily            │ │
│  │  ├── Meta: sf_user_goals.target_steps_daily (10.000)      │ │
│  │  ├── Progresso: (passos / meta) × 100                     │ │
│  │  ├── Distância: passos × comprimento_passo                 │ │
│  │  └── Comprimento: 76cm (homem) / 66cm (mulher)            │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📊 PASSOS SEMANA                                              │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: SUM(steps_daily)                              │ │
│  │  ├── Meta: sf_user_goals.target_steps_weekly (70.000)    │ │
│  │  ├── Progresso: (passos / meta) × 100                     │ │
│  │  ├── Distância: total_passos × comprimento_passo          │ │
│  │  └── Meta Distância: meta_passos × comprimento_passo      │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **💪 EXERCÍCIOS - 2 Cards (Condicionais)**

```
┌─────────────────────────────────────────────────────────────────┐
│  🏋️ TREINO (se user_has_exercises = true)                      │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.workout_hours           │ │
│  │  ├── Meta: sf_user_goals.target_workout_hours_weekly / 7   │ │
│  │  ├── Progresso: (horas / meta) × 100                       │ │
│  │  ├── Semanal: sf_user_goals.target_workout_hours_weekly    │ │
│  │  └── Mensal: sf_user_goals.target_workout_hours_monthly   │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🏃 CARDIO (se user_has_exercises = true)                     │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.cardio_hours            │ │
│  │  ├── Meta: sf_user_goals.target_cardio_hours_weekly / 7    │ │
│  │  ├── Progresso: (horas / meta) × 100                       │ │
│  │  ├── Semanal: sf_user_goals.target_cardio_hours_weekly     │ │
│  │  └── Mensal: sf_user_goals.target_cardio_hours_monthly     │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  🛋️ SEDENTÁRIO (se exercise_frequency = 'sedentary')           │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: "--" (sem dados)                               │ │
│  │  ├── Meta: "Sem metas de exercício"                       │ │
│  │  ├── Progresso: 0%                                         │ │
│  │  └── Fonte: sf_user_profiles.exercise_frequency            │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  ⚙️ CONFIGURAR (se user_has_exercises = false)                │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: "--" (sem dados)                               │ │
│  │  ├── Meta: "Adicione exercícios"                           │ │
│  │  ├── Progresso: 0%                                         │ │
│  │  └── Fonte: Verificação de exercícios configurados         │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **😴 SONO - 2 Cards**

```
┌─────────────────────────────────────────────────────────────────┐
│  😴 SONO HOJE                                                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_daily_tracking.sleep_hours             │ │
│  │  ├── Meta: sf_user_goals.target_sleep_hours (8h)          │ │
│  │  ├── Progresso: (horas / meta) × 100                       │ │
│  │  └── Formato: formatHours() (8h, 7h30, 45min)             │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📊 MÉDIA SEMANAL                                              │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: AVG(sleep_hours)                               │ │
│  │  ├── Meta: sf_user_goals.target_sleep_hours (8h)          │ │
│  │  ├── Progresso: (média / meta) × 100                       │ │
│  │  └── Fonte: AVG(sf_user_daily_tracking.sleep_hours)        │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### **⚖️ PESO - 2 Cards + Gráfico**

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚖️ PESO ATUAL                                                 │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: sf_user_weight_history.weight_kg (último)      │ │
│  │  ├── Mudança: último - primeiro (30 dias)                 │ │
│  │  ├── Período: "em 30 dias"                                │ │
│  │  └── Fonte: sf_user_weight_history (últimos 30 dias)      │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📊 REGISTROS                                                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Valor: COUNT(sf_user_weight_history)                  │ │
│  │  ├── Período: "pesagens em 30 dias"                       │ │
│  │  └── Fonte: sf_user_weight_history (últimos 30 dias)       │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│  📈 GRÁFICO EVOLUÇÃO DO PESO                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │  DADOS:                                                     │ │
│  │  ├── Eixo X: Datas dos últimos 30 dias                     │ │
│  │  ├── Eixo Y: Peso em kg                                    │ │
│  │  ├── Dados: sf_user_weight_history (array)                │ │
│  │  ├── Biblioteca: Chart.js                                 │ │
│  │  └── Responsivo: Sim                                       │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 **RESUMO EXECUTIVO**

A página `progress.php` é uma **dashboard completa** que:

1. **📊 Busca dados** de **5 tabelas** diferentes
2. **🔄 Calcula progressos** automaticamente
3. **📱 Adapta-se** a diferentes perfis de usuário
4. **⚡ Otimiza** consultas com fallbacks
5. **🎨 Apresenta** dados de forma visual e intuitiva

**Total de Cards**: 16 cards (4 nutrição + 2 hidratação + 2 atividade + 2 exercícios + 2 sono + 2 peso + 2 gráficos)

**Total de Consultas**: 6 queries principais + 2 queries condicionais

**Total de Tabelas**: 5 tabelas do banco de dados

Cada card tem uma **fonte específica** de dados e **cálculos próprios**, garantindo que todas as informações sejam **precisas e atualizadas** em tempo real.
