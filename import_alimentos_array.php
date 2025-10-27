<?php
// Arquivo: import_alimentos_array.php
// Script para importar dados do alimentos_array.php

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

$page_title = "Importar Alimentos Array";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
.import-container {
    max-width: 1000px;
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

.result {
    background: rgba(0, 255, 0, 0.1);
    border: 1px solid #00ff00;
    border-radius: 8px;
    padding: 12px;
    margin: 10px 0;
    font-family: monospace;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
}

.btn:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin: 16px 0;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 12px;
    text-align: center;
}

.stat-number {
    font-size: 20px;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 11px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.warning {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

.warning h4 {
    color: #ffc107;
    margin: 0 0 8px 0;
}

.success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

.success h4 {
    color: #28a745;
    margin: 0 0 8px 0;
}
</style>

<div class="import-container">
    <h1>📊 Importar Alimentos - Array</h1>
    
    <?php
    // Verificar se o arquivo existe
    if (!file_exists('alimentos_array.php')) {
        echo "<div class='warning'>";
        echo "<h4>❌ Arquivo não encontrado</h4>";
        echo "<p>O arquivo <code>alimentos_array.php</code> não foi encontrado na raiz do projeto.</p>";
        echo "</div>";
        exit;
    }
    
    // Carregar dados do array
    include 'alimentos_array.php';
    
    if (!isset($alimentos) || !is_array($alimentos)) {
        echo "<div class='warning'>";
        echo "<h4>❌ Array inválido</h4>";
        echo "<p>O arquivo não contém um array <code>\$alimentos</code> válido.</p>";
        echo "</div>";
        exit;
    }
    
    $total_alimentos = count($alimentos);
    ?>
    
    <div class="step">
        <h2 class="step-title">📋 Dados Carregados</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_alimentos); ?></div>
                <div class="stat-label">Total de Alimentos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_alimentos); ?></div>
                <div class="stat-label">Para Importar</div>
            </div>
        </div>
        
        <div class="warning">
            <h4>⚠️ Importante</h4>
            <ul>
                <li>🔒 <strong>Backup:</strong> Faça backup da tabela <code>sf_food_items</code> antes de executar</li>
                <li>📝 <strong>Prioridade:</strong> Alimentos da Sonia Tucunduva terão prioridade sobre TACO</li>
                <li>🔄 <strong>Transação:</strong> Se houver erro, nada será alterado</li>
                <li>⚡ <strong>Processo:</strong> Pode levar alguns minutos para processar todos os alimentos</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <h3>Primeiros 5 Alimentos:</h3>
            <?php for ($i = 0; $i < min(5, count($alimentos)); $i++): ?>
                <div style="background: rgba(255, 255, 255, 0.02); padding: 8px 12px; border-radius: 6px; margin: 4px 0; font-size: 12px;">
                    <strong><?php echo htmlspecialchars($alimentos[$i]['name']); ?></strong> | 
                    <?php echo $alimentos[$i]['kcal']; ?> kcal | 
                    P: <?php echo $alimentos[$i]['protein_g']; ?>g | 
                    C: <?php echo $alimentos[$i]['carbs_g']; ?>g | 
                    G: <?php echo $alimentos[$i]['fat_g']; ?>g
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <?php if ($_POST['action'] ?? '' === 'import'): ?>
        <div class="step">
            <h2 class="step-title">🚀 Executando Importação...</h2>
            
            <div class="result">
                <?php
                $duplicates_handled = 0;
                $new_items_added = 0;
                $taco_items_updated = 0;
                $errors = [];
                $skipped = 0;
                
                echo "=== INICIANDO IMPORTAÇÃO SONIA TUCUNDUVA ===\n";
                echo "Total de alimentos: {$total_alimentos}\n";
                echo "Iniciando processamento...\n\n";
                
                try {
                    $conn->begin_transaction();
                    
                    foreach ($alimentos as $index => $item) {
                        $progress = round(($index + 1) / $total_alimentos * 100, 1);
                        
                        // Validar dados
                        if (empty($item['name']) || !is_numeric($item['kcal']) || !is_numeric($item['protein_g']) || !is_numeric($item['carbs_g']) || !is_numeric($item['fat_g'])) {
                            $skipped++;
                            echo "⚠️ [{$progress}%] Dados inválidos: {$item['name']}\n";
                            continue;
                        }
                        
                        echo "[{$progress}%] Processando: {$item['name']}\n";
                        
                        // Buscar item similar no TACO (busca mais ampla)
                        $stmt_check = $conn->prepare("
                            SELECT id, name_pt, source_table 
                            FROM sf_food_items 
                            WHERE (
                                name_pt LIKE ? OR 
                                name_pt LIKE ? OR
                                ? LIKE CONCAT('%', name_pt, '%')
                            )
                            AND source_table = 'TACO'
                            LIMIT 1
                        ");
                        
                        $search_term1 = '%' . $item['name'] . '%';
                        $search_term2 = '%' . strtolower($item['name']) . '%';
                        $search_term3 = strtolower($item['name']);
                        
                        $stmt_check->bind_param("sss", $search_term1, $search_term2, $search_term3);
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
                                $item['kcal'],
                                $item['protein_g'],
                                $item['carbs_g'],
                                $item['fat_g'],
                                $existing_item['id']
                            );
                            
                            if ($stmt_update->execute()) {
                                $taco_items_updated++;
                                echo "✓ Atualizado: {$item['name']} (ID: {$existing_item['id']})\n";
                            } else {
                                $errors[] = "Erro ao atualizar {$item['name']}: " . $stmt_update->error;
                                echo "❌ Erro ao atualizar: {$item['name']}\n";
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
                                ) VALUES (?, ?, ?, ?, ?, 'Sonia Tucunduva')
                            ");
                            
                            $stmt_insert->bind_param("sdddd", 
                                $item['name'],
                                $item['kcal'],
                                $item['protein_g'],
                                $item['carbs_g'],
                                $item['fat_g']
                            );
                            
                            if ($stmt_insert->execute()) {
                                $new_items_added++;
                                echo "✓ Adicionado: {$item['name']}\n";
                            } else {
                                $errors[] = "Erro ao adicionar {$item['name']}: " . $stmt_insert->error;
                                echo "❌ Erro ao adicionar: {$item['name']}\n";
                            }
                            $stmt_insert->close();
                        }
                        
                        $duplicates_handled++;
                        
                        // Mostrar progresso a cada 100 itens
                        if (($index + 1) % 100 === 0) {
                            echo "\n--- PROGRESSO: " . ($index + 1) . "/{$total_alimentos} ---\n\n";
                        }
                    }
                    
                    $conn->commit();
                    
                    echo "\n=== RESUMO DA IMPORTAÇÃO ===\n";
                    echo "Itens processados: {$duplicates_handled}\n";
                    echo "Novos itens adicionados: {$new_items_added}\n";
                    echo "Itens TACO atualizados: {$taco_items_updated}\n";
                    echo "Itens ignorados (dados inválidos): {$skipped}\n";
                    echo "Transação confirmada com sucesso!\n";
                    
                    if (!empty($errors)) {
                        echo "\n=== ERROS ENCONTRADOS ===\n";
                        foreach ($errors as $error) {
                            echo "❌ {$error}\n";
                        }
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    echo "\n❌ ERRO FATAL: " . $e->getMessage() . "\n";
                    echo "Transação revertida. Nenhum dado foi alterado.\n";
                }
                ?>
            </div>
            
            <div class="success">
                <h4>✅ Importação Concluída!</h4>
                <p>Os dados da Sonia Tucunduva foram importados com sucesso. Agora você pode testar a busca de alimentos.</p>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="admin_check_foods.php" class="btn">Ver Estatísticas</a>
                <a href="add_food_to_diary.php" class="btn">Testar Busca</a>
                <a href="diary.php" class="btn btn-secondary">Voltar ao Diário</a>
            </div>
        </div>
        
    <?php else: ?>
        <div class="step">
            <h2 class="step-title">🚀 Executar Importação</h2>
            
            <div class="warning">
                <h4>⚠️ Confirmação Necessária</h4>
                <p>Você está prestes a importar <strong><?php echo number_format($total_alimentos); ?> alimentos</strong> da Sonia Tucunduva.</p>
                <p>Este processo pode levar alguns minutos e irá:</p>
                <ul>
                    <li>✅ Atualizar alimentos existentes no TACO com dados da Sonia</li>
                    <li>✅ Adicionar novos alimentos que não existem no TACO</li>
                    <li>✅ Dar prioridade aos dados da Sonia Tucunduva</li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="import">
                <div style="text-align: center;">
                    <button type="submit" class="btn" onclick="return confirm('Tem certeza que deseja importar <?php echo number_format($total_alimentos); ?> alimentos da Sonia Tucunduva? Este processo pode levar alguns minutos.')">
                        🚀 Importar <?php echo number_format($total_alimentos); ?> Alimentos
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>




