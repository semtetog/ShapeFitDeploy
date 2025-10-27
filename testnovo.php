<?php
// clean_orphaned_points_logs.php - Limpar logs de pontos órfãos

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário
$today = date('Y-m-d');

echo "<h2>🧹 Limpando Logs de Pontos Órfãos</h2>";

try {
    // 1. Verificar logs de pontos hoje
    echo "<h3>📊 Logs de Pontos Hoje (ANTES):</h3>";
    $stmt = $conn->prepare("
        SELECT 
            points_awarded,
            action_key,
            action_context_id,
            timestamp
        FROM sf_user_points_log 
        WHERE user_id = ? AND date_awarded = ?
        ORDER BY timestamp DESC
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $points_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($points_logs)) {
        echo "<p style='color: green;'>✅ Nenhum log de pontos hoje!</p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Pontos</th><th>Ação</th><th>Contexto</th><th>Timestamp</th></tr>";
    foreach ($points_logs as $log) {
        echo "<tr>";
        echo "<td>{$log['points_awarded']}</td>";
        echo "<td>{$log['action_key']}</td>";
        echo "<td>{$log['action_context_id']}</td>";
        echo "<td>{$log['timestamp']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Verificar rotinas completadas hoje
    echo "<h3>✅ Rotinas Completadas Hoje:</h3>";
    $stmt = $conn->prepare("
        SELECT 
            url.routine_item_id,
            ri.title,
            url.is_completed,
            url.date
        FROM sf_user_routine_log url
        LEFT JOIN sf_routine_items ri ON url.routine_item_id = ri.id
        WHERE url.user_id = ? AND url.date = ? AND url.is_completed = 1
        ORDER BY url.routine_item_id
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $completed_routines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($completed_routines)) {
        echo "<p style='color: red;'>❌ Nenhuma rotina completada hoje!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Completado</th><th>Data</th></tr>";
        foreach ($completed_routines as $routine) {
            echo "<tr>";
            echo "<td>{$routine['routine_item_id']}</td>";
            echo "<td>" . htmlspecialchars($routine['title'] ?? 'Rotina não encontrada') . "</td>";
            echo "<td style='color: green;'>Sim</td>";
            echo "<td>{$routine['date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Identificar logs órfãos
    echo "<h3>🔍 Identificando Logs Órfãos:</h3>";
    
    $orphaned_logs = [];
    foreach ($points_logs as $log) {
        if ($log['action_key'] === 'ROUTINE_COMPLETE') {
            $routine_id = $log['action_context_id'];
            $has_corresponding_routine = false;
            
            foreach ($completed_routines as $routine) {
                if ($routine['routine_item_id'] == $routine_id) {
                    $has_corresponding_routine = true;
                    break;
                }
            }
            
            if (!$has_corresponding_routine) {
                $orphaned_logs[] = $log;
            }
        }
    }
    
    if (empty($orphaned_logs)) {
        echo "<p style='color: green;'>✅ Nenhum log órfão encontrado!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Encontrados " . count($orphaned_logs) . " logs órfãos:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Pontos</th><th>Ação</th><th>Contexto</th><th>Timestamp</th></tr>";
        foreach ($orphaned_logs as $log) {
            echo "<tr>";
            echo "<td>{$log['points_awarded']}</td>";
            echo "<td>{$log['action_key']}</td>";
            echo "<td>{$log['action_context_id']}</td>";
            echo "<td>{$log['timestamp']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Limpar logs órfãos
    if (!empty($orphaned_logs)) {
        echo "<h3>🧹 Limpando Logs Órfãos:</h3>";
        
        $total_points_to_remove = 0;
        foreach ($orphaned_logs as $log) {
            $total_points_to_remove += $log['points_awarded'];
        }
        
        echo "<p><strong>Total de pontos a remover:</strong> {$total_points_to_remove}</p>";
        
        // Botão para confirmar limpeza
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='clean_orphaned'>";
        echo "<button type='submit' style='background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>🧹 Limpar Logs Órfãos</button>";
        echo "</form>";
        
        if (isset($_POST['action']) && $_POST['action'] === 'clean_orphaned') {
            $conn->begin_transaction();
            
            try {
                // Remover logs órfãos
                $stmt = $conn->prepare("DELETE FROM sf_user_points_log WHERE user_id = ? AND date_awarded = ? AND action_key = 'ROUTINE_COMPLETE'");
                $stmt->bind_param("is", $user_id, $today);
                $success = $stmt->execute();
                $deleted_logs = $stmt->affected_rows;
                $stmt->close();
                
                if (!$success) {
                    throw new Exception("Falha ao remover logs órfãos");
                }
                
                echo "<p style='color: green;'>✅ {$deleted_logs} logs órfãos removidos</p>";
                
                // Ajustar pontos do usuário
                $stmt = $conn->prepare("UPDATE sf_users SET points = GREATEST(points - ?, 0) WHERE id = ?");
                $stmt->bind_param("ii", $total_points_to_remove, $user_id);
                $success = $stmt->execute();
                $stmt->close();
                
                if (!$success) {
                    throw new Exception("Falha ao ajustar pontos do usuário");
                }
                
                echo "<p style='color: green;'>✅ Pontos ajustados (-{$total_points_to_remove})</p>";
                
                $conn->commit();
                echo "<p style='color: green;'>✅ Limpeza concluída com sucesso!</p>";
                
                // Verificar estado final
                $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $final_points = $stmt->get_result()->fetch_assoc()['points'];
                $stmt->close();
                
                echo "<p><strong>Pontos finais:</strong> {$final_points}</p>";
                
            } catch (Exception $e) {
                $conn->rollback();
                echo "<p style='color: red;'>❌ Erro na limpeza: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // 5. Verificar estado final
    echo "<h3>📊 Estado Final:</h3>";
    
    $stmt = $conn->prepare("
        SELECT 
            points_awarded,
            action_key,
            action_context_id,
            timestamp
        FROM sf_user_points_log 
        WHERE user_id = ? AND date_awarded = ?
        ORDER BY timestamp DESC
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $final_points_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($final_points_logs)) {
        echo "<p style='color: green;'>✅ Nenhum log de pontos hoje (limpo!)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Ainda há logs de pontos:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Pontos</th><th>Ação</th><th>Contexto</th><th>Timestamp</th></tr>";
        foreach ($final_points_logs as $log) {
            echo "<tr>";
            echo "<td>{$log['points_awarded']}</td>";
            echo "<td>{$log['action_key']}</td>";
            echo "<td>{$log['action_context_id']}</td>";
            echo "<td>{$log['timestamp']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
