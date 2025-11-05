<?php
// admin/recipes.php (REFATORADO COM ESTILO VIEW_USER E PAGINAÇÃO)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'recipes';
$page_title = 'Gerenciar Receitas';

// --- Lógica de busca, filtro e paginação ---
$search_term = trim($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20; // Itens por página
$offset = ($page - 1) * $limit;

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
$base_sql = "SELECT DISTINCT r.id, r.name, r.created_at, r.is_public, r.image_filename
        FROM sf_recipes r";

$conditions = [];
$params = [];
$types = '';

// Adiciona o JOIN com a tabela de "ponte" `sf_recipe_categories`
if ($category_id) {
    $base_sql .= " LEFT JOIN `sf_recipe_categories` rc ON r.id = rc.recipe_id";
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
    $base_sql .= " WHERE " . implode(" AND ", $conditions);
}

$base_sql .= " ORDER BY r.created_at DESC";

// --- Contagem total para paginação ---
$count_sql = "SELECT COUNT(DISTINCT r.id) as total " . substr($base_sql, strpos($base_sql, "FROM"));
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count) {
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_recipes = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
    $total_recipes = 0;
}

$total_pages = ceil($total_recipes / $limit);

// --- Query com paginação ---
$sql = $base_sql . " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

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
    <!-- Header Card -->
    <div class="dashboard-card recipes-header-card">
        <div class="card-header-section">
            <div class="header-title">
                <h2><i class="fas fa-utensils"></i> Gerenciar Receitas</h2>
                <p>Gerencie todas as receitas cadastradas no sistema</p>
            </div>
            <a href="edit_recipe.php" class="btn-add-recipe-circular" title="Nova Receita">
                <i class="fas fa-plus"></i>
            </a>
        </div>
        
        <!-- Filtros -->
        <form method="GET" action="recipes.php" class="recipes-filter-form">
            <div class="filter-row">
                <div class="form-group">
                    <input type="text" 
                           name="search" 
                           placeholder="Buscar por nome da receita..." 
                           value="<?php echo htmlspecialchars($search_term); ?>" 
                           class="form-control recipe-search-input">
                </div>
                <div class="form-group">
                    <div class="custom-select-wrapper" id="category_select_wrapper">
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
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-filter-circular" title="Filtrar">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <?php if (!empty($search_term) || $category_id): ?>
                <div class="form-group">
                    <a href="recipes.php" class="btn-secondary recipe-clear-btn">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Grid de Receitas -->
    <?php if (empty($recipes)): ?>
        <div class="dashboard-card empty-state-card">
            <div class="empty-state-content">
                <i class="fas fa-utensils"></i>
                <p>Nenhuma receita encontrada.</p>
                <?php if (!empty($search_term) || $category_id): ?>
                    <a href="recipes.php" class="btn-primary">Ver Todas as Receitas</a>
                <?php else: ?>
                    <a href="edit_recipe.php" class="btn-primary">Criar Primeira Receita</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="recipes-grid">
            <?php foreach ($recipes as $recipe): ?>
                <div class="dashboard-card recipe-card">
                    <div class="recipe-image-container">
                        <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . ($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" 
                             alt="Foto de <?php echo htmlspecialchars($recipe['name']); ?>" 
                             class="recipe-image"
                             loading="lazy"
                             decoding="async">
                        <span class="status-badge <?php echo $recipe['is_public'] ? 'status-public' : 'status-private'; ?>">
                            <?php echo $recipe['is_public'] ? 'Pública' : 'Privada'; ?>
                        </span>
                    </div>
                    <div class="recipe-content">
                        <h3 class="recipe-name"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <div class="recipe-meta">
                            <span class="recipe-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($recipe['created_at'])); ?>
                            </span>
                        </div>
                        <div class="recipe-actions">
                            <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-action edit" title="Editar">
                                <i class="fas fa-pencil-alt"></i> Editar
                            </a>
                            <a href="delete_recipe.php?id=<?php echo $recipe['id']; ?>" 
                               class="btn-action delete" 
                               title="Apagar" 
                               onclick="return confirm('Tem certeza que deseja apagar esta receita? Esta ação não pode ser desfeita.');">
                                <i class="fas fa-trash-alt"></i> Apagar
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="dashboard-card pagination-card">
                <div class="pagination-info">
                    Mostrando <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $total_recipes); ?> de <?php echo $total_recipes; ?> receitas
                </div>
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>" 
                           class="btn-secondary pagination-btn">
                            <i class="fas fa-chevron-left"></i> Anterior
                        </a>
                    <?php endif; ?>
                    
                    <div class="pagination-numbers">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>" 
                               class="pagination-number">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>" 
                               class="pagination-number <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>" 
                               class="pagination-number"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $category_id ? '&category_id=' . $category_id : ''; ?>" 
                           class="btn-secondary pagination-btn">
                            Próxima <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* ========================================================================= */
/*       RECIPES PAGE - REFATORADO COM ESTILO VIEW_USER                     */
/* ========================================================================= */

.recipes-page-container {
    width: 100%;
    max-width: 100%;
    padding: 0;
}

/* Header Card */
.recipes-header-card {
    margin-bottom: 2rem;
    overflow: visible !important; /* Permite que o dropdown apareça fora do card */
    position: relative;
    z-index: 1; /* Z-index baixo para não interferir */
}

.card-header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-wrap: wrap;
    gap: 1rem;
}

.header-title h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-title h2 i {
    color: var(--accent-orange);
    font-size: 1.5rem;
}

.header-title p {
    margin: 0;
    font-size: 0.95rem;
    color: var(--text-secondary);
}

/* Botão circular de adicionar receita (igual ao botão de adicionar missão) */
.btn-add-recipe-circular {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    text-decoration: none;
}

