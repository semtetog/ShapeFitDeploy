<?php
// test_endpoints_direct.php - Teste direto dos endpoints

echo "<h2>🧪 Teste Direto dos Endpoints</h2>";

// Simular dados de POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = 'test_token'; // Token de teste
$_SESSION['csrf_token'] = 'test_token';
$_SESSION['user_id'] = 77; // Substitua pelo ID do seu usuário

echo "<h3>1. Testando complete_routine_item.php</h3>";

// Teste 1: Rotina normal
$_POST['routine_id'] = '1'; // ID de uma rotina existente

echo "<p>Testando com routine_id = 1...</p>";

// Capturar output
ob_start();
include 'actions/complete_routine_item.php';
$output1 = ob_get_clean();

echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo htmlspecialchars($output1);
echo "</pre>";

echo "<h3>2. Testando complete_onboarding_routine.php</h3>";

// Teste 2: Exercício onboarding
$_POST['routine_id'] = 'Corrida'; // Nome de um exercício
$_POST['duration_minutes'] = '60';

echo "<p>Testando com routine_id = 'Corrida' e duration_minutes = 60...</p>";

// Capturar output
ob_start();
include 'actions/complete_onboarding_routine.php';
$output2 = ob_get_clean();

echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo htmlspecialchars($output2);
echo "</pre>";

echo "<h3>3. Verificar pontos após testes</h3>";

require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário
$stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo "<p><strong>Pontos atuais:</strong> {$result['points']}</p>";

echo "<h3>4. Verificar logs de pontos</h3>";

$stmt = $conn->prepare("
    SELECT 
        points_awarded, 
        action_key, 
        action_context_id, 
        date_awarded, 
        timestamp 
    FROM sf_user_points_log 
    WHERE user_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($logs)) {
    echo "<p>❌ Nenhum log encontrado!</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Pontos</th><th>Ação</th><th>Contexto</th><th>Data</th><th>Timestamp</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['points_awarded']}</td>";
        echo "<td>{$log['action_key']}</td>";
        echo "<td>{$log['action_context_id']}</td>";
        echo "<td>{$log['date_awarded']}</td>";
        echo "<td>{$log['timestamp']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><strong>Nota:</strong> Este teste simula chamadas diretas aos endpoints. Se funcionar aqui mas não funcionar no frontend, o problema está no JavaScript.</p>";
?>





