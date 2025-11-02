<?php
// admin/food_classification.php - Sistema ULTRA SIMPLES de classificação

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'food_classification';
$page_title = 'Classificar Alimentos';

// Categorias SIMPLIFICADAS
$categories = [
    'líquido' => ['name' => 'Líquido', 'color' => '#3B82F6', 'icon' => '💧'],
    'semi_liquido' => ['name' => 'Semi-líquido', 'color' => '#8B5CF6', 'icon' => '🥄'],
    'granular' => ['name' => 'Granular', 'color' => '#F59E0B', 'icon' => '🌾'],
    'unidade_inteira' => ['name' => 'Unidade Inteira', 'color' => '#10B981', 'icon' => '🍎'],
    'fatias_pedacos' => ['name' => 'Fatias/Pedaços', 'color' => '#EF4444', 'icon' => '🧀'],
    'corte_porcao' => ['name' => 'Corte/Porção', 'color' => '#F97316', 'icon' => '🥩'],
    'colher_cremoso' => ['name' => 'Colher Cremoso', 'color' => '#EC4899', 'icon' => '🍦'],
    'condimentos' => ['name' => 'Condimentos', 'color' => '#6B7280', 'icon' => '🧂'],
    'oleos_gorduras' => ['name' => 'Óleos/Gorduras', 'color' => '#FCD34D', 'icon' => '🫒'],
    'preparacoes_compostas' => ['name' => 'Preparações Compostas', 'color' => '#8B5A2B', 'icon' => '🍽️']
];

// Buscar alimentos
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Adicionar filtros de intervalo de página no topo
$page_start = isset($_GET['page_start']) ? max(1, (int)$_GET['page_start']) : 1;
$page_end = isset($_GET['page_end']) ? max($page_start, (int)$_GET['page_end']) : null; // será definido após $total_pages

// Restringir o offset e a quantidade conforme o intervalo
if ($page_start > $page_end) $page_end = $page_start;
$effective_offset = ($page_start - 1) * $per_page;
$effective_limit = ($page_end - $page_start + 1) * $per_page;

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "name_pt LIKE ?";
    $params[] = "%{$search}%";
    $param_types .= 's';
}

