<?php
// Debug específico para edit_meal.php

echo "<h1>Debug - Edit Meal</h1>";

// Incluir config
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "<h2>1. Verificações Básicas:</h2>";
echo "BASE_APP_URL: " . (defined('BASE_APP_URL') ? BASE_APP_URL : 'NÃO DEFINIDA') . "<br>";
echo "APP_ROOT_PATH: " . (defined('APP_ROOT_PATH') ? APP_ROOT_PATH : 'NÃO DEFINIDA') . "<br>";

echo "<h2>2. Verificação de Sessão:</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ Usuário logado: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ Usuário não logado<br>";
    echo "Sessão ID: " . session_id() . "<br>";
    echo "Dados da sessão: <pre>" . print_r($_SESSION, true) . "</pre>";
}

echo "<h2>3. Parâmetros GET:</h2>";
echo "ID recebido: " . (isset($_GET['id']) ? $_GET['id'] : 'NÃO FORNECIDO') . "<br>";

if (isset($_GET['id'])) {
    $meal_id = (int)$_GET['id'];
    $user_id = $_SESSION['user_id'] ?? 0;
    
    echo "<h2>4. Teste de Conexão com Banco:</h2>";
    if ($conn) {
        echo "✅ Conexão com banco OK<br>";
        
        // Testar query
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $meal_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc();
            $stmt->close();
            
            echo "Refeições encontradas: " . $count['total'] . "<br>";
            
            if ($count['total'] > 0) {
                echo "<h2>5. Dados da Refeição:</h2>";
                $stmt = $conn->prepare("SELECT * FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $meal_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $meal = $result->fetch_assoc();
                $stmt->close();
                
                echo "<pre>" . print_r($meal, true) . "</pre>";
            } else {
                echo "❌ Nenhuma refeição encontrada com este ID para este usuário<br>";
            }
        } else {
            echo "❌ Erro na preparação da query: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Erro de conexão com banco<br>";
    }
}

echo "<h2>6. Links de Teste:</h2>";
echo "<a href='edit_meal_simple.php?id=" . ($_GET['id'] ?? '45') . "'>Teste Simplificado</a><br>";
echo "<a href='diary.php'>Voltar ao Diário</a><br>";
?>
