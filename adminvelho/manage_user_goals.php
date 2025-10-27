<?php
// admin/manage_user_goals.php - Sistema Completo de CRUD para Metas
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminLogin();

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: users.php");
    exit;
}

// Buscar dados do usuário
$stmt_user = $conn->prepare("SELECT u.*, p.* FROM sf_users u LEFT JOIN sf_user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    header("Location: users.php");
    exit;
}

// Processar ações
$action = $_GET['action'] ?? 'list';
$goal_id = filter_input(INPUT_GET, 'goal_id', FILTER_VALIDATE_INT);

// Calcular metas baseadas no perfil (para referência)
$gender = $user_data['gender'] ?? 'male';
$weight_kg = (float)($user_data['weight_kg'] ?? 70);
$height_cm = (int)($user_data['height_cm'] ?? 170);
$dob = $user_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
$exercise_frequency = $user_data['exercise_frequency'] ?? 'sedentary';
$objective = $user_data['objective'] ?? 'maintain_weight';

$age_years = calculateAge($dob);
$calculated_calories = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
$calculated_macros = calculateMacronutrients($calculated_calories, $objective);
$calculated_water = getWaterIntakeSuggestion($weight_kg);

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn->begin_transaction();
        
        if ($action === 'create') {
            $target_kcal = filter_input(INPUT_POST, 'target_kcal', FILTER_VALIDATE_INT);
            $target_protein = filter_input(INPUT_POST, 'target_protein', FILTER_VALIDATE_FLOAT);
            $target_carbs = filter_input(INPUT_POST, 'target_carbs', FILTER_VALIDATE_FLOAT);
            $target_fat = filter_input(INPUT_POST, 'target_fat', FILTER_VALIDATE_FLOAT);
            $target_water = filter_input(INPUT_POST, 'target_water', FILTER_VALIDATE_INT);
            $target_steps_daily = filter_input(INPUT_POST, 'target_steps_daily', FILTER_VALIDATE_INT);
            $target_steps_weekly = filter_input(INPUT_POST, 'target_steps_weekly', FILTER_VALIDATE_INT);
            $target_workout_weekly = filter_input(INPUT_POST, 'target_workout_weekly', FILTER_VALIDATE_FLOAT);
            $target_workout_monthly = filter_input(INPUT_POST, 'target_workout_monthly', FILTER_VALIDATE_FLOAT);
            $target_cardio_weekly = filter_input(INPUT_POST, 'target_cardio_weekly', FILTER_VALIDATE_FLOAT);
            $target_cardio_monthly = filter_input(INPUT_POST, 'target_cardio_monthly', FILTER_VALIDATE_FLOAT);
            $target_sleep = filter_input(INPUT_POST, 'target_sleep', FILTER_VALIDATE_FLOAT);
            $step_length = filter_input(INPUT_POST, 'step_length', FILTER_VALIDATE_FLOAT);
            $goal_type = $_POST['goal_type'] ?? 'nutrition';
            
            $stmt_insert = $conn->prepare("
                INSERT INTO sf_user_goals (
                    user_id, goal_type, target_kcal, target_protein_g, target_carbs_g, target_fat_g,
                    target_water_cups, target_steps_daily, target_steps_weekly,
                    target_workout_hours_weekly, target_workout_hours_monthly,
                    target_cardio_hours_weekly, target_cardio_hours_monthly,
                    target_sleep_hours, user_gender, step_length_cm, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt_insert->bind_param("isdddiiidddddsd", 
                $user_id, $goal_type, $target_kcal, $target_protein, $target_carbs, $target_fat,
                $target_water, $target_steps_daily, $target_steps_weekly,
                $target_workout_weekly, $target_workout_monthly,
                $target_cardio_weekly, $target_cardio_monthly,
                $target_sleep, $gender, $step_length
            );
            $stmt_insert->execute();
            $stmt_insert->close();
            
            $success_message = "Meta criada com sucesso!";
            
        } elseif ($action === 'update') {
            $goal_id = filter_input(INPUT_POST, 'goal_id', FILTER_VALIDATE_INT);
            $target_kcal = filter_input(INPUT_POST, 'target_kcal', FILTER_VALIDATE_INT);
            $target_protein = filter_input(INPUT_POST, 'target_protein', FILTER_VALIDATE_FLOAT);
            $target_carbs = filter_input(INPUT_POST, 'target_carbs', FILTER_VALIDATE_FLOAT);
            $target_fat = filter_input(INPUT_POST, 'target_fat', FILTER_VALIDATE_FLOAT);
            $target_water = filter_input(INPUT_POST, 'target_water', FILTER_VALIDATE_INT);
            $target_steps_daily = filter_input(INPUT_POST, 'target_steps_daily', FILTER_VALIDATE_INT);
            $target_steps_weekly = filter_input(INPUT_POST, 'target_steps_weekly', FILTER_VALIDATE_INT);
            $target_workout_weekly = filter_input(INPUT_POST, 'target_workout_weekly', FILTER_VALIDATE_FLOAT);
            $target_workout_monthly = filter_input(INPUT_POST, 'target_workout_monthly', FILTER_VALIDATE_FLOAT);
            $target_cardio_weekly = filter_input(INPUT_POST, 'target_cardio_weekly', FILTER_VALIDATE_FLOAT);
            $target_cardio_monthly = filter_input(INPUT_POST, 'target_cardio_monthly', FILTER_VALIDATE_FLOAT);
            $target_sleep = filter_input(INPUT_POST, 'target_sleep', FILTER_VALIDATE_FLOAT);
            $step_length = filter_input(INPUT_POST, 'step_length', FILTER_VALIDATE_FLOAT);
            $goal_type = $_POST['goal_type'] ?? 'nutrition';
            
            $stmt_update = $conn->prepare("
                UPDATE sf_user_goals SET 
                    goal_type = ?, target_kcal = ?, target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?,
                    target_water_cups = ?, target_steps_daily = ?, target_steps_weekly = ?,
                    target_workout_hours_weekly = ?, target_workout_hours_monthly = ?,
                    target_cardio_hours_weekly = ?, target_cardio_hours_monthly = ?,
                    target_sleep_hours = ?, step_length_cm = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ?
            ");
            $stmt_update->bind_param("sdddiiidddddsii", 
                $goal_type, $target_kcal, $target_protein, $target_carbs, $target_fat,
                $target_water, $target_steps_daily, $target_steps_weekly,
                $target_workout_weekly, $target_workout_monthly,
                $target_cardio_weekly, $target_cardio_monthly,
                $target_sleep, $step_length, $goal_id, $user_id
            );
            $stmt_update->execute();
            $stmt_update->close();
            
            $success_message = "Meta atualizada com sucesso!";
            
        } elseif ($action === 'delete') {
            $goal_id = filter_input(INPUT_POST, 'goal_id', FILTER_VALIDATE_INT);
            
            $stmt_delete = $conn->prepare("UPDATE sf_user_goals SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt_delete->bind_param("ii", $goal_id, $user_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            
            $success_message = "Meta excluída com sucesso!";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erro: " . $e->getMessage();
    }
}

// Buscar todas as metas do usuário
$stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$user_goals = $stmt_goals->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_goals->close();

// Buscar meta específica para edição
$edit_goal = null;
if ($action === 'edit' && $goal_id) {
    $stmt_edit = $conn->prepare("SELECT * FROM sf_user_goals WHERE id = ? AND user_id = ? AND is_active = 1");
    $stmt_edit->bind_param("ii", $goal_id, $user_id);
    $stmt_edit->execute();
    $edit_goal = $stmt_edit->get_result()->fetch_assoc();
    $stmt_edit->close();
}

$page_title = "Gerenciar Metas - " . $user_data['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🎯 Gerenciar Metas</h1>
        <p class="page-subtitle">Controle total das metas de <?php echo htmlspecialchars($user_data['name']); ?></p>
        <div class="header-actions">
            <a href="view_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">← Voltar</a>
            <button class="btn btn-primary" onclick="openCreateModal()">➕ Nova Meta</button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Metas Calculadas (Referência) -->
    <div class="reference-section">
        <h2>📊 Metas Calculadas (Referência)</h2>
        <div class="reference-grid">
            <div class="reference-item">
                <span class="label">Calorias:</span>
                <span class="value"><?php echo $calculated_calories; ?> kcal</span>
            </div>
            <div class="reference-item">
                <span class="label">Proteínas:</span>
                <span class="value"><?php echo $calculated_macros['protein_g']; ?>g</span>
            </div>
            <div class="reference-item">
                <span class="label">Carboidratos:</span>
                <span class="value"><?php echo $calculated_macros['carbs_g']; ?>g</span>
            </div>
            <div class="reference-item">
                <span class="label">Gorduras:</span>
                <span class="value"><?php echo $calculated_macros['fat_g']; ?>g</span>
            </div>
            <div class="reference-item">
                <span class="label">Água:</span>
                <span class="value"><?php echo $calculated_water['cups']; ?> copos</span>
            </div>
        </div>
    </div>

    <!-- Lista de Metas -->
    <div class="goals-section">
        <h2>📋 Metas Personalizadas</h2>
        
        <?php if (empty($user_goals)): ?>
            <div class="empty-state">
                <p>Nenhuma meta personalizada encontrada.</p>
                <button class="btn btn-primary" onclick="openCreateModal()">Criar Primeira Meta</button>
            </div>
        <?php else: ?>
            <div class="goals-list">
                <?php foreach ($user_goals as $goal): ?>
                    <div class="goal-card">
                        <div class="goal-header">
                            <h3><?php echo ucfirst($goal['goal_type']); ?> - Meta #<?php echo $goal['id']; ?></h3>
                            <div class="goal-actions">
                                <button class="btn btn-sm btn-secondary" onclick="editGoal(<?php echo $goal['id']; ?>)">
                                    ✏️ Editar
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteGoal(<?php echo $goal['id']; ?>)">
                                    🗑️ Excluir
                                </button>
                            </div>
                        </div>
                        
                        <div class="goal-content">
                            <div class="goal-grid">
                                <div class="goal-item">
                                    <span class="label">Calorias:</span>
                                    <span class="value"><?php echo $goal['target_kcal']; ?> kcal</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Proteínas:</span>
                                    <span class="value"><?php echo $goal['target_protein_g']; ?>g</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Carboidratos:</span>
                                    <span class="value"><?php echo $goal['target_carbs_g']; ?>g</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Gorduras:</span>
                                    <span class="value"><?php echo $goal['target_fat_g']; ?>g</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Água:</span>
                                    <span class="value"><?php echo $goal['target_water_cups']; ?> copos</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Passos Diários:</span>
                                    <span class="value"><?php echo number_format($goal['target_steps_daily']); ?></span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Passos Semanais:</span>
                                    <span class="value"><?php echo number_format($goal['target_steps_weekly']); ?></span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Treino Semanal:</span>
                                    <span class="value"><?php echo $goal['target_workout_hours_weekly']; ?>h</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Treino Mensal:</span>
                                    <span class="value"><?php echo $goal['target_workout_hours_monthly']; ?>h</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Cardio Semanal:</span>
                                    <span class="value"><?php echo $goal['target_cardio_hours_weekly']; ?>h</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Cardio Mensal:</span>
                                    <span class="value"><?php echo $goal['target_cardio_hours_monthly']; ?>h</span>
                                </div>
                                <div class="goal-item">
                                    <span class="label">Sono:</span>
                                    <span class="value"><?php echo $goal['target_sleep_hours']; ?>h</span>
                                </div>
                            </div>
                            
                            <div class="goal-meta">
                                <small>Criado em: <?php echo date('d/m/Y H:i', strtotime($goal['created_at'])); ?></small>
                                <?php if ($goal['updated_at'] !== $goal['created_at']): ?>
                                    <small>Atualizado em: <?php echo date('d/m/Y H:i', strtotime($goal['updated_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Criação/Edição -->
<div id="goalModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nova Meta</h2>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="goalForm" method="POST">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="goal_id" id="goalId" value="">
            
            <div class="form-section">
                <h3>Tipo de Meta</h3>
                <select name="goal_type" id="goalType" required>
                    <option value="nutrition">Nutrição</option>
                    <option value="activity">Atividade</option>
                    <option value="sleep">Sono</option>
                </select>
            </div>
            
            <div class="form-section">
                <h3>Metas Nutricionais</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="target_kcal">Calorias Diárias</label>
                        <input type="number" name="target_kcal" id="target_kcal" min="800" max="5000" required>
                    </div>
                    <div class="form-group">
                        <label for="target_protein">Proteínas (g)</label>
                        <input type="number" name="target_protein" id="target_protein" min="20" max="300" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="target_carbs">Carboidratos (g)</label>
                        <input type="number" name="target_carbs" id="target_carbs" min="50" max="500" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="target_fat">Gorduras (g)</label>
                        <input type="number" name="target_fat" id="target_fat" min="20" max="200" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="target_water">Água (copos)</label>
                        <input type="number" name="target_water" id="target_water" min="4" max="20" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Metas de Atividade</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="target_steps_daily">Passos Diários</label>
                        <input type="number" name="target_steps_daily" id="target_steps_daily" min="1000" max="50000" required>
                    </div>
                    <div class="form-group">
                        <label for="target_steps_weekly">Passos Semanais</label>
                        <input type="number" name="target_steps_weekly" id="target_steps_weekly" min="7000" max="350000" required>
                    </div>
                    <div class="form-group">
                        <label for="target_workout_weekly">Treino Semanal (h)</label>
                        <input type="number" name="target_workout_weekly" id="target_workout_weekly" min="0" max="20" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label for="target_workout_monthly">Treino Mensal (h)</label>
                        <input type="number" name="target_workout_monthly" id="target_workout_monthly" min="0" max="80" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label for="target_cardio_weekly">Cardio Semanal (h)</label>
                        <input type="number" name="target_cardio_weekly" id="target_cardio_weekly" min="0" max="15" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label for="target_cardio_monthly">Cardio Mensal (h)</label>
                        <input type="number" name="target_cardio_monthly" id="target_cardio_monthly" min="0" max="60" step="0.5" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Metas de Sono</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="target_sleep">Horas de Sono</label>
                        <input type="number" name="target_sleep" id="target_sleep" min="4" max="12" step="0.5" required>
                    </div>
                    <div class="form-group">
                        <label for="step_length">Comprimento do Passo (cm)</label>
                        <input type="number" name="step_length" id="step_length" min="50" max="100" step="0.1" required>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Meta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirmar Exclusão</h2>
            <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <p>Tem certeza que deseja excluir esta meta?</p>
            <p class="warning">Esta ação não pode ser desfeita!</p>
        </div>
        
        <form id="deleteForm" method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="goal_id" id="deleteGoalId" value="">
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Meta</button>
            </div>
        </form>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 16px;
    border-bottom: 2px solid var(--accent-orange);
}

.header-actions {
    display: flex;
    gap: 12px;
}

.reference-section {
    background: var(--surface-color);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    border: 1px solid var(--border-color);
}

.reference-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.reference-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-color);
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.reference-item .label {
    color: var(--text-secondary);
    font-weight: 500;
}

.reference-item .value {
    color: var(--text-primary);
    font-weight: 600;
}

.goals-section {
    background: var(--surface-color);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--border-color);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.goals-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.goal-card {
    background: var(--bg-color);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.goal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--accent-orange);
    color: white;
}

.goal-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.goal-actions {
    display: flex;
    gap: 8px;
}

.goal-content {
    padding: 20px;
}

.goal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.goal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: var(--surface-color);
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

.goal-item .label {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.goal-item .value {
    color: var(--text-primary);
    font-weight: 600;
}

.goal-meta {
    display: flex;
    gap: 16px;
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--surface-color);
    border-radius: 16px;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border-color);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h2 {
    margin: 0;
    color: var(--text-primary);
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-section {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    color: var(--text-primary);
    margin-bottom: 16px;
    font-size: 1.1rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.9rem;
}

.form-group input,
.form-group select {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-color);
    color: var(--text-primary);
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding: 20px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--accent-orange);
    color: white;
}

.btn-primary:hover {
    background: #e55a00;
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--surface-color);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--border-color);
    transform: translateY(-1px);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.8rem;
}

.modal-body {
    padding: 20px;
}

.warning {
    color: #ef4444;
    font-weight: 600;
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
    
    .goal-header {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }
    
    .goal-actions {
        justify-content: center;
    }
    
    .goal-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Nova Meta';
    document.getElementById('formAction').value = 'create';
    document.getElementById('goalId').value = '';
    document.getElementById('goalForm').reset();
    
    // Preencher com valores calculados
    document.getElementById('target_kcal').value = <?php echo $calculated_calories; ?>;
    document.getElementById('target_protein').value = <?php echo $calculated_macros['protein_g']; ?>;
    document.getElementById('target_carbs').value = <?php echo $calculated_macros['carbs_g']; ?>;
    document.getElementById('target_fat').value = <?php echo $calculated_macros['fat_g']; ?>;
    document.getElementById('target_water').value = <?php echo $calculated_water['cups']; ?>;
    document.getElementById('target_steps_daily').value = 10000;
    document.getElementById('target_steps_weekly').value = 70000;
    document.getElementById('target_workout_weekly').value = 3.0;
    document.getElementById('target_workout_monthly').value = 12.0;
    document.getElementById('target_cardio_weekly').value = 2.5;
    document.getElementById('target_cardio_monthly').value = 10.0;
    document.getElementById('target_sleep').value = 8.0;
    document.getElementById('step_length').value = <?php echo $gender == 'male' ? 76.0 : 66.0; ?>;
    
    document.getElementById('goalModal').style.display = 'flex';
}

function editGoal(goalId) {
    // Buscar dados da meta via AJAX ou usar dados já carregados
    const goal = <?php echo json_encode($edit_goal); ?>;
    
    if (goal && goal.id == goalId) {
        document.getElementById('modalTitle').textContent = 'Editar Meta';
        document.getElementById('formAction').value = 'update';
        document.getElementById('goalId').value = goal.id;
        
        document.getElementById('goalType').value = goal.goal_type;
        document.getElementById('target_kcal').value = goal.target_kcal;
        document.getElementById('target_protein').value = goal.target_protein_g;
        document.getElementById('target_carbs').value = goal.target_carbs_g;
        document.getElementById('target_fat').value = goal.target_fat_g;
        document.getElementById('target_water').value = goal.target_water_cups;
        document.getElementById('target_steps_daily').value = goal.target_steps_daily;
        document.getElementById('target_steps_weekly').value = goal.target_steps_weekly;
        document.getElementById('target_workout_weekly').value = goal.target_workout_hours_weekly;
        document.getElementById('target_workout_monthly').value = goal.target_workout_hours_monthly;
        document.getElementById('target_cardio_weekly').value = goal.target_cardio_hours_weekly;
        document.getElementById('target_cardio_monthly').value = goal.target_cardio_hours_monthly;
        document.getElementById('target_sleep').value = goal.target_sleep_hours;
        document.getElementById('step_length').value = goal.step_length_cm;
        
        document.getElementById('goalModal').style.display = 'flex';
    } else {
        // Redirecionar para edição
        window.location.href = 'manage_user_goals.php?id=<?php echo $user_id; ?>&action=edit&goal_id=' + goalId;
    }
}

function deleteGoal(goalId) {
    document.getElementById('deleteGoalId').value = goalId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('goalModal').style.display = 'none';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const goalModal = document.getElementById('goalModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target === goalModal) {
        closeModal();
    }
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


