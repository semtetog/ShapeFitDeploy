<?php
// debug_points_detailed.php - Debug detalhado do sistema de pontos

require_once 'includes/config.php';
require_once 'includes/db.php';

// Simular sessão de usuário (substitua pelo ID real do usuário)
$user_id = 77; // Substitua pelo ID do seu usuário

echo "<h2>🔍 Debug Detalhado do Sistema de Pontos</h2>";

try {
    // 1. Verificar pontos atuais
    $stmt = $conn->prepare("SELECT id, name, points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        echo "❌ Usuário não encontrado!";
        exit;
    }
    
    echo "<h3>👤 Usuário: {$user['name']} (ID: {$user['id']})</h3>";
    echo "<p><strong>Pontos atuais:</strong> {$user['points']}</p>";
    
    // 2. Teste manual da função addPointsToUser
    echo "<h3>🧪 Teste da Função addPointsToUser</h3>";
    
    $test_points = 5;
    echo "<p>Testando adição de {$test_points} pontos...</p>";
    
    // Chamar a função diretamente
    require_once 'includes/functions.php';
    $result = addPointsToUser($conn, $user_id, $test_points, "Teste manual");
    
    if ($result) {
        echo "<p style='color: green;'>✅ Função addPointsToUser executada com sucesso!</p>";
        
        // Verificar novos pontos
        $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $new_points = $stmt->get_result()->fetch_assoc()['points'];
        $stmt->close();
        
        echo "<p><strong>Pontos após teste:</strong> {$new_points}</p>";
        echo "<p><strong>Diferença:</strong> " . ($new_points - $user['points']) . " pontos</p>";
    } else {
        echo "<p style='color: red;'>❌ Função addPointsToUser falhou!</p>";
    }
    
    // 3. Verificar logs de erro do PHP
    echo "<h3>📋 Logs de Erro Recentes</h3>";
    $error_log = ini_get('error_log');
    echo "<p><strong>Arquivo de log:</strong> {$error_log}</p>";
    
    if (file_exists($error_log)) {
        $logs = file_get_contents($error_log);
        $recent_logs = array_slice(explode("\n", $logs), -20); // Últimas 20 linhas
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        foreach ($recent_logs as $log) {
            if (strpos($log, 'addPointsToUser') !== false || strpos($log, 'points') !== false) {
                echo htmlspecialchars($log) . "\n";
            }
        }
        echo "</pre>";
    }
    
    // 4. Teste de inserção no log de pontos
    echo "<h3>🧪 Teste de Inserção no Log de Pontos</h3>";
    
    $current_date = date('Y-m-d');
    $action_key = 'ROUTINE_COMPLETE';
    $action_context = 'TESTE_MANUAL';
    
    // Verificar se já existe
    $stmt = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
    $stmt->bind_param("isss", $user_id, $action_key, $action_context, $current_date);
    $stmt->execute();
    $log_exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    echo "<p><strong>Log já existe?</strong> " . ($log_exists ? 'Sim' : 'Não') . "</p>";
    
    if (!$log_exists) {
        // Tentar inserir
        $stmt = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $user_id, $test_points, $action_key, $action_context, $current_date);
        $insert_result = $stmt->execute();
        $stmt->close();
        
        if ($insert_result) {
            echo "<p style='color: green;'>✅ Inserção no log de pontos bem-sucedida!</p>";
        } else {
            echo "<p style='color: red;'>❌ Falha na inserção no log de pontos!</p>";
            echo "<p><strong>Erro MySQL:</strong> " . $conn->error . "</p>";
        }
    }
    
    // 5. Verificar estrutura da tabela sf_users
    echo "<h3>📊 Estrutura da Tabela sf_users</h3>";
    $result = $conn->query("DESCRIBE sf_users");
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. Simular completar rotina
    echo "<h3>🎯 Simulação de Completar Rotina</h3>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='simulate_complete' value='1'>";
    echo "<button type='submit' style='background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Simular Completar Rotina</button>";
    echo "</form>";
    
    if (isset($_POST['simulate_complete'])) {
        echo "<h4>Executando simulação...</h4>";
        
        // Simular o processo completo
        $routine_id = 999; // ID fictício para teste
        $points_to_award = 5;
        $action_key = 'ROUTINE_COMPLETE';
        
        // 1. Verificar se já existe log
        $stmt = $conn->prepare("SELECT id FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
        $stmt->bind_param("isss", $user_id, $action_key, $routine_id, $current_date);
        $stmt->execute();
        $log_exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        echo "<p><strong>Log já existe?</strong> " . ($log_exists ? 'Sim' : 'Não') . "</p>";
        
        if (!$log_exists) {
            // 2. Inserir no log
            $stmt = $conn->prepare("INSERT INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisss", $user_id, $points_to_award, $action_key, $routine_id, $current_date);
            $log_success = $stmt->execute();
            $stmt->close();
            
            echo "<p><strong>Inserção no log:</strong> " . ($log_success ? 'Sucesso' : 'Falha') . "</p>";
            
            if ($log_success) {
                // 3. Adicionar pontos
                $points_success = addPointsToUser($conn, $user_id, $points_to_award, "Simulação de rotina ID: {$routine_id}");
                echo "<p><strong>Adição de pontos:</strong> " . ($points_success ? 'Sucesso' : 'Falha') . "</p>";
                
                if ($points_success) {
                    // 4. Verificar resultado final
                    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $final_points = $stmt->get_result()->fetch_assoc()['points'];
                    $stmt->close();
                    
                    echo "<p style='color: green;'><strong>✅ Simulação completa! Pontos finais: {$final_points}</strong></p>";
                }
            }
        } else {
            echo "<p style='color: orange;'>⚠️ Log já existe para hoje - pontos não seriam adicionados</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>





