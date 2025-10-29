<?php
// Arquivo: admin_check_foods.php
// Script para verificar status da tabela de alimentos

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

$page_title = "Verificar Alimentos";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
.check-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
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

.table-container {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.table-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.food-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.food-table th,
.food-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.food-table th {
    background: rgba(255, 255, 255, 0.05);
    font-weight: 600;
    color: var(--text-primary);
}

.food-table td {
    color: var(--text-secondary);
}

.source-taco {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.source-sonia {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.source-usda {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px;
    text-decoration: none;
    display: inline-block;
}

.btn:hover {
    background: #ff7a1a;
}
</style>

<div class="check-container">
    <h1>Status da Base de Alimentos</h1>
    
    <?php
    // Estatísticas gerais
    $total_foods = 0;
    $taco_foods = 0;
    $sonia_foods = 0;
    $usda_foods = 0;
    
    // Buscar estatísticas
    $stats_query = "SELECT source_table, COUNT(*) as count FROM sf_food_items GROUP BY source_table";
    $stats_result = $conn->query($stats_query);
    
    while ($row = $stats_result->fetch_assoc()) {
        $total_foods += $row['count'];
        switch ($row['source_table']) {
            case 'TACO':
                $taco_foods = $row['count'];
                break;
            case 'Sonia Tucunduva':
                $sonia_foods = $row['count'];
                break;
            case 'Sonia Tucunduva (Prioridade)':
                $sonia_foods += $row['count'];
                break;
            case 'USDA':
                $usda_foods = $row['count'];
                break;
        }
    }
    ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_foods); ?></div>
            <div class="stat-label">Total de Alimentos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($taco_foods); ?></div>
            <div class="stat-label">TACO</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($sonia_foods); ?></div>
            <div class="stat-label">Sonia Tucunduva</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($usda_foods); ?></div>
            <div class="stat-label">USDA</div>
        </div>
    </div>
    
    <div class="table-container">
        <h2 class="table-title">Alimentos da Sonia Tucunduva</h2>
        <?php
        $sonia_query = "SELECT * FROM sf_food_items WHERE source_table LIKE '%Sonia%' ORDER BY name_pt LIMIT 20";
        $sonia_result = $conn->query($sonia_query);
        ?>
        
        <table class="food-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Calorias</th>
                    <th>Proteína</th>
                    <th>Carbs</th>
                    <th>Gordura</th>
                    <th>Fonte</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $sonia_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name_pt']); ?></td>
                    <td><?php echo round($row['energy_kcal_100g']); ?></td>
                    <td><?php echo round($row['protein_g_100g'], 1); ?>g</td>
                    <td><?php echo round($row['carbohydrate_g_100g'], 1); ?>g</td>
                    <td><?php echo round($row['fat_g_100g'], 1); ?>g</td>
                    <td>
                        <span class="source-sonia">
                            <?php echo $row['source_table'] === 'Sonia Tucunduva (Prioridade)' ? 'Sonia (Atualizado)' : 'Sonia'; ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php if ($sonia_result->num_rows === 0): ?>
        <p style="text-align: center; color: var(--text-secondary); margin: 20px 0;">
            Nenhum alimento da Sonia Tucunduva encontrado.
        </p>
        <?php endif; ?>
    </div>
    
    <div class="table-container">
        <h2 class="table-title">Últimos Alimentos Adicionados</h2>
        <?php
        $recent_query = "SELECT * FROM sf_food_items ORDER BY id DESC LIMIT 10";
        $recent_result = $conn->query($recent_query);
        ?>
        
        <table class="food-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Calorias</th>
                    <th>Fonte</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $recent_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['name_pt']); ?></td>
                    <td><?php echo round($row['energy_kcal_100g']); ?></td>
                    <td>
                        <?php
                        $source = $row['source_table'];
                        $class = '';
                        switch ($source) {
                            case 'TACO':
                                $class = 'source-taco';
                                break;
                            case 'Sonia Tucunduva':
                            case 'Sonia Tucunduva (Prioridade)':
                                $class = 'source-sonia';
                                break;
                            case 'USDA':
                                $class = 'source-usda';
                                break;
                            default:
                                $class = 'source-taco';
                        }
                        ?>
                        <span class="<?php echo $class; ?>"><?php echo $source; ?></span>
                    </td>
                    <td><?php echo $row['id']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="admin_import_sonia.php" class="btn">Importar Sonia Tucunduva</a>
        <a href="add_food_to_diary.php" class="btn">Testar Busca</a>
        <a href="diary.php" class="btn">Voltar ao Diário</a>
    </div>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>