if (!empty($category_filter)) {
    $where_conditions[] = "food_type = ?";
    $params[] = $category_filter;
    $param_types .= 's';
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Contar total
$count_sql = "SELECT COUNT(*) as total FROM sf_food_items {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $per_page);
if ($page_end === null) { $page_end = $total_pages; }
if ($page_start > $page_end) { $page_end = $page_start; }
$block_page_count = max(1, $page_end - $page_start + 1);
$effective_offset = ($page_start - 1) * $per_page;
$effective_limit = $block_page_count * $per_page;

// Fixar blocos em 5
$num_blocos = 5;
$blocos = [];
$bloco_atual = isset($_GET['bloco']) ? (int)$_GET['bloco'] : null;
$per_page = 15;
$paginas_totais = ceil($total_items / $per_page);
$blocos_tamanhos = array_fill(0, $num_blocos, floor($paginas_totais / $num_blocos));
$resto = $paginas_totais % $num_blocos; for ($i = 0; $i < $resto; $i++) $blocos_tamanhos[$i]++;
$page_idx = 1;
for ($i = 0; $i < $num_blocos; $i++) {
    $start = $page_idx;
    $end = $start + $blocos_tamanhos[$i] - 1;
    $blocos[$i+1] = [ 'pagina_inicio' => $start, 'pagina_fim' => $end ];
    $page_idx = $end + 1;
}
// Determina pagina atual (restrita ao bloco)
$page = max(1, (int)($_GET['page'] ?? ($bloco_atual && isset($blocos[$bloco_atual]) ? $blocos[$bloco_atual]['pagina_inicio'] : 1)));
if ($bloco_atual && isset($blocos[$bloco_atual])) {
    $pagina_ini = $blocos[$bloco_atual]['pagina_inicio'];
    $pagina_fim = $blocos[$bloco_atual]['pagina_fim'];
    // Mostrar TODOS os itens do bloco de uma vez: offset no início do bloco e limit até o fim do bloco
    $offset = ($pagina_ini - 1) * $per_page;
    $limit = ($pagina_fim - $pagina_ini + 1) * $per_page;
    $pagina_atual_do_bloco = 1;
    $total_paginas_bloco = $pagina_fim - $pagina_ini + 1;
} else {
    $pagina_ini = 1; $pagina_fim = $paginas_totais;
    $offset = 0; $limit = $per_page;
    $pagina_atual_do_bloco = $page; $total_paginas_bloco = $paginas_totais;
}

// Lógica dos blocos para alimentar o modal e o filtro:
$blocos = [];
$bloco_atual = isset($_GET['bloco']) ? (int)$_GET['bloco'] : null;
$num_blocos = isset($_GET['num_blocos']) ? max(1,(int)$_GET['num_blocos']) : 5; // padrao 5 blocos
$paginas_totais = ceil($total_items / $per_page);
$blocos_tamanhos = array_fill(0, $num_blocos, floor($paginas_totais / $num_blocos));
$resto = $paginas_totais % $num_blocos; for ($i = 0; $i < $resto; $i++) $blocos_tamanhos[$i]++;

// Calcular faixas de cada bloco
$page_idx = 1;
for ($i = 0; $i < $num_blocos; $i++) {
    $start = $page_idx;
    $end = $start + $blocos_tamanhos[$i] - 1;
    $blocos[$i+1] = [ 'pagina_inicio' => $start, 'pagina_fim' => $end ];
    $page_idx = $end + 1;
}

// Determina o offset a partir do bloco escolhido
if ($bloco_atual && isset($blocos[$bloco_atual])) {
    $filtrar_pagina_ini = $blocos[$bloco_atual]['pagina_inicio'];
    $filtrar_pagina_fim = $blocos[$bloco_atual]['pagina_fim'];
    $offset = ($filtrar_pagina_ini - 1) * $per_page;
    $limit = ($filtrar_pagina_fim - $filtrar_pagina_ini + 1) * $per_page;
} else {
    $offset = 0;
    $limit = $per_page;
}

// Buscar alimentos APENAS do bloco ativo
$sql = "SELECT 
    sfi.id, sfi.name_pt, sfi.food_type, sfi.energy_kcal_100g, sfi.protein_g_100g, sfi.carbohydrate_g_100g, sfi.fat_g_100g,
    GROUP_CONCAT(sfc.category_type ORDER BY sfc.is_primary DESC, sfc.category_type ASC) as categories
    FROM sf_food_items sfi
    LEFT JOIN sf_food_categories sfc ON sfi.id = sfc.food_id
    $where_sql
    GROUP BY sfi.id
    ORDER BY sfi.name_pt
    LIMIT ? OFFSET ?";
$param_types .= 'ii';
array_push($params, $limit, $offset);
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calcular progresso de cada bloco:
$progresso_blocos = [];
foreach ($blocos as $nb => $faixa) {
    $offset_b = ($faixa['pagina_inicio'] - 1) * $per_page;
    $limit_b = ($faixa['pagina_fim'] - $faixa['pagina_inicio'] + 1) * $per_page;
    $sql_b = "SELECT COUNT(*) AS total, SUM(IF(sfc.category_type IS NULL,0,1)) AS classificados
        FROM (
            SELECT sfi.id, GROUP_CONCAT(sfc.category_type) as cts
            FROM sf_food_items sfi LEFT JOIN sf_food_categories sfc ON sfi.id = sfc.food_id
            $where_sql
            GROUP BY sfi.id
            ORDER BY sfi.name_pt
            LIMIT $limit_b OFFSET $offset_b
        ) TB LEFT JOIN sf_food_categories sfc ON TB.id = sfc.food_id";
    $result_b = $conn->query($sql_b);
    $dados_b = $result_b->fetch_assoc();
    $pct = ($dados_b['total'] > 0 ? round(($dados_b['classificados'] / $dados_b['total']) * 100) : 0);
    $progresso_blocos[$nb] = ['total' => (int)$dados_b['total'], 'classificados' => (int)$dados_b['classificados'], 'pct' => $pct];
}

// Progresso do bloco atual (com base no resultado carregado)
$total_items_in_block = count($foods);
$classified_in_block = 0;
foreach ($foods as $f_it) {
    if (!empty($f_it['categories'])) { $classified_in_block++; }
}
$block_progress_pct = $total_items_in_block > 0 ? round(($classified_in_block / $total_items_in_block) * 100) : 0;

// Buscar estatísticas
$stats_sql = "SELECT food_type, COUNT(*) as count FROM sf_food_items GROUP BY food_type";
$stats_result = $conn->query($stats_sql);
$stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['food_type']] = $row['count'];
}

// Contar alimentos realmente classificados (que têm pelo menos uma categoria)
$classified_sql = "SELECT COUNT(DISTINCT sfi.id) as classified_count 
                   FROM sf_food_items sfi 
                   INNER JOIN sf_food_categories sfc ON sfi.id = sfc.food_id";
$classified_result = $conn->query($classified_sql);
$classified_count = $classified_result->fetch_assoc()['classified_count'];

include 'includes/header.php';
?>

<style>
/* ========================================================================= */
/*          SISTEMA ULTRA SIMPLES - DESIGN MINIMALISTA                      */
/* ========================================================================= */

.classification-container {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 25px;
    min-height: calc(100vh - 200px);
}

/* ===== LEGENDAS SIMPLES (LADO ESQUERDO) ===== */
.legends-panel {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
    position: sticky;
    top: 20px;
}

.legends-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 15px;
    text-align: center;
}

.legends-subtitle {
    font-size: 0.85rem;
    color: var(--secondary-text-color);
    text-align: center;
    margin-bottom: 20px;
}

.category-legend {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.category-legend:hover {
    border-color: var(--category-color);
    background: var(--category-bg);
}

.category-legend.selected {
    border-color: var(--category-color);
    background: var(--category-bg);
}

.category-legend-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-legend-icon {
    font-size: 1rem;
}

.category-legend-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--category-color);
    margin: 0;
}

.category-legend-examples {
    margin-top: 8px;
    margin-bottom: 8px;
}

.examples-label {
    font-size: 0.7rem;
    color: var(--secondary-text-color);
    margin-bottom: 4px;
    font-weight: 500;
}

