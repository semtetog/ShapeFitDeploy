<?php
// admin/foods_management_new.php - Gerenciamento de Alimentos - Design Profissional

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

<link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/foods_admin.css">

<div class="admin-container">
    <!-- Cabeçalho -->
    <div class="page-header">
        <h1>
            <i class="fas fa-apple-alt"></i>
            Gerenciar Alimentos
        </h1>
    </div>
    
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
                        case 'FatSecret': echo 'FatSecret'; break;
                        default: echo htmlspecialchars($source);
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filtros e Busca -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-filter"></i>
                Filtros e Busca
            </h2>
        </div>
        <div class="section-content">
            <form method="GET" class="form-row">
                <div class="form-group">
                    <label>Buscar Alimento</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Digite o nome do alimento...">
                </div>
                
                <div class="form-group">
                    <label>Fonte</label>
                    <select name="source" class="form-control">
                        <option value="">Todas as fontes</option>
                        <option value="TACO" <?php echo $source_filter === 'TACO' ? 'selected' : ''; ?>>TACO</option>
                        <option value="Sonia Tucunduva" <?php echo $source_filter === 'Sonia Tucunduva' ? 'selected' : ''; ?>>Sonia Tucunduva</option>
                        <option value="Sonia Tucunduva (Prioridade)" <?php echo $source_filter === 'Sonia Tucunduva (Prioridade)' ? 'selected' : ''; ?>>Sonia (Atualizado)</option>
                        <option value="USDA" <?php echo $source_filter === 'USDA' ? 'selected' : ''; ?>>USDA</option>
                        <option value="FatSecret" <?php echo $source_filter === 'FatSecret' ? 'selected' : ''; ?>>FatSecret</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                    <a href="foods_management_new.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Adicionar Novo Alimento -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-plus"></i>
                Adicionar Novo Alimento
            </h2>
        </div>
        <div class="section-content">
            <form method="POST" action="process_food.php" class="form-grid">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Nome do Alimento</label>
                    <input type="text" name="name_pt" class="form-control" required 
                           placeholder="Ex: Arroz integral">
                </div>
                
                <div class="form-group">
                    <label>Calorias (por 100g)</label>
                    <input type="number" name="energy_kcal_100g" class="form-control" 
                           step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Proteína (por 100g)</label>
                    <input type="number" name="protein_g_100g" class="form-control" 
                           step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Carboidratos (por 100g)</label>
                    <input type="number" name="carbohydrate_g_100g" class="form-control" 
                           step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Gordura (por 100g)</label>
                    <input type="number" name="fat_g_100g" class="form-control" 
                           step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label>Fonte</label>
                    <select name="source_table" class="form-control">
                        <option value="TACO">TACO</option>
                        <option value="Sonia Tucunduva">Sonia Tucunduva</option>
                        <option value="USDA">USDA</option>
                        <option value="FatSecret">FatSecret</option>
                        <option value="Manual">Manual</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Adicionar Alimento
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Lista de Alimentos -->
    <div class="table-container">
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
            <table class="data-table">
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
                                <div style="font-weight: 500; color: var(--text-primary);">
                                    <?php echo htmlspecialchars($food['name_pt']); ?>
                                    <?php if (!empty($food['brand']) && $food['brand'] !== 'TACO'): ?>
                                        <br><small style="color: var(--text-secondary); font-size: 0.85em;">🏷️ <?php echo htmlspecialchars($food['brand']); ?></small>
                                    <?php endif; ?>
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
                                        $class = 'badge-taco';
                                        break;
                                    case 'Sonia Tucunduva':
                                        $class = 'badge-sonia';
                                        break;
                                    case 'Sonia Tucunduva (Prioridade)':
                                        $class = 'badge-priority';
                                        break;
                                    case 'USDA':
                                        $class = 'badge-usda';
                                        break;
                                    case 'FatSecret':
                                        $class = 'badge-fatsecret';
                                        break;
                                    default:
                                        $class = 'badge-taco';
                                }
                                ?>
                                <span class="badge <?php echo $class; ?>">
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
                                    <a href="edit_food.php?id=<?php echo $food['id']; ?>" 
                                       class="action-btn edit">
                                        <i class="fas fa-edit"></i>
                                        Editar
                                    </a>
                                    <a href="process_food.php?action=delete&id=<?php echo $food['id']; ?>" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Tem certeza que deseja excluir este alimento?')">
                                        <i class="fas fa-trash"></i>
                                        Excluir
                                    </a>
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
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Ações Finais -->
    <div class="text-center mt-3">
        <a href="foods_management_new.php" class="btn btn-secondary">
            <i class="fas fa-sync"></i>
            Atualizar
        </a>
        <a href="foods_stats.php" class="btn btn-primary">
            <i class="fas fa-chart-bar"></i>
            Ver Estatísticas
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

