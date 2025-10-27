<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Buscar dados atuais do dia
$current_data = [
    'steps' => 0,
    'workout_hours' => 0,
    'cardio_hours' => 0,
    'sleep_hours' => 0
];

$stmt = $conn->prepare("SELECT steps_daily, workout_hours, cardio_hours, sleep_hours FROM sf_user_daily_tracking WHERE user_id = ? AND date = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $current_data = [
        'steps' => (int)($row['steps_daily'] ?? 0),
        'workout_hours' => (float)($row['workout_hours'] ?? 0),
        'cardio_hours' => (float)($row['cardio_hours'] ?? 0),
        'sleep_hours' => (float)($row['sleep_hours'] ?? 0)
    ];
}
$stmt->close();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $steps = (int)($_POST['steps'] ?? 0);
    $workout = (float)($_POST['workout_hours'] ?? 0);
    $cardio = (float)($_POST['cardio_hours'] ?? 0);
    $sleep = (float)($_POST['sleep_hours'] ?? 0);
    
    try {
        // Inserir ou atualizar dados
        $stmt = $conn->prepare("
            INSERT INTO sf_user_daily_tracking 
            (user_id, date, steps_daily, workout_hours, cardio_hours, sleep_hours) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                steps_daily = VALUES(steps_daily),
                workout_hours = VALUES(workout_hours),
                cardio_hours = VALUES(cardio_hours),
                sleep_hours = VALUES(sleep_hours),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("isiddd", $user_id, $today, $steps, $workout, $cardio, $sleep);
        
        if ($stmt->execute()) {
            $success_message = "Dados atualizados com sucesso! ✅";
            
            // Atualizar dados para exibição
            $current_data = [
                'steps' => $steps,
                'workout_hours' => $workout,
                'cardio_hours' => $cardio,
                'sleep_hours' => $sleep
            ];
        } else {
            $error_message = "Erro ao salvar dados. Tente novamente.";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Erro: " . $e->getMessage();
    }
}

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Registrar Atividades";
$extra_js = ['script.js'];
$extra_css = ['pages/_update_tracking.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* ===================================
   ESTILOS PARA ATUALIZAÇÃO DE TRACKING
   =================================== */

.tracking-page {
    padding: 20px 12px 60px 12px;
}

.page-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.page-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 24px 0;
    line-height: 1.5;
}

.glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.95rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #22c55e;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Form */
.tracking-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

.label-icon {
    font-size: 1.3rem;
}

.label-text {
    flex: 1;
}

.label-unit {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 400;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.08);
    border-color: #FF6B00;
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

/* Input com ícone */
.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Helper text */
.form-helper {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-top: 4px;
    line-height: 1.4;
}

/* Submit Button */
.btn-submit {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, #FF6B00, #FF8533);
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.4);
}

.btn-submit:active {
    transform: translateY(0);
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.quick-action-btn {
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.quick-action-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 107, 0, 0.3);
    transform: translateY(-1px);
}

/* Current Values Display */
.current-values {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.current-values-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.value-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
}

