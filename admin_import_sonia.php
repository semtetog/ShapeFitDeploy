<?php
// Arquivo: admin_import_sonia.php
// Script para executar via navegador na Hostinger

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

// Verificar se é admin (opcional - remova se quiser que qualquer usuário execute)
// requireAdminLogin();

$page_title = "Importar Sonia Tucunduva";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
.import-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.step {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.step-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--accent-orange);
    margin-bottom: 12px;
}

.code-block {
    background: #1a1a1a;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 12px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #00ff00;
    overflow-x: auto;
    margin: 10px 0;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px;
}

.btn:hover {
    background: #ff7a1a;
}

.result {
    background: rgba(0, 255, 0, 0.1);
    border: 1px solid #00ff00;
    border-radius: 8px;
    padding: 12px;
    margin: 10px 0;
    font-family: monospace;
    white-space: pre-wrap;
}
</style>

<div class="import-container">
    <h1>Importar Dados - Sonia Tucunduva</h1>
    
    <?php if ($_POST['action'] ?? '' === 'import'): ?>
        <div class="step">
            <h2 class="step-title">Executando Importação...</h2>
            
            <?php
            // Dados da Sonia Tucunduva (você pode adicionar mais aqui)
            $sonia_data = [
                [
                    'name' => 'Bacon cozido',
                    'kcal_100g' => 541,
                    'protein_100g' => 37.04,
                    'carbs_100g' => 1.43,
                    'fat_100g' => 41.78,
                    'source' => 'Sonia Tucunduva'
                ],
                [
                    'name' => 'Carne de porco assada',
                    'kcal_100g' => 297,
                    'protein_100g' => 26.51,
                    'carbs_100g' => 0,
                    'fat_100g' => 20.47,
                    'source' => 'Sonia Tucunduva'
                ],
                [
                    'name' => 'Carne de porco cozida',
                    'kcal_100g' => 297,
                    'protein_100g' => 26.51,
                    'carbs_100g' => 0,
                    'fat_100g' => 20.47,
                    'source' => 'Sonia Tucunduva'
                ],
                [
                    'name' => 'Linguiça de porco',
                    'kcal_100g' => 301,
                    'protein_100g' => 12.93,
                    'carbs_100g' => 2.06,
                    'fat_100g' => 25.93,
                    'source' => 'Sonia Tucunduva'
                ],
                [
                    'name' => 'Salsicha de porco',
                    'kcal_100g' => 301,
                    'protein_100g' => 12.93,
                    'carbs_100g' => 2.06,
                    'fat_100g' => 25.93,
                    'source' => 'Sonia Tucunduva'
                ]
                // Adicione mais alimentos aqui conforme necessário
            ];
            
            $duplicates_handled = 0;
            $new_items_added = 0;
            $taco_items_updated = 0;
            $errors = [];
            
            echo "<div class='result'>";
            echo "=== INICIANDO MERGE SONIA TUCUNDUVA ===\n";
            echo "Processando " . count($sonia_data) . " itens...\n\n";
            
            try {
                $conn->begin_transaction();
                
                foreach ($sonia_data as $item) {
                    echo "Processando: {$item['name']}\n";
                    
                    // Buscar item similar no TACO
                    $stmt_check = $conn->prepare("
                        SELECT id, name_pt, source_table 
                        FROM sf_food_items 
                        WHERE name_pt LIKE ? 
                        AND source_table = 'TACO'
                        LIMIT 1
                    ");
                    
                    $search_term = '%' . $item['name'] . '%';
                    $stmt_check->bind_param("s", $search_term);
                    $stmt_check->execute();
                    $result = $stmt_check->get_result();
                    $existing_item = $result->fetch_assoc();
                    $stmt_check->close();
                    
                    if ($existing_item) {
                        // Atualizar item do TACO com dados da Sonia
                        $stmt_update = $conn->prepare("
                            UPDATE sf_food_items 
                            SET name_pt = ?, 
                                energy_kcal_100g = ?, 
                                protein_g_100g = ?, 
                                carbohydrate_g_100g = ?, 
                                fat_g_100g = ?,
                                source_table = 'Sonia Tucunduva (Prioridade)'
                            WHERE id = ?
                        ");
                        
                        $stmt_update->bind_param("sddddi", 
                            $item['name'],
                            $item['kcal_100g'],
                            $item['protein_100g'],
                            $item['carbs_100g'],
                            $item['fat_100g'],
                            $existing_item['id']
                        );
                        
                        if ($stmt_update->execute()) {
                            $taco_items_updated++;
                            echo "✓ Atualizado: {$item['name']} (ID: {$existing_item['id']})\n";
                        } else {
                            $errors[] = "Erro ao atualizar {$item['name']}: " . $stmt_update->error;
                        }
                        $stmt_update->close();
                        
                    } else {
                        // Inserir novo item
                        $stmt_insert = $conn->prepare("
                            INSERT INTO sf_food_items (
                                name_pt, 
                                energy_kcal_100g, 
                                protein_g_100g, 
                                carbohydrate_g_100g, 
                                fat_g_100g,
                                source_table
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt_insert->bind_param("sddddds", 
                            $item['name'],
                            $item['kcal_100g'],
                            $item['protein_100g'],
                            $item['carbs_100g'],
                            $item['fat_100g'],
                            $item['source']
                        );
                        
                        if ($stmt_insert->execute()) {
                            $new_items_added++;
                            echo "✓ Adicionado: {$item['name']}\n";
                        } else {
                            $errors[] = "Erro ao adicionar {$item['name']}: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    }
                    
                    $duplicates_handled++;
                }
                
                $conn->commit();
                
                echo "\n=== RESUMO DO MERGE ===\n";
                echo "Itens processados: {$duplicates_handled}\n";
                echo "Novos itens adicionados: {$new_items_added}\n";
                echo "Itens TACO atualizados: {$taco_items_updated}\n";
                echo "Transação confirmada com sucesso!\n";
                
                if (!empty($errors)) {
                    echo "\n=== ERROS ENCONTRADOS ===\n";
                    foreach ($errors as $error) {
                        echo "❌ {$error}\n";
                    }
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                echo "\n❌ ERRO: " . $e->getMessage() . "\n";
                echo "Transação revertida.\n";
            }
            
            echo "</div>";
            ?>
            
            <a href="admin_import_sonia.php" class="btn">Voltar</a>
            <a href="add_food_to_diary.php" class="btn">Testar Busca</a>
        </div>
        
    <?php else: ?>
        <div class="step">
            <h2 class="step-title">📋 Instruções</h2>
            <p>Este script irá:</p>
            <ul>
                <li>✅ Buscar alimentos similares na tabela TACO</li>
                <li>✅ Atualizar dados existentes com prioridade para Sonia Tucunduva</li>
                <li>✅ Adicionar novos alimentos que não existem no TACO</li>
                <li>✅ Evitar duplicatas mantendo apenas uma versão</li>
            </ul>
        </div>
        
        <div class="step">
            <h2 class="step-title">📊 Dados a serem importados</h2>
            <div class="code-block">
<?php
$sample_data = [
    ['name' => 'Bacon cozido', 'kcal' => 541, 'protein' => 37.04, 'carbs' => 1.43, 'fat' => 41.78],
    ['name' => 'Carne de porco assada', 'kcal' => 297, 'protein' => 26.51, 'carbs' => 0, 'fat' => 20.47],
    ['name' => 'Linguiça de porco', 'kcal' => 301, 'protein' => 12.93, 'carbs' => 2.06, 'fat' => 25.93]
];

foreach ($sample_data as $item) {
    echo sprintf("%-25s | %3.0f kcal | P: %4.1f | C: %4.1f | G: %4.1f\n",
        $item['name'], $item['kcal'], $item['protein'], $item['carbs'], $item['fat']);
}
?>
            </div>
            <p><strong>Total de itens:</strong> 5 alimentos da Sonia Tucunduva</p>
        </div>
        
        <div class="step">
            <h2 class="step-title">⚠️ Importante</h2>
            <ul>
                <li>🔒 <strong>Backup:</strong> Faça backup da tabela <code>sf_food_items</code> antes de executar</li>
                <li>📝 <strong>Logs:</strong> O processo será executado em transação (tudo ou nada)</li>
                <li>🔄 <strong>Reversível:</strong> Se houver erro, nada será alterado</li>
                <li>⚡ <strong>Rápido:</strong> Processo deve levar menos de 5 segundos</li>
            </ul>
        </div>
        
        <div class="step">
            <h2 class="step-title">🚀 Executar Importação</h2>
            <form method="POST">
                <input type="hidden" name="action" value="import">
                <button type="submit" class="btn" onclick="return confirm('Tem certeza que deseja executar a importação?')">
                    Executar Importação Sonia Tucunduva
                </button>
            </form>
        </div>
        
        <div class="step">
            <h2 class="step-title">📝 Como adicionar mais dados</h2>
            <p>Para adicionar mais alimentos da Sonia Tucunduva:</p>
            <ol>
                <li>Abra o arquivo <code>admin_import_sonia.php</code></li>
                <li>Encontre o array <code>$sonia_data</code></li>
                <li>Adicione novos itens no formato:</li>
            </ol>
            <div class="code-block">
[
    'name' => 'Nome do alimento',
    'kcal_100g' => 300,
    'protein_100g' => 25.0,
    'carbs_100g' => 5.0,
    'fat_100g' => 20.0,
    'source' => 'Sonia Tucunduva'
],
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>