.examples-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.example-tag {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.category-legend-units {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.units-label {
    font-size: 0.7rem;
    color: var(--secondary-text-color);
    margin-bottom: 4px;
    font-weight: 500;
}

.units-list {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.unit-tag {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== CLASSIFICADOR (LADO DIREITO) ===== */
.classifier-panel {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 20px;
}

.classifier-header {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.classifier-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 5px;
}

.classifier-subtitle {
    font-size: 0.9rem;
    color: var(--secondary-text-color);
}

/* Estatísticas simples */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}

.stat-item {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 12px;
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--accent-orange);
    margin-bottom: 2px;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--secondary-text-color);
    font-weight: 500;
}

/* Filtros simples */
.filters-section {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

/* Painel estilizado do bloco de páginas */
.block-panel {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 16px;
}
.block-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin: 0 0 10px 0;
}
.block-grid {
    display: grid;
    grid-template-columns: repeat(4, max-content) 1fr;
    gap: 10px 12px;
    align-items: center;
}
.block-input {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 8px 10px;
    color: var(--primary-text-color);
    width: 120px;
}
.block-help {
    font-size: 0.8rem;
    color: var(--secondary-text-color);
}
.block-actions { display:flex; gap:8px; align-items:center; }
.btn-block {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--primary-text-color);
    cursor: pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
}
.btn-block.primary { background: var(--accent-orange); color: white; border-color: var(--accent-orange); }
.btn-block:disabled { opacity: .6; cursor: not-allowed; }
.progress-wrap { display:flex; align-items:center; gap:10px; }
.progress-bar { height: 8px; width: 220px; border-radius: 999px; background: rgba(255,255,255,.08); border:1px solid var(--border-color); overflow:hidden; }
.progress-bar > span { display:block; height:100%; background:#10B981; width:0%; transition:width .3s; }

.filters-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 10px;
}

.filters-grid {
    display: grid;
    grid-template-columns: 2fr 1fr auto auto;
    gap: 10px;
    align-items: end;
}

.search-input, .category-select {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 8px 10px;
    color: var(--primary-text-color);
    font-size: 0.85rem;
}

.search-input:focus, .category-select:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.filter-btn, .clear-btn {
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.filter-btn {
    background: var(--accent-orange);
    color: white;
}

.filter-btn:hover {
    background: var(--accent-orange-hover);
}

.clear-btn {
    background: var(--surface-color);
    color: var(--secondary-text-color);
    border: 1px solid var(--border-color);
}

.clear-btn:hover {
    background: var(--border-color);
    color: var(--primary-text-color);
}

/* Ações em lote simples */
.bulk-actions {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

.bulk-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 10px;
}

.bulk-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.bulk-select {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    padding: 8px 10px;
    color: var(--primary-text-color);
    font-size: 0.85rem;
    min-width: 180px;
}

.bulk-btn {
    background: var(--accent-orange);
    border: none;
    border-radius: 4px;
    padding: 8px 12px;
    color: white;
    font-weight: 500;
    cursor: pointer;
    font-size: 0.85rem;
}

.bulk-btn:hover {
    background: var(--accent-orange-hover);
}

.bulk-btn:disabled {
    background: var(--border-color);
    cursor: not-allowed;
}

.bulk-checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--secondary-text-color);
    font-size: 0.85rem;
}

/* Lista de alimentos simples */
.foods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    margin-bottom: 25px;
}

.food-card {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    transition: all 0.2s ease;
}

.food-card:hover {
    border-color: var(--accent-orange);
}

/* Estados visuais dos cards */
.food-card.classified {
    background: rgba(16, 185, 129, 0.05);
    border-color: #10B981;
    box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2);
}

.food-card.unclassified {
    background: rgba(239, 68, 68, 0.05);
    border-color: #EF4444;
    box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2);
}

.food-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}

.food-checkbox {
    margin-top: 2px;
    transform: scale(1.1);
    accent-color: var(--accent-orange);
}

.food-info {
    flex-grow: 1;
}

.food-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 6px;
    line-height: 1.3;
}

.food-macros {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
    margin-bottom: 10px;
}

.macro-item {
    background: var(--surface-color);
    border-radius: 3px;
    padding: 4px 6px;
    text-align: center;
}

.macro-label {
    font-size: 0.65rem;
    color: var(--secondary-text-color);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 1px;
}

.macro-value {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary-text-color);
}

.food-current-category {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 10px;
}

.category-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.category-tag:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.unclassified-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #ef444420;
    color: #ef4444;
    border: 1px solid #ef444440;
}

.food-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px;
}

.category-btn {
    background: var(--category-bg);
    border: 1px solid var(--category-color);
    border-radius: 4px;
    padding: 6px 8px;
    color: var(--category-color);
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    opacity: 0.7;
}

.category-btn:hover {
    background: var(--category-color);
    color: white;
    opacity: 1;
    transform: translateY(-1px);
}

.category-btn.selected {
    background: var(--category-color) !important;
    color: white !important;
    opacity: 1;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transform: translateY(-1px);
}

.units-btn {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
    border: 1px solid #3B82F6;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 2px;
    margin-top: 8px;
    width: 100%;
    justify-content: center;
}

.units-btn:hover:not(.disabled) {
    background: #3B82F6;
    color: white;
    transform: translateY(-1px);
}

