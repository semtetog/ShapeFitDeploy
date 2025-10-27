<?php
// manage_exercise_durations.php - Gerenciar Durações dos Exercícios
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exercise_durations = $_POST['exercise_duration'] ?? [];
    
    try {
        $conn->begin_transaction();
        
        foreach ($exercise_durations as $exercise_name => $duration_minutes) {
            $duration_minutes = filter_var($duration_minutes, FILTER_VALIDATE_INT);
            if ($duration_minutes && $duration_minutes >= 15 && $duration_minutes <= 300) {
                $stmt = $conn->prepare("
                    INSERT INTO sf_user_exercise_durations (user_id, exercise_name, duration_minutes) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE duration_minutes = VALUES(duration_minutes)
                ");
                $stmt->bind_param("isi", $user_id, $exercise_name, $duration_minutes);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->commit();
        $success_message = "Durações atualizadas com sucesso!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erro ao salvar durações: " . $e->getMessage();
    }
}

// Buscar exercícios do usuário (do perfil)
$user_profile_data = getUserProfileData($conn, $user_id);
$user_exercises = [];

if (!empty($user_profile_data['exercise_type'])) {
    $exercise_types = explode(', ', $user_profile_data['exercise_type']);
    $user_exercises = array_map('trim', $exercise_types);
}

// Buscar durações existentes
$existing_durations = [];
$stmt_durations = $conn->prepare("SELECT exercise_name, duration_minutes FROM sf_user_exercise_durations WHERE user_id = ?");
$stmt_durations->bind_param("i", $user_id);
$stmt_durations->execute();
$result_durations = $stmt_durations->get_result();
while ($row = $result_durations->fetch_assoc()) {
    $existing_durations[$row['exercise_name']] = $row['duration_minutes'];
}
$stmt_durations->close();

$page_title = "Durações dos Exercícios";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
.app-container { 
    max-width: 600px; 
    margin: 0 auto; 
    padding-bottom: 100px; 
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 20px;
    margin-bottom: 24px;
}

.page-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 12px 16px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary {
    background: var(--surface-color);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--border-color);
    transform: translateY(-2px);
}

.content-wrapper {
    padding: 0 24px;
}
</style>

<div class="app-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">⏱️ Durações dos Exercícios</h1>
            <p class="page-subtitle">Defina quanto tempo dura cada tipo de treino</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <div class="content-wrapper">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($user_exercises)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏃‍♂️</div>
            <h3>Nenhum exercício encontrado</h3>
            <p>Você ainda não definiu quais exercícios pratica.</p>
            <a href="onboarding/onboarding.php" class="btn btn-primary">Refazer Questionário</a>
        </div>
    <?php else: ?>
        <form method="POST" class="exercise-durations-form">
            <div class="exercise-list">
                <?php foreach ($user_exercises as $exercise): ?>
                    <div class="exercise-item">
                        <div class="exercise-info">
                            <h3><?php echo htmlspecialchars($exercise); ?></h3>
                            <p>Defina quanto tempo dura este treino</p>
                        </div>
                        <div class="exercise-duration">
                            <div class="duration-input-group">
                                <input type="number" 
                                       name="exercise_duration[<?php echo htmlspecialchars($exercise); ?>]" 
                                       value="<?php echo $existing_durations[$exercise] ?? 60; ?>" 
                                       min="15" max="300" required>
                                <span class="duration-unit">minutos</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Salvar Durações</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<style>
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--surface-color);
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--text-primary);
    margin-bottom: 12px;
    font-size: 1.5rem;
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

.exercise-durations-form {
    background: var(--surface-color);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--border-color);
}

.exercise-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 32px;
}

.exercise-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-color);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.exercise-item:hover {
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.exercise-info {
    flex: 1;
}

.exercise-info h3 {
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 1.2rem;
}

.exercise-info p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0;
}

.exercise-duration {
    flex-shrink: 0;
}

.duration-input-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.duration-input-group input {
    width: 100px;
    padding: 12px 16px;
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-primary);
    text-align: center;
    font-size: 1rem;
    font-weight: 600;
}

.duration-input-group input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.duration-unit {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

.form-actions {
    display: flex;
    justify-content: center;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.btn-primary {
    padding: 16px 32px;
    background: var(--accent-orange);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn-primary:hover {
    background: #e55a00;
    transform: translateY(-2px);
}

.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-weight: 500;
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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .exercise-item {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .exercise-duration {
        align-self: center;
    }
    
    .duration-input-group {
        justify-content: center;
    }
}
</style>

<?php require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; ?>
