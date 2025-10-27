<?php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

// Configuração de timezone
date_default_timezone_set('America/Sao_Paulo');

// Lógica de filtros e busca
$search_query = trim($_GET['q'] ?? '');
$sort_by_param = trim($_GET['sort'] ?? '');
$filter_categories_str = trim($_GET['categories'] ?? '');
$filter_categories = !empty($filter_categories_str) ? explode(',', $filter_categories_str) : [];
$is_filtered_view = !empty($search_query) || !empty($filter_categories) || !empty($sort_by_param);

// Buscar categorias para filtro
$all_categories_for_filter = $conn->query("SELECT id, name FROM sf_categories ORDER BY display_order ASC, name ASC")->fetch_all(MYSQLI_ASSOC);
$active_filter_names = [];

if ($is_filtered_view) {
    // Construir nomes dos filtros ativos
    if (!empty($filter_categories)) {
        $category_map = array_column($all_categories_for_filter, 'name', 'id');
        foreach ($filter_categories as $cat_id) {
            if (isset($category_map[$cat_id])) {
                $active_filter_names[] = $category_map[$cat_id];
            }
        }
    }
    if (!empty($search_query)) {
        $active_filter_names[] = '"' . htmlspecialchars($search_query) . '"';
    }
    
    // Construir query SQL
    $sort_by = $sort_by_param ?: 'name_asc';
    $recipes = [];
    $sql_base = "SELECT DISTINCT r.id, r.name, r.image_filename, r.kcal_per_serving, r.description, r.prep_time_minutes, r.cook_time_minutes, r.protein_g_per_serving FROM sf_recipes r";
    $sql_joins = "";
    $sql_conditions = ["r.is_public = 1"];
    $params = [];
    $types = "";
    
    if (!empty($filter_categories)) {
        $sql_joins .= " JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id";
        $placeholders = implode(',', array_fill(0, count($filter_categories), '?'));
        $sql_conditions[] = "rhc.category_id IN ($placeholders)";
        foreach ($filter_categories as $cat_id) {
            $params[] = (int)$cat_id;
        }
        $types .= str_repeat('i', count($filter_categories));
    }
    
    if (!empty($search_query)) {
        $sql_conditions[] = "(r.name LIKE ? OR r.description LIKE ?)";
        $params[] = "%" . $search_query . "%";
        $params[] = "%" . $search_query . "%";
        $types .= "ss";
    }
    
    $sql_query = $sql_base . $sql_joins . " WHERE " . implode(" AND ", $sql_conditions);
    
    if (count($filter_categories) > 1) {
        $sql_query .= " GROUP BY r.id HAVING COUNT(DISTINCT rhc.category_id) = " . count($filter_categories);
    }
    
    // Ordenação
    $order_by_clause = " ORDER BY ";
    switch ($sort_by) {
        case 'kcal_asc':
            $order_by_clause .= "r.kcal_per_serving ASC, r.name ASC";
            break;
        case 'protein_desc':
            $order_by_clause .= "r.protein_g_per_serving DESC, r.name ASC";
            break;
        case 'time_asc':
            $order_by_clause .= "(r.prep_time_minutes + r.cook_time_minutes) ASC, r.name ASC";
            break;
        default:
            $order_by_clause .= "r.name ASC";
            break;
    }
    $sql_query .= $order_by_clause;
    
    $stmt = $conn->prepare($sql_query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Modo carrossel - buscar receitas por categoria
    $sections_with_recipes = [];
    foreach ($all_categories_for_filter as $category) {
        $stmt = $conn->prepare("SELECT r.id, r.name, r.image_filename, r.kcal_per_serving FROM sf_recipes r JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id WHERE r.is_public = 1 AND rhc.category_id = ? ORDER BY RAND() LIMIT 6");
        $stmt->bind_param("i", $category['id']);
        $stmt->execute();
        $recipes_in_section = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (!empty($recipes_in_section)) {
            $sections_with_recipes[] = [
                'title' => $category['name'],
                'recipes' => $recipes_in_section,
                'link_params' => http_build_query(['categories' => $category['id']])
            ];
        }
    }
}

$page_title = "Explorar Receitas";
$extra_css = ['pages/_dashboard.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === CONTAINER PRINCIPAL === */
.app-container {
    padding: calc(24px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;
    min-height: 100vh;
    box-sizing: border-box;
}

/* === HEADER === */
.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 0 24px;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* === BARRA DE BUSCA === */
.search-section {
    display: flex;
    gap: 12px;
    margin-bottom: 1.5rem;
    align-items: center;
    padding: 0 24px;
}

.search-wrapper {
    flex: 1;
    position: relative;
}

.search-input {
    width: 100%;
    height: 48px;
    padding: 0 16px 0 48px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    color: var(--text-primary);
    font-size: 16px;
    box-sizing: border-box;
    transition: all 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 16px;
    pointer-events: none;
}

.filter-btn {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.filter-btn.active::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background: var(--accent-orange);
    border-radius: 50%;
    border: 2px solid var(--bg-color);
}

/* === BOTÃO FAVORITOS === */
.favorites-section {
    margin-bottom: 2rem;
    padding: 0 24px;
}

.favorites-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    height: 48px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    color: var(--text-primary);
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.favorites-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.favorites-btn i {
    color: var(--accent-orange);
    font-size: 18px;
}

/* === FILTROS ATIVOS === */
.active-filters {
    text-align: center;
    margin: 0 24px 1.5rem 24px;
    padding: 12px 16px;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 12px;
    font-size: 14px;
    color: var(--text-secondary);
    display: inline-block;
    width: auto;
    max-width: calc(100% - 48px);
}

.active-filters strong {
    color: var(--accent-orange);
    font-weight: 600;
}

/* === LISTA DE RECEITAS (MODO FILTRADO) === */
.recipes-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 0 24px;
}

.recipe-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    text-decoration: none;
    transition: all 0.2s ease;
}


.recipe-image {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
}

.recipe-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
}

