<?php
// debug_complete_routine.php - Debug específico para completar rotina

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário
$routine_id = 1; // ID da rotina que você está tentando completar

echo "<h2>🔍 Debug: Completar Rotina</h2>";
echo "<p><strong>Usuário ID:</strong> {$user_id}</p>";
echo "<p><strong>Rotina ID:</strong> {$routine_id}</p>";

try {
    // 1. Verificar se a rotina existe
    $stmt = $conn->prepare("SELECT id, title FROM sf_routine_items WHERE id = ?");
    $stmt->bind_param("i", $routine_id);
    $stmt->execute();
    $routine = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$routine) {
        echo "<p style='color: red;'>❌ Rotina não encontrada!</p>";
        exit;
    }
    
    echo "<p><strong>Rotina encontrada:</strong> {$routine['title']}</p>";
    
    // 2. Verificar se já foi completada hoje
    $current_date = date('Y-m-d');
    $stmt = $conn->prepare("SELECT id FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?");
    $stmt->bind_param("iis", $user_id, $routine_id, $current_date);
    $stmt->execute();
    $already_completed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    echo "<p><strong>Já completada hoje?</strong> " . ($already_completed ? 'Sim' : 'Não') . "</p>";
    
    if ($already_completed) {
        echo "<p style='color: orange;'>⚠️ Esta rotina já foi completada hoje!</p>";
        exit;
    }
    
    // 3. Verificar pontos atuais
    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_points = $stmt->get_result()->fetch_assoc()['points'];
    $stmt->close();
    
    echo "<p><strong>Pontos atuais:</strong> {$current_points}</p>";
    
    // 4. Simular o processo completo
    echo "<h3>🧪 Simulando processo completo:</h3>";
    
    $conn->begin_transaction();
    
    try {
        // Inserir no log de rotinas
        $stmt = $conn->prepare("INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iis", $user_id, $routine_id, $current_date);
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            throw new Exception("Falha ao inserir no log de rotinas");
        }
        
        echo "<p style='color: green;'>✅ Inserido no log de rotinas</p>";
        
        // Verificar se já existe log de pontos
        $action_key = 'ROUTINE_COMPLETE';
        $stmt = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
        $stmt->bind_param("isss", $user_id, $action_key, $routine_id, $current_date);
        $stmt->execute();
        $log_exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        echo "<p><strong>Log de pontos já existe?</strong> " . ($log_exists ? 'Sim' : 'Não') . "</p>";
        
        if (!$log_exists) {
            // Inserir no log de pontos
            $points_to_award = 5;
            $stmt = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisss", $user_id, $points_to_award, $action_key, $routine_id, $current_date);
            $success = $stmt->execute();
            $stmt->close();
            
            if (!$success) {
                throw new Exception("Falha ao inserir no log de pontos");
            }
            
            echo "<p style='color: green;'>✅ Inserido no log de pontos</p>";
            
            // Adicionar pontos
            require_once 'includes/functions.php';
            $points_success = addPointsToUser($conn, $user_id, $points_to_award, "Debug - completou rotina ID: {$routine_id}");
            
            if (!$points_success) {
                throw new Exception("Falha ao adicionar pontos");
            }
            
            echo "<p style='color: green;'>✅ Pontos adicionados (+{$points_to_award})</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Log de pontos já existe - pontos não adicionados</p>";
        }
        
        $conn->commit();
        echo "<p style='color: green;'>✅ Transação commitada com sucesso!</p>";
        
        // Verificar pontos finais
        $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $final_points = $stmt->get_result()->fetch_assoc()['points'];
        $stmt->close();
        
        echo "<p><strong>Pontos finais:</strong> {$final_points}</p>";
        echo "<p><strong>Diferença:</strong> " . ($final_points - $current_points) . " pontos</p>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color: red;'>❌ Erro na transação: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>




