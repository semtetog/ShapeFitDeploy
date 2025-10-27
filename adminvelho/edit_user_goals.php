<?php
// admin/edit_user_goals.php - Sistema de Metas Personalizadas
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

// Calcular metas baseadas no perfil
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

// Buscar metas personalizadas existentes
$stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND is_active = 1 LIMIT 1");
$stmt_goals->bind_param("i", $user_id);
$stmt_goals->execute();
$existing_goals = $stmt_goals->get_result()->fetch_assoc();
$stmt_goals->close();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    try {
        $conn->begin_transaction();
        
        if ($existing_goals) {
            // Atualizar metas existentes
            $stmt_update = $conn->prepare("
                UPDATE sf_user_goals SET 
                    target_kcal = ?, target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?,
                    target_water_cups = ?, target_steps_daily = ?, target_steps_weekly = ?,
                    target_workout_hours_weekly = ?, target_workout_hours_monthly = ?,
                    target_cardio_hours_weekly = ?, target_cardio_hours_monthly = ?,
                    target_sleep_hours = ?, step_length_cm = ?, user_gender = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt_update->bind_param("idddiiidddddsi", 
                $target_kcal, $target_protein, $target_carbs, $target_fat,
                $target_water, $target_steps_daily, $target_steps_weekly,
                $target_workout_weekly, $target_workout_monthly,
                $target_cardio_weekly, $target_cardio_monthly,
                $target_sleep, $step_length, $gender, $user_id
            );
        } else {
            // Criar novas metas
            $stmt_insert = $conn->prepare("
                INSERT INTO sf_user_goals (
                    user_id, goal_type, target_kcal, target_protein_g, target_carbs_g, target_fat_g,
                    target_water_cups, target_steps_daily, target_steps_weekly,
                    target_workout_hours_weekly, target_workout_hours_monthly,
                    target_cardio_hours_weekly, target_cardio_hours_monthly,
                    target_sleep_hours, user_gender, step_length_cm, is_active
                ) VALUES (?, 'nutrition', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt_insert->bind_param("idddiiidddddsd", 
                $user_id, $target_kcal, $target_protein, $target_carbs, $target_fat,
                $target_water, $target_steps_daily, $target_steps_weekly,
                $target_workout_weekly, $target_workout_monthly,
                $target_cardio_weekly, $target_cardio_monthly,
                $target_sleep, $gender, $step_length
            );
        }
        
        if ($existing_goals) {
            $stmt_update->execute();
            $stmt_update->close();
        } else {
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        
        $conn->commit();
        $success_message = "Metas atualizadas com sucesso!";
        
        // Recarregar metas
        $stmt_goals = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND is_active = 1 LIMIT 1");
        $stmt_goals->bind_param("i", $user_id);
        $stmt_goals->execute();
        $existing_goals = $stmt_goals->get_result()->fetch_assoc();
        $stmt_goals->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erro ao salvar metas: " . $e->getMessage();
    }
}

$page_title = "Metas Personalizadas - " . $user_data['name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🎯 Metas Personalizadas</h1>
        <p class="page-subtitle">Definir metas específicas para <?php echo htmlspecialchars($user_data['name']); ?></p>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="goals-container">
        <form method="POST" class="goals-form">
            <!-- Metas Nutricionais -->
            <div class="goals-section">
                <h2>🍽️ Metas Nutricionais</h2>
                <div class="goals-grid">
                    <div class="goal-item">
                        <label for="target_kcal">Calorias Diárias</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_kcal" name="target_kcal" 
                                   value="<?php echo $existing_goals['target_kcal'] ?? $calculated_calories; ?>" 
                                   min="800" max="5000" required>
                            <span class="unit">kcal</span>
                        </div>
                        <small class="calculated-value">Calculado: <?php echo $calculated_calories; ?> kcal</small>
                    </div>

                    <div class="goal-item">
                        <label for="target_protein">Proteínas</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_protein" name="target_protein" 
                                   value="<?php echo $existing_goals['target_protein_g'] ?? $calculated_macros['protein_g']; ?>" 
                                   min="20" max="300" step="0.1" required>
                            <span class="unit">g</span>
                        </div>
                        <small class="calculated-value">Calculado: <?php echo $calculated_macros['protein_g']; ?>g</small>
                    </div>

                    <div class="goal-item">
                        <label for="target_carbs">Carboidratos</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_carbs" name="target_carbs" 
                                   value="<?php echo $existing_goals['target_carbs_g'] ?? $calculated_macros['carbs_g']; ?>" 
                                   min="50" max="500" step="0.1" required>
                            <span class="unit">g</span>
                        </div>
                        <small class="calculated-value">Calculado: <?php echo $calculated_macros['carbs_g']; ?>g</small>
                    </div>

                    <div class="goal-item">
                        <label for="target_fat">Gorduras</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_fat" name="target_fat" 
                                   value="<?php echo $existing_goals['target_fat_g'] ?? $calculated_macros['fat_g']; ?>" 
                                   min="20" max="200" step="0.1" required>
                            <span class="unit">g</span>
                        </div>
                        <small class="calculated-value">Calculado: <?php echo $calculated_macros['fat_g']; ?>g</small>
                    </div>

                    <div class="goal-item">
                        <label for="target_water">Água</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_water" name="target_water" 
                                   value="<?php echo $existing_goals['target_water_cups'] ?? $calculated_water['cups']; ?>" 
                                   min="4" max="20" required>
                            <span class="unit">copos</span>
                        </div>
                        <small class="calculated-value">Calculado: <?php echo $calculated_water['cups']; ?> copos</small>
                    </div>
                </div>
            </div>

            <!-- Metas de Atividade -->
            <div class="goals-section">
                <h2>🏃‍♂️ Metas de Atividade</h2>
                <div class="goals-grid">
                    <div class="goal-item">
                        <label for="target_steps_daily">Passos Diários</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_steps_daily" name="target_steps_daily" 
                                   value="<?php echo $existing_goals['target_steps_daily'] ?? 10000; ?>" 
                                   min="1000" max="50000" required>
                            <span class="unit">passos</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="target_steps_weekly">Passos Semanais</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_steps_weekly" name="target_steps_weekly" 
                                   value="<?php echo $existing_goals['target_steps_weekly'] ?? 70000; ?>" 
                                   min="7000" max="350000" required>
                            <span class="unit">passos</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="target_workout_weekly">Treino Semanal</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_workout_weekly" name="target_workout_weekly" 
                                   value="<?php echo $existing_goals['target_workout_hours_weekly'] ?? 3.0; ?>" 
                                   min="0" max="20" step="0.5" required>
                            <span class="unit">horas</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="target_workout_monthly">Treino Mensal</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_workout_monthly" name="target_workout_monthly" 
                                   value="<?php echo $existing_goals['target_workout_hours_monthly'] ?? 12.0; ?>" 
                                   min="0" max="80" step="0.5" required>
                            <span class="unit">horas</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="target_cardio_weekly">Cardio Semanal</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_cardio_weekly" name="target_cardio_weekly" 
                                   value="<?php echo $existing_goals['target_cardio_hours_weekly'] ?? 2.5; ?>" 
                                   min="0" max="15" step="0.5" required>
                            <span class="unit">horas</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="target_cardio_monthly">Cardio Mensal</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_cardio_monthly" name="target_cardio_monthly" 
                                   value="<?php echo $existing_goals['target_cardio_hours_monthly'] ?? 10.0; ?>" 
                                   min="0" max="60" step="0.5" required>
                            <span class="unit">horas</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metas de Sono -->
            <div class="goals-section">
                <h2>😴 Metas de Sono</h2>
                <div class="goals-grid">
                    <div class="goal-item">
                        <label for="target_sleep">Horas de Sono</label>
                        <div class="goal-input-group">
                            <input type="number" id="target_sleep" name="target_sleep" 
                                   value="<?php echo $existing_goals['target_sleep_hours'] ?? 8.0; ?>" 
                                   min="4" max="12" step="0.5" required>
                            <span class="unit">horas</span>
                        </div>
                    </div>

                    <div class="goal-item">
                        <label for="step_length">Comprimento do Passo</label>
                        <div class="goal-input-group">
                            <input type="number" id="step_length" name="step_length" 
                                   value="<?php echo $existing_goals['step_length_cm'] ?? ($gender == 'male' ? 76.0 : 66.0); ?>" 
                                   min="50" max="100" step="0.1" required>
                            <span class="unit">cm</span>
                        </div>
                        <small class="calculated-value">Padrão: <?php echo $gender == 'male' ? '76cm' : '66cm'; ?></small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Salvar Metas</button>
                <a href="view_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">← Voltar</a>
            </div>
        </form>
    </div>
</div>

<style>
.goals-container {
    max-width: 1000px;
    margin: 0 auto;
}

.goals-form {
    background: var(--surface-color);
    border-radius: 16px;
    padding: 32px;
    border: 1px solid var(--border-color);
}

.goals-section {
    margin-bottom: 40px;
}

.goals-section h2 {
    color: var(--text-primary);
    font-size: 1.5rem;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--accent-orange);
}

.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.goal-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.goal-item label {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 1rem;
}

.goal-input-group {
    display: flex;
    align-items: center;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
}

.goal-input-group input {
    flex: 1;
    padding: 16px;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 1rem;
    outline: none;
}

.goal-input-group .unit {
    padding: 16px 20px;
    background: var(--accent-orange);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.calculated-value {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-style: italic;
}

.form-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--border-color);
}

.btn {
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn-primary {
    background: var(--accent-orange);
    color: white;
}

.btn-primary:hover {
    background: #e55a00;
    transform: translateY(-2px);
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

@media (max-width: 768px) {
    .goals-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .goals-form {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