.recipe-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recipe-kcal {
    font-size: 14px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
}

.recipe-kcal i {
    color: var(--accent-orange);
}

/* === CARROSSÉIS (MODO NORMAL) === */
.categories-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.category-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 24px;
}

.category-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.view-all-link {
    font-size: 14px;
    color: var(--accent-orange);
    text-decoration: none;
    font-weight: 500;
    transition: opacity 0.2s ease;
}

.view-all-link:hover {
    opacity: 0.8;
}

.recipes-carousel {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding: 0 24px 8px 24px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.recipes-carousel::-webkit-scrollbar {
    display: none;
}

.recipe-card {
    flex-shrink: 0;
    width: 160px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    transition: all 0.2s ease;
}


.card-image {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.card-info {
    padding: 12px;
}

.card-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
    line-height: 1.3;
}

.card-kcal {
    font-size: 12px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 4px;
}

.card-kcal i {
    color: var(--accent-orange);
    font-size: 10px;
}

/* === MODAL DE FILTROS REDESENHADO === */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.visible {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    position: fixed;
    bottom: calc(60px + env(safe-area-inset-bottom));
    left: 0;
    right: 0;
    background: #1C1C1E;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    transform: translateY(100%);
    transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    z-index: 1001;
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.modal-overlay.visible .modal-content {
    transform: translateY(0);
}

.modal-header {
    text-align: center;
    position: relative;
    padding: 20px 24px 12px 24px;
    cursor: grab;
    flex-shrink: 0;
}

.modal-header:active {
    cursor: grabbing;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 4px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.modal-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    padding-top: 12px;
}

.modal-scrollable-content {
    padding: 16px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* === AQUI A CORREÇÃO FINAL === */
.modal-actions {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 24px; /* Padding igual em todos os lados, sem safe-area extra */
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.filter-group-title {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 12px;
    text-align: left;
}

.filter-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.filter-option label {
    display: block;
    padding: 12px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    font-size: 14px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.category-options {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.category-option label {
    display: block;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    font-size: 13px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-option input, .category-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.filter-option input:checked + label, .category-option input:checked + label {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: #fff;
    font-weight: 600;
}

.btn-secondary, .btn-primary {
    flex: 1;
    height: 48px;
    border-radius: 14px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-primary {
    background: var(--accent-orange);
    color: #fff;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.btn-primary:hover {
    background: #e55a00;
}
</style>

<div class="app-container">
    <!-- Header -->
    <header class="page-header">
        <h1 class="page-title">Explorar Receitas</h1>
    </header>

    <!-- Busca e Filtros -->
    <div class="search-section">
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="search" id="search-input" class="search-input" placeholder="Buscar por nome..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button class="filter-btn" id="filter-btn" aria-label="Filtrar">
            <i class="fas fa-sliders-h"></i>
        </button>
    </div>

    <!-- Botão Favoritos -->
    <div class="favorites-section">
        <a href="<?php echo BASE_APP_URL; ?>/favorite_recipes.php" class="favorites-btn">
            <i class="fas fa-heart"></i>
            <span>Minhas Favoritas</span>
        </a>
    </div>

    <!-- Filtros Ativos -->
    <?php if ($is_filtered_view && !empty($active_filter_names)): ?>
        <div style="text-align: center;">
            <div class="active-filters">
                Filtrando por: <strong><?php echo implode(', ', $active_filter_names); ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <!-- Conteúdo Principal -->
    <main id="main-content">
        <?php if ($is_filtered_view): ?>
            <!-- Lista de Receitas (Modo Filtrado) -->
            <div class="recipes-list">
                <?php if (!empty($recipes)): ?>
                    <?php foreach ($recipes as $recipe): ?>
                        <a href="view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-item">
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($recipe['name']); ?>" 
                                 class="recipe-image">
                            <div class="recipe-info">
                                <h3 class="recipe-name"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                                <span class="recipe-kcal">
                                    <i class="fas fa-fire-alt"></i>
                                    <?php echo round($recipe['kcal_per_serving']); ?> kcal
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--text-secondary);">
                        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                        <p>Nenhuma receita encontrada com estes filtros.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Carrosséis por Categoria (Modo Normal) -->
            <div class="categories-grid">
                <?php foreach ($sections_with_recipes as $section): ?>
                    <section class="category-section">
                        <div class="category-header">
                            <h2 class="category-title"><?php echo htmlspecialchars($section['title']); ?></h2>
                            <a href="explore_recipes.php?<?php echo $section['link_params']; ?>" class="view-all-link">
                                Ver mais
                            </a>
                        </div>
                        <div class="recipes-carousel">
                            <?php foreach ($section['recipes'] as $recipe): ?>
                                <a href="view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-card">
                                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($recipe['name']); ?>" 
                                         class="card-image">
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($recipe['name']); ?></h4>
                                        <span class="card-kcal">
                                            <i class="fas fa-fire-alt"></i>
                                            <?php echo round($recipe['kcal_per_serving']); ?> kcal
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal de Filtros -->
<div class="modal-overlay" id="filter-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Filtros</h2>
        </div>
        
        <div class="modal-scrollable-content">
            <div class="filter-group">
                <h3 class="filter-group-title">Ordenar por</h3>
                <div class="filter-options">
                    <div class="filter-option">
                        <input type="radio" id="sort_name_asc" name="sort" value="name_asc">
                        <label for="sort_name_asc">Nome (A-Z)</label>
                    </div>
                    <div class="filter-option">
                        <input type="radio" id="sort_kcal_asc" name="sort" value="kcal_asc">
                        <label for="sort_kcal_asc">Menos Calóricas</label>
                    </div>
                    <div class="filter-option">
                        <input type="radio" id="sort_protein_desc" name="sort" value="protein_desc">
                        <label for="sort_protein_desc">Mais Proteicas</label>
                    </div>
                    <div class="filter-option">
                        <input type="radio" id="sort_time_asc" name="sort" value="time_asc">
                        <label for="sort_time_asc">Mais Rápidas</label>
                    </div>
                </div>
            </div>
            
            <div class="filter-group">
                <h3 class="filter-group-title">Filtrar por Categoria</h3>
                <div class="category-options">
                    <?php foreach ($all_categories_for_filter as $category): ?>
                        <div class="category-option">
                            <input type="checkbox" id="cat_<?php echo $category['id']; ?>" name="categories" value="<?php echo $category['id']; ?>">
                            <label for="cat_<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="modal-actions">
            <button class="btn-secondary" id="clear-filters">Limpar</button>
            <button class="btn-primary" id="apply-filters">Aplicar</button>
        </div>
    </div>
</div>

<?php include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos
    const filterBtn = document.getElementById('filter-btn');
    const filterModal = document.getElementById('filter-modal');
    const modalContent = filterModal.querySelector('.modal-content');
    const searchInput = document.getElementById('search-input');
    const applyBtn = document.getElementById('apply-filters');
    const clearBtn = document.getElementById('clear-filters');
    
    // Funções do Modal
    if (filterBtn && filterModal) {
        const openModal = () => {
            modalContent.style.transform = ''; 
            filterModal.classList.add('visible');
            document.body.style.overflow = 'hidden';
        };
        
        const closeModal = () => {
            filterModal.classList.remove('visible');
            document.body.style.overflow = '';
        };
        
        filterBtn.addEventListener('click', openModal);
        filterModal.addEventListener('click', (e) => {
            if (e.target === filterModal) {
                closeModal();
            }
        });
        
        // Funcionalidade de arrastar para fechar
        const modalHeader = modalContent.querySelector('.modal-header');
        if (modalHeader) {
            let startY = 0;
            let isDragging = false;
            
            modalHeader.addEventListener('touchstart', (e) => {
                startY = e.touches[0].clientY;
                isDragging = true;
                modalContent.style.transition = 'none';
            }, { passive: true });
            
            modalHeader.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                const currentY = e.touches[0].clientY;
                const deltaY = currentY - startY;
                if (deltaY > 0) {
                    modalContent.style.transform = `translateY(${deltaY}px)`;
                }
            }, { passive: true });
            
            modalHeader.addEventListener('touchend', (e) => {
                if (!isDragging) return;
                isDragging = false;
                modalContent.style.transition = 'transform 0.4s cubic-bezier(0.25, 1, 0.5, 1)';
                const currentY = e.changedTouches[0].clientY;
                const deltaY = currentY - startY;
                const threshold = modalContent.offsetHeight * 0.3;
                
                if (deltaY > threshold) {
                    closeModal();
                } else {
                    modalContent.style.transform = 'translateY(0)';
                }
            }, { passive: true });
        }
    }
    
    // Aplicar filtros
    const applyFilters = () => {
        const query = searchInput.value.trim();
        const sortValue = document.querySelector('input[name="sort"]:checked')?.value || 'name_asc';
        const selectedCategories = Array.from(document.querySelectorAll('input[name="categories"]:checked')).map(input => input.value);
        
        const url = new URL(window.location.origin + window.location.pathname);
        
        if (query) url.searchParams.set('q', query);
        if (sortValue && sortValue !== 'name_asc') url.searchParams.set('sort', sortValue);
        if (selectedCategories.length > 0) url.searchParams.set('categories', selectedCategories.join(','));
        
        window.location.href = url.toString();
    };
    
    // Limpar filtros
    const clearFilters = () => {
        window.location.href = window.location.pathname;
    };
    
    // Event listeners
    if (applyBtn) applyBtn.addEventListener('click', applyFilters);
    if (clearBtn) clearBtn.addEventListener('click', clearFilters);
    
    // Busca com "debounce"
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const url = new URL(window.location.href);
                const currentQuery = url.searchParams.get('q') || '';
                const newQuery = searchInput.value.trim();
                
                if (currentQuery !== newQuery) {
                    if (newQuery) {
                        url.searchParams.set('q', newQuery);
                    } else {
                        url.searchParams.delete('q');
                    }
                    window.location.href = url.toString();
                }
            }, 500);
        });
    }
    
    // Restaurar estado dos filtros ao carregar a página
    const urlParams = new URLSearchParams(window.location.search);
    
    // Marcar botão de filtro como ativo
    if (urlParams.has('sort') || urlParams.has('categories') || urlParams.has('q')) {
        if (filterBtn) filterBtn.classList.add('active');
    }
    
    // Restaurar ordenação
    const initialSort = urlParams.get('sort') || 'name_asc';
    const sortRadio = document.querySelector(`input[name="sort"][value="${initialSort}"]`);
    if (sortRadio) sortRadio.checked = true;
    else document.getElementById('sort_name_asc').checked = true;
    
    // Restaurar categorias
    const initialCategories = (urlParams.get('categories') || '').split(',');
    initialCategories.forEach(catId => {
        if (catId) {
            const checkbox = document.getElementById(`cat_${catId}`);
            if (checkbox) checkbox.checked = true;
        }
    });
});
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>