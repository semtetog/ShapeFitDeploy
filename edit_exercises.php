<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Buscar dados do usuário
$stmt = $conn->prepare("
    SELECT 
        u.id, u.name,
        p.exercise_type, p.exercise_frequency
    FROM sf_users u
    INNER JOIN sf_user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Processar formulário se enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exercise_type_none = isset($_POST['exercise_type_none']);
    $exercise_type = $_POST['exercise_type'] ?? '';
    $exercise_frequency = $_POST['exercise_frequency'] ?? '';
    
    // Se o checkbox "Nenhuma / Não pratico" está marcado, limpar exercícios
    if ($exercise_type_none) {
        $exercise_type = '';
        $exercise_frequency = 'sedentary';
    } else {
        // Validação: Se há exercícios, a frequência é obrigatória
        $has_exercises = !empty($exercise_type) && trim($exercise_type) !== '';
        $has_frequency = !empty($exercise_frequency) && trim($exercise_frequency) !== '';
        
        if ($has_exercises && !$has_frequency) {
            // Se há exercícios mas não há frequência, redirecionar com erro
            header('Location: edit_profile.php?error=exercise_frequency_required');
            exit;
        }
        
        // Se não há exercícios, definir frequência como 'sedentary'
        if (!$has_exercises) {
            $exercise_frequency = 'sedentary';
        }
    }
    
    // Atualizar no banco
    $stmt = $conn->prepare("UPDATE sf_user_profiles SET exercise_type = ?, exercise_frequency = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $exercise_type, $exercise_frequency, $user_id);
    
    if ($stmt->execute()) {
        // Redirecionar de volta para edit_profile com sucesso
        header('Location: edit_profile.php?success=1');
        exit;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Editar Exercícios - ShapeFIT</title>
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
        #exercises-form { display: flex; flex-direction: column; flex-grow: 1; min-height: 0; }
        .header-nav { padding: calc(env(safe-area-inset-top, 0px) + 15px) 24px 15px; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; }
        .back-button { color: var(--text-secondary); font-size: 1.5rem; background: none; border: none; cursor: pointer; padding: 5px; }
        .back-button:hover { color: var(--text-primary); }
        .footer-nav { padding: 20px 24px calc(env(safe-area-inset-bottom, 0px) + 20px); flex-shrink: 0; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; transition: all 0.3s ease; border-radius: 16px; overflow: hidden; }
        .btn-primary:disabled { background-image: var(--disabled-gray-gradient); color: var(--text-secondary); cursor: not-allowed; opacity: 0.7; }
        .form-step { display: flex; flex-direction: column; width: 100%; flex-grow: 1; min-height: 0; animation: fadeIn 0.4s ease; }
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
        .form-control { padding: 16px 20px; font-size: 1rem; background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-primary); outline: none; border-radius: 16px; -webkit-appearance: none; appearance: none; width: 100%; }
        .form-control-wrapper { width: 100%; position: relative; margin-bottom: 20px; }
        .form-actions { display: flex; gap: 15px; margin-top: 20px; }
        .btn-secondary { background: var(--glass-bg); color: var(--text-primary); border: 1px solid var(--glass-border); padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border-radius: 16px; text-decoration: none; text-align: center; display: inline-block; flex: 1; }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }
        .btn-primary { flex: 1; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="header-nav">
            <a href="edit_profile.php" class="back-button">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
        </header>
        <form id="exercises-form" method="POST">
            <input type="hidden" name="custom_activities" id="custom-activities-hidden-input">
            <div class="form-step">
                <div class="step-content">
                    <h1 class="page-title">Quais exercícios você pratica?</h1>
                    <p class="page-subtitle">Selecione suas atividades físicas.</p>
                    <div id="exercise-options-wrapper">
                        <div class="selectable-options-grid">
                            <input type="checkbox" id="ex1" name="exercise_types[]" value="Musculação">
                            <label for="ex1">Musculação</label>
                            <input type="checkbox" id="ex2" name="exercise_types[]" value="Corrida">
                            <label for="ex2">Corrida</label>
                            <input type="checkbox" id="ex3" name="exercise_types[]" value="Crossfit">
                            <label for="ex3">Crossfit</label>
                            <input type="checkbox" id="ex4" name="exercise_types[]" value="Natação">
                            <label for="ex4">Natação</label>
                            <input type="checkbox" id="ex5" name="exercise_types[]" value="Yoga">
                            <label for="ex5">Yoga</label>
                            <input type="checkbox" id="ex6" name="exercise_types[]" value="Futebol">
                            <label for="ex6">Futebol</label>
                            <button type="button" class="option-button" id="other-activity-btn">Outro</button>
                        </div>
                    </div>
                    <div class="selectable-options" style="margin-top: 15px;">
                        <input type="checkbox" id="ex-none" name="exercise_type_none">
                        <label for="ex-none">Nenhuma / Não pratico</label>
                    </div>
                    
                    <div id="frequency-wrapper">
                        <p class="page-subtitle" style="margin-top: 40px;">Com que frequência você treina por semana?</p>
                        <div class="selectable-options">
                            <input type="radio" id="freq1" name="exercise_frequency" value="1_2x_week">
                            <label for="freq1">1 a 2 vezes</label>
                            <input type="radio" id="freq2" name="exercise_frequency" value="3_4x_week">
                            <label for="freq2">3 a 4 vezes</label>
                            <input type="radio" id="freq3" name="exercise_frequency" value="5_6x_week">
                            <label for="freq3">5 a 6 vezes</label>
                            <input type="radio" id="freq4" name="exercise_frequency" value="6_7x_week">
                            <label for="freq4">6 a 7 vezes</label>
                            <input type="radio" id="freq5" name="exercise_frequency" value="7plus_week">
                            <label for="freq5">Mais de 7 vezes</label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        
        <footer class="footer-nav">
            <div class="form-actions">
                <a href="edit_profile.php" class="btn-secondary">Cancelar</a>
                <button type="submit" form="exercises-form" class="btn-primary">Salvar</button>
            </div>
        </footer>
    </div>

    <!-- Modal de Atividade Customizada -->
    <div id="custom-activity-modal" class="modal-overlay">
        <div class="modal-content">
            <h2>Adicionar Atividade</h2>
            <div class="modal-input-group">
                <div class="form-control-wrapper">
                    <input type="text" id="custom-activity-input" class="form-control" placeholder="Ex: Pilates, Dança, etc.">
                </div>
                <button type="button" class="btn-primary add-btn" id="add-activity-btn">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>
            <div id="custom-activities-list">
                <!-- Atividades customizadas serão exibidas aqui -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary" id="close-modal-btn">Fechar</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
                removeBtn.onclick = () => { 
                    customActivities = customActivities.filter(item => item !== activity); 
                    renderTags(); 
                };
                tag.appendChild(removeBtn);
                activityList.appendChild(tag);
            });
            hiddenInput.value = customActivities.join(',');
            otherActivityBtn.classList.toggle('active', customActivities.length > 0);
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
        activityInput.addEventListener('keypress', (e) => { 
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                addActivity(); 
            } 
        });
        
        noneCheckbox.addEventListener('change', function() {
            const isDisabled = this.checked;
            exerciseOptionsWrapper.classList.toggle('disabled', isDisabled);
            frequencyWrapper.classList.toggle('disabled', isDisabled);
            if (isDisabled) {
                allExerciseCheckboxes.forEach(cb => {
                    if (cb.id !== 'ex-none') cb.checked = false;
                });
                customActivities = [];
                renderTags();
                const freqRadios = frequencyWrapper.querySelectorAll('input[type="radio"]');
                freqRadios.forEach(radio => radio.checked = false);
            }
        });
        
        allExerciseCheckboxes.forEach(checkbox => {
            if (checkbox.id !== 'ex-none') {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        noneCheckbox.checked = false;
                        exerciseOptionsWrapper.classList.remove('disabled');
                        frequencyWrapper.classList.remove('disabled');
                    }
                });
            }
        });
        
        // Carregar exercícios atuais
        function loadCurrentExercises() {
            const currentExercises = '<?php echo htmlspecialchars($user_data['exercise_type'] ?? ''); ?>';
            if (currentExercises && currentExercises !== '0' && currentExercises !== '') {
                const exercises = currentExercises.split(', ');
                exercises.forEach(exercise => {
                    const checkbox = Array.from(allExerciseCheckboxes).find(cb => cb.value === exercise);
                    if (checkbox) {
                        checkbox.checked = true;
                    } else {
                        customActivities.push(exercise);
                    }
                });
                renderTags();
            }
            
            // Carregar frequência atual
            const currentFrequency = '<?php echo htmlspecialchars($user_data['exercise_frequency'] ?? ''); ?>';
            if (currentFrequency) {
                const frequencyRadio = document.querySelector(`input[name="exercise_frequency"][value="${currentFrequency}"]`);
                if (frequencyRadio) {
                    frequencyRadio.checked = true;
                }
            }
        }
        
        // Salvar exercícios
        function saveExercises() {
            const exercises = [];
            
            allExerciseCheckboxes.forEach(checkbox => {
                if (checkbox.checked && checkbox.id !== 'ex-none') {
                    exercises.push(checkbox.value);
                }
            });
            
            exercises.push(...customActivities);
            
            const exerciseString = exercises.join(', ');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'exercise_type';
            hiddenInput.value = exerciseString;
            document.getElementById('exercises-form').appendChild(hiddenInput);
            
            // Verificar se há exercícios
            const hasExercises = exercises.length > 0;
            
            // Verificar se há frequência selecionada
            const frequencyRadios = document.querySelectorAll('input[name="exercise_frequency"]');
            let selectedFrequency = null;
            frequencyRadios.forEach(radio => {
                if (radio.checked) {
                    selectedFrequency = radio.value;
                }
            });
            
            // Validação: Se há exercícios, a frequência é obrigatória
            if (hasExercises && !selectedFrequency) {
                alert('Por favor, selecione a frequência de treino. Se você pratica exercícios, é necessário informar com que frequência.');
                return false;
            }
            
            // Se não tem exercícios, força frequency como sedentary
            if (!hasExercises) {
                const frequencyInput = document.createElement('input');
                frequencyInput.type = 'hidden';
                frequencyInput.name = 'exercise_frequency';
                frequencyInput.value = 'sedentary';
                document.getElementById('exercises-form').appendChild(frequencyInput);
            }
            
            return true;
        }
        
        document.getElementById('exercises-form').addEventListener('submit', function(e) {
            if (!saveExercises()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Carregar dados atuais
        loadCurrentExercises();
    });
    </script>
</body>
</html>