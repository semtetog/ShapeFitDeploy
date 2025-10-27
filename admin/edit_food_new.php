<?php
// admin/edit_food_new.php - Editar alimento - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods';
$page_title = 'Editar Alimento';

$food_id = (int)($_GET['id'] ?? 0);

if ($food_id <= 0) {
    $_SESSION['admin_alert'] = ['type' => 'danger', 'message' => 'ID inválido'];
    header('Location: foods_management_new.php');
    exit;
}

// Buscar dados do alimento
$stmt = $conn->prepare("SELECT * FROM sf_food_items WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['admin_alert'] = ['type' => 'danger', 'message' => 'Alimento não encontrado'];
    header('Location: foods_management_new.php');
    exit;
}

$food = $result->fetch_assoc();
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/foods_admin.css">

<div class="admin-container">
    <!-- Cabeçalho -->
    <div class="page-header">
        <h1>
            <i class="fas fa-edit"></i>
            Editar Alimento
        </h1>
    </div>
    
    <!-- Informações do Alimento -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informações do Alimento
            </h2>
        </div>
        <div class="section-content">
            <div class="d-flex align-center justify-between">
                <div>
                    <strong>ID:</strong> <?php echo $food['id']; ?>
                    <span class="badge 
                        <?php 
                        switch ($food['source_table']) {
                            case 'TACO': echo 'badge-taco'; break;
                            case 'Sonia Tucunduva': echo 'badge-sonia'; break;
                            case 'Sonia Tucunduva (Prioridade)': echo 'badge-priority'; break;
                            case 'USDA': echo 'badge-usda'; break;
                            default: echo 'badge-taco';
                        }
                        ?>">
                        <?php echo htmlspecialchars($food['source_table']); ?>
                    </span>
                </div>
                <div>
                    <a href="foods_management_new.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar à Lista
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulário de Edição -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-edit"></i>
                Dados Nutricionais
            </h2>
        </div>
        <div class="section-content">
            <form method="POST" action="process_food.php" class="form-grid">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $food['id']; ?>">
                
                <div class="form-group">
                    <label>Nome do Alimento</label>
                    <input type="text" name="name_pt" class="form-control" 
                           value="<?php echo htmlspecialchars($food['name_pt']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Calorias (por 100g)</label>
                    <input type="number" name="energy_kcal_100g" class="form-control" 
                           step="0.01" value="<?php echo $food['energy_kcal_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Proteína (por 100g)</label>
                    <input type="number" name="protein_g_100g" class="form-control" 
                           step="0.01" value="<?php echo $food['protein_g_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Carboidratos (por 100g)</label>
                    <input type="number" name="carbohydrate_g_100g" class="form-control" 
                           step="0.01" value="<?php echo $food['carbohydrate_g_100g']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Gordura (por 100g)</label>
                    <input type="number" name="fat_g_100g" class="form-control" 
                           step="0.01" value="<?php echo $food['fat_g_100g']; ?>" required>
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
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                    <a href="foods_management_new.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                    <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('Tem certeza que deseja EXCLUIR este alimento?\n\nEsta ação não pode ser desfeita!')">
                        <i class="fas fa-trash"></i>
                        Excluir
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Informações Calculadas -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-calculator"></i>
                Informações Calculadas
            </h2>
        </div>
        <div class="section-content">
            <div class="form-grid">
                <div class="form-group">
                    <label>Total de Macronutrientes</label>
                    <input type="text" class="form-control" 
                           value="<?php echo number_format($food['protein_g_100g'] + $food['carbohydrate_g_100g'] + $food['fat_g_100g'], 1); ?>g" 
                           readonly>
                </div>
                
                <div class="form-group">
                    <label>Calorias por Proteína</label>
                    <input type="text" class="form-control" 
                           value="<?php echo number_format($food['protein_g_100g'] * 4, 1); ?> kcal" 
                           readonly>
                </div>
                
                <div class="form-group">
                    <label>Calorias por Carboidratos</label>
                    <input type="text" class="form-control" 
                           value="<?php echo number_format($food['carbohydrate_g_100g'] * 4, 1); ?> kcal" 
                           readonly>
                </div>
                
                <div class="form-group">
                    <label>Calorias por Gordura</label>
                    <input type="text" class="form-control" 
                           value="<?php echo number_format($food['fat_g_100g'] * 9, 1); ?> kcal" 
                           readonly>
                </div>
                
                <div class="form-group">
                    <label>Calorias Totais Calculadas</label>
                    <input type="text" class="form-control" 
                           value="<?php echo number_format(($food['protein_g_100g'] * 4) + ($food['carbohydrate_g_100g'] * 4) + ($food['fat_g_100g'] * 9), 1); ?> kcal" 
                           readonly>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Dica:</strong> 
                As calorias totais devem ser aproximadamente: 
                (Proteína × 4) + (Carboidratos × 4) + (Gordura × 9) = 
                <?php echo number_format(($food['protein_g_100g'] * 4) + ($food['carbohydrate_g_100g'] * 4) + ($food['fat_g_100g'] * 9), 1); ?> kcal
            </div>
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
            updateCalculations();
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
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


