# 📋 LISTA SIMPLIFICADA DE PÁGINAS - SHAPEFIT APP

## ✅ Checklist para Conversão Mobile

---

## 🔐 AUTENTICAÇÃO
- [ ] `auth/login.php` - Login
- [ ] `auth/register.php` - Cadastro
- [ ] `auth/logout.php` - Logout (ação)

---

## 🎓 ONBOARDING (1 página única)
- [ ] `onboarding/onboarding.php` - **Onboarding completo** (faz tudo em uma página)

**⚠️ NÃO USAR**: step1_intro.php, step2_register_details.php, step3_*, step4_*, step5_*, step6_*, step7_*, step8_* - Estes não são mais usados!

---

## 🏠 PÁGINAS PRINCIPAIS (Menu Inferior)

### HOME
- [ ] `index.php` - Redirecionamento inicial
- [ ] `main_app.php` - Dashboard principal

### PROGRESSO
- [ ] `progress.php` - Progresso/estatísticas

### DIÁRIO
- [ ] `diary.php` - Diário alimentar
- [ ] `add_food_to_diary.php` - Adicionar alimento
- [ ] `meal_types_overview.php` - Visão geral refeições
- [ ] `edit_meal.php` - Editar refeição

### EXPLORAR
- [ ] `explore_recipes.php` - Explorar receitas
- [ ] `view_recipe.php` - Ver receita
- [ ] `favorite_recipes.php` - Receitas favoritas

### CONFIGURAÇÕES
- [ ] `more_options.php` - Mais opções
- [ ] `profile_overview.php` - Perfil
- [ ] `edit_profile.php` - Editar perfil

---

## 📊 PROGRESSO E ESTATÍSTICAS
- [ ] `progress.php` - Progresso principal
- [ ] `measurements_progress.php` - Medidas corporais
- [ ] `points_history.php` - Histórico de pontos
- [ ] `ranking.php` - Ranking

---

## 🍽️ DIÁRIO ALIMENTAR
- [ ] `diary.php` - Diário principal
- [ ] `add_food_to_diary.php` - Adicionar alimento
- [ ] `meal_types_overview.php` - Visão geral
- [ ] `edit_meal.php` - Editar refeição
- [ ] `create_custom_food.php` - Criar alimento custom
- [ ] `scan_barcode.php` - Escanear código de barras

---

## 🍳 RECEITAS
- [ ] `explore_recipes.php` - Explorar receitas
- [ ] `view_recipe.php` - Ver receita
- [ ] `favorite_recipes.php` - Favoritas

---

## 🏋️ ROTINA
- [ ] `routine.php` - Rotina diária

---

## 🎯 DESAFIOS
- [ ] `challenges.php` - Lista de desafios
- [ ] `challenge_rooms.php` - Salas de desafio
- [ ] `challenge_room_details.php` - Detalhes da sala

---

## 👤 PERFIL E CONFIGURAÇÕES
- [ ] `profile_overview.php` - Perfil
- [ ] `edit_profile.php` - Editar perfil
- [ ] `more_options.php` - Configurações
- [ ] `weekly_checkin.php` - Check-in semanal

---

## 📱 PÁGINAS ADICIONAIS
- [ ] `content.php` - Conteúdo
- [ ] `tutorial.php` - Tutorial
- [ ] `privacy.php` - Privacidade
- [ ] `account_deleted.php` - Conta deletada
- [ ] `delete_account.php` - Deletar conta

---

## 🔄 ACTIONS (Processamento - não são páginas visuais)

### Refeições
- [ ] `actions/process_log_meal.php`
- [ ] `actions/process_edit_meal.php`
- [ ] `actions/process_delete_meal.php`
- [ ] `actions/process_add_entire_meal.php`
- [ ] `actions/process_save_custom_food.php`

### Rotina
- [ ] `actions/complete_routine_item.php`
- [ ] `actions/complete_routine_item_v2.php`
- [ ] `actions/complete_sleep_routine.php`
- [ ] `actions/complete_onboarding_routine.php`
- [ ] `actions/uncomplete_routine_item.php`
- [ ] `actions/uncomplete_onboarding_routine.php`

---

## 🌐 API ENDPOINTS (JSON, não HTML)

### Autenticação
- [ ] `api/authenticate_with_token.php`
- [ ] `api/verify_token.php`

### Dados
- [ ] `api/api_dados_usuario.php`
- [ ] `api/get_dashboard_data.php`

### Alimentos
- [ ] `api/ajax_search_food.php`
- [ ] `api/ajax_get_food_units.php`
- [ ] `api/ajax_search_foods_recipes.php`

### Receitas
- [ ] `api/ajax_search_recipes.php`

### Outros
- [ ] `api/calculate_nutrition.php`
- [ ] `api/update_routine_status.php`
- [ ] `api/update_water.php`
- [ ] `api/update_weight.php`
- [ ] `api/checkin.php`
- [ ] `api/sync.php`
- [ ] `api/save_push_token.php`
- [ ] `api/get_units.php`

### Desafios
- [ ] `api/challenges.php`
- [ ] `api/challenge_rooms.php`
- [ ] `api/challenge_room_details.php`
- [ ] `api/challenge_members.php`
- [ ] `api/challenge_progress.php`
- [ ] `api/challenge_notifications.php`
- [ ] `api/studio_ligas.php`
- [ ] `api/liga_participants.php`
- [ ] `api/liga_scoring.php`

---

## 📊 RESUMO

### Páginas Visuais: ~30-33 páginas
- Auth: 3
- Onboarding: 1 (única página)
- Principais: ~15
- Adicionais: ~5-8

### Actions: 11 arquivos
### API Endpoints: ~24 arquivos

**TOTAL**: ~65-68 arquivos PHP para converter

---

## ⚠️ IMPORTANTE

1. **Onboarding**: Só `onboarding.php` é usado. Ignorar todos os `step*.php`
2. **Admin**: NÃO incluir nada de `admin/`, `admin-react/`, `adminnovo/`
3. **Testes/Debug**: Ignorar arquivos de teste, debug, fix, etc.

---

**Última atualização**: 2025-01-13





