<?php
// admin/foods_management.php - Painel de Gerenciamento de Alimentos

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods';
$page_title = 'Gerenciar Alimentos';

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$source_filter = $_GET['source'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// --- Estatísticas gerais ---
$stats = [];

// Total de alimentos
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_food_items")->fetch_assoc()['count'];

// Por fonte
$stats_query = "SELECT source_table, COUNT(*) as count FROM sf_food_items GROUP BY source_table ORDER BY count DESC";
$stats_result = $conn->query($stats_query);
while ($row = $stats_result->fetch_assoc()) {
    $stats['by_source'][$row['source_table']] = $row['count'];
}

// --- Construir query de busca ---
$sql = "SELECT * FROM sf_food_items";
$conditions = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $conditions[] = "name_pt LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($source_filter)) {
    $conditions[] = "source_table = ?";
    $params[] = $source_filter;
    $types .= 's';
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $foods = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_food_items";
$count_conditions = [];
$count_params = [];
$count_types = '';

if (!empty($search_term)) {
    $count_conditions[] = "name_pt LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($source_filter)) {
    $count_conditions[] = "source_table = ?";
    $count_params[] = $source_filter;
    $count_types .= 's';
}

if (!empty($count_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $count_conditions);
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();
} else {
    $total_items = 0;
}

$total_pages = ceil($total_items / $per_page);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.filters-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filters-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filters-form .form-group:last-child {
    display: flex;
    flex-direction: row;
    gap: 8px;
    align-items: flex-end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.form-group label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 8px 12px;
    color: var(--text-primary);
    font-size: 14px;
    min-width: 200px;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
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

.btn-success {
    background: #28a745;
}

.btn-success:hover {
    background: #218838;
}

.foods-table {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    overflow: hidden;
}

.table-header {
    background: rgba(255, 255, 255, 0.05);
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.table-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.table-content {
    overflow-x: auto;
}

.food-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.food-table th,
.food-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.food-table th {
    background: rgba(255, 255, 255, 0.05);
    font-weight: 600;
    color: var(--text-primary);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.food-table td {
    color: var(--text-secondary);
}

.food-name {
    font-weight: 500;
    color: var(--text-primary);
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.source-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
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

.actions {
    display: flex;
    gap: 6px;
    align-items: center;
}

.action-btn {
    padding: 6px 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.edit-btn {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.edit-btn:hover {
    background: rgba(255, 193, 7, 0.2);
}

.delete-btn {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.delete-btn:hover {
    background: rgba(220, 53, 69, 0.2);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 20px;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.pagination a {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.pagination a:hover {
    background: var(--accent-orange);
    color: #fff;
}

.pagination .current {
    background: var(--accent-orange);
    color: #fff;
}

.add-food-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.add-food-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    align-items: end;
}

.form-control-small {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 8px 12px;
    color: var(--text-primary);
    font-size: 14px;
}

.form-control-small:focus {
    outline: none;
    border-color: var(--accent-orange);
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-control {
        min-width: auto;
    }
    
    .foods-table {
        font-size: 11px;
    }
    
    .food-table th,
    .food-table td {
        padding: 8px;
    }
}
</style>

<div class="admin-container">
    <h1><i class="fas fa-apple-alt"></i> Gerenciar Alimentos</h1>
    
    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total de Alimentos</div>
        </div>
        
        <?php foreach ($stats['by_source'] as $source => $count): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($count); ?></div>
                <div class="stat-label">
                    <?php 
                    switch ($source) {
                        case 'TACO': echo 'TACO'; break;
                        case 'Sonia Tucunduva': echo 'Sonia'; break;
                        case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                        case 'USDA': echo 'USDA'; break;
                        default: echo htmlspecialchars($source);
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filtros -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-group">
                <label>Buscar Alimento</label>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nome do alimento...">
            </div>
            
            <div class="form-group">
                <label>Fonte</label>
                <select name="source" class="form-control">
                    <option value="">Todas as fontes</option>
                    <option value="TACO" <?php echo $source_filter === 'TACO' ? 'selected' : ''; ?>>TACO</option>
                    <option value="Sonia Tucunduva" <?php echo $source_filter === 'Sonia Tucunduva' ? 'selected' : ''; ?>>Sonia Tucunduva</option>
                    <option value="Sonia Tucunduva (Prioridade)" <?php echo $source_filter === 'Sonia Tucunduva (Prioridade)' ? 'selected' : ''; ?>>Sonia (Atualizado)</option>
                    <option value="USDA" <?php echo $source_filter === 'USDA' ? 'selected' : ''; ?>>USDA</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn"><i class="fas fa-search"></i> Filtrar</button>
                <a href="foods_management.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpar</a>
            </div>
        </form>
    </div>
    
    <!-- Adicionar Alimento -->
    <div class="add-food-section">
        <h3 style="margin: 0 0 16px 0; color: var(--text-primary);"><i class="fas fa-plus"></i> Adicionar Novo Alimento</h3>
        <form method="POST" action="process_food.php" class="add-food-form">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label>Nome</label>
                <input type="text" name="name_pt" class="form-control-small" required placeholder="Nome do alimento">
            </div>
            
            <div class="form-group">
                <label>Calorias (100g)</label>
                <input type="number" name="energy_kcal_100g" class="form-control-small" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Proteína (100g)</label>
                <input type="number" name="protein_g_100g" class="form-control-small" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Carboidratos (100g)</label>
                <input type="number" name="carbohydrate_g_100g" class="form-control-small" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Gordura (100g)</label>
                <input type="number" name="fat_g_100g" class="form-control-small" step="0.01" required placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Fonte</label>
                <select name="source_table" class="form-control-small">
                    <option value="TACO">TACO</option>
                    <option value="Sonia Tucunduva">Sonia Tucunduva</option>
                    <option value="USDA">USDA</option>
                    <option value="Manual">Manual</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Adicionar</button>
        </form>
    </div>
    
    <!-- Lista de Alimentos -->
    <div class="foods-table">
        <div class="table-header">
            <h2 class="table-title">
                Alimentos 
                <?php if (!empty($search_term) || !empty($source_filter)): ?>
                    - Filtrados
                <?php endif; ?>
                (<?php echo number_format($total_items); ?> total)
            </h2>
        </div>
        
        <div class="table-content">
            <table class="food-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Calorias</th>
                        <th>Proteína</th>
                        <th>Carbs</th>
                        <th>Gordura</th>
                        <th>Fonte</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($foods as $food): ?>
                        <tr>
                            <td><?php echo $food['id']; ?></td>
                            <td>
                                <div class="food-name" title="<?php echo htmlspecialchars($food['name_pt']); ?>">
                                    <?php echo htmlspecialchars($food['name_pt']); ?>
                                </div>
                            </td>
                            <td><?php echo number_format($food['energy_kcal_100g'], 1); ?></td>
                            <td><?php echo number_format($food['protein_g_100g'], 1); ?>g</td>
                            <td><?php echo number_format($food['carbohydrate_g_100g'], 1); ?>g</td>
                            <td><?php echo number_format($food['fat_g_100g'], 1); ?>g</td>
                            <td>
                                <?php
                                $source = $food['source_table'];
                                $class = '';
                                switch ($source) {
                                    case 'TACO':
                                        $class = 'source-taco';
                                        break;
                                    case 'Sonia Tucunduva':
                                        $class = 'source-sonia';
                                        break;
                                    case 'Sonia Tucunduva (Prioridade)':
                                        $class = 'source-priority';
                                        break;
                                    case 'USDA':
                                        $class = 'source-usda';
                                        break;
                                    default:
                                        $class = 'source-taco';
                                }
                                ?>
                                <span class="source-badge <?php echo $class; ?>">
                                    <?php 
                                    switch ($source) {
                                        case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                                        default: echo htmlspecialchars($source);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="edit_food.php?id=<?php echo $food['id']; ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i> Editar</a>
                                    <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                                       class="action-btn delete-btn" 
                                       onclick="return confirm('Tem certeza que deseja excluir este alimento?')"><i class="fas fa-trash"></i> Excluir</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹ Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próximo ›</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="foods_management.php" class="btn btn-secondary"><i class="fas fa-sync"></i> Atualizar</a>
        <a href="foods_stats.php" class="btn"><i class="fas fa-chart-bar"></i> Ver Estatísticas Detalhadas</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
