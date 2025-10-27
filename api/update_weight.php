<?php

header("Access-Control-Allow-Origin: https://localhost"); 
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Arquivo: api/update_weight.php
// VERSÃO FINAL COM TRAVA SEMANAL E LIMITE DE VARIAÇÃO
define('IS_AJAX_REQUEST', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

// --- Validação de Segurança e Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); 
    exit(json_encode(['status' => 'error', 'message' => 'Acesso negado.']));
}

$user_id = $_SESSION['user_id'];
$new_weight_str = $_POST['weight'] ?? '0';
$new_weight = (float) str_replace(',', '.', $new_weight_str);

if ($new_weight <= 20 || $new_weight > 300) {
    http_response_code(400); 
    exit(json_encode(['status' => 'error', 'message' => 'Peso inválido. Insira um valor realista.']));
}

try {
    $conn->begin_transaction();

    // --- LÓGICA DE VERIFICAÇÃO DUPLA ---
    
    // 1. BUSCA O ÚLTIMO PESO E A ÚLTIMA DATA REGISTRADOS
    $stmt_check = $conn->prepare("SELECT weight_kg, date_recorded FROM sf_user_weight_history WHERE user_id = ? ORDER BY date_recorded DESC LIMIT 1");
    if (!$stmt_check) { throw new Exception("Erro SQL ao buscar histórico de peso."); }
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $last_log = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($last_log) {
        $last_weight = (float)$last_log['weight_kg'];
        $last_log_date = new DateTime($last_log['date_recorded']);
        $today = new DateTime('today');

        // VERIFICAÇÃO A: TRAVA DE 7 DIAS
        if ($last_log_date->format('Y-m-d') !== $today->format('Y-m-d')) {
            $interval_days = $last_log_date->diff($today)->days;
            if ($interval_days < 7) {
                $days_remaining = 7 - $interval_days;
                $plural = ($days_remaining > 1) ? 'dias' : 'dia';
                $message = "Você só pode registrar um novo peso a cada 7 dias. Próximo registro em {$days_remaining} {$plural}.";
                http_response_code(429);
                exit(json_encode(['status' => 'error', 'message' => $message]));
            }
        }
        
        // =================================================================
        // VERIFICAÇÃO B: LIMITE DE VARIAÇÃO DE PESO (NOVA LÓGICA)
        // =================================================================
        if ($last_weight > 0) {
            $max_variation_percentage = 10.0; // Limite de 10% de variação (pode ajustar)
            $difference = abs($new_weight - $last_weight);
            $percentage_change = ($difference / $last_weight) * 100;

            if ($percentage_change > $max_variation_percentage) {
                $message = "Variação de peso muito grande. Por favor, insira um valor mais próximo do seu peso anterior (" . number_format($last_weight, 1, ',', '.') . " kg).";
                http_response_code(400); // Bad Request, pois o valor é irreal
                exit(json_encode(['status' => 'error', 'message' => $message]));
            }
        }
        // =================================================================
    }
    // Se não houver último log, ambas as verificações são puladas (primeiro registro)

    // --- ATUALIZAÇÃO DO PERFIL E HISTÓRICO ---
    // (O código a partir daqui permanece o mesmo, pois as verificações já passaram)
    $stmt_update_profile = $conn->prepare("UPDATE sf_user_profiles SET weight_kg = ? WHERE user_id = ?");
    if (!$stmt_update_profile) { throw new Exception("Erro SQL (update profile)"); }
    $stmt_update_profile->bind_param("di", $new_weight, $user_id);
    $stmt_update_profile->execute();
    $stmt_update_profile->close();
    
    $current_date_str = date('Y-m-d');
    $stmt_log_weight = $conn->prepare("INSERT INTO sf_user_weight_history (user_id, weight_kg, date_recorded) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)");
    if (!$stmt_log_weight) { throw new Exception("Erro SQL (insert history)"); }
    $stmt_log_weight->bind_param("ids", $user_id, $new_weight, $current_date_str);
    $stmt_log_weight->execute();
    $stmt_log_weight->close();

    $conn->commit();

    // --- RESPOSTA DE SUCESSO ---
    echo json_encode([
        'status' => 'success',
        'message' => 'Peso atualizado com sucesso!'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("ERRO FATAL EM UPDATE_WEIGHT.PHP: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ocorreu um erro inesperado no servidor.']);
}

$conn->close();
?>