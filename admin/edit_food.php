<?php
// admin/edit_food.php - Editar alimento

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods';
$page_title = 'Editar Alimento';

$food_id = (int)($_GET['id'] ?? 0);

if ($food_id <= 0) {
    $_SESSION['admin_alert'] = ['type' => 'danger', 'message' => 'ID inválido'];
    header('Location: foods_management.php');
    exit;
}

// Buscar dados do alimento
$stmt = $conn->prepare("SELECT * FROM sf_food_items WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['admin_alert'] = ['type' => 'danger', 'message' => 'Alimento não encontrado'];
    header('Location: foods_management.php');
    exit;
}

$food = $result->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.admin-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.edit-form {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 24px;
}

.form-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.form-control.full-width {
    grid-column: 1 / -1;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
    margin-right: 10px;
}

.btn:hover {
    background: #ff7a1a;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.btn-danger {
    background: #dc3545;
}

.btn-danger:hover {
    background: #c82333;
}

.food-info {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 20px;
    font-size: 12px;
    color: var(--text-secondary);
}

.source-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 8px;
}

.source-taco {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.source-sonia {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.source-usda {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
}

.source-priority {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="admin-container">
    <h1><i class="fas fa-edit"></i> Editar Alimento</h1>
    
    <div class="food-info">
        <strong>ID:</strong> <?php echo $food['id']; ?>
        <span class="source-badge 
            <?php 
            switch ($food['source_table']) {
                case 'TACO': echo 'source-taco'; break;
                case 'Sonia Tucunduva': echo 'source-sonia'; break;
                case 'Sonia Tucunduva (Prioridade)': echo 'source-priority'; break;
                case 'USDA': echo 'source-usda'; break;
                default: echo 'source-taco';
            }
            ?>">
            <?php echo htmlspecialchars($food['source_table']); ?>
        </span>
    </div>
    
    <div class="edit-form">
        <h2 class="form-title">Dados Nutricionais</h2>
        
        <form method="POST" action="process_food.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo $food['id']; ?>">
            
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Nome do Alimento</label>
                    <input type="text" name="name_pt" class="form-control" value="<?php echo htmlspecialchars($food['name_pt']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Calorias (por 100g)</label>
                    <input type="number" name="energy_kcal_100g" class="form-control" step="0.01" value="<?php echo $food['energy_kcal_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Proteína (por 100g)</label>
                    <input type="number" name="protein_g_100g" class="form-control" step="0.01" value="<?php echo $food['protein_g_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Carboidratos (por 100g)</label>
                    <input type="number" name="carbohydrate_g_100g" class="form-control" step="0.01" value="<?php echo $food['carbohydrate_g_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Gordura (por 100g)</label>
                    <input type="number" name="fat_g_100g" class="form-control" step="0.01" value="<?php echo $food['fat_g_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Fonte</label>
                    <select name="source_table" class="form-control">
                        <option value="TACO" <?php echo $food['source_table'] === 'TACO' ? 'selected' : ''; ?>>TACO</option>
                        <option value="Sonia Tucunduva" <?php echo $food['source_table'] === 'Sonia Tucunduva' ? 'selected' : ''; ?>>Sonia Tucunduva</option>
                        <option value="Sonia Tucunduva (Prioridade)" <?php echo $food['source_table'] === 'Sonia Tucunduva (Prioridade)' ? 'selected' : ''; ?>>Sonia Tucunduva (Prioridade)</option>
                        <option value="USDA" <?php echo $food['source_table'] === 'USDA' ? 'selected' : ''; ?>>USDA</option>
                        <option value="Manual" <?php echo $food['source_table'] === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                    </select>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 24px;">
                <button type="submit" class="btn"><i class="fas fa-save"></i> Salvar Alterações</button>
                <a href="foods_management.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Tem certeza que deseja EXCLUIR este alimento?\n\nEsta ação não pode ser desfeita!')">
                    <i class="fas fa-trash"></i> Excluir
                </a>
            </div>
        </form>
    </div>
    
    <!-- Informações adicionais -->
    <div class="edit-form" style="margin-top: 20px;">
        <h3 style="margin: 0 0 16px 0; color: var(--text-primary);"><i class="fas fa-chart-line"></i> Informações Calculadas</h3>
        
        <div class="form-grid">
            <div class="form-group">
                <label>Total de Macronutrientes</label>
                <input type="text" class="form-control" value="<?php echo number_format($food['protein_g_100g'] + $food['carbohydrate_g_100g'] + $food['fat_g_100g'], 1); ?>g" readonly>
            </div>
            
            <div class="form-group">
                <label>Calorias por Proteína</label>
                <input type="text" class="form-control" value="<?php echo number_format($food['protein_g_100g'] * 4, 1); ?> kcal" readonly>
            </div>
            
            <div class="form-group">
                <label>Calorias por Carboidratos</label>
                <input type="text" class="form-control" value="<?php echo number_format($food['carbohydrate_g_100g'] * 4, 1); ?> kcal" readonly>
            </div>
            
            <div class="form-group">
                <label>Calorias por Gordura</label>
                <input type="text" class="form-control" value="<?php echo number_format($food['fat_g_100g'] * 9, 1); ?> kcal" readonly>
            </div>
        </div>
        
        <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3); border-radius: 8px; padding: 12px; margin-top: 16px;">
            <p style="margin: 0; color: #ffc107; font-size: 12px;">
                <strong><i class="fas fa-lightbulb"></i> Dica:</strong> 
                Calorias totais devem ser aproximadamente: 
                (Proteína × 4) + (Carboidratos × 4) + (Gordura × 9) = 
                <?php echo number_format(($food['protein_g_100g'] * 4) + ($food['carbohydrate_g_100g'] * 4) + ($food['fat_g_100g'] * 9), 1); ?> kcal
            </p>
        </div>
    </div>
</div>

<script>
// Validação em tempo real
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[type="number"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (parseFloat(this.value) < 0) {
                this.value = 0;
            }
        });
    });
    
    // Calcular total de macronutrientes em tempo real
    function updateCalculations() {
        const protein = parseFloat(document.querySelector('input[name="protein_g_100g"]').value) || 0;
        const carbs = parseFloat(document.querySelector('input[name="carbohydrate_g_100g"]').value) || 0;
        const fat = parseFloat(document.querySelector('input[name="fat_g_100g"]').value) || 0;
        
        const total = protein + carbs + fat;
        const calories = (protein * 4) + (carbs * 4) + (fat * 9);
        
        // Atualizar campos calculados se existirem
        const totalInput = document.querySelector('input[value*="g"][readonly]');
        if (totalInput) {
            totalInput.value = total.toFixed(1) + 'g';
        }
    }
    
    inputs.forEach(input => {
        input.addEventListener('input', updateCalculations);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
