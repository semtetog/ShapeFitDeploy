<?php
// fix_points_inconsistency.php - Corrigir inconsistência de pontos

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário

echo "<h2>🔧 Corrigindo Inconsistência de Pontos</h2>";

try {
    // 1. Verificar pontos atuais
    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_points = $stmt->get_result()->fetch_assoc()['points'];
    $stmt->close();
    
    echo "<h3>💰 Pontos Atuais: {$current_points}</h3>";
    
    // 2. Calcular pontos corretos baseados nos logs
    $stmt = $conn->prepare("SELECT SUM(points_awarded) as total FROM sf_user_points_log WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $logged_points = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    echo "<h3>📊 Pontos nos Logs: {$logged_points}</h3>";
    
    $difference = $current_points - $logged_points;
    echo "<h3>🔍 Diferença: {$difference} pontos</h3>";
    
    if ($difference > 0) {
        echo "<p style='color: orange;'>⚠️ Você tem {$difference} pontos extras que não estão nos logs!</p>";
        
        // 3. Opções para corrigir
        echo "<h3>🔧 Opções para Corrigir:</h3>";
        echo "<p><strong>Opção 1:</strong> Ajustar pontos para o valor correto ({$logged_points})</p>";
        echo "<p><strong>Opção 2:</strong> Manter os pontos atuais e investigar mais</p>";
        echo "<p><strong>Opção 3:</strong> Adicionar log para os pontos extras</p>";
        
        // 4. Botão para corrigir
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='fix_points'>";
        echo "<button type='submit' style='background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>🔧 Corrigir Pontos para {$logged_points}</button>";
        echo "</form>";
        
        if (isset($_POST['action']) && $_POST['action'] === 'fix_points') {
            // Corrigir pontos
            $stmt = $conn->prepare("UPDATE sf_users SET points = ? WHERE id = ?");
            $stmt->bind_param("di", $logged_points, $user_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                echo "<p style='color: green;'>✅ Pontos corrigidos para {$logged_points}!</p>";
                
                // Verificar pontos finais
                $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $final_points = $stmt->get_result()->fetch_assoc()['points'];
                $stmt->close();
                
                echo "<p><strong>Pontos finais:</strong> {$final_points}</p>";
                echo "<p style='color: green;'>✅ Agora os pontos estão consistentes com os logs!</p>";
            } else {
                echo "<p style='color: red;'>❌ Erro ao corrigir pontos!</p>";
            }
        }
        
    } elseif ($difference < 0) {
        echo "<p style='color: red;'>❌ Você tem menos pontos do que deveria ter!</p>";
        echo "<p><strong>Isso é estranho... Vamos investigar mais.</strong></p>";
    } else {
        echo "<p style='color: green;'>✅ Pontos estão consistentes!</p>";
    }
    
    // 5. Verificar se há logs de outros usuários para comparar
    echo "<h3>🔍 Verificando Outros Usuários (para comparação):</h3>";
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.points,
            COALESCE(SUM(pl.points_awarded), 0) as logged_points
        FROM sf_users u
        LEFT JOIN sf_user_points_log pl ON u.id = pl.user_id
        WHERE u.id != ?
        GROUP BY u.id, u.name, u.points
        HAVING u.points != COALESCE(SUM(pl.points_awarded), 0)
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $other_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($other_users)) {
        echo "<p style='color: orange;'>⚠️ Outros usuários também têm inconsistências:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Pontos Atuais</th><th>Pontos nos Logs</th><th>Diferença</th></tr>";
        foreach ($other_users as $user) {
            $diff = $user['points'] - $user['logged_points'];
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['name']}</td>";
            echo "<td>{$user['points']}</td>";
            echo "<td>{$user['logged_points']}</td>";
            echo "<td>{$diff}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ Outros usuários estão consistentes</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>

