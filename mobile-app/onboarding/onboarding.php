<?php
// onboarding/onboarding.php (VERSÃO FINAL E FUNCIONAL)
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['final_submit'])) {
    $data = $_POST;
    $user_id = $_SESSION['user_id'];

    if (!$user_id) { exit("Erro crítico: Sessão do usuário perdida."); }
    
    $cleaned_exercises_string = null;
    $exercise_durations = [];
    if (!isset($data['exercise_type_none'])) {
        $selected_exercises = isset($data['exercise_types']) && is_array($data['exercise_types']) ? $data['exercise_types'] : [];
        $custom_activities_raw = trim($data['custom_activities'] ?? '');
        $custom_activities_array = [];
        if (!empty($custom_activities_raw)) {
            $custom_activities_array = preg_split('/,\s*/', $custom_activities_raw, -1, PREG_SPLIT_NO_EMPTY);
        }
        $all_exercises = array_merge($selected_exercises, $custom_activities_array);
        $final_exercises = array_unique(array_filter($all_exercises));
        if (!empty($final_exercises)) {
            $cleaned_exercises_string = implode(', ', $final_exercises);
            
            // Processar durações dos exercícios
            if (isset($data['exercise_duration']) && is_array($data['exercise_duration'])) {
                foreach ($data['exercise_duration'] as $exercise => $duration) {
                    $duration_minutes = filter_var($duration, FILTER_VALIDATE_INT);
                    if ($duration_minutes && $duration_minutes >= 15 && $duration_minutes <= 300) {
                        $exercise_durations[$exercise] = $duration_minutes;
                    }
                }
            }
        }
    }

    $objective = $data['objective'] ?? '';
    $weight_kg_str = str_replace(',', '.', trim($data['weight_kg'] ?? '0'));
    $weight_kg = filter_var($weight_kg_str, FILTER_VALIDATE_FLOAT);
    $height_cm = filter_var($data['height_cm'] ?? 0, FILTER_VALIDATE_INT);
    
    // Validação de exercícios e frequência
    $exercise_type_none = isset($data['exercise_type_none']);
    $has_exercises = !empty($cleaned_exercises_string) && trim($cleaned_exercises_string) !== '';
    $exercise_frequency_raw = $data['exercise_frequency'] ?? '';
    
    // Validações válidas de frequência (excluindo 'sedentary')
    $valid_frequencies = ['1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week'];
    
    if ($exercise_type_none) {
        // Se checkbox "Nenhuma / Não pratico" está marcado
        $exercise_frequency = 'sedentary';
        $cleaned_exercises_string = null; // Garantir que não há exercícios
    } else {
        // Se não está marcado, verificar validação
        if ($has_exercises) {
            // Se há exercícios, a frequência é obrigatória e deve ser uma das válidas
            if (empty($exercise_frequency_raw) || trim($exercise_frequency_raw) === '' || !in_array($exercise_frequency_raw, $valid_frequencies)) {
                // Se há exercícios mas não há frequência válida, redirecionar com erro
                $_SESSION['onboarding_error'] = 'Por favor, selecione a frequência de treino. Se você pratica exercícios, é necessário informar com que frequência.';
                header('Location: onboarding.php');
                exit;
            }
            $exercise_frequency = $exercise_frequency_raw;
        } else {
            // Se não há exercícios, definir como 'sedentary'
            $exercise_frequency = 'sedentary';
        }
    }
    
    $water_intake = $data['water_intake_liters'] ?? '1_2l';
    $sleep_bed = $data['sleep_time_bed'] ?? null;
    $sleep_wake = $data['sleep_time_wake'] ?? null;
    $eats_meat = ($data['meat_consumption'] ?? '1') === '1';
    $vegetarian_type = !$eats_meat ? ($data['vegetarian_type'] ?? 'not_like') : null;
    $lactose_intolerance = ($data['lactose_intolerance'] ?? '0') === '1';
    $gluten_intolerance = ($data['gluten_intolerance'] ?? '0') === '1';
    $name = trim($data['name'] ?? $_SESSION['user_name']);
    $uf = trim($data['uf'] ?? '');
    $city = trim($data['city'] ?? '');
    $phone_ddd = trim($data['phone_ddd'] ?? '');
    $phone_number = trim($data['phone_number'] ?? '');
    $dob = $data['dob'] ?? null;
    $gender = $data['gender'] ?? 'not_informed';

    $conn->begin_transaction();
    try {
        $stmt_users = $conn->prepare("UPDATE sf_users SET name = ?, uf = ?, city = ?, phone_ddd = ?, phone_number = ?, onboarding_complete = TRUE WHERE id = ?");
        $stmt_users->bind_param("sssssi", $name, $uf, $city, $phone_ddd, $phone_number, $user_id);
        $stmt_users->execute();
        $stmt_users->close();
        
        $stmt_profile = $conn->prepare(
            "INSERT INTO sf_user_profiles (user_id, dob, gender, height_cm, weight_kg, objective, 
            exercise_type, exercise_frequency, water_intake_liters, sleep_time_bed, sleep_time_wake, 
            meat_consumption, vegetarian_type, lactose_intolerance, gluten_intolerance) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            dob=VALUES(dob), gender=VALUES(gender), height_cm=VALUES(height_cm), weight_kg=VALUES(weight_kg), 
            objective=VALUES(objective), exercise_type=VALUES(exercise_type), exercise_frequency=VALUES(exercise_frequency), 
            water_intake_liters=VALUES(water_intake_liters), sleep_time_bed=VALUES(sleep_time_bed), 
            sleep_time_wake=VALUES(sleep_time_wake), meat_consumption=VALUES(meat_consumption), 
            vegetarian_type=VALUES(vegetarian_type), lactose_intolerance=VALUES(lactose_intolerance), 
            gluten_intolerance=VALUES(gluten_intolerance)"
        );
        
        // --- CORREÇÃO DO PHP BIND_PARAM ---
        // A string de tipos foi corrigida para "issidssssssisii", que possui 15 caracteres
        // correspondentes às 15 variáveis e 15 placeholders (?) na query SQL.
        $stmt_profile->bind_param("issidssssssisii", $user_id, $dob, $gender, $height_cm, $weight_kg, $objective,
            $cleaned_exercises_string, $exercise_frequency, $water_intake, $sleep_bed, $sleep_wake, $eats_meat,
            $vegetarian_type, $lactose_intolerance, $gluten_intolerance
        );
        
        $stmt_profile->execute();
        $stmt_profile->close();
        
        if ($weight_kg > 0) {
            $current_date_str = date('Y-m-d');
            $stmt_log_initial_weight = $conn->prepare("INSERT INTO sf_user_weight_history (user_id, weight_kg, date_recorded) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)");
            $stmt_log_initial_weight->bind_param("ids", $user_id, $weight_kg, $current_date_str);
            $stmt_log_initial_weight->execute();
            $stmt_log_initial_weight->close();
        }

        // ===========================
        // CRIAR METAS AUTOMATICAMENTE BASEADAS NO ONBOARDING
        // ===========================
        require_once APP_ROOT_PATH . '/includes/functions.php';
        
        $age_years = calculateAge($dob);
        $calculated_calories = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
        $calculated_macros = calculateMacronutrients($calculated_calories, $objective);
        $calculated_water = getWaterIntakeSuggestion($weight_kg);
        
        // Calcular metas de exercício baseadas na frequência
        $workout_hours_weekly = 0;
        $cardio_hours_weekly = 0;
        switch ($exercise_frequency) {
            case '1_2x_week':
                $workout_hours_weekly = 2.0;
                $cardio_hours_weekly = 1.5;
                break;
            case '3_4x_week':
                $workout_hours_weekly = 4.0;
                $cardio_hours_weekly = 2.5;
                break;
            case '5_6x_week':
                $workout_hours_weekly = 6.0;
                $cardio_hours_weekly = 3.5;
                break;
            case '6_7x_week':
                $workout_hours_weekly = 8.0;
                $cardio_hours_weekly = 4.0;
                break;
            case '7plus_week':
                $workout_hours_weekly = 10.0;
                $cardio_hours_weekly = 5.0;
                break;
            default:
                $workout_hours_weekly = 0;
                $cardio_hours_weekly = 0;
        }
        
        // Calcular horas de sono baseadas nos horários
        $sleep_hours = 8.0; // Padrão
        if ($sleep_bed && $sleep_wake) {
            $bed_time = new DateTime($sleep_bed);
            $wake_time = new DateTime($sleep_wake);
            if ($wake_time < $bed_time) {
                $wake_time->add(new DateInterval('P1D')); // Adiciona 1 dia se acordar no dia seguinte
            }
            $sleep_duration = $wake_time->diff($bed_time);
            $sleep_hours = $sleep_duration->h + ($sleep_duration->i / 60);
        }
        
        // Calcular água igual ao main_app (baseado no peso)
        $water_cups = $calculated_water['cups'];
        
        // Inserir metas calculadas
        $stmt_goals = $conn->prepare("
            INSERT INTO sf_user_goals (
                user_id, goal_type, target_kcal, target_protein_g, target_carbs_g, target_fat_g,
                target_water_cups, target_steps_daily, target_steps_weekly,
                target_workout_hours_weekly, target_workout_hours_monthly,
                target_cardio_hours_weekly, target_cardio_hours_monthly,
                target_sleep_hours, user_gender, step_length_cm
            ) VALUES (?, 'nutrition', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Variáveis para bind_param (não pode passar literais diretamente)
        $step_length = ($gender == 'male') ? 76.0 : 66.0;
        $target_steps_daily = 10000;
        $target_steps_weekly = 70000;
        $workout_hours_monthly = $workout_hours_weekly * 4;
        $cardio_hours_monthly = $cardio_hours_weekly * 4;
        
        // 15 placeholders: i d d d d i i i d d d d d s d
        $stmt_goals->bind_param("idddiiidddddssd", 
            $user_id,
            $calculated_calories, 
            $calculated_macros['protein_g'], 
            $calculated_macros['carbs_g'], 
            $calculated_macros['fat_g'],
            $water_cups, 
            $target_steps_daily, 
            $target_steps_weekly,
            $workout_hours_weekly, 
            $workout_hours_monthly,
            $cardio_hours_weekly, 
            $cardio_hours_monthly,
            $sleep_hours, 
            $gender, 
            $step_length
        );
        
        $stmt_goals->execute();
        $stmt_goals->close();

        // ===========================
        // SALVAR DURAÇÕES DOS EXERCÍCIOS
        // ===========================
        if (!empty($exercise_durations)) {
            foreach ($exercise_durations as $exercise_name => $duration_minutes) {
                $stmt_duration = $conn->prepare("
                    INSERT INTO sf_user_exercise_durations (user_id, exercise_name, duration_minutes) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE duration_minutes = VALUES(duration_minutes)
                ");
                $stmt_duration->bind_param("isi", $user_id, $exercise_name, $duration_minutes);
                $stmt_duration->execute();
                $stmt_duration->close();
            }
        }

        // ===========================
        // CRIAR MISSÕES PADRÃO PARA O USUÁRIO
        // ===========================
        // Criar as 3 missões principais padrão diretamente (sempre garantidas)
        // A missão de sono será criada automaticamente depois
        $default_missions = [
            [
                'title' => 'Lembrou de registrar todas as suas refeições?',
                'icon_class' => 'fa-utensils',
                'description' => null,
                'is_exercise' => 0,
                'exercise_type' => ''
            ],
            [
                'title' => 'Seu intestino funcionou hoje?',
                'icon_class' => 'fa-check-circle',
                'description' => null,
                'is_exercise' => 0,
                'exercise_type' => ''
            ],
            [
                'title' => 'Comeu salada hoje?',
                'icon_class' => 'fa-leaf',
                'description' => null,
                'is_exercise' => 0,
                'exercise_type' => ''
            ]
        ];
        
        foreach ($default_missions as $default_mission) {
            // Verificar se já existe uma missão similar (por palavra-chave)
            $keyword = '';
            if (stripos($default_mission['title'], 'refeições') !== false) {
                $keyword = 'refeições';
            } elseif (stripos($default_mission['title'], 'intestino') !== false) {
                $keyword = 'intestino';
            } elseif (stripos($default_mission['title'], 'salada') !== false) {
                $keyword = 'salada';
            } elseif (stripos($default_mission['title'], 'sono') !== false) {
                $keyword = 'sono';
            }
            
            $exists = false;
            if ($keyword) {
                $check_stmt = $conn->prepare("
                    SELECT id FROM sf_user_routine_items 
                    WHERE user_id = ? 
                    AND LOWER(title) LIKE ?
                    LIMIT 1
                ");
                $title_like = '%' . $keyword . '%';
                $check_stmt->bind_param("is", $user_id, $title_like);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->num_rows > 0;
                $check_stmt->close();
            }
            
            // Se não existe, criar
            if (!$exists) {
                $create_stmt = $conn->prepare("
                    INSERT INTO sf_user_routine_items 
                    (user_id, title, icon_class, description, is_exercise, exercise_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $description = $default_mission['description'] ?? null;
                $create_stmt->bind_param("isssis", 
                    $user_id,
                    $default_mission['title'],
                    $default_mission['icon_class'],
                    $description,
                    $default_mission['is_exercise'],
                    $default_mission['exercise_type']
                );
                $create_stmt->execute();
                $create_stmt->close();
            }
        }
        
        // Garantir que sempre existe uma missão de sono para o usuário
        $check_sleep_stmt = $conn->prepare("
            SELECT id FROM sf_user_routine_items 
            WHERE user_id = ? 
            AND (exercise_type = 'sleep' OR LOWER(title) LIKE '%sono%')
            LIMIT 1
        ");
        $check_sleep_stmt->bind_param("i", $user_id);
        $check_sleep_stmt->execute();
        $sleep_result = $check_sleep_stmt->get_result();
        $check_sleep_stmt->close();
        
        // Se não existe missão de sono, criar automaticamente
        if ($sleep_result->num_rows === 0) {
            $create_sleep_stmt = $conn->prepare("
                INSERT INTO sf_user_routine_items 
                (user_id, title, icon_class, description, is_exercise, exercise_type)
                VALUES (?, 'Como foi seu sono esta noite?', 'fa-bed', 'Registre quantas horas você dormiu: hora que deitou e hora que acordou', 1, 'sleep')
            ");
            $create_sleep_stmt->bind_param("i", $user_id);
            $create_sleep_stmt->execute();
            $create_sleep_stmt->close();
        }

        $conn->commit();
        $_SESSION['onboarding_complete'] = true;
        $_SESSION['user_name'] = $name;
        
        header("Location: " . BASE_APP_URL . "/dashboard.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("CRITICAL Onboarding Error for user {$user_id}: " . $e->getMessage());
        exit("Ocorreu um erro ao salvar seus dados. Tente novamente.");
    }
}

$user_name_from_session = $_SESSION['user_name'] ?? '';
$ufs_from_api = [];
$json_estados = @file_get_contents("https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome");
if($json_estados) { $ufs_from_api = json_decode($json_estados, true); }

// Verificar se o usuário já completou o onboarding antes (está refazendo)
$is_redoing_onboarding = isset($_SESSION['onboarding_complete']) && $_SESSION['onboarding_complete'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Questionário - ShapeFIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-color: #101010;
            --primary-orange-gradient: linear-gradient(90deg, #FFAE00, #F83600);
            --disabled-gray-gradient: linear-gradient(90deg, #333, #444);
            --text-primary: #F5F5F5;
            --text-secondary: #A3A3A3;
            --font-family: 'Montserrat', sans-serif;
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); }
        body { position: fixed; top: 0; left: 0; width: 100%; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-color); color: var(--text-primary); font-family: var(--font-family); display: flex; justify-content: center; }
        .app-container { width: 100%; max-width: 480px; height: 100%; display: flex; flex-direction: column; }
        #onboarding-form { display: flex; flex-direction: column; flex-grow: 1; min-height: 0; }
        .header-nav { padding: calc(env(safe-area-inset-top, 0px) + 15px) 24px 15px; flex-shrink: 0; visibility: hidden; display: flex; justify-content: space-between; align-items: center; }
        .back-button { color: var(--text-secondary); font-size: 1.5rem; background: none; border: none; cursor: pointer; padding: 5px; }
        .close-button { color: var(--text-secondary); font-size: 1.5rem; background: none; border: none; cursor: pointer; padding: 5px; }
        .close-button:hover { color: var(--text-primary); }
        .footer-nav { padding: 20px 24px calc(env(safe-area-inset-bottom, 0px) + 20px); flex-shrink: 0; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.3s ease; border-radius: 16px; overflow: hidden; }
        .btn-primary:disabled { background-image: var(--disabled-gray-gradient); color: var(--text-secondary); cursor: not-allowed; opacity: 0.7; }
        .form-step { display: none; flex-direction: column; width: 100%; flex-grow: 1; min-height: 0; animation: fadeIn 0.4s ease; }
        .form-step.active { display: flex; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .step-content { flex-grow: 1; overflow-y: auto; padding: 0 24px; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        .step-content::-webkit-scrollbar { display: none; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 40px; font-weight: 400; transition: opacity 0.3s ease; }
        .selectable-options label, .selectable-options-grid label, .selectable-options-grid .option-button { display: flex; align-items: center; justify-content: center; width: 100%; padding: 18px 24px; font-size: 1rem; text-align: center; background-color: var(--glass-bg); border: 1px solid var(--glass-border); cursor: pointer; transition: all 0.3s ease; border-radius: 16px; color: var(--text-primary); overflow: hidden; -webkit-background-clip: padding-box; background-clip: padding-box; }
        .selectable-options label { margin-bottom: 15px; }
        .selectable-options input, .selectable-options-grid input { display: none; }
        .selectable-options input:checked + label, .selectable-options-grid input:checked + label, .selectable-options-grid .option-button.active { background-image: var(--primary-orange-gradient); border-color: transparent; font-weight: 600; }
        .selectable-options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .selectable-options-grid label, .selectable-options-grid .option-button { margin-bottom: 0; }
        #exercise-options-wrapper.disabled, #frequency-wrapper.disabled { opacity: 0.4; pointer-events: none; }
        #frequency-wrapper { transition: opacity 0.3s ease; }
        .form-control { padding: 16px 20px; font-size: 1rem; background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); outline: none; border-radius: 16px; -webkit-appearance: none; appearance: none; width: 100%; }
        .select-wrapper, .form-control-wrapper { width: 100%; position: relative; margin-bottom: 20px; }
        .select-wrapper::after { content: '\f078'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
        select:required:invalid { color: var(--text-secondary); }
        select::-ms-expand { display: none; }
        .flex-row { display: flex; gap: 12px; align-items: flex-start; }
        label.input-label { color: var(--text-secondary); margin-bottom: 8px; display: block; font-size: 0.9rem; text-align: left; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 1000; display: none; align-items: flex-end; justify-content: center; animation: fadeInModal 0.3s ease; }
        .modal-overlay.active { display: flex; }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background: #1C1C1E; width: 100%; max-width: 480px; padding: 24px; padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 24px); border-radius: 24px 24px 0 0; animation: slideUp 0.4s ease; display: flex; flex-direction: column; gap: 20px; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-content h2 { font-size: 1.5rem; text-align: center; color: var(--text-primary); }
        .modal-input-group { display: flex; gap: 12px; }
        .modal-input-group .form-control-wrapper { margin-bottom: 0; flex-grow: 1; }
        .modal-input-group .add-btn { padding: 0; width: 54px; height: 54px; flex-shrink: 0; font-size: 1.2rem; }
        #custom-activities-list { display: flex; flex-wrap: wrap; gap: 10px; min-height: 20px; }
        .activity-tag { display: inline-flex; align-items: center; background-color: var(--glass-bg); border: 1px solid var(--glass-border); padding: 8px 12px; border-radius: 12px; font-size: 0.9rem; animation: popIn 0.2s ease; }
        @keyframes popIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .activity-tag .remove-tag { background: none; border: none; color: var(--text-secondary); margin-left: 8px; cursor: pointer; font-size: 1rem; padding: 0; line-height: 1; }
        .modal-footer { display: flex; justify-content: center; }
        .modal-footer .btn-primary { width: auto; padding: 16px 40px; }
        input[type="time"]::-webkit-calendar-picker-indicator, input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(75%) grayscale(100%) brightness(150%); }
        
        /* Estilos para campos de duração de exercícios */
        .exercise-duration-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
        }
        .exercise-duration-item .exercise-name {
            flex: 1;
            color: var(--text-primary);
            font-weight: 500;
        }
        .exercise-duration-item .duration-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .exercise-duration-item input[type="number"] {
            width: 80px;
            padding: 8px 12px;
            background: var(--bg-color);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-primary);
            text-align: center;
        }
        .exercise-duration-item .duration-unit {
            color: var(--text-secondary);
            font-size: 0.9rem;
            min-width: 30px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="header-nav">
            <?php if ($is_redoing_onboarding): ?>
                <button type="button" class="close-button" id="close-btn" onclick="window.location.href='<?php echo BASE_APP_URL; ?>/dashboard.php'">
                    <i class="fa-solid fa-times"></i>
                </button>
            <?php else: ?>
                <button type="button" class="back-button" id="back-btn"><i class="fa-solid fa-arrow-left"></i></button>
            <?php endif; ?>
        </header>
        <form id="onboarding-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
            <input type="hidden" name="custom_activities" id="custom-activities-hidden-input">
            <div class="form-step active" data-step="1"> <div class="step-content"> <h1 class="page-title">Qual é o seu objetivo principal?</h1> <p class="page-subtitle">Isto definirá a base do seu plano alimentar.</p> <div class="selectable-options"> <input type="radio" id="obj1" name="objective" value="lose_fat" required><label for="obj1">Emagrecimento</label> <input type="radio" id="obj2" name="objective" value="maintain_weight" required><label for="obj2">Manutenção de Peso</label> <input type="radio" id="obj3" name="objective" value="gain_muscle" required><label for="obj3">Ganho de Massa Muscular</label> </div> </div> </div>
            <div class="form-step" data-step="2"> <div class="step-content"> <h1 class="page-title">Vamos começar com seu peso e altura</h1> <p class="page-subtitle">Essas informações são essenciais para o cálculo.</p> <div class="form-control-wrapper"><input type="text" name="weight_kg" class="form-control" placeholder="Seu peso (kg)" required pattern="[0-9]+([,\.]?[0-9]{1,2})?"></div> <div class="form-control-wrapper"><input type="number" name="height_cm" class="form-control" placeholder="Sua altura (cm)" required min="50" max="300"></div> </div> </div>
            <div class="form-step" data-step="3">
                <div class="step-content">
                    <h1 class="page-title">Quais exercícios você pratica?</h1>
                    <p class="page-subtitle">Selecione suas atividades e defina quanto tempo dura cada treino.</p>
                    <div id="exercise-options-wrapper">
                        <div class="selectable-options-grid">
                            <input type="checkbox" id="ex1" name="exercise_types[]" value="Musculação"><label for="ex1">Musculação</label>
                            <input type="checkbox" id="ex2" name="exercise_types[]" value="Corrida"><label for="ex2">Corrida</label>
                            <input type="checkbox" id="ex3" name="exercise_types[]" value="Crossfit"><label for="ex3">Crossfit</label>
                            <input type="checkbox" id="ex4" name="exercise_types[]" value="Natação"><label for="ex4">Natação</label>
                            <input type="checkbox" id="ex5" name="exercise_types[]" value="Yoga"><label for="ex5">Yoga</label>
                            <input type="checkbox" id="ex6" name="exercise_types[]" value="Futebol"><label for="ex6">Futebol</label>
                            <button type="button" class="option-button" id="other-activity-btn">Outro</button>
                        </div>
                    </div>
                    <div class="selectable-options" style="margin-top: 15px;">
                         <input type="checkbox" id="ex-none" name="exercise_type_none"><label for="ex-none">Nenhuma / Não pratico</label>
                    </div>
                    
                    
                    <div id="frequency-wrapper">
                        <p class="page-subtitle" style="margin-top: 40px;">Com que frequência você treina por semana?</p>
                        <div class="selectable-options">
                            <input type="radio" id="freq1" name="exercise_frequency" value="1_2x_week" required><label for="freq1">1 a 2 vezes</label>
                            <input type="radio" id="freq2" name="exercise_frequency" value="3_4x_week" required><label for="freq2">3 a 4 vezes</label>
                            <input type="radio" id="freq3" name="exercise_frequency" value="5_6x_week" required><label for="freq3">5 a 6 vezes</label>
                            <input type="radio" id="freq4" name="exercise_frequency" value="6_7x_week" required><label for="freq4">6 a 7 vezes</label>
                            <input type="radio" id="freq5" name="exercise_frequency" value="7plus_week" required><label for="freq5">Mais de 7 vezes</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-step" data-step="4"> <div class="step-content"> <h1 class="page-title">Hidratação e Sono</h1> <p class="page-subtitle">Quanto de água você toma por dia, em média?</p> <div class="selectable-options"> <input type="radio" id="w1" name="water_intake_liters" value="_1l" required><label for="w1">Até 1 Litro</label> <input type="radio" id="w2" name="water_intake_liters" value="1_2l" required><label for="w2">De 1 a 2 Litros</label> <input type="radio" id="w3" name="water_intake_liters" value="2_3l" required><label for="w3">De 2 a 3 Litros</label> <input type="radio" id="w4" name="water_intake_liters" value="3plus_l" required><label for="w4">Mais de 3 Litros</label> </div> <p class="page-subtitle" style="margin-top: 20px;">Em média, que horas você costuma deitar e levantar?</p> <div class="flex-row"> <div style="flex-grow: 1;"> <label class="input-label">Costumo deitar</label> <div class="form-control-wrapper"><input type="time" name="sleep_time_bed" class="form-control" required></div> </div> <div style="flex-grow: 1;"> <label class="input-label">Costumo levantar</label> <div class="form-control-wrapper"><input type="time" name="sleep_time_wake" class="form-control" required></div> </div> </div> </div> </div>
            <div class="form-step" data-step="5"> <div class="step-content"> <h1 class="page-title">Restrições e Preferências</h1> <p class="page-subtitle">Você consome carnes (vermelha e branca)?</p> <div class="selectable-options"> <input type="radio" id="meat_yes" name="meat_consumption" value="1" required><label for="meat_yes">Sim</label> <input type="radio" id="meat_no" name="meat_consumption" value="0" required><label for="meat_no">Não</label> </div> </div> </div>
            <div class="form-step" data-step="6"> <div class="step-content"> <h1 class="page-title">Entendido!</h1> <p class="page-subtitle">Qual opção melhor descreve você?</p> <div class="selectable-options"> <input type="radio" id="veg1" name="vegetarian_type" value="strict_vegetarian" required><label for="veg1">Vegetariano Estrito</label> <input type="radio" id="veg2" name="vegetarian_type" value="ovolacto" required><label for="veg2">Ovolactovegetariano</label> <input type="radio" id="veg3" name="vegetarian_type" value="vegan" required><label for="veg3">Vegano</label> <input type="radio" id="veg4" name="vegetarian_type" value="not_like" required><label for="veg4">Apenas não gosto de carnes</label> </div> </div> </div>
            <div class="form-step" data-step="7"> <div class="step-content"> <h1 class="page-title">Intolerâncias</h1> <p class="page-subtitle">Você tem intolerância à lactose?</p> <div class="selectable-options"> <input type="radio" id="lac_yes" name="lactose_intolerance" value="1" required><label for="lac_yes">Sim</label> <input type="radio" id="lac_no" name="lactose_intolerance" value="0" required><label for="lac_no">Não</label> </div> <p class="page-subtitle" style="margin-top: 20px;">Você tem alguma restrição ao glúten?</p> <div class="selectable-options"> <input type="radio" id="glu_yes" name="gluten_intolerance" value="1" required><label for="glu_yes">Sim</label> <input type="radio" id="glu_no" name="gluten_intolerance" value="0" required><label for="glu_no">Não</label> </div> </div> </div>
            <div class="form-step" data-step="8">
                <div class="step-content">
                    <h1 class="page-title">Finalize seu cadastro</h1>
                    <p class="page-subtitle">Estamos quase lá! Complete seus dados para começar.</p>
                    <div class="form-control-wrapper"><input type="text" name="name" class="form-control" placeholder="Nome completo" required value="<?php echo htmlspecialchars($user_name_from_session); ?>"></div>
                    <div class="flex-row">
                        <div class="select-wrapper" style="flex: 0 0 90px;"> <select name="uf" class="form-control" required><option value="" disabled selected>UF</option><?php if(!empty($ufs_from_api)) { foreach($ufs_from_api as $uf) { echo "<option value='{$uf['sigla']}'>{$uf['sigla']}</option>"; } } ?></select> </div>
                        <div class="form-control-wrapper" style="flex-grow: 1;"><input type="text" name="city" class="form-control" placeholder="Cidade" required></div>
                    </div>
                    <div class="flex-row">
                        <div class="form-control-wrapper" style="flex: 0 0 80px;"><input type="tel" name="phone_ddd" class="form-control" placeholder="DDD" maxlength="2" required pattern="[0-9]*"></div>
                        <div class="form-control-wrapper" style="flex-grow: 1;"><input type="tel" name="phone_number" class="form-control" placeholder="Celular" maxlength="9" required pattern="[0-9]*"></div>
                    </div>
                    <label class="input-label">Data de Nascimento</label>
                    <div class="form-control-wrapper"><input type="date" name="dob" class="form-control" required></div>
                    <label class="input-label">Com qual gênero você se identifica?</label>
                    <div class="select-wrapper">
                        <select name="gender" class="form-control" required>
                            <option value="" disabled selected>Selecione uma opção</option>
                            <option value="female">Mulher</option>
                            <option value="male">Homem</option>
                            <option value="other">Outro</option>
                            <option value="not_informed">Prefiro não informar</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <footer class="footer-nav"> <button type="button" id="action-btn" class="btn-primary" disabled>Continuar</button> </footer>
        </form>
    </div>

    <div class="modal-overlay" id="custom-activity-modal"> <div class="modal-content"> <h2>Adicionar Atividade</h2> <div id="custom-activities-list"></div> <div class="modal-input-group"> <div class="form-control-wrapper"><input type="text" id="custom-activity-input" class="form-control" placeholder="Ex: Tênis de Mesa"></div> <button type="button" id="add-activity-btn" class="btn-primary add-btn"><i class="fas fa-plus"></i></button> </div> <div class="modal-footer"> <button type="button" id="close-modal-btn" class="btn-primary">Concluir</button> </div> </div> </div>

    <script>
    function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
    window.addEventListener('resize', setRealViewportHeight);
    setRealViewportHeight();
    document.body.addEventListener('touchmove', function(e) { if (!e.target.closest('.step-content, .modal-content')) { e.preventDefault(); } }, { passive: false });
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('onboarding-form');
        const steps = Array.from(form.querySelectorAll('.form-step'));
        const actionBtn = document.getElementById('action-btn');
        const backBtn = document.getElementById('back-btn');
        const headerNav = document.querySelector('.header-nav');
        let stepHistory = [0];
        const otherActivityBtn = document.getElementById('other-activity-btn');
        const modal = document.getElementById('custom-activity-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const addActivityBtn = document.getElementById('add-activity-btn');
        const activityInput = document.getElementById('custom-activity-input');
        const activityList = document.getElementById('custom-activities-list');
        const hiddenInput = document.getElementById('custom-activities-hidden-input');
        const noneCheckbox = document.getElementById('ex-none');
        const exerciseOptionsWrapper = document.getElementById('exercise-options-wrapper');
        const frequencyWrapper = document.getElementById('frequency-wrapper');
        const exerciseDurationWrapper = document.getElementById('exercise-duration-wrapper');
        const exerciseDurationFields = document.getElementById('exercise-duration-fields');
        const allExerciseCheckboxes = exerciseOptionsWrapper.querySelectorAll('input[type="checkbox"]');
        let customActivities = [];
        let selectedExercises = [];
        
        function renderTags() {
            activityList.innerHTML = '';
            customActivities.forEach(activity => {
                const tag = document.createElement('div');
                tag.className = 'activity-tag';
                tag.textContent = activity;
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-tag';
                removeBtn.innerHTML = '&times;';
                removeBtn.onclick = () => { customActivities = customActivities.filter(item => item !== activity); renderTags(); updateExerciseDurationFields(); };
                tag.appendChild(removeBtn);
                activityList.appendChild(tag);
            });
            hiddenInput.value = customActivities.join(',');
            otherActivityBtn.classList.toggle('active', customActivities.length > 0);
            
            // Se tem atividades customizadas e não tem frequência, marcar mínima
            if (customActivities.length > 0 && frequencyWrapper && !noneCheckbox.checked) {
                const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                const hasFrequencySelected = Array.from(freqRadios).some(radio => radio.checked);
                
                if (!hasFrequencySelected) {
                    const minFreqRadio = document.getElementById('freq1');
                    if (minFreqRadio) {
                        minFreqRadio.checked = true;
                    }
                }
            }
            
            updateExerciseDurationFields();
            updateButtonState();
        }
        
        function updateExerciseDurationFields() {
            // Função removida - não há mais seção de duração
        }
        
        
        function addActivity() {
            const newActivity = activityInput.value.trim();
            if (newActivity && !customActivities.includes(newActivity)) {
                customActivities.push(newActivity);
                activityInput.value = '';
                renderTags();
            }
            activityInput.focus();
        }
        
        otherActivityBtn.addEventListener('click', () => modal.classList.add('active'));
        closeModalBtn.addEventListener('click', () => modal.classList.remove('active'));
        addActivityBtn.addEventListener('click', addActivity);
        activityInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); addActivity(); } });
        
        noneCheckbox.addEventListener('change', function() {
            const isDisabled = this.checked;
            
            if (isDisabled) {
                // Desmarcar todos os exercícios
                allExerciseCheckboxes.forEach(cb => {
                    if (cb.id !== 'ex-none') {
                        cb.checked = false;
                        cb.disabled = true;
                    }
                });
                
                // Limpar atividades customizadas
                customActivities = [];
                renderTags();
                
                // Desmarcar todas as frequências
                if (frequencyWrapper) {
                    const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                    freqRadios.forEach(radio => {
                        radio.checked = false;
                        radio.disabled = true;
                    });
                }
                
                // Desabilitar botão de outras atividades
                if (otherActivityBtn) {
                    otherActivityBtn.disabled = true;
                    otherActivityBtn.style.opacity = '0.5';
                    otherActivityBtn.style.pointerEvents = 'none';
                }
                
                // Desabilitar visualmente os wrappers
                if (exerciseOptionsWrapper) {
                    exerciseOptionsWrapper.classList.add('disabled');
                }
                if (frequencyWrapper) {
                    frequencyWrapper.classList.add('disabled');
                }
                if (exerciseDurationWrapper && exerciseDurationWrapper.style) {
                    exerciseDurationWrapper.style.display = 'none';
                }
            } else {
                // Reabilitar tudo quando desmarcar "não pratico"
                allExerciseCheckboxes.forEach(cb => {
                    cb.disabled = false;
                });
                
                if (frequencyWrapper) {
                const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                    freqRadios.forEach(radio => radio.disabled = false);
                }
                
                if (otherActivityBtn) {
                    otherActivityBtn.disabled = false;
                    otherActivityBtn.style.opacity = '1';
                    otherActivityBtn.style.pointerEvents = 'auto';
                }
                
                if (exerciseOptionsWrapper) {
                    exerciseOptionsWrapper.classList.remove('disabled');
            }
                if (frequencyWrapper) {
                    frequencyWrapper.classList.remove('disabled');
                }
                if (exerciseDurationWrapper && exerciseDurationWrapper.style) {
                    exerciseDurationWrapper.style.display = 'block';
                }
            }
            
            updateButtonState();
        });
        
        // Adicionar listeners para checkboxes de exercícios
        allExerciseCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.id !== 'ex-none') {
                    // Se marcou um exercício e não tem frequência selecionada, marcar a mínima automaticamente
                    if (this.checked && frequencyWrapper) {
                        const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                        const hasFrequencySelected = Array.from(freqRadios).some(radio => radio.checked);
                        
                        if (!hasFrequencySelected) {
                            // Marcar a frequência mínima (1_2x_week)
                            const minFreqRadio = document.getElementById('freq1');
                            if (minFreqRadio) {
                                minFreqRadio.checked = true;
                            }
                        }
                    }
                    
                    // Se desmarcou todos os exercícios, desmarcar frequência também
                    if (!this.checked && frequencyWrapper) {
                        const anyExerciseSelected = Array.from(allExerciseCheckboxes).some(cb => cb.checked && cb.id !== 'ex-none') || customActivities.length > 0;
                        if (!anyExerciseSelected) {
                            const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                            freqRadios.forEach(radio => radio.checked = false);
                        }
                    }
                    
                    // Desmarcar "não pratico" se marcou algum exercício
                    if (this.checked && noneCheckbox) {
                        noneCheckbox.checked = false;
                        // Reabilitar tudo
                        allExerciseCheckboxes.forEach(cb => cb.disabled = false);
                        if (frequencyWrapper) {
                            const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                            freqRadios.forEach(radio => radio.disabled = false);
                        }
                        if (otherActivityBtn) {
                            otherActivityBtn.disabled = false;
                            otherActivityBtn.style.opacity = '1';
                            otherActivityBtn.style.pointerEvents = 'auto';
                        }
                        if (exerciseOptionsWrapper) {
                            exerciseOptionsWrapper.classList.remove('disabled');
                        }
                        if (frequencyWrapper) {
                            frequencyWrapper.classList.remove('disabled');
                        }
                        if (exerciseDurationWrapper) {
                            exerciseDurationWrapper.style.display = 'block';
                        }
                    }
                    
                    updateExerciseDurationFields();
                    updateButtonState();
                }
            });
        });
        
        
        const updateButtonState = () => {
            const currentStepDiv = steps[stepHistory[stepHistory.length - 1]];
            if (!currentStepDiv) return;
            let isStepValid = false;
            if (currentStepDiv.dataset.step === '3') {
                if (noneCheckbox.checked) { 
                    isStepValid = true; 
                } else {
                    const anyExerciseSelected = currentStepDiv.querySelector('input[name="exercise_types[]"]:checked') || customActivities.length > 0;
                    const frequencySelected = currentStepDiv.querySelector('input[name="exercise_frequency"]:checked');
                    
                    // Verificar se todos os campos de duração estão preenchidos (se o elemento existir)
                    let allDurationsFilled = true;
                    if (exerciseDurationFields) {
                    const durationInputs = exerciseDurationFields.querySelectorAll('input[type="number"]');
                        allDurationsFilled = durationInputs.length === 0 || Array.from(durationInputs).every(input => input.value && parseInt(input.value) >= 15);
                    }
                    
                    isStepValid = !!(anyExerciseSelected && frequencySelected && allDurationsFilled);
                }
            } else {
                const inputs = currentStepDiv.querySelectorAll('input[required], select[required]');
                isStepValid = Array.from(inputs).every(input => {
                    if (input.type === 'radio' || input.type === 'checkbox') { return form.querySelector(`input[name="${input.name}"]:checked`) !== null; }
                    if (input.tagName === 'SELECT') { return input.value !== ''; }
                    return input.value.trim() !== '' && input.checkValidity();
                });
            }
            actionBtn.disabled = !isStepValid;
        };
        
        const showStep = (stepIndex) => {
            steps.forEach((step, index) => step.classList.toggle('active', index === stepIndex));
            // Mostrar header se não for a primeira página OU se estiver refazendo
            const isRedoing = <?php echo $is_redoing_onboarding ? 'true' : 'false'; ?>;
            headerNav.style.visibility = (stepIndex > 0 || isRedoing) ? 'visible' : 'hidden';
            actionBtn.textContent = (stepIndex === steps.length - 1) ? 'Finalizar e Criar Plano' : 'Continuar';
            updateButtonState();
        };
        
        actionBtn.addEventListener('click', () => {
            if (actionBtn.disabled) return;
            let currentStepIndex = stepHistory[stepHistory.length - 1];
            if (currentStepIndex === steps.length - 1) {
                const finalSubmitInput = document.createElement('input');
                finalSubmitInput.type = 'hidden';
                finalSubmitInput.name = 'final_submit';
                finalSubmitInput.value = '1';
                form.appendChild(finalSubmitInput);
                form.submit();
                return;
            }
            let nextStepIndex = currentStepIndex + 1;
            if (currentStepIndex === 4 && form.querySelector('input[name="meat_consumption"]:checked').value === '1') {
                nextStepIndex = 6;
            }
            stepHistory.push(nextStepIndex);
            showStep(nextStepIndex);
        });
        
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                if (stepHistory.length > 1) {
                    stepHistory.pop();
                    showStep(stepHistory[stepHistory.length - 1]);
                }
            });
        }

        // --- CORREÇÃO FINAL DO JAVASCRIPT ---
        // Adiciona múltiplos listeners para garantir a atualização em todos os casos.
        // Isso faz com que o botão "Continuar" seja habilitado assim que os campos
        // obrigatórios da etapa atual forem preenchidos.
        form.addEventListener('input', updateButtonState);
        form.addEventListener('change', updateButtonState);
        
        showStep(stepHistory[0]);
    });
    </script>
</body>
</html>