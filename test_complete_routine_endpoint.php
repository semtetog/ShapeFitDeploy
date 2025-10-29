<?php
// test_complete_routine_endpoint.php - Teste completo do endpoint

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário
$routine_id = 1; // ID da rotina que você está tentando completar

echo "<h2>🧪 Teste Completo do Endpoint</h2>";

try {
    // 1. Simular sessão
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo "<p><strong>CSRF Token gerado:</strong> " . substr($_SESSION['csrf_token'], 0, 10) . "...</p>";
    
    // 2. Simular POST request
    $_POST['routine_id'] = $routine_id;
    $_POST['csrf_token'] = $_SESSION['csrf_token'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    echo "<p><strong>Simulando POST com:</strong></p>";
    echo "<ul>";
    echo "<li>routine_id: {$routine_id}</li>";
    echo "<li>csrf_token: " . substr($_POST['csrf_token'], 0, 10) . "...</li>";
    echo "</ul>";
    
    // 3. Capturar output do endpoint
    ob_start();
    
    // Incluir o endpoint
    include 'actions/complete_routine_item.php';
    
    $output = ob_get_clean();
    
    echo "<h3>📤 Resposta do Endpoint:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // 4. Tentar decodificar JSON
    $response = json_decode($output, true);
    
    if ($response) {
        echo "<h3>📊 Resposta Decodificada:</h3>";
        echo "<ul>";
        foreach ($response as $key => $value) {
            echo "<li><strong>{$key}:</strong> " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "</li>";
        }
        echo "</ul>";
        
        if ($response['success']) {
            echo "<p style='color: green;'>✅ Endpoint funcionou corretamente!</p>";
        } else {
            echo "<p style='color: red;'>❌ Endpoint retornou erro: " . $response['message'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Resposta não é JSON válido!</p>";
        echo "<p><strong>Raw output:</strong></p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
    // 5. Verificar pontos finais
    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $final_points = $stmt->get_result()->fetch_assoc()['points'];
    $stmt->close();
    
    echo "<p><strong>Pontos finais:</strong> {$final_points}</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>





