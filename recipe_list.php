<?php
// public_html/recipe_list.php (VERSÃO FINAL COM CORREÇÃO DO HISTÓRICO DE NAVEGAÇÃO)

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$is_ajax_request = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$page_heading = "Receitas";
$search_term = trim($_GET['search'] ?? '');
$recipes = [];

// Base da consulta SQL
$sql_base = "SELECT DISTINCT r.id, r.name, r.image_filename, r.kcal_per_serving, r.description 
             FROM sf_recipes r";
$sql_joins = "";
$sql_conditions = ["r.is_public = 1"];
$params = [];
$types = "";

// Lógica de Filtro
if (!empty($_GET['meal_type'])) {
    $meal_type = trim($_GET['meal_type']);
    $page_heading = ucwords(str_replace('_', ' de ', $meal_type));
    $sql_conditions[] = "r.meal_type_suggestion LIKE ?";
    $params[] = "%" . $meal_type . "%";
    $types .= "s";
} 
elseif (!empty($_GET['category_slug'])) {
    $category_slug = trim($_GET['category_slug']);
    $stmt_cat_name = $conn->prepare("SELECT name FROM sf_categories WHERE slug = ?");
    if ($stmt_cat_name) {
        $stmt_cat_name->bind_param("s", $category_slug);
        $stmt_cat_name->execute();
        $result_cat_name = $stmt_cat_name->get_result();
        if($cat_row = $result_cat_name->fetch_assoc()){ $page_heading = htmlspecialchars($cat_row['name']); }
        $stmt_cat_name->close();
    }
    $sql_joins = " JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id JOIN sf_categories rc ON rhc.category_id = rc.id";
    $sql_conditions[] = "rc.slug = ?";
    $params[] = $category_slug;
    $types .= "s";
}

// Lógica de Pesquisa
if (!empty($search_term)) {
    $page_heading = $page_heading !== "Receitas" ? $page_heading : "Busca por: \"" . htmlspecialchars($search_term) . "\"";
    $sql_conditions[] = "(r.name LIKE ? OR r.description LIKE ?)";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $types .= "ss";
}

$sql_query = $sql_base . $sql_joins . " WHERE " . implode(" AND ", $sql_conditions) . " ORDER BY r.name ASC";

$stmt_recipes = $conn->prepare($sql_query);
if ($stmt_recipes) {
    if (!empty($types) && !empty($params)) {
        $stmt_recipes->bind_param($types, ...$params);
    }
    $stmt_recipes->execute();
    $result_recipes = $stmt_recipes->get_result();
    while($row = $result_recipes->fetch_assoc()) { $recipes[] = $row; }
    $stmt_recipes->close();
}

// Controle de Renderização (AJAX vs. Página Completa)
if ($is_ajax_request) {
    if (!empty($recipes)):
        foreach($recipes as $recipe): ?>
            <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-list-item glass-card">
                <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-list-image">
                <div class="recipe-list-info">
                    <h3><?php echo htmlspecialchars($recipe['name']); ?></h3>
                    <p><?php echo htmlspecialchars($recipe['description']); ?></p>
                    <span class="kcal"><i class="fas fa-fire-alt"></i> <?php echo round($recipe['kcal_per_serving']); ?> kcal</span>
                </div>
            </a>
        <?php endforeach;
    else: ?>
        <p class="text-center" style="padding:20px;">Nenhuma receita encontrada.</p>
    <?php endif;
    exit();
}

$page_title = $page_heading;
$extra_css = ['pages/_dashboard.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>
<style>
/* O CSS da versão anterior já está correto e não precisa de alterações */
.recipe-list-header { text-align: center; padding: 0 1rem 1rem 1rem; position: relative; }
.back-button-list { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem; color: var(--text-primary); }
.search-container { padding: 0 1rem 1.5rem 1rem; }
.search-form { position: relative; }
.search-input { width: 100%; height: 48px; padding-left: 45px; padding-right: 15px; background-color: var(--surface-color); border: 1px solid var(--border-color); border-radius: 16px; color: var(--text-primary); font-size: 16px; font-family: var(--font-family); transition: border-color 0.2s ease; }
.search-input:focus { outline: none; border-color: var(--accent-orange); }
.search-input::placeholder { color: var(--text-secondary); }
.search-icon { position: absolute; top: 50%; left: 18px; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; }
.recipe-list-stack { display: flex; flex-direction: column; gap: 12px; padding: 0 1rem; }
.recipe-list-item { padding: 12px; display: flex; gap: 12px; align-items: center; text-decoration: none; transition: transform 0.2s ease, background-color 0.2s ease; }
.recipe-list-item:hover { transform: scale(1.02); background: rgba(255, 255, 255, 0.05); }
.recipe-list-image { width: 80px; height: 80px; flex-shrink: 0; border-radius: 12px; object-fit: cover; }
.recipe-list-info { flex-grow: 1; color: var(--text-primary); overflow: hidden; }
.recipe-list-info h3 { font-size: 16px; font-weight: 600; margin: 0 0 4px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.recipe-list-info p { font-size: 13px; color: var(--text-secondary); margin: 0 0 8px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; }
.recipe-list-info .kcal { font-size: 13px; font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
.recipe-list-info .kcal i { color: var(--accent-orange); }
</style>

<div class="app-container" style="padding-top: 24px; padding-bottom: 90px;">
    <header class="recipe-list-header">
        <a href="javascript:history.back()" class="back-button-list" aria-label="Voltar"><i class="fas fa-arrow-left"></i></a>
        <h1><?php echo htmlspecialchars($page_heading); ?></h1>
    </header>

    <div class="search-container">
        <div class="search-form">
            <i class="fas fa-search search-icon"></i>
            <input type="search" id="search-input" name="search" class="search-input" placeholder="Buscar em <?php echo htmlspecialchars($page_heading); ?>..." value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
    </div>
    
    <div id="recipe-list-container" class="recipe-list-stack">
        <?php if (!empty($recipes)): ?>
            <?php foreach($recipes as $recipe): ?>
                <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-list-item glass-card">
                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-list-image">
                    <div class="recipe-list-info">
                        <h3><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <p><?php echo htmlspecialchars($recipe['description']); ?></p>
                        <span class="kcal"><i class="fas fa-fire-alt"></i> <?php echo round($recipe['kcal_per_serving']); ?> kcal</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center" style="padding:20px;">Nenhuma receita encontrada.</p>
        <?php endif; ?>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const recipeListContainer = document.getElementById('recipe-list-container');
    let debounceTimer;

    const performSearch = () => {
        const query = searchInput.value;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('search', query);
        currentUrl.searchParams.set('ajax', '1');

        // URL para a barra de endereço do navegador
        const userFriendlyUrl = new URL(window.location);
        userFriendlyUrl.searchParams.set('search', query);

        // ===================================================================
        //      MUDANÇA CRÍTICA AQUI: de pushState para replaceState
        //      Isso atualiza a URL sem criar um novo registro no histórico.
        // ===================================================================
        history.replaceState({}, '', userFriendlyUrl);

        fetch(currentUrl)
            .then(response => response.text())
            .then(html => {
                recipeListContainer.innerHTML = html;
            })
            .catch(error => console.error('Erro ao buscar receitas:', error));
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performSearch, 300);
    });
});
</script>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>