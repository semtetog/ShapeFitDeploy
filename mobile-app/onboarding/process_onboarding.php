<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
// Mantenha estas linhas se quiser logar erros, mas não exibir na tela em produção
// ini_set('display_errors', 0); // Importante para produção
// ini_set('log_errors', 1);
// error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['onboarding_data'])) {
    error_log("CRITICAL: process_onboarding.php accessed without _SESSION['onboarding_data']");
    header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php");
    exit();
}

$data = $_SESSION['onboarding_data'];
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    error_log("CRITICAL: process_onboarding.php accessed without _SESSION['user_id']");
    header("Location: " . BASE_APP_URL . "/auth/login.php");
    exit();
}

$errors = [];
// ... (Sua lógica de validação completa, como antes) ...
// Exemplo:
$name = trim($data['name'] ?? '');
if (empty($name)) $errors[] = "Nome não pode ser vazio.";
// Adicione todas as suas validações aqui

// ... (validação para dob, gender, height_cm, weight_kg, objective, activity_level, etc.) ...
// Exemplo para DOB:
$dob = $data['dob'] ?? '';
if (empty($dob)) { $errors[] = "Data de nascimento está vazia."; }
else {
    $d = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$d || $d->format('Y-m-d') !== $dob || new DateTime() < $d) { 
        $errors[] = "Data de nascimento inválida ou futura. Recebido: " . htmlspecialchars($dob); 
    }
}
// (Certifique-se que $height_cm e $weight_kg são validados para serem numéricos e dentro dos ranges)
$height_cm = filter_var($data['height_cm'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 50, "max_range" => 300]]);
if ($height_cm === false || $height_cm === null) { // filter_var retorna false em falha, null se a flag FILTER_NULL_ON_FAILURE for usada
    $errors[] = "Altura inválida. Deve ser um número entre 50 e 300. Recebido: ".htmlspecialchars($data['height_cm'] ?? '');
}

$weight_kg_str = str_replace(',', '.', trim($data['weight_kg'] ?? ''));
$weight_kg = filter_var($weight_kg_str, FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 20, "max_range" => 500]]);
if ($weight_kg === false || $weight_kg === null) {
    $errors[] = "Peso inválido. Deve ser um número entre 20 e 500. Recebido: ".htmlspecialchars($data['weight_kg'] ?? '');
}

// (Continue com todas as suas validações)

if (!empty($errors)) {
    $_SESSION['onboarding_errors'] = $errors;
    error_log("Onboarding validation errors for user {$user_id}: " . implode(" | ", $errors));
    header("Location: " . BASE_APP_URL . "/onboarding/step3_personal_data.php?validation_failed=true"); // Redireciona para o último passo de input
    exit();
}

// Se passou na validação, pega os dados já validados e convertidos
$name = trim($data['name'] ?? '');
$uf = trim($data['uf'] ?? '');
$city = trim($data['city'] ?? '');
$phone_ddd = trim($data['phone_ddd'] ?? '');
$phone_number = trim($data['phone_number'] ?? '');
$dob = $data['dob'] ?? ''; // Já validado
$gender = $data['gender'] ?? ''; // Já validado
// $height_cm e $weight_kg já são do tipo correto após filter_var e validação
$objective = $data['objective'] ?? ''; // Já validado
$activity_level = $data['activity_level'] ?? ''; // Já validado
$bowel_movement = $data['bowel_movement'] ?? null;
$has_dietary_restrictions = $data['has_dietary_restrictions'] ?? false;
$selected_restrictions_ids = $data['selected_restrictions'] ?? [];
if ($has_dietary_restrictions && empty($selected_restrictions_ids)) { $has_dietary_restrictions = false; }
if (!$has_dietary_restrictions && !empty($selected_restrictions_ids)) { $selected_restrictions_ids = []; }


