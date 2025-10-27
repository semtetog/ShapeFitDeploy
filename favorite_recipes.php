<?php
// public_html/favorite_recipes.php (VERSÃO FINAL COM BUSCA, FILTRO E DESIGN AJUSTADO)

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_heading = "Minhas Receitas Favoritas";

// Pega os parâmetros de filtro da URL, igual na página explorar
$search_query = trim($_GET['q'] ?? '');
$sort_by_param = trim($_GET['sort'] ?? '');
$filter_categories_str = trim($_GET['categories'] ?? '');
$filter_categories = !empty($filter_categories_str) ? explode(',', $filter_categories_str) : [];

$is_filtered_request = !empty($search_query) || !empty($filter_categories) || !empty($sort_by_param);

// Pega todas as categorias para popular o modal de filtros
$all_categories_for_filter = $conn->query("SELECT id, name FROM sf_categories ORDER BY display_order ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

$recipes = [];

// A query base agora SEMPRE inclui o JOIN com a tabela de favoritos
$sql_base = "
    SELECT DISTINCT r.id, r.name, r.image_filename, r.kcal_per_serving, 
    r.prep_time_minutes, r.cook_time_minutes, r.protein_g_per_serving
    FROM sf_recipes r
    JOIN sf_user_favorite_recipes f ON r.id = f.recipe_id
";
$sql_joins = "";
$sql_conditions = ["f.user_id = ?"]; // A condição base é sempre o ID do usuário
$params = [$user_id];
$types = "i";

// Adiciona as condições de filtro se elas existirem
if (!empty($filter_categories)) {
    $sql_joins .= " JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id";
    $placeholders = implode(',', array_fill(0, count($filter_categories), '?'));
    $sql_conditions[] = "rhc.category_id IN ($placeholders)";
    foreach ($filter_categories as $cat_id) { $params[] = (int)$cat_id; }
    $types .= str_repeat('i', count($filter_categories));
}
if (!empty($search_query)) {
    $sql_conditions[] = "(r.name LIKE ?)"; // Simplificado para buscar apenas no nome
    $params[] = "%" . $search_query . "%";
    $types .= "s";
}

$sql_query = $sql_base . $sql_joins . " WHERE " . implode(" AND ", $sql_conditions);

if (count($filter_categories) > 1) {
    $sql_query .= " GROUP BY r.id HAVING COUNT(DISTINCT rhc.category_id) = " . count($filter_categories);
}

$sort_by = $sort_by_param ?: 'favorited_at_desc'; // Ordenação padrão por mais recente
$order_by_clause = " ORDER BY ";
switch ($sort_by) {
    case 'kcal_asc': $order_by_clause .= "r.kcal_per_serving ASC, r.name ASC"; break;
    case 'protein_desc': $order_by_clause .= "r.protein_g_per_serving DESC, r.name ASC"; break;
    case 'time_asc': $order_by_clause .= "(r.prep_time_minutes + r.cook_time_minutes) ASC, r.name ASC"; break;
    case 'name_asc': $order_by_clause .= "r.name ASC"; break;
    default: $order_by_clause .= "f.favorited_at DESC"; break;
}
$sql_query .= $order_by_clause;

$stmt = $conn->prepare($sql_query);
if ($stmt) {
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = $page_heading;
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Cabeçalho */
.page-header{display:flex;align-items:center;padding:0 1rem 1.5rem;gap:.5rem}.back-button{font-size:1.2rem;color:var(--text-primary);text-decoration:none}.page-header-text{flex-grow:1}.page-header-text h1{font-size:1.5rem;margin:0;text-align:center}.page-header-text p{font-size:.9rem;color:var(--text-secondary);margin:.25rem 0 0;text-align:center}.header-spacer{width:24px;flex-shrink:0}

/* Barra de busca e filtro (copiado de explore_recipes) */
.search-filter-container{display:flex;gap:.75rem;padding:0 1rem 1.5rem;align-items:center}.search-form{position:relative;flex-grow:1}.search-input{width:100%;height:48px;padding-left:45px;padding-right:15px;background-color:var(--surface-color);border:1px solid var(--border-color);border-radius:16px;color:var(--text-primary);font-size:16px}.search-input:focus{outline:0;border-color:var(--accent-orange)}.search-icon{position:absolute;top:50%;left:18px;transform:translateY(-50%);color:var(--text-secondary);pointer-events:none}.filter-button{flex-shrink:0;width:48px;height:48px;border-radius:16px;border:1px solid var(--border-color);background-color:var(--surface-color);color:var(--text-primary);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;position:relative;z-index:101}.filter-button.active::after{content:'';position:absolute;top:8px;right:8px;width:8px;height:8px;background-color:var(--accent-orange);border-radius:50%;border:2px solid var(--bg-color)}

/* === AJUSTE DE ESTILO: Espaçamento reduzido entre os cards === */
.recipe-list-stack { 
    display: flex; 
    flex-direction: column; 
    gap: 8px; /* Valor reduzido de 12px para 8px */
    padding: 0 1rem; 
}
.recipe-list-item{padding:12px;display:flex;gap:1rem;align-items:center;text-decoration:none}.recipe-list-image{width:64px;height:64px;flex-shrink:0;border-radius:12px;object-fit:cover}.recipe-list-info{flex-grow:1;display:flex;flex-direction:column;justify-content:center;overflow:hidden}.recipe-list-info h3{font-size:1rem;font-weight:600;margin:0 0 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.recipe-list-info .kcal{font-size:.85rem;font-weight:500;color:var(--text-secondary);display:flex;align-items:center;gap:6px}.recipe-list-info .kcal i{color:var(--accent-orange)}.favorite-icon{flex-shrink:0;padding-left:1rem;color:var(--accent-orange);font-size:1rem}
.empty-favorites-message{margin:0 1rem;padding:2rem 1.5rem;border-radius:16px;text-align:center}.empty-favorites-message i{font-size:2.5rem;color:var(--accent-orange);margin-bottom:1rem}.empty-favorites-message p{margin:0;color:var(--text-secondary);line-height:1.5}

/* Modal de Filtro (copiado de explore_recipes) */
.filter-modal-overlay{position:fixed;bottom:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);z-index:9998;opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease}.filter-modal-overlay.visible{opacity:1;visibility:visible}.filter-modal-content{position:fixed;bottom:0;left:0;right:0;margin:0 auto;width:100%;max-width:480px;background:#181818;border-top-left-radius:24px;border-top-right-radius:24px;border-top:1px solid var(--glass-border);padding:1rem 1.5rem;padding-bottom:calc(1.5rem + env(safe-area-inset-bottom));transform:translateY(100%);transition:transform .4s cubic-bezier(.25,1,.5,1);z-index:9999}.filter-modal-overlay.visible .filter-modal-content{transform:translateY(0)}.filter-modal-header{text-align:center;position:relative;padding-bottom:1rem;margin-bottom:1rem}.filter-modal-header::before{content:'';position:absolute;top:-8px;left:50%;transform:translateX(-50%);width:40px;height:4px;background:var(--border-color);border-radius:2px}.filter-modal-header h2{font-size:1.2rem}.filter-section h3{font-size:1rem;color:var(--text-secondary);margin-bottom:1rem;border-bottom:1px solid var(--border-color);padding-bottom:.5rem}.filter-options{display:flex;flex-wrap:wrap;gap:.75rem}.filter-option input{display:none}.filter-option label{display:block;padding:8px 16px;background:var(--surface-color);border:1px solid var(--border-color);border-radius:20px;font-size:.9rem;cursor:pointer;transition:all .2s ease}.filter-option input:checked+label{background:var(--accent-orange);border-color:var(--accent-orange);color:#fff;font-weight:600}.filter-actions{display:flex;gap:1rem;margin-top:2rem}.secondary-button{background-color:var(--surface-color);border:1px solid var(--border-color);color:var(--text-secondary);padding:14px;font-size:1rem;font-weight:600;border-radius:24px;cursor:pointer;flex-grow:1}

/* === FALLBACKS PARA SAFE AREA === */
@supports (padding: max(0px)) {
    .app-container {
        padding: max(calc(24px + env(safe-area-inset-top)), 44px) 0 max(calc(60px + env(safe-area-inset-bottom)), 110px) 0;
    }
}

@supports (-webkit-touch-callout: none) {
    .app-container {
        padding: calc(24px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;
    }
}

/* === RESPONSIVIDADE === */
@media (max-width: 768px) {
    .app-container {
        padding: calc(20px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;
    }
}
</style>

<div class="app-container" style="padding: calc(24px + env(safe-area-inset-top)) 0 calc(60px + env(safe-area-inset-bottom)) 0;">
    
    <header class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/explore_recipes.php" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
        <div class="page-header-text">
            <h1><?php echo $page_heading; ?></h1>
            <p>Sua coleção pessoal de receitas.</p>
        </div>
        <div class="header-spacer"></div>
    </header>

    <div class="search-filter-container">
        <div class="search-form">
            <i class="fas fa-search search-icon"></i>
            <input type="search" id="search-input" class="search-input" placeholder="Buscar nos favoritos..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <button class="filter-button" id="filter-button" aria-label="Filtrar"><i class="fas fa-sliders-h"></i></button>
    </div>

    <?php if (!empty($recipes)): ?>
        <div class="recipe-list-stack">
            <?php foreach($recipes as $recipe): ?>
                <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-list-item glass-card">
                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ? $recipe['image_filename'] : 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-list-image">
                    <div class="recipe-list-info">
                        <h3><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <span class="kcal"><i class="fas fa-fire-alt"></i> <?php echo round($recipe['kcal_per_serving']); ?> kcal</span>
                    </div>
                    <div class="favorite-icon"><i class="fas fa-heart"></i></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-favorites-message glass-card">
            <i class="far fa-heart"></i>
            <?php if ($is_filtered_request): ?>
                <p>Nenhuma receita favorita encontrada com estes filtros.</p>
            <?php else: ?>
                <p>Você ainda não favoritou nenhuma receita.<br>Toque no coração ♡ para guardá-las aqui.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="filter-modal-overlay" id="filter-modal">
    <div class="filter-modal-content">
        <div class="filter-modal-header"><h2>Filtros</h2></div>
        <div class="filter-section"><h3>Ordenar por</h3><div class="filter-options" id="sort-options" style="display: grid; grid-template-columns: 1fr 1fr;"><div class="filter-option"><input type="radio" id="sort_name_asc" name="sort" value="name_asc"><label for="sort_name_asc">Nome (A-Z)</label></div><div class="filter-option"><input type="radio" id="sort_kcal_asc" name="sort" value="kcal_asc"><label for="sort_kcal_asc">Menos Calóricas</label></div><div class="filter-option"><input type="radio" id="sort_protein_desc" name="sort" value="protein_desc"><label for="sort_protein_desc">Mais Proteicas</label></div><div class="filter-option"><input type="radio" id="sort_time_asc" name="sort" value="time_asc"><label for="sort_time_asc">Mais Rápidas</label></div></div></div>
        <div class="filter-section" style="margin-top: 1.5rem;">
            <h3>Filtrar por Categoria</h3>
            <div class="filter-options" id="category-options"><?php foreach ($all_categories_for_filter as $category): ?><div class="filter-option"><input type="checkbox" id="cat_<?php echo $category['id']; ?>" value="<?php echo $category['id']; ?>"><label for="cat_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label></div><?php endforeach; ?></div>
        </div>
        <div class="filter-actions"><button class="secondary-button" id="clear-filters-btn">Limpar</button><button class="primary-button" id="apply-filters-btn" style="flex-grow:1;">Aplicar</button></div>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>
<script>
// Script de filtro (copiado e adaptado de explore_recipes)
document.addEventListener('DOMContentLoaded', function() {
    const filterButton = document.getElementById('filter-button');
    const filterModal = document.getElementById('filter-modal');
    if (filterButton && filterModal) { const toggleModal = () => filterModal.classList.toggle('visible'); filterButton.addEventListener('click', toggleModal); filterModal.addEventListener('click', (e) => { if (e.target === filterModal) { toggleModal(); } }); }
    const searchInput = document.getElementById('search-input');
    const applyFiltersBtn = document.getElementById('apply-filters-btn');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');
    const applyAndRedirect = () => {
        const query = searchInput.value.trim();
        const sortValueInput = document.querySelector('input[name="sort"]:checked');
        const sortValue = sortValueInput ? sortValueInput.value : '';
        const selectedCategories = Array.from(document.querySelectorAll('#category-options input:checked')).map(input => input.value);
        if (query === '' && selectedCategories.length === 0 && !sortValue) { window.location.href = window.location.pathname; return; }
        const url = new URL(window.location.origin + window.location.pathname);
        if (query) url.searchParams.set('q', query);
        if (sortValue) url.searchParams.set('sort', sortValue);
        if (selectedCategories.length > 0) url.searchParams.set('categories', selectedCategories.join(','));
        window.location.href = url.toString();
    };
    if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', applyAndRedirect);
    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', () => { window.location.href = window.location.pathname; });
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const url = new URL(window.location.href);
                const currentQuery = url.searchParams.get('q') || '';
                const newQuery = searchInput.value.trim();
                if (currentQuery !== newQuery) { if (newQuery) { url.searchParams.set('q', newQuery); } else { url.searchParams.delete('q'); } window.location.href = url.toString(); }
            }, 500);
        });
    }
    const initialUrlParams = new URLSearchParams(window.location.search);
    if (initialUrlParams.has('sort') || initialUrlParams.has('categories') || initialUrlParams.has('q')) { if (filterButton) filterButton.classList.add('active'); }
    const initialSort = initialUrlParams.get('sort') || '';
    const sortRadio = document.querySelector(`input[name="sort"][value="${initialSort}"]`);
    if (sortRadio) sortRadio.checked = true;
    const initialCategories = (initialUrlParams.get('categories') || '').split(',');
    initialCategories.forEach(catId => { if(catId) { const catCheckbox = document.getElementById(`cat_${catId}`); if(catCheckbox) catCheckbox.checked = true; } });
});
</script>