<?php
/**
 * API de Sincronização - Endpoint para sincronização em lote
 * Permite que o app mobile sincronize múltiplos dados de uma vez
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Autenticação
$headers = getallheaders();
$token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
    exit;
}

// Remover "Bearer " do token
$token = str_replace('Bearer ', '', $token);

// Validar token (implementar sua lógica de validação)
$user_id = validateToken($token);
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// Ler dados do body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$results = [
    'success' => true,
    'synced' => [],
    'errors' => []
];

// Sincronizar refeições
if (isset($input['meals']) && is_array($input['meals'])) {
    foreach ($input['meals'] as $meal) {
        try {
            $result = syncMeal($conn, $user_id, $meal);
            $results['synced'][] = [
                'type' => 'meal',
                'id' => $meal['local_id'] ?? null,
                'server_id' => $result['id'] ?? null
            ];
        } catch (Exception $e) {
            $results['errors'][] = [
                'type' => 'meal',
                'id' => $meal['local_id'] ?? null,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Sincronizar histórico de peso
if (isset($input['weight_history']) && is_array($input['weight_history'])) {
    foreach ($input['weight_history'] as $weight) {
        try {
            $result = syncWeight($conn, $user_id, $weight);
            $results['synced'][] = [
                'type' => 'weight',
                'id' => $weight['local_id'] ?? null,
                'server_id' => $result['id'] ?? null
            ];
        } catch (Exception $e) {
            $results['errors'][] = [
                'type' => 'weight',
                'id' => $weight['local_id'] ?? null,
                'error' => $e->getMessage()
            ];
        }
    }
}

echo json_encode($results);

// Funções auxiliares
function validateToken($token) {
    global $conn;
    
    // Implementar validação de token JWT ou sessão
    // Exemplo simples (substituir por JWT real):
    $stmt = $conn->prepare("SELECT user_id FROM sf_user_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['user_id'] ?? null;
}

function syncMeal($conn, $user_id, $meal) {
    // Verificar se já existe (por local_id ou data/hora)
    $stmt = $conn->prepare("
        SELECT id FROM sf_meal_log 
        WHERE user_id = ? 
        AND date = ? 
        AND meal_type = ? 
        AND TIME(created_at) = TIME(?)
        LIMIT 1
    ");
    
    $date = $meal['date'] ?? date('Y-m-d');
    $meal_type = $meal['meal_type'] ?? 'breakfast';
    $created_at = $meal['created_at'] ?? date('Y-m-d H:i:s');
    
    $stmt->bind_param("isss", $user_id, $date, $meal_type, $created_at);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Atualizar existente
        $stmt = $conn->prepare("
            UPDATE sf_meal_log 
            SET food_id = ?, quantity = ?, unit = ?, 
                calories = ?, protein = ?, carbs = ?, fat = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "iisddddi",
            $meal['food_id'],
            $meal['quantity'],
            $meal['unit'],
            $meal['calories'],
            $meal['protein'],
            $meal['carbs'],
            $meal['fat'],
            $existing['id']
        );
        
        $stmt->execute();
        $stmt->close();
        
        return ['id' => $existing['id']];
    } else {
        // Inserir novo
        $stmt = $conn->prepare("
            INSERT INTO sf_meal_log 
            (user_id, date, meal_type, food_id, quantity, unit, 
             calories, protein, carbs, fat, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issisddddss",
            $user_id,
            $date,
            $meal_type,
            $meal['food_id'],
            $meal['quantity'],
            $meal['unit'],
            $meal['calories'],
            $meal['protein'],
            $meal['carbs'],
            $meal['fat'],
            $created_at
        );
        
        $stmt->execute();
        $meal_id = $conn->insert_id;
        $stmt->close();
        
        return ['id' => $meal_id];
    }
}

function syncWeight($conn, $user_id, $weight) {
    $date = $weight['date'] ?? date('Y-m-d');
    $weight_kg = $weight['weight_kg'] ?? 0;
    
    // Verificar se já existe
    $stmt = $conn->prepare("
        SELECT id FROM sf_user_weight_history 
        WHERE user_id = ? AND date_recorded = ?
    ");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        // Atualizar
        $stmt = $conn->prepare("
            UPDATE sf_user_weight_history 
            SET weight_kg = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("di", $weight_kg, $existing['id']);
        $stmt->execute();
        $stmt->close();
        
        return ['id' => $existing['id']];
    } else {
        // Inserir
        $stmt = $conn->prepare("
            INSERT INTO sf_user_weight_history 
            (user_id, date_recorded, weight_kg)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("isd", $user_id, $date, $weight_kg);
        $stmt->execute();
        $weight_id = $conn->insert_id;
        $stmt->close();
        
        return ['id' => $weight_id];
    }
}