.value-item-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.value-item-number {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* Responsive */
@media (max-width: 768px) {
    .tracking-page {
        padding: 16px 10px 60px 10px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .quick-actions {
        gap: 10px;
    }
}
</style>

<div class="app-container">
    <section class="tracking-page">
        <header class="page-header">
            <h1>📝 Registrar Atividades</h1>
            <p class="page-description">
                Registre suas atividades diárias manualmente. 
                <br><small>💡 Em breve você poderá sincronizar automaticamente com seu smartwatch!</small>
            </p>
        </header>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <span>❌</span>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <h3 class="current-values-title">📊 Valores Atuais de Hoje</h3>
            <div class="current-values">
                <div class="values-grid">
                    <div class="value-item">
                        <span class="value-item-label">Passos</span>
                        <span class="value-item-number"><?php echo number_format($current_data['steps']); ?></span>
                    </div>
                    <div class="value-item">
                        <span class="value-item-label">Treino</span>
                        <span class="value-item-number"><?php echo number_format($current_data['workout_hours'], 1); ?>h</span>
                    </div>
                    <div class="value-item">
                        <span class="value-item-label">Cardio</span>
                        <span class="value-item-number"><?php echo number_format($current_data['cardio_hours'], 1); ?>h</span>
                    </div>
                    <div class="value-item">
                        <span class="value-item-label">Sono</span>
                        <span class="value-item-number"><?php echo number_format($current_data['sleep_hours'], 1); ?>h</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <form method="POST" class="tracking-form">
                <!-- Passos -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">👟</span>
                        <span class="label-text">Passos Dados</span>
                        <span class="label-unit">(número de passos)</span>
                    </label>
                    <input 
                        type="number" 
                        name="steps" 
                        class="form-input" 
                        placeholder="Ex: 8500"
                        value="<?php echo $current_data['steps']; ?>"
                        min="0"
                        step="1"
                    />
                    <p class="form-helper">📍 Meta recomendada: 10.000 passos/dia</p>
                </div>

                <!-- Treino -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">💪</span>
                        <span class="label-text">Horas de Treino</span>
                        <span class="label-unit">(musculação, crossfit, etc)</span>
                    </label>
                    <input 
                        type="number" 
                        name="workout_hours" 
                        class="form-input" 
                        placeholder="Ex: 1.5"
                        value="<?php echo $current_data['workout_hours']; ?>"
                        min="0"
                        max="12"
                        step="0.1"
                    />
                    <p class="form-helper">⏱️ Use decimais. Ex: 1h30min = 1.5</p>
                </div>

                <!-- Cardio -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">🏃</span>
                        <span class="label-text">Horas de Cardio</span>
                        <span class="label-unit">(corrida, bike, natação)</span>
                    </label>
                    <input 
                        type="number" 
                        name="cardio_hours" 
                        class="form-input" 
                        placeholder="Ex: 0.5"
                        value="<?php echo $current_data['cardio_hours']; ?>"
                        min="0"
                        max="12"
                        step="0.1"
                    />
                    <p class="form-helper">⏱️ Use decimais. Ex: 30min = 0.5</p>
                </div>

                <!-- Sono -->
                <div class="form-group">
                    <label class="form-label">
                        <span class="label-icon">😴</span>
                        <span class="label-text">Horas Dormidas</span>
                        <span class="label-unit">(última noite)</span>
                    </label>
                    <input 
                        type="number" 
                        name="sleep_hours" 
                        class="form-input" 
                        placeholder="Ex: 8"
                        value="<?php echo $current_data['sleep_hours']; ?>"
                        min="0"
                        max="24"
                        step="0.5"
                    />
                    <p class="form-helper">🌙 Meta recomendada: 7-9 horas/noite</p>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i>
                    Salvar Atividades
                </button>
            </form>
        </div>

        <!-- Ações Rápidas (Preset Values) -->
        <div class="glass-card">
            <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 0 0 16px 0;">
                ⚡ Ações Rápidas
            </h3>
            <div class="quick-actions">
                <button class="quick-action-btn" onclick="setQuickValue('steps', 5000)">
                    <span>👟</span> 5k passos
                </button>
                <button class="quick-action-btn" onclick="setQuickValue('steps', 10000)">
                    <span>👟</span> 10k passos
                </button>
                <button class="quick-action-btn" onclick="setQuickValue('workout_hours', 1)">
                    <span>💪</span> 1h treino
                </button>
                <button class="quick-action-btn" onclick="setQuickValue('cardio_hours', 0.5)">
                    <span>🏃</span> 30min cardio
                </button>
                <button class="quick-action-btn" onclick="setQuickValue('sleep_hours', 8)">
                    <span>😴</span> 8h sono
                </button>
                <button class="quick-action-btn" onclick="clearAll()">
                    <span>🗑️</span> Limpar tudo
                </button>
            </div>
        </div>

        <!-- Link para Progresso -->
        <div class="glass-card" style="text-align: center;">
            <a href="<?php echo BASE_APP_URL; ?>/progress_v2.php" style="text-decoration: none; color: #FF6B00; font-weight: 600;">
                <i class="fas fa-chart-line"></i> Ver Meu Progresso Completo
            </a>
        </div>

    </section>
</div>

<script>
// Função para preencher valores rápidos
function setQuickValue(fieldName, value) {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    if (input) {
        input.value = value;
        input.focus();
    }
}

// Função para limpar todos os campos
function clearAll() {
    if (confirm('Deseja realmente limpar todos os campos?')) {
        document.querySelectorAll('.form-input').forEach(input => {
            input.value = '';
        });
    }
}

// Validação básica antes de enviar
document.querySelector('.tracking-form').addEventListener('submit', function(e) {
    const steps = parseInt(document.querySelector('input[name="steps"]').value) || 0;
    const workout = parseFloat(document.querySelector('input[name="workout_hours"]').value) || 0;
    const cardio = parseFloat(document.querySelector('input[name="cardio_hours"]').value) || 0;
    const sleep = parseFloat(document.querySelector('input[name="sleep_hours"]').value) || 0;
    
    // Validação básica
    if (steps < 0 || steps > 100000) {
        alert('⚠️ Número de passos parece inválido (0-100.000)');
        e.preventDefault();
        return;
    }
    
    if (workout < 0 || workout > 12) {
        alert('⚠️ Horas de treino parecem inválidas (0-12h)');
        e.preventDefault();
        return;
    }
    
    if (cardio < 0 || cardio > 12) {
        alert('⚠️ Horas de cardio parecem inválidas (0-12h)');
        e.preventDefault();
        return;
    }
    
    if (sleep < 0 || sleep > 24) {
        alert('⚠️ Horas de sono parecem inválidas (0-24h)');
        e.preventDefault();
        return;
    }
});
</script>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>

