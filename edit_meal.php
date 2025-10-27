<?php
// Arquivo: edit_meal.php - Editar refeição existente no diário

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_APP_URL . "/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$meal_id) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'ID da refeição inválido.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// Buscar dados da refeição
$stmt = $conn->prepare("
    SELECT 
        log.id,
        log.meal_type,
        log.custom_meal_name,
        log.date_consumed,
        log.servings_consumed,
        log.kcal_consumed,
        log.protein_consumed_g,
        log.carbs_consumed_g,
        log.fat_consumed_g,
        log.logged_at,
        r.name as recipe_name,
        r.kcal_per_serving,
        r.protein_g_per_serving,
        r.carbs_g_per_serving,
        r.fat_g_per_serving
    FROM sf_user_meal_log log
    LEFT JOIN sf_recipes r ON log.recipe_id = r.id
    WHERE log.id = ? AND log.user_id = ?
");

if (!$stmt) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro na consulta ao banco de dados.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

$stmt->bind_param("ii", $meal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meal = $result->fetch_assoc();
$stmt->close();

if (!$meal) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Refeição não encontrada.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// Opções de tipos de refeição
$meal_type_options = [
    'breakfast' => 'Café da Manhã',
    'morning_snack' => 'Lanche da Manhã',
    'lunch' => 'Almoço',
    'afternoon_snack' => 'Lanche da Tarde',
    'dinner' => 'Jantar',
    'supper' => 'Ceia',
    'pre_workout' => 'Pré-Treino',
    'post_workout' => 'Pós-Treino'
];

$page_title = "Editar Refeição";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === EDIT MEAL PAGE - DESIGN HARMONIZADO COM O APP === */
.app-container {
    padding: calc(24px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;
    min-height: 100vh;
    background: #1a1a1a;
}

.header {
    display: flex;
    align-items: center;
    padding: 0 24px;
    margin-bottom: 24px;
}

.back-button {
    color: var(--text-secondary);
    font-size: 1.2rem;
    margin-right: 16px;
    text-decoration: none;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.2s ease;
}


.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.edit-form {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    margin: 0 24px 24px 24px;
    padding: 24px;
    overflow: hidden;
}

.form-section {
    margin-bottom: 24px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.form-grid.two-cols {
    grid-template-columns: 1fr 1fr;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    -webkit-appearance: none; /* Adicione esta linha */
    appearance: none; /* Adicione esta linha */
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 14px 16px;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-control[readonly] {
    background: rgba(255, 255, 255, 0.02);
    color: var(--text-secondary);
    cursor: not-allowed;
}

/* Wrapper para campos de data e hora para evitar overflow */
.date-time-wrapper {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.nutrition-display {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px;
}

.nutrition-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.nutrition-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 16px 8px;
}

.nutrition-item-label {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.nutrition-item-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
}

.nutrition-item-unit {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--accent-orange);
    margin-left: 2px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.btn {
    flex: 1;
    height: 48px;
    border-radius: 16px;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.06);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-primary {
    background: var(--accent-orange);
    color: #fff;
}

.btn-primary:hover {
    background: #ff7a1a;
}

.btn-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.btn-danger:hover {
    background: rgba(220, 53, 69, 0.2);
}

/* Responsividade */
@media (max-width: 768px) {
    .app-container {
        padding: calc(20px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;
    }

    .header {
        padding: 0 20px;
        margin-bottom: 20px;
    }

    .page-title {
        font-size: 1.3rem;
    }

    .edit-form {
        margin: 0 20px 20px 20px;
        padding: 20px;
    }

    .form-grid.two-cols {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .date-time-wrapper {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .nutrition-grid {
        gap: 12px;
    }

    .nutrition-item {
        padding: 14px 6px;
    }

    .nutrition-item-value {
        font-size: 1.1rem;
    }

    .action-buttons {
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
        padding-top: 16px;
    }

    .btn {
        height: 44px;
        font-size: 0.9rem;
    }
}

/* Fallbacks para safe area */
@supports (padding: max(0px)) {
    .app-container {
        padding-top: max(calc(24px + env(safe-area-inset-top)), 44px);
        padding-bottom: max(calc(60px + env(safe-area-inset-bottom)), 110px);
    }
}

@supports (-webkit-touch-callout: none) {
    .app-container {
        padding-top: calc(24px + env(safe-area-inset-top));
        padding-bottom: calc(60px + env(safe-area-inset-bottom));
    }
}
</style>

<div class="app-container">
    <div class="header">
        <a href="<?php echo BASE_APP_URL; ?>/diary.php?date=<?php echo $meal['date_consumed']; ?>" class="back-button" aria-label="Voltar">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">Editar Refeição</h1>
    </div>

    <div class="edit-form">
        <form id="edit-meal-form" method="POST" action="<?php echo BASE_APP_URL; ?>/process_edit_meal.php">
            <input type="hidden" name="meal_id" value="<?php echo $meal['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Informações Básicas -->
            <div class="form-section">
                <h3 class="section-title">Informações Básicas</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="meal_name">Nome da Refeição</label>
                        <input type="text" id="meal_name" name="meal_name" class="form-control" 
                               value="<?php echo htmlspecialchars($meal['custom_meal_name'] ?: $meal['recipe_name']); ?>" 
                               placeholder="Ex: Arroz com frango grelhado">
                    </div>
                    <div class="form-group">
                        <label for="meal_type">Tipo de Refeição</label>
                        <select id="meal_type" name="meal_type" class="form-control">
                            <?php foreach ($meal_type_options as $slug => $name): ?>
                                <option value="<?php echo $slug; ?>" <?php echo ($meal['meal_type'] === $slug) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="date-time-wrapper">
                    <div class="form-group">
                        <label for="date_consumed">Data</label>
                        <input type="date" id="date_consumed" name="date_consumed" class="form-control" 
                               value="<?php echo $meal['date_consumed']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="time_consumed">Horário</label>
                        <input type="time" id="time_consumed" name="time_consumed" class="form-control" 
                               value="<?php echo date('H:i', strtotime($meal['logged_at'])); ?>">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="servings">Quantidade (porções)</label>
                        <input type="number" id="servings" name="servings" class="form-control" 
                               value="<?php echo $meal['servings_consumed']; ?>" min="0.1" step="0.1">
                    </div>
                </div>
            </div>

            <!-- Informações Nutricionais -->
            <div class="form-section">
                <h3 class="section-title">Informações Nutricionais</h3>
                <div class="nutrition-display">
                    <div class="nutrition-grid">
                        <div class="nutrition-item">
                            <div class="nutrition-item-label">Calorias</div>
                            <div class="nutrition-item-value" id="total-kcal">
                                <?php echo round($meal['kcal_consumed']); ?> <span class="nutrition-item-unit">kcal</span>
                            </div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-item-label">Proteínas</div>
                            <div class="nutrition-item-value" id="total-protein">
                                <?php echo round($meal['protein_consumed_g']); ?> <span class="nutrition-item-unit">g</span>
                            </div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-item-label">Carboidratos</div>
                            <div class="nutrition-item-value" id="total-carbs">
                                <?php echo round($meal['carbs_consumed_g']); ?> <span class="nutrition-item-unit">g</span>
                            </div>
                        </div>
                        <div class="nutrition-item">
                            <div class="nutrition-item-label">Gorduras</div>
                            <div class="nutrition-item-value" id="total-fat">
                                <?php echo round($meal['fat_consumed_g']); ?> <span class="nutrition-item-unit">g</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?php echo BASE_APP_URL; ?>/diary.php?date=<?php echo $meal['date_consumed']; ?>'">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" onclick="deleteMeal()">
                    <i class="fas fa-trash"></i>
                    Excluir
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Dados nutricionais por porção (para recálculo)
const nutritionPerServing = {
    kcal: <?php echo $meal['kcal_per_serving'] ?: ($meal['kcal_consumed'] / $meal['servings_consumed']); ?>,
    protein: <?php echo $meal['protein_g_per_serving'] ?: ($meal['protein_consumed_g'] / $meal['servings_consumed']); ?>,
    carbs: <?php echo $meal['carbs_g_per_serving'] ?: ($meal['carbs_consumed_g'] / $meal['servings_consumed']); ?>,
    fat: <?php echo $meal['fat_g_per_serving'] ?: ($meal['fat_consumed_g'] / $meal['servings_consumed']); ?>
};

// Função para atualizar valores nutricionais
function updateNutrition() {
    const servings = parseFloat(document.getElementById('servings').value) || 1;
    
    const totalKcal = Math.round(nutritionPerServing.kcal * servings);
    const totalProtein = Math.round(nutritionPerServing.protein * servings * 10) / 10;
    const totalCarbs = Math.round(nutritionPerServing.carbs * servings * 10) / 10;
    const totalFat = Math.round(nutritionPerServing.fat * servings * 10) / 10;
    
    document.getElementById('total-kcal').innerHTML = totalKcal + ' <span class="nutrition-item-unit">kcal</span>';
    document.getElementById('total-protein').innerHTML = totalProtein + ' <span class="nutrition-item-unit">g</span>';
    document.getElementById('total-carbs').innerHTML = totalCarbs + ' <span class="nutrition-item-unit">g</span>';
    document.getElementById('total-fat').innerHTML = totalFat + ' <span class="nutrition-item-unit">g</span>';
}

// Função para excluir refeição
function deleteMeal() {
    if (confirm('Tem certeza que deseja excluir esta refeição? Esta ação não pode ser desfeita.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo BASE_APP_URL; ?>/process_delete_meal.php';
        
        const fields = {
            'meal_id': '<?php echo $meal['id']; ?>',
            'csrf_token': '<?php echo $_SESSION['csrf_token']; ?>'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Event listeners
document.getElementById('servings').addEventListener('input', updateNutrition);

// Validação do formulário
document.getElementById('edit-meal-form').addEventListener('submit', function(e) {
    const mealName = document.getElementById('meal_name').value.trim();
    const servings = parseFloat(document.getElementById('servings').value);
    
    if (!mealName) {
        e.preventDefault();
        alert('Por favor, insira o nome da refeição.');
        return;
    }
    
    if (!servings || servings <= 0) {
        e.preventDefault();
        alert('Por favor, insira uma quantidade válida.');
        return;
    }
});
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; ?>
<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>
