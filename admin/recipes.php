<?php
// admin/recipes.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'recipes';
$page_title = 'Gerenciar Receitas';
$extra_css = ['recipes.css'];

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

// Busca todas as categorias da sua tabela `sf_categories`
$categories = [];
$category_sql = "SELECT id, name FROM `sf_categories` ORDER BY name ASC";
if ($category_result = $conn->query($category_sql)) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
    $category_result->free();
}

// --- Constrói a query de busca de receitas dinamicamente ---
$sql = "SELECT DISTINCT r.id, r.name, r.created_at, r.is_public, r.image_filename
        FROM sf_recipes r";

$conditions = [];
$params = [];
$types = '';

// Adiciona o JOIN com a tabela de "ponte" `sf_recipe_categories`
if ($category_id) {
    $sql .= " LEFT JOIN `sf_recipe_categories` rc ON r.id = rc.recipe_id";
    $conditions[] = "rc.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

// Adiciona a condição de busca por texto
if (!empty($search_term)) {
    $conditions[] = "r.name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= 's';
}

// Junta as condições na query SQL
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY r.created_at DESC";

// Prepara e executa a query
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Erro fatal: A consulta SQL não pôde ser preparada.");
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="recipes-page-container">
    <div class="toolbar">
        <h2>Receitas</h2>
        <form method="GET" action="recipes.php" class="search-form-flex">
            <input type="text" name="search" placeholder="Buscar por nome da receita..." value="<?php echo htmlspecialchars($search_term); ?>">
            <div class="custom-select-wrapper">
                <input type="hidden" name="category_id" id="category_id_input" value="<?php echo $category_id ?: ''; ?>">
                <div class="custom-select" id="category_select">
                    <div class="custom-select-trigger">
                        <span class="custom-select-value">
                            <?php 
                            if ($category_id) {
                                foreach ($categories as $cat) {
                                    if ($cat['id'] == $category_id) {
                                        echo htmlspecialchars($cat['name']);
                                        break;
                                    }
                                }
                            } else {
                                echo 'Todas as Categorias';
                            }
                            ?>
                        </span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="custom-select-options">
                        <div class="custom-select-option" data-value="">Todas as Categorias</div>
                        <?php foreach ($categories as $category): ?>
                            <div class="custom-select-option <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>" data-value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="custom-select-overlay" id="custom_select_overlay"></div>
            </div>
            <button type="submit"><i class="fas fa-search"></i> Filtrar</button>
            <a href="recipes.php" class="btn-secondary">Limpar</a>
        </form>
        <a href="edit_recipe.php" class="btn-primary"><i class="fas fa-plus"></i> Nova Receita</a>
    </div>

<div class="content-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Imagem</th>
                <th>Nome da Receita</th>
                <th>Data de Criação</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recipes)): ?>
                <tr>
                    <td colspan="5" class="empty-state">Nenhuma receita encontrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <tr>
                        <td>
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . ($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" alt="Foto de <?php echo htmlspecialchars($recipe['name']); ?>" class="table-image-preview">
                        </td>
                        <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($recipe['created_at'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $recipe['is_public'] ? 'status-public' : 'status-private'; ?>">
                                <?php echo $recipe['is_public'] ? 'Pública' : 'Privada'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-action edit" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                            <a href="delete_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-action delete" title="Apagar" onclick="return confirm('Tem certeza que deseja apagar esta receita? Esta ação não pode ser desfeita.');"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<script>
// Custom Select Dropdown Functionality
(function() {
    const customSelect = document.getElementById('category_select');
    const hiddenInput = document.getElementById('category_id_input');
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const options = customSelect.querySelectorAll('.custom-select-option');
    const valueDisplay = customSelect.querySelector('.custom-select-value');
    const overlay = document.getElementById('custom_select_overlay');
    
    // Toggle dropdown
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        customSelect.classList.toggle('active');
    });
    
    // Select option
    options.forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Remove selected class from all options
            options.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Update hidden input value
            const value = this.getAttribute('data-value');
            hiddenInput.value = value;
            
            // Update displayed value
            valueDisplay.textContent = this.textContent;
            
            // Close dropdown
            customSelect.classList.remove('active');
        });
    });
    
    // Close dropdown when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            customSelect.classList.remove('active');
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!customSelect.contains(e.target) && !overlay.contains(e.target)) {
            customSelect.classList.remove('active');
        }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && customSelect.classList.contains('active')) {
            customSelect.classList.remove('active');
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>