.btn-add-recipe-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-add-recipe-circular i {
    font-size: 1.5rem;
}

/* Botão circular de filtrar */
.btn-filter-circular {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
    text-decoration: none;
    padding: 0;
    margin: 0;
}

.btn-filter-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-filter-circular i {
    font-size: 1.25rem;
}

/* Filter Form */
.recipes-filter-form {
    width: 100%;
}

.filter-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto auto;
    gap: 1rem;
    align-items: center;
}

.filter-row .form-group {
    margin-bottom: 0;
}

.recipe-search-input {
    width: 100%;
}

.recipe-clear-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    white-space: nowrap;
    text-decoration: none;
    border-radius: 12px;
}

/* ============================================================= */
/*       CSS FINAL E CORRETO PARA O CUSTOM SELECT              */
/* ============================================================= */

/* 1. O container principal PRECISA ter position: relative */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    box-sizing: border-box; /* Garante que o padding não afete a largura total */
}

/* Efeito hover e rotação da seta */
.custom-select-trigger:hover {
    border-color: var(--accent-orange);
}
.custom-select.active .custom-select-trigger i {
    transform: rotate(180deg);
}

.custom-select-trigger i {
    transition: transform 0.3s ease;
    color: var(--text-secondary);
}

/* 2. A lista de opções. A MÁGICA ESTÁ AQUI. */
.custom-select-options {
    display: none; /* Escondido por padrão */
    position: absolute; /* Posicionado em relação ao .custom-select-wrapper */
    
    top: calc(100% + 8px); /* Aparece abaixo do botão com um respiro de 8px */
    
    /* FORÇA A LARGURA A SER IDÊNTICA AO PAI */
    left: 0;
    right: 0;
    
    z-index: 1000; /* Garante que fique por cima de outros elementos */
    
    background: rgba(255, 255, 255, 0.05); /* Mesma cor do trigger */
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    max-height: 250px;
    overflow-y: auto;
    box-sizing: border-box; /* Essencial para o cálculo correto da largura */
}

/* 3. Mostra a lista quando o pai tem a classe .active */
.custom-select.active .custom-select-options {
    display: block;
}

.custom-select-option {
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-select-option:last-child {
    border-bottom: none;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
    font-weight: 600;
}

/* Recipes Grid */
.recipes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.recipe-card {
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
}

.recipe-image-container {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.recipe-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.recipe-card:hover .recipe-image {
    transform: scale(1.05);
}

.status-badge {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-public {
    background: rgba(76, 175, 80, 0.2);
    color: #4CAF50;
    border: 1px solid rgba(76, 175, 80, 0.4);
}

.status-private {
    background: rgba(158, 158, 158, 0.2);
    color: #9E9E9E;
    border: 1px solid rgba(158, 158, 158, 0.4);
}

.recipe-content {
    padding: 1.5rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.recipe-name {
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.4;
}

.recipe-meta {
    margin-bottom: 1rem;
}

.recipe-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.recipe-date i {
    color: var(--accent-orange);
    font-size: 0.75rem;
}

.recipe-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: auto;
}

.btn-action {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-action.edit {
    background: rgba(33, 150, 243, 0.1);
    color: #2196F3;
    border: 1px solid rgba(33, 150, 243, 0.3);
}

.btn-action.edit:hover {
    background: rgba(33, 150, 243, 0.2);
    border-color: #2196F3;
    transform: translateY(-2px);
}

.btn-action.delete {
    background: rgba(244, 67, 54, 0.1);
    color: #F44336;
    border: 1px solid rgba(244, 67, 54, 0.3);
}

.btn-action.delete:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: #F44336;
    transform: translateY(-2px);
}

/* Empty State */
.empty-state-card {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.empty-state-content i {
    font-size: 4rem;
    color: var(--text-secondary);
    opacity: 0.5;
    margin-bottom: 0.5rem;
}

.empty-state-content p {
    font-size: 1.125rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Pagination */
.pagination-card {
    margin-top: 2rem;
}

.pagination-info {
    text-align: center;
    margin-bottom: 1rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.pagination-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.pagination-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    text-decoration: none;
    white-space: nowrap;
    border-radius: 12px;
}

.pagination-numbers {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pagination-number {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-number:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.pagination-number.active {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.pagination-ellipsis {
    color: var(--text-secondary);
    padding: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .recipes-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .recipe-add-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Custom Select Dropdown Functionality - VERSÃO SIMPLIFICADA (CSS FAZ O TRABALHO)
(function() {
    const customSelect = document.getElementById('category_select');
    if (!customSelect) return;
    
    const hiddenInput = document.getElementById('category_id_input');
    const trigger = customSelect.querySelector('.custom-select-trigger');
    const optionsContainer = customSelect.querySelector('.custom-select-options');
    const options = customSelect.querySelectorAll('.custom-select-option');
    const valueDisplay = customSelect.querySelector('.custom-select-value');

    // Abre/fecha o dropdown
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        customSelect.classList.toggle('active');
    });

    // Seleciona uma opção
    options.forEach(option => {
        option.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Atualiza o valor do input escondido
            hiddenInput.value = this.getAttribute('data-value');
            // Atualiza o texto visível
            valueDisplay.textContent = this.textContent;
            
            // Remove a classe 'selected' de todos e adiciona na clicada
            options.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');

            // Fecha o dropdown
            customSelect.classList.remove('active');
            
            // Submete o formulário
            customSelect.closest('form').submit();
        });
    });

    // Fecha o dropdown se clicar fora
    document.addEventListener('click', function(e) {
        if (!customSelect.contains(e.target)) {
            customSelect.classList.remove('active');
        }
    });

    // Fecha com a tecla Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            customSelect.classList.remove('active');
        }
    });
})();
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>