$conn->begin_transaction();
try {
    $stmt_users = $conn->prepare("UPDATE sf_users SET name = ?, uf = ?, city = ?, phone_ddd = ?, phone_number = ?, onboarding_complete = TRUE WHERE id = ?");
    if (!$stmt_users) throw new Exception("Prepare sf_users failed: " . $conn->error);
    $stmt_users->bind_param("sssssi", $name, $uf, $city, $phone_ddd, $phone_number, $user_id);
    if (!$stmt_users->execute()) throw new Exception("Execute sf_users failed: " . $stmt_users->error);
    $stmt_users->close();

    $stmt_profile = $conn->prepare("
        INSERT INTO sf_user_profiles (user_id, dob, gender, height_cm, weight_kg, objective, activity_level, bowel_movement, has_dietary_restrictions)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        dob = VALUES(dob), gender = VALUES(gender), height_cm = VALUES(height_cm), weight_kg = VALUES(weight_kg),
        objective = VALUES(objective), activity_level = VALUES(activity_level), bowel_movement = VALUES(bowel_movement),
        has_dietary_restrictions = VALUES(has_dietary_restrictions), updated_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt_profile) throw new Exception("Prepare sf_user_profiles failed: " . $conn->error);
    $has_restrictions_db = $has_dietary_restrictions ? 1 : 0;
    $stmt_profile->bind_param("issidsdsi", $user_id, $dob, $gender, $height_cm, $weight_kg, $objective, $activity_level, $bowel_movement, $has_restrictions_db);
    if (!$stmt_profile->execute()) throw new Exception("Execute sf_user_profiles failed: " . $stmt_profile->error);
    $stmt_profile->close();
    
    
   // =================================================================
//  INÍCIO DO CÓDIGO FINAL PARA SALVAR O PESO INICIAL NO HISTÓRICO
// =================================================================

// Log para depuração: confirma que o código está sendo alcançado com os dados certos.
error_log("Tentando salvar peso inicial para user_id: {$user_id} com peso: {$weight_kg}");

// Garante que temos um peso válido antes de salvar no histórico
if ($weight_kg !== null && is_numeric($weight_kg) && $weight_kg > 0) {
    
    $current_date_str = date('Y-m-d'); // Data do cadastro
    
    $stmt_log_initial_weight = $conn->prepare(
        "INSERT INTO sf_user_weight_history (user_id, weight_kg, date_recorded) VALUES (?, ?, ?)"
    );
    
    // VERIFICAÇÃO DE ERRO: Se a preparação da query falhar, lança uma exceção.
    if (!$stmt_log_initial_weight) {
        throw new Exception("Falha ao preparar a query para sf_user_weight_history: " . $conn->error);
    }
    
    $stmt_log_initial_weight->bind_param("ids", $user_id, $weight_kg, $current_date_str);
    
    // VERIFICAÇÃO DE ERRO: Se a execução falhar, lança uma exceção.
    if (!$stmt_log_initial_weight->execute()) {
        throw new Exception("Falha ao executar a inserção em sf_user_weight_history: " . $stmt_log_initial_weight->error);
    } else {
        // Log de sucesso
        error_log("Peso inicial salvo com sucesso para user_id: {$user_id}");
    }
    
    $stmt_log_initial_weight->close();
} else {
    // Log para depuração: informa por que o peso não foi salvo.
    error_log("Peso inicial para user_id: {$user_id} não foi salvo. Valor recebido: " . var_export($weight_kg, true));
}

// =================================================================
//  FIM DO NOVO BLOCO DE CÓDIGO
// =================================================================
    

    $stmt_delete_restrictions = $conn->prepare("DELETE FROM sf_user_selected_restrictions WHERE user_id = ?");
    if (!$stmt_delete_restrictions) throw new Exception("Prepare delete restrictions failed: " . $conn->error);
    $stmt_delete_restrictions->bind_param("i", $user_id);
    if (!$stmt_delete_restrictions->execute()) throw new Exception("Execute delete restrictions failed: " . $stmt_delete_restrictions->error);
    $stmt_delete_restrictions->close();

    if ($has_dietary_restrictions && !empty($selected_restrictions_ids)) {
        $stmt_insert_restriction = $conn->prepare("INSERT INTO sf_user_selected_restrictions (user_id, restriction_id) VALUES (?, ?)");
        if (!$stmt_insert_restriction) throw new Exception("Prepare insert restriction failed: " . $conn->error);
        foreach ($selected_restrictions_ids as $restriction_id) {
            $stmt_insert_restriction->bind_param("ii", $user_id, $restriction_id);
            if (!$stmt_insert_restriction->execute()) throw new Exception("Execute insert restriction failed for ID {$restriction_id}: " . $stmt_insert_restriction->error);
        }
        $stmt_insert_restriction->close();
    }

    $conn->commit();
    unset($_SESSION['onboarding_data']);
    unset($_SESSION['onboarding_errors']);

    header("Location: " . BASE_APP_URL . "/dashboard.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $detailedErrorMessage = "Onboarding processing error for user {$user_id}: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
    error_log($detailedErrorMessage);

    $_SESSION['onboarding_error_message'] = "Ocorreu um erro grave ao salvar seus dados. Por favor, tente o onboarding novamente.";
    // Se você quiser passar a mensagem de erro para a página de erro:
    // $_SESSION['onboarding_error_message'] = "Erro: " . htmlspecialchars($e->getMessage());
    header("Location: " . BASE_APP_URL . "/onboarding/step3_personal_data.php?processing_error_db=true");
    exit();
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
?>