.units-btn.disabled {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border-color: rgba(255, 255, 255, 0.1);
    cursor: not-allowed;
    opacity: 0.6;
}

.units-btn.disabled:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    transform: none;
}

.units-hint {
    display: block;
    font-size: 0.6rem;
    color: var(--text-secondary);
    margin-top: 2px;
    font-style: italic;
}

/* Indicador de classificado */
.classified-indicator {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #10B981;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    animation: slideIn 0.3s ease;
}

.classified-indicator i {
    font-size: 0.6rem;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Paginação simples */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 25px;
}

.pagination a, .pagination span {
    padding: 6px 10px;
    border: 1px solid var(--border-color);
    border-radius: 3px;
    text-decoration: none;
    color: var(--secondary-text-color);
    font-weight: 500;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.pagination a:hover {
    background: var(--surface-color);
    color: var(--primary-text-color);
    border-color: var(--accent-orange);
}

.pagination .current {
    background: var(--accent-orange);
    color: white;
    border-color: var(--accent-orange);
}

/* ====== CSS do sistema de blocos (modais) ====== */
.modal-blocos-overlay { position: fixed; left:0; top:0; width:100vw; height:100vh; background: rgba(0,0,0,0.6)!important; z-index: 2000; display: flex; align-items: center; justify-content: center; }
.modal-blocos { max-width:940px; background: var(--surface-color)!important; color: var(--primary-text-color); border-radius:16px; box-shadow:0 24px 64px #0006; padding:22px 22px 18px 22px; margin:20px; width:98vw; border:1px solid var(--border-color); }
.modal-header-blocos{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px}
.modal-header-blocos h2{margin:0;font-size:1.35rem}
.modal-header-blocos p{margin:6px 0 0 0;color:var(--secondary-text-color);font-size:.92rem}
.btn-fechar-x{background:var(--bg-color);color:var(--primary-text-color);border:1px solid var(--border-color);border-radius:8px;font-size:1.15rem;line-height:1;padding:6px 10px;cursor:pointer}
.blocos-cards { display:grid; gap:13px; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); margin:13px 0 18px 0; }
.bloco-card { background: var(--bg-color); border-radius: 12px; padding:16px 14px; border: 1px solid var(--border-color); text-align: center; box-shadow:0 5px 30px #0003; position:relative; }
.bloco-card.mini{padding:12px 12px}
.bloco-card.ativo-bloco { border-color: var(--accent-orange); background: var(--surface-color); }
.bloco-titulo { font-size: 1.1em; font-weight: bold; margin-bottom: 3px; }
.bloco-sub{color:var(--secondary-text-color);font-size:.9rem;margin-bottom:6px}
.bloco-metricas{display:flex;align-items:center;justify-content:space-between;font-size:.86rem;color:var(--secondary-text-color);margin:6px 4px}
.bloco-progress-bar { background: var(--border-color); height:7px; border-radius:7px; margin: 10px 0 4px 0; overflow:hidden; }
.bloco-progress-bar span { display:block; height:100%; background: #45c651; border-radius:7px; min-width:5px; transition:width .3s; }
.bloco-pct{font-weight:600;margin-top:2px}
.bloco-form{display:flex;align-items:center;justify-content:center}
.btn-bloco-card {padding:8px 18px;border-radius:8px;background:var(--accent-orange);color:#fff;font-weight:600;border:1px solid var(--accent-orange);cursor:pointer;margin-top:6px;font-size:.98em;}
.btn-modal-fechar{margin-top:10px;padding:6px 14px;background:var(--surface-color);color:var(--secondary-text-color);font-size:1em;border:1px solid var(--border-color);border-radius:6px;cursor:pointer;}
.btn-secondary{padding:8px 14px;background:var(--surface-color);color:var(--primary-text-color);border:1px solid var(--border-color);border-radius:8px;cursor:pointer}
.btn-blocos-toolbar{position:fixed;right:22px;top:86px;display:flex;gap:10px;z-index:1500}
.btn-blocos-flutuante{padding:11px 16px;background:var(--accent-orange);color:#fff;font-weight:600;font-size:.98em;border:1px solid var(--accent-orange);border-radius:10px;box-shadow:0 5px 30px #0002;cursor:pointer}
.btn-blocos-flutuante.muted{background:var(--surface-color);border:1px solid var(--border-color);color:var(--primary-text-color)}
.config-row{display:flex;align-items:center;justify-content:space-between;gap:10px;background:var(--bg-color);border:1px solid var(--border-color);border-radius:10px;padding:10px 12px}
.lbl-inline{font-size:.95rem;color:var(--primary-text-color)}
.num-blocos-wrap{display:flex;align-items:center;gap:8px}
.num-blocos-wrap input{font-size:1.1rem;width:92px;text-align:center;border-radius:8px;border:1px solid var(--border-color);background:var(--surface-color);color:var(--primary-text-color);padding:6px}
.btn-preset{background:var(--surface-color);color:var(--primary-text-color);border:1px solid var(--border-color);border-radius:8px;padding:6px 10px;cursor:pointer}
.stats-inline{color:var(--secondary-text-color);font-size:.9rem}
.modal-actions{display:flex;align-items:center;justify-content:space-between;margin-top:6px}
.btn-blocos-flutuante:focus,.btn-preset:focus,.btn-bloco-card:focus{outline:2px solid var(--accent-orange); outline-offset:2px}
@media(max-width:600px){.modal-blocos{padding:14px 2vw 7px 2vw}.blocos-cards{grid-template-columns:1fr;}.btn-blocos-toolbar{right:10px;top:10vw}.btn-blocos-flutuante{padding:9px 14px}}

/* Auto-save indicator */
.auto-save-indicator {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--success-green);
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    z-index: 1000;
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.3s ease;
}

.auto-save-indicator.show {
    opacity: 1;
    transform: translateY(0);
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.loading-content {
    text-align: center;
    color: var(--text-primary);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top: 3px solid var(--accent-orange);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

.loading-text {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.loading-subtext {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1200px) {
    .classification-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .legends-panel {
        position: static;
        order: 2;
    }
    
    .classifier-panel {
        order: 1;
    }
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .foods-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .bulk-controls {
        flex-direction: column;
        align-items: stretch;
    }
}

.block-btn-row {
  display:flex;
  gap:14px;
  margin-bottom:12px;
  align-items:center;
}
.block-btn {
  background: var(--surface-color);
  border:1px solid var(--border-color);
  color: var(--primary-text-color);
  border-radius:7px;
  padding:7px 18px;
  font-size:1em;
  font-weight:600;
  transition:.18s;
  cursor:pointer;
}
.block-btn.primary {
  background: var(--accent-orange);
  border-color: var(--accent-orange);
  color:#fff;
}
.block-btn:hover {
  background: var(--bg-color);
  border-color: var(--accent-orange);
}
</style>

<div class="classification-container">
    <!-- LEGENDAS SIMPLES (LADO ESQUERDO) -->
    <div class="legends-panel">
        <h2 class="legends-title">📋 Categorias</h2>
        <p class="legends-subtitle">Clique para classificar</p>
        
        <?php 
        // Definir unidades e exemplos para cada categoria
        $category_units = [
            'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
            'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc'],
            'granular' => ['g', 'kg', 'cs', 'cc', 'xc'],
            'unidade_inteira' => ['un', 'g', 'kg'],
            'fatias_pedacos' => ['fat', 'g', 'kg'],
            'corte_porcao' => ['g', 'kg', 'un'],
            'colher_cremoso' => ['cs', 'cc', 'g'],
            'condimentos' => ['cc', 'cs', 'g'],
            'oleos_gorduras' => ['cs', 'cc', 'ml', 'l'],
            'preparacoes_compostas' => ['g', 'kg', 'un']
        ];
        
        $category_examples = [
            'líquido' => ['Água', 'Suco', 'Leite', 'Refrigerante', 'Café'],
            'semi_liquido' => ['Iogurte', 'Pudim', 'Mingau', 'Vitamina', 'Abacate'],
            'granular' => ['Arroz', 'Feijão', 'Açúcar', 'Sal', 'Farinha'],
            'unidade_inteira' => ['Maçã', 'Banana', 'Ovo', 'Pão', 'Biscoito'],
            'fatias_pedacos' => ['Queijo', 'Presunto', 'Tomate', 'Cenoura', 'Batata'],
            'corte_porcao' => ['Carne', 'Frango', 'Peixe', 'Lasanha', 'Pizza'],
            'colher_cremoso' => ['Manteiga', 'Cream Cheese', 'Doce de Leite', 'Maionese'],
            'condimentos' => ['Sal', 'Pimenta', 'Açúcar', 'Canela', 'Orégano'],
            'oleos_gorduras' => ['Azeite', 'Óleo', 'Manteiga', 'Margarina', 'Banha'],
            'preparacoes_compostas' => ['Lasanha', 'Pizza', 'Bolo', 'Torta', 'Sopa']
        ];
        
        // Nomes das unidades
        $unit_names = [
            'ml' => 'Mililitro',
            'l' => 'Litro', 
            'cs' => 'Colher de Sopa',
            'cc' => 'Colher de Chá',
            'xc' => 'Xícara',
            'g' => 'Grama',
            'kg' => 'Quilograma',
            'un' => 'Unidade',
            'fat' => 'Fatia'
        ];
        
        foreach ($categories as $key => $cat): 
            $units = $category_units[$key] ?? [];
            $examples = $category_examples[$key] ?? [];
        ?>
            <div class="category-legend" data-category="<?= $key ?>" style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                <div class="category-legend-header">
                    <span class="category-legend-icon"><?= $cat['icon'] ?></span>
                    <h3 class="category-legend-name"><?= $cat['name'] ?></h3>
                </div>
                
                <div class="category-legend-examples">
                    <div class="examples-label">Exemplos:</div>
                    <div class="examples-list">
                        <?php foreach ($examples as $example): ?>
                            <span class="example-tag"><?= $example ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="category-legend-units">
                    <div class="units-label">Unidades:</div>
                    <div class="units-list">
                        <?php foreach ($units as $unit): ?>
                            <span class="unit-tag"><?= $unit_names[$unit] ?? $unit ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- CLASSIFICADOR (LADO DIREITO) -->
    <div class="classifier-panel">
        <div class="classifier-header">
            <h1 class="classifier-title">Classificar Alimentos</h1>
            <p class="classifier-subtitle">Selecione uma categoria para cada alimento</p>
        </div>

        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number"><?= $total_items ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="classified-count"><?= $classified_count ?></div>
                <div class="stat-label">Classificados</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="remaining-count"><?= $total_items - $classified_count ?></div>
                <div class="stat-label">Restantes</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" id="session-count">0</div>
                <div class="stat-label">Nesta Sessão</div>
            </div>
        </div>

        <!-- Indicador de bloco ativo -->
        <?php if ($bloco_atual && isset($blocos[$bloco_atual])): ?>
            <div style="margin-bottom:12px; padding:10px 12px; background:#1f2431; border:1px solid #2d3342; border-radius:8px; color:#e8e8e8;">
                Trabalhando no <b>Bloco <?= (int)$bloco_atual ?></b>: páginas <b><?= $pagina_ini ?></b> – <b><?= $pagina_fim ?></b>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters-section">
            <h3 class="filters-title">🔍 Buscar</h3>
            <div class="block-btn-row" style="justify-content:flex-end;">
                <button type="button" class="block-btn primary" onclick="document.getElementById('modal-blocos').style.display='flex'">Escolher bloco</button>
                <button type="button" class="block-btn" onclick="document.getElementById('modal-progress').style.display='flex'">Progresso dos blocos</button>
            </div>
            <form method="GET" class="filters-grid">
                <input type="text" class="search-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nome do alimento...">
                <select class="category-select" name="category">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= $cat['name'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($bloco_atual): ?><input type="hidden" name="bloco" value="<?= (int)$bloco_atual ?>"><?php endif; ?>
                <button type="submit" class="filter-btn">Buscar</button>
                <a href="food_classification.php" class="clear-btn">Limpar</a>
            </form>
        </div>

        <!-- Ações em Lote -->
        <div class="bulk-actions">
            <h3 class="bulk-title">⚡ Ações em Lote</h3>
            <div class="bulk-controls">
                <select class="bulk-select" id="bulk-category">
                    <option value="">Selecione uma categoria</option>
                    <?php foreach ($categories as $key => $cat): ?>
                        <option value="<?= $key ?>"><?= $cat['icon'] ?> <?= $cat['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bulk-btn" onclick="applyBulkClassification()" id="bulk-btn" disabled>
                    Aplicar aos Selecionados
                </button>
                <label class="bulk-checkbox">
                    <input type="checkbox" id="select-all" style="transform: scale(1.1); accent-color: var(--accent-orange);">
                    Selecionar Todos
                </label>
            </div>
        </div>

        <!-- Lista de Alimentos -->
        <div class="foods-grid" id="foods-list">
            <?php foreach ($foods as $food): ?>
                <div class="food-card" data-food-id="<?= $food['id'] ?>">
                    <div class="food-header">
                        <input class="food-checkbox" type="checkbox" value="<?= $food['id'] ?>">
                        <div class="food-info">
                            <div class="food-name">
                                <?= htmlspecialchars($food['name_pt']) ?>
                                <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                    <br><small style="color: #6b7280; font-size: 0.85em;">🏷️ <?= htmlspecialchars($food['brand']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="food-macros">
                                <div class="macro-item">
                                    <div class="macro-label">Calorias</div>
                                    <div class="macro-value"><?= $food['energy_kcal_100g'] ?>kcal</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Proteína</div>
                                    <div class="macro-value"><?= $food['protein_g_100g'] ?>g</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Carboidratos</div>
                                    <div class="macro-value"><?= $food['carbohydrate_g_100g'] ?>g</div>
                                </div>
                                <div class="macro-item">
                                    <div class="macro-label">Gorduras</div>
                                    <div class="macro-value"><?= $food['fat_g_100g'] ?>g</div>
                                </div>
                            </div>
                            <div class="food-current-category" 
                                 id="category-display-<?= $food['id'] ?>" 
                                 data-categories="<?= htmlspecialchars($food['categories'] ?? '') ?>">
                                <?php 
                                if (!empty($food['categories'])) {
                                    $food_categories = explode(',', $food['categories']);
                                    $tagsHtml = '';
                                    foreach ($food_categories as $cat_key) {
                                        if (isset($categories[$cat_key])) {
                                            $cat_info = $categories[$cat_key];
                                            $tagsHtml .= sprintf(
                                                '<span class="category-tag" style="background: %s20; color: %s; border: 1px solid %s40;">%s %s</span>',
                                                $cat_info['color'], $cat_info['color'], $cat_info['color'], $cat_info['icon'], $cat_info['name']
                                            );
                                        }
                                    }
                                    echo $tagsHtml;
                                } else {
                                    echo '<span class="unclassified-tag">Não classificado</span>';
                                }
                                ?>
                            </div>
                            <div class="food-actions">
                                <?php foreach ($categories as $key => $cat): ?>
                                    <button class="category-btn" 
                                            data-food-id="<?= $food['id'] ?>"
                                            data-category="<?= $key ?>"
                                            style="--category-color: <?= $cat['color'] ?>; --category-bg: <?= $cat['color'] ?>20;">
                                        <?= $cat['icon'] ?> <?= $cat['name'] ?>
                                    </button>
                                <?php endforeach; ?>
                                
                                <!-- Botão para editar unidades -->
                                <button class="units-btn" 
                                        data-food-id="<?= $food['id'] ?>"
                                        onclick="openUnitsEditor(<?= $food['id'] ?>, '<?= htmlspecialchars($food['name_pt']) ?>', getFoodCategories(<?= $food['id'] ?>))">
                                    <i class="fas fa-ruler"></i>
                                    <span>Unidades</span>
                                    <small class="units-hint">Salve a classificação primeiro</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação (escondida quando há bloco selecionado) -->
        <?php if (!$bloco_atual && $total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">« Anterior</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category_filter) ?>">Próximo »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modais de Blocos - Seleção e Progresso -->
<div id="modal-blocos" class="modal-blocos-overlay" style="display:none;">
  <div class="modal-blocos">
    <div class="modal-header-blocos">
      <div>
        <h2>Dividir em Blocos</h2>
        <p>Defina o número de blocos para dividir as páginas igualmente. Cada pessoa escolhe um bloco e trabalha somente nele.</p>
      </div>
      <button class="btn-fechar-x" title="Fechar" onclick="document.getElementById('modal-blocos').style.display='none'">×</button>
    </div>
    <div class="config-row">
      <div class="lbl-inline" style="font-weight:600;">Este projeto usa 5 blocos fixos</div>
      <div class="stats-inline">Total: <b><?= $total_items ?></b> alimentos • <b><?= $paginas_totais ?></b> páginas (<?= $per_page ?>/página)</div>
    </div>
    <div class="blocos-cards">
      <?php foreach ($blocos as $b_n => $faixa): ?>
      <div class="bloco-card<?= $bloco_atual==$b_n?' ativo-bloco':'' ?>">
        <div class="bloco-titulo">Bloco <?= $b_n ?></div>
        <div class="bloco-sub">Páginas <?= $faixa['pagina_inicio'] ?>–<?= $faixa['pagina_fim'] ?></div>
        <div class="bloco-metricas"><span><?= $progresso_blocos[$b_n]['total'] ?> itens</span><span><?= $progresso_blocos[$b_n]['classificados'] ?> classificados</span></div>
        <div class="bloco-progress-bar"><span style="width:<?= $progresso_blocos[$b_n]['pct'] ?>%"></span></div>
        <div class="bloco-pct"><?= $progresso_blocos[$b_n]['pct'] ?>% concluído</div>
        <form method="GET" class="bloco-form">
          <input type="hidden" name="bloco" value="<?= $b_n ?>">
          <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
          <?php if ($category_filter): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>"><?php endif; ?>
          <button class="btn-bloco-card">Trabalhar neste bloco</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="modal-actions">
      <button onclick="document.getElementById('modal-progress').style.display='flex'" class="btn-secondary">Ver progresso geral</button>
      <button onclick="document.getElementById('modal-blocos').style.display='none'" class="btn-modal-fechar">Fechar</button>
    </div>
  </div>
</div>

<div id="modal-progress" class="modal-blocos-overlay" style="display:none;">
  <div class="modal-blocos">
    <div class="modal-header-blocos">
      <div>
        <h2>Progresso dos Blocos</h2>
        <p>Acompanhe o andamento geral da equipe.</p>
      </div>
      <button class="btn-fechar-x" title="Fechar" onclick="document.getElementById('modal-progress').style.display='none'">×</button>
    </div>
    <div class="blocos-cards">
      <?php foreach ($blocos as $b_n => $faixa): ?>
        <div class="bloco-card mini">
          <div class="bloco-titulo">Bloco <?= $b_n ?></div>
          <div class="bloco-sub">Páginas <?= $faixa['pagina_inicio'] ?>–<?= $faixa['pagina_fim'] ?></div>
          <div class="bloco-progress-bar"><span style="width:<?= $progresso_blocos[$b_n]['pct'] ?>%"></span></div>
          <div class="bloco-pct"><?= $progresso_blocos[$b_n]['pct'] ?>%</div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="modal-actions">
      <button onclick="document.getElementById('modal-progress').style.display='none'" class="btn-modal-fechar">Fechar</button>
    </div>
  </div>
</div>



<!-- Auto-save indicator -->
<div class="auto-save-indicator" id="auto-save-indicator">
    💾 Salvo!
</div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <div class="loading-text">Salvando...</div>
        <div class="loading-subtext">Aguarde um momento</div>
    </div>
</div>

<!-- Script de inicialização dos modais de blocos -->
<script>
  (function(){
    function openModal(id){ var el = document.getElementById(id); if(el) el.style.display='flex'; }
    function showModalOnFirstLoad(){ if(window.location.search.indexOf('bloco=')===-1){ openModal('modal-blocos'); } }
    document.addEventListener('DOMContentLoaded', function(){
      // Exibir modal na primeira carga (se bloco não estiver definido)
      setTimeout(showModalOnFirstLoad, 250);
      // Garantir navegação limpa ao escolher bloco
      document.querySelectorAll('.btn-bloco-card').forEach(function(btn){
        btn.addEventListener('click', function(){
          // nada extra, só evita submissões duplicadas
        });
      });
    });
  })();
</script>

<script>
// Definir categorias globalmente para acesso no JS
window.categories = <?= json_encode($categories) ?>;

// Definir unidades padrão por categoria
window.categoryUnits = {
    'líquido': ['ml', 'l', 'cs', 'cc', 'xc'],
    'semi_liquido': ['g', 'ml', 'cs', 'cc', 'xc'],
    'granular': ['g', 'kg', 'cs', 'cc'],
    'unidade_inteira': ['un', 'g', 'kg'],
    'fatias_pedacos': ['fat', 'g', 'kg'],
    'corte_porcao': ['g', 'kg', 'un'],
    'colher_cremoso': ['cs', 'cc', 'g'],
    'condimentos': ['cc', 'cs', 'g'],
    'oleos_gorduras': ['cs', 'cc', 'ml', 'l'],
    'preparacoes_compostas': ['g', 'kg', 'un']
};

window.unitNames = {
    'ml': 'Mililitro',
    'l': 'Litro', 
    'cs': 'Colher de Sopa',
    'cc': 'Colher de Chá',
    'xc': 'Xícara',
    'g': 'Grama',
    'kg': 'Quilograma',
    'un': 'Unidade',
    'fat': 'Fatia'
};

let classifications = {}; // Estrutura para { foodId: [category1, category2] }
let sessionClassifiedCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Carregar o estado inicial das classificações a partir do HTML
    document.querySelectorAll('.food-card').forEach(card => {
        const foodId = card.dataset.foodId;
        const existingCategories = card.querySelector('.food-current-category').dataset.categories;
        if (existingCategories) {
            classifications[foodId] = existingCategories.split(',');
        } else {
            classifications[foodId] = [];
        }
        updateFoodVisual(foodId); // Atualiza o visual para refletir o estado carregado
    });

    // 2. Adicionar listener de clique para TODOS os botões de categoria
    document.getElementById('foods-list').addEventListener('click', function(e) {
        if (e.target.classList.contains('category-btn')) {
            const foodId = e.target.dataset.foodId;
            const category = e.target.dataset.category;
            toggleCategory(foodId, category);
        }
    });
    
    // ... (listeners de ações em lote, se mantidos) ...
});

// Lida com a lógica de adicionar/remover uma categoria
function toggleCategory(foodId, category) {
    const currentCategories = classifications[foodId] || [];
    const index = currentCategories.indexOf(category);

    if (index > -1) {
        // Categoria já existe, então remove
        currentCategories.splice(index, 1);
    } else {
        // Categoria não existe, então adiciona
        currentCategories.push(category);
    }
    classifications[foodId] = currentCategories;
    
    updateFoodVisual(foodId);
    saveClassification(foodId); // Salva a alteração imediatamente
}

// Atualiza a aparência de um card de alimento com base nas categorias selecionadas
function updateFoodVisual(foodId) {
    const foodCard = document.querySelector(`.food-card[data-food-id="${foodId}"]`);
    if (!foodCard) return;

    const currentCategories = classifications[foodId] || [];
    
    // Atualiza os botões (adicionando/removendo a classe 'selected')
    foodCard.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.toggle('selected', currentCategories.includes(btn.dataset.category));
    });

    // Atualiza o display de texto/tags
    const categoryDisplay = foodCard.querySelector('.food-current-category');
    if (currentCategories.length > 0) {
        const tagsHtml = currentCategories.map(catKey => {
            const catInfo = window.categories[catKey];
            if (!catInfo) return ''; // Segurança caso a categoria não exista
            return `<span class="category-tag" style="background: ${catInfo.color}20; color: ${catInfo.color}; border: 1px solid ${catInfo.color}40;">${catInfo.icon} ${catInfo.name}</span>`;
        }).join('');
        categoryDisplay.innerHTML = tagsHtml;
        foodCard.classList.add('classified');
        foodCard.classList.remove('unclassified');
    } else {
        categoryDisplay.innerHTML = '<span class="unclassified-tag">Não classificado</span>';
        foodCard.classList.remove('classified');
        foodCard.classList.add('unclassified');
    }
}

// Salva a classificação para um ÚNICO alimento
function saveClassification(foodId) {
    showLoading();
    const categoriesToSave = classifications[foodId] || [];
    
    const formData = new FormData();
    formData.append('action', 'save_classifications');
    // Envia o payload no formato esperado pelo backend: { "food_id": ["cat1", "cat2"] }
    formData.append('classifications', JSON.stringify({ [foodId]: categoriesToSave }));

    fetch('ajax_food_classification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showAutoSaveIndicator();
            // Atualiza contadores (opcional, pode ser feito de forma mais robusta)
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Erro desconhecido.'));
            // Reverter a mudança visual em caso de erro
            // (implementação mais complexa, omitida por simplicidade agora)
        }
    })
    .catch(error => {
        hideLoading();
        alert('Erro de conexão ao salvar.');
        console.error('Save Error:', error);
    });
}
// Função para obter as categorias de um alimento
function getFoodCategories(foodId) {
    return classifications[foodId] || [];
}

// Funções de loading e indicadores
function showLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.add('show');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

function showAutoSaveIndicator() {
    const indicator = document.getElementById('auto-save-indicator');
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/includes/units_editor.php'; ?>
<?php include 'includes/footer.php'; ?>