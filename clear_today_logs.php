<?php
// clear_today_logs.php - Limpar logs de hoje para teste

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário
$today = date('Y-m-d');

echo "<h2>🧹 Limpar Logs de Hoje (Para Teste)</h2>";

try {
    // 1. Verificar logs existentes
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sf_user_points_log WHERE user_id = ? AND date_awarded = ?");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    echo "<p><strong>Logs encontrados para hoje:</strong> {$count}</p>";
    
    if ($count > 0) {
        // 2. Mostrar logs que serão removidos
        $stmt = $conn->prepare("SELECT action_key, action_context_id, points_awarded FROM sf_user_points_log WHERE user_id = ? AND date_awarded = ?");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo "<h3>📋 Logs que serão removidos:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Ação</th><th>Contexto</th><th>Pontos</th></tr>";
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>{$log['action_key']}</td>";
            echo "<td>{$log['action_context_id']}</td>";
            echo "<td>{$log['points_awarded']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 3. Botão para confirmar limpeza
        echo "<form method='POST'>";
        echo "<input type='hidden' name='confirm_clear' value='1'>";
        echo "<button type='submit' style='background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>🗑️ Limpar Logs de Hoje</button>";
        echo "</form>";
        
        if (isset($_POST['confirm_clear'])) {
            // 4. Remover logs
            $stmt = $conn->prepare("DELETE FROM sf_user_points_log WHERE user_id = ? AND date_awarded = ?");
            $stmt->bind_param("is", $user_id, $today);
            $success = $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            
            if ($success) {
                echo "<p style='color: green;'>✅ {$deleted} logs removidos com sucesso!</p>";
                echo "<p><strong>Agora você pode completar rotinas novamente e ganhar pontos.</strong></p>";
            } else {
                echo "<p style='color: red;'>❌ Erro ao remover logs!</p>";
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Nenhum log encontrado para hoje. Você pode completar rotinas normalmente.</p>";
    }
    
    // 5. Verificar pontos atuais
    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $points = $stmt->get_result()->fetch_assoc()['points'];
    $stmt->close();
    
    echo "<h3>💰 Pontos Atuais: {$points}</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>⚠️ Aviso:</strong> Este script é apenas para teste. Em produção, você não deveria limpar logs diariamente.</p>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>




