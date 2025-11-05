<?php
/**
 * API para CRUD de Rotina - Versão com Debug Detalhado
 */

// Ativar logs de erro
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Log inicial
error_log('=== ROUTINE_CRUD.PHP INICIADO ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
error_log('QUERY_STRING: ' . ($_SERVER['QUERY_STRING'] ?? 'N/A'));

require_once __DIR__ . '/../../includes/config.php';

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');

// TEMPORARIAMENTE REMOVER VERIFICAÇÃO DE AUTENTICAÇÃO PARA FAZER FUNCIONAR
error_log('PULANDO VERIFICAÇÃO DE AUTENTICAÇÃO - MODO DEBUG');

// Verificar se há erro fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro fatal no servidor: ' . $error['message']]);
    }
});

// Obter ação e patient_id (de GET ou POST JSON)
$action = $_GET['action'] ?? '';
$post_data = null;

// Se não tem no GET, tentar pegar do POST JSON
if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $post_data = json_decode($raw_input, true);
    $action = $post_data['action'] ?? '';
}

$patient_id = intval($_GET['patient_id'] ?? 0);

error_log('Ação: ' . $action);
error_log('Patient ID inicial (GET): ' . $patient_id);
error_log('GET params: ' . print_r($_GET, true));
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);

// Validar que a ação foi fornecida
if (empty($action)) {
    error_log('ERRO: Ação não fornecida');
    error_log('GET params: ' . print_r($_GET, true));
    if ($post_data !== null) {
        error_log('POST data: ' . print_r($post_data, true));
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação não fornecida']);
    exit;
}

// Obter patient_id de POST também se necessário
if (!empty($post_data['patient_id'])) {
    $patient_id = intval($post_data['patient_id']);
    error_log('Patient ID atualizado (POST): ' . $patient_id);
}

// Ações que NÃO requerem patient_id
$actions_no_patient = ['get_mission'];

// Ações de missões que requerem patient_id
$patient_missions_actions = ['list_missions', 'list', 'create_mission', 'update_mission', 'delete_mission', 'check_sleep_mission'];

if (in_array($action, $patient_missions_actions) && !$patient_id) {
    error_log('ERRO: ID do paciente é obrigatório para ação: ' . $action);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do paciente é obrigatório']);
    exit;
}

// Conexão com o banco
try {
    error_log('Tentando conectar ao banco...');
    error_log('DB_HOST: ' . DB_HOST);
    error_log('DB_USER: ' . DB_USER);
    error_log('DB_NAME: ' . DB_NAME);
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('ERRO de conexão: ' . $conn->connect_error);
        throw new Exception('Erro de conexão: ' . $conn->connect_error);
    }
    error_log('Conexão com banco bem-sucedida');
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    error_log('EXCEÇÃO na conexão: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Processar ação
try {
    switch ($action) {
        case 'list_missions':
        case 'list':
            // Buscar missões personalizadas do usuário, excluindo missão de sono (não é editável)
            $missions = [];
            $sql_personal = "SELECT id, title, icon_class, description, is_exercise, exercise_type 
                             FROM sf_user_routine_items 
                             WHERE user_id = ?
                             AND NOT (IFNULL(exercise_type, '') = 'sleep' OR LOWER(title) LIKE '%sono%')
                             ORDER BY id DESC";
            $stmt = $conn->prepare($sql_personal);
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['is_personal'] = 1;
                $missions[] = $row;
            }
            $stmt->close();
            
            // Buscar exercícios dinâmicos do usuário (do onboarding/edit_profile)
            $sql_profile = "SELECT exercise_type FROM sf_user_profiles WHERE user_id = ?";
            $stmt_profile = $conn->prepare($sql_profile);
            if ($stmt_profile) {
                $stmt_profile->bind_param('i', $patient_id);
                $stmt_profile->execute();
                $result_profile = $stmt_profile->get_result();
                $profile_data = $result_profile->fetch_assoc();
                $stmt_profile->close();
                
                // Se o usuário tem exercícios definidos, adicionar como missões dinâmicas
                if ($profile_data && !empty(trim($profile_data['exercise_type'] ?? ''))) {
                    $activities_string = trim($profile_data['exercise_type']);
                    $user_activities = preg_split('/,\s*/', $activities_string, -1, PREG_SPLIT_NO_EMPTY);
                    
                    foreach ($user_activities as $activity) {
                        $clean_activity = trim($activity);
                        $missions[] = [
                            'id' => 'onboarding_' . $clean_activity,
                            'title' => $clean_activity,
                            'icon_class' => 'fa-dumbbell',
                            'description' => null,
                            'is_exercise' => 1,
                            'exercise_type' => 'duration',
                            'is_personal' => 0  // Não é personalizada (não pode ser editada)
                        ];
                    }
                }
            }
            
            echo json_encode(['success' => true, 'data' => $missions]);
            break;
            
        case 'list_exercises':
            error_log('Executando list_exercises...');
            $sql = "SELECT id, activity_name, completion_date 
                    FROM sf_user_onboarding_completion 
                    WHERE user_id = ? 
                    ORDER BY completion_date DESC";
            
            error_log('SQL: ' . $sql);
            error_log('Patient ID para bind: ' . $patient_id);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('ERRO ao preparar statement: ' . $conn->error);
                throw new Exception('Erro ao preparar statement: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $exercises = [];
            while ($row = $result->fetch_assoc()) {
                $exercises[] = $row;
            }
            
            error_log('Exercícios encontrados: ' . count($exercises));
            error_log('Dados dos exercícios: ' . print_r($exercises, true));
            
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $exercises]);
            break;
            
        case 'check_sleep_mission':
            error_log('Executando check_sleep_mission para patient_id: ' . $patient_id);
            
            // Verificar se já existe uma missão de sono para este usuário
            // Uma missão é considerada "sono" se exercise_type = 'sleep' OU se o título contém "sono"
            $sql = "SELECT id, title, exercise_type 
                    FROM sf_user_routine_items 
                    WHERE user_id = ? 
                    AND (exercise_type = 'sleep' OR LOWER(title) LIKE '%sono%')
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $has_sleep = $result->num_rows > 0;
            
            if ($has_sleep) {
                $sleep_mission = $result->fetch_assoc();
                error_log('Missão de sono encontrada: ID ' . $sleep_mission['id']);
            } else {
                error_log('Nenhuma missão de sono encontrada');
            }
            
            $stmt->close();
            echo json_encode([
                'success' => true, 
                'has_sleep_mission' => $has_sleep,
                'sleep_mission_id' => $has_sleep ? intval($sleep_mission['id']) : null
            ]);
            break;
            
        case 'get_mission':
            $id_raw = $_GET['id'] ?? $post_data['id'] ?? '';
            if (!$id_raw) {
                throw new Exception('ID da missão é obrigatório');
            }
            
            // Se é uma missão dinâmica (onboarding_), buscar do perfil do usuário
            if (strpos($id_raw, 'onboarding_') === 0) {
                $activity_name = str_replace('onboarding_', '', $id_raw);
                
                // Buscar o perfil do usuário
                $sql_profile = "SELECT exercise_type FROM sf_user_profiles WHERE user_id = ?";
                $stmt_profile = $conn->prepare($sql_profile);
                if ($stmt_profile) {
                    $stmt_profile->bind_param('i', $patient_id);
                    $stmt_profile->execute();
                    $result_profile = $stmt_profile->get_result();
                    $profile_data = $result_profile->fetch_assoc();
                    $stmt_profile->close();
                    
                    // Verificar se a atividade existe no perfil
                    if ($profile_data && !empty(trim($profile_data['exercise_type'] ?? ''))) {
                        $activities_string = trim($profile_data['exercise_type']);
                        $user_activities = preg_split('/,\s*/', $activities_string, -1, PREG_SPLIT_NO_EMPTY);
                        
                        if (in_array($activity_name, $user_activities)) {
                            $mission_data = [
                                'id' => $id_raw,
                                'title' => $activity_name,
                                'icon_class' => 'fa-dumbbell',
                                'description' => null,
                                'is_exercise' => 1,
                                'exercise_type' => 'duration'
                            ];
                            echo json_encode(['success' => true, 'data' => $mission_data]);
                            break;
                        }
                    }
                }
                throw new Exception('Missão dinâmica não encontrada');
            }
            
            // Buscar missão personalizada
            $id = intval($id_raw);
            $sql = "SELECT id, title, icon_class, description, is_exercise, exercise_type
                    FROM sf_user_routine_items 
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar statement: ' . $conn->error);
            }
            
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                $stmt->close();
                throw new Exception('Missão não encontrada');
            }
            break;
            
        case 'get_exercise':
            error_log('Executando get_exercise...');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            $sql = "SELECT id, activity_name, completion_date 
                    FROM sf_user_onboarding_completion 
                    WHERE id = ? AND user_id = ?";
            
            error_log('SQL: ' . $sql);
            error_log('ID do exercício: ' . $id);
            error_log('Patient ID: ' . $patient_id);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('ERRO ao preparar statement: ' . $conn->error);
                throw new Exception('Erro ao preparar statement: ' . $conn->error);
            }
            
            $stmt->bind_param('ii', $id, $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                error_log('Exercício encontrado: ' . print_r($row, true));
                $stmt->close();
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                error_log('Exercício não encontrado');
                $stmt->close();
                throw new Exception('Exercício não encontrado');
            }
            break;
            
        case 'create_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            // Usar $post_data se já foi lido, senão ler novamente
            if ($post_data === null) {
                $data = json_decode(file_get_contents('php://input'), true);
            } else {
                $data = $post_data;
            }
            
            if (!$data || empty($data['title'])) {
                throw new Exception('Título da missão é obrigatório');
            }
            
            $title = $conn->real_escape_string(trim($data['title']));
            $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : '';
            $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
            $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
            $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : '';
            
            // Verificar se está tentando criar uma missão de sono (bloquear criação)
            $is_sleep = ($exercise_type === 'sleep' || stripos($title, 'sono') !== false);
            
            if ($is_sleep) {
                throw new Exception('Não é possível criar missões de sono. A missão de sono é gerenciada automaticamente pelo sistema.');
            }
            
            // Todas as missões agora são personalizadas
            $stmt = $conn->prepare("INSERT INTO sf_user_routine_items 
                                   (user_id, title, icon_class, description, is_exercise, exercise_type) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssis', $patient_id, $title, $icon_class, $description, $is_exercise, $exercise_type);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Missão criada com sucesso']);
            } else {
                throw new Exception('Erro ao criar missão: ' . $conn->error);
            }
            $stmt->close();
            break;
            
        case 'create_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['activity_name'])) {
                throw new Exception('Nome do exercício é obrigatório');
            }
            
            $activity_name = $conn->real_escape_string(trim($data['activity_name']));
            $completion_date = $data['completion_date'] ?? date('Y-m-d');
            
            $stmt = $conn->prepare("INSERT INTO sf_user_onboarding_completion (user_id, activity_name, completion_date) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $patient_id, $activity_name, $completion_date);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Exercício criado com sucesso']);
            } else {
                throw new Exception('Erro ao criar exercício: ' . $conn->error);
            }
            $stmt->close();
            break;
            
        case 'update_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            // Usar $post_data se já foi lido, senão ler novamente
            if ($post_data === null) {
                $data = json_decode(file_get_contents('php://input'), true);
            } else {
                $data = $post_data;
            }
            
            if (!$data || empty($data['id']) || empty($data['title'])) {
                throw new Exception('ID e título da missão são obrigatórios');
            }
            
            $id_raw = $data['id'];
            
            // Se é uma missão dinâmica (onboarding_), criar/atualizar como missão real no banco
            if (strpos($id_raw, 'onboarding_') === 0) {
                $old_activity = str_replace('onboarding_', '', $id_raw);
                $new_activity = trim($data['title']);
                $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-dumbbell');
                $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : '';
                $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
                $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : '';
                
                // Verificar se já existe uma missão de exercício com esse título (do exercício antigo)
                // Isso garante que se já existe uma missão "Musculação" no banco, ela será atualizada
                $check_existing = $conn->prepare("SELECT id FROM sf_user_routine_items WHERE user_id = ? AND title = ? AND is_exercise = 1 LIMIT 1");
                $check_existing->bind_param('is', $patient_id, $old_activity);
                $check_existing->execute();
                $existing_result = $check_existing->get_result();
                $existing_mission = $existing_result->fetch_assoc();
                $check_existing->close();
                
                // Se não encontrou com o título antigo, verificar se existe com o novo título (caso o título não tenha mudado)
                if (!$existing_mission && $old_activity === $new_activity) {
                    $check_existing2 = $conn->prepare("SELECT id FROM sf_user_routine_items WHERE user_id = ? AND title = ? AND is_exercise = 1 LIMIT 1");
                    $check_existing2->bind_param('is', $patient_id, $new_activity);
                    $check_existing2->execute();
                    $existing_result2 = $check_existing2->get_result();
                    $existing_mission = $existing_result2->fetch_assoc();
                    $check_existing2->close();
                }
                
                if ($existing_mission) {
                    // Se já existe, atualizar incluindo o ícone
                    $update_stmt = $conn->prepare("UPDATE sf_user_routine_items 
                                                   SET title = ?, icon_class = ?, description = ?, is_exercise = ?, exercise_type = ?
                                                   WHERE id = ? AND user_id = ?");
                    $update_stmt->bind_param('sssisii', $new_activity, $icon_class, $description, $is_exercise, $exercise_type, $existing_mission['id'], $patient_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Se não existe, criar uma nova missão no banco
                    $insert_stmt = $conn->prepare("INSERT INTO sf_user_routine_items 
                                                  (user_id, title, icon_class, description, is_exercise, exercise_type) 
                                                  VALUES (?, ?, ?, ?, ?, ?)");
                    $insert_stmt->bind_param('isssis', $patient_id, $new_activity, $icon_class, $description, $is_exercise, $exercise_type);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                
                // Atualizar o perfil do usuário também (nome do exercício)
                $sql_profile = "SELECT exercise_type FROM sf_user_profiles WHERE user_id = ?";
                $stmt_profile = $conn->prepare($sql_profile);
                if ($stmt_profile) {
                    $stmt_profile->bind_param('i', $patient_id);
                    $stmt_profile->execute();
                    $result_profile = $stmt_profile->get_result();
                    $profile_data = $result_profile->fetch_assoc();
                    $stmt_profile->close();
                    
                    if ($profile_data && !empty(trim($profile_data['exercise_type'] ?? ''))) {
                        $activities_string = trim($profile_data['exercise_type']);
                        $user_activities = preg_split('/,\s*/', $activities_string, -1, PREG_SPLIT_NO_EMPTY);
                        
                        // Substituir o nome antigo pelo novo
                        if (in_array($old_activity, $user_activities)) {
                            $key = array_search($old_activity, $user_activities);
                            $user_activities[$key] = $new_activity;
                            $new_activities_string = implode(', ', $user_activities);
                            
                            // Atualizar o perfil
                            $update_sql = "UPDATE sf_user_profiles SET exercise_type = ? WHERE user_id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param('si', $new_activities_string, $patient_id);
                                $update_stmt->execute();
                                $update_stmt->close();
                            }
                        }
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Missão atualizada com sucesso']);
                break;
            }
            
            $id = intval($id_raw);
            
            // Verificar se é uma missão de sono (não pode ser editada)
            $check_sleep = $conn->prepare("SELECT id, title, exercise_type FROM sf_user_routine_items WHERE id = ? AND user_id = ?");
            $check_sleep->bind_param('ii', $id, $patient_id);
            $check_sleep->execute();
            $sleep_check_result = $check_sleep->get_result();
            $check_sleep->close();
            
            if ($sleep_check_result->num_rows > 0) {
                $current_mission = $sleep_check_result->fetch_assoc();
                $title_lower = strtolower($current_mission['title'] ?? '');
                $exercise_type = $current_mission['exercise_type'] ?? '';
                
                // Se é uma missão de sono, bloquear edição
                if (stripos($title_lower, 'sono') !== false || $exercise_type === 'sleep') {
                    throw new Exception('A missão de sono não pode ser editada. Ela é gerenciada automaticamente pelo sistema.');
                }
            }
            
            $title = $conn->real_escape_string(trim($data['title']));
            $description = isset($data['description']) ? $conn->real_escape_string(trim($data['description'])) : '';
            $icon_class = $conn->real_escape_string($data['icon_class'] ?? 'fa-check-circle');
            $is_exercise = isset($data['is_exercise']) ? intval($data['is_exercise']) : 0;
            $exercise_type = isset($data['exercise_type']) ? $conn->real_escape_string($data['exercise_type']) : '';
            
            // Verificar se está tentando alterar para uma missão de sono
            $is_sleep = ($exercise_type === 'sleep' || stripos($title, 'sono') !== false);
            
            if ($is_sleep) {
                throw new Exception('Não é possível criar ou editar missões de sono. A missão de sono é gerenciada automaticamente pelo sistema.');
            }
            
            // Todas as missões agora são personalizadas
            $stmt = $conn->prepare("UPDATE sf_user_routine_items 
                                   SET title = ?, icon_class = ?, description = ?, is_exercise = ?, exercise_type = ?
                                   WHERE id = ? AND user_id = ?");
            $stmt->bind_param('sssisii', $title, $icon_class, $description, $is_exercise, $exercise_type, $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Missão atualizada com sucesso']);
            } else {
                throw new Exception('Missão não encontrada ou não pode ser editada');
            }
            $stmt->close();
            break;
            
        case 'update_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id']) || empty($data['activity_name'])) {
                throw new Exception('ID e nome do exercício são obrigatórios');
            }
            
            $id = intval($data['id']);
            $activity_name = $conn->real_escape_string(trim($data['activity_name']));
            $completion_date = $data['completion_date'] ?? date('Y-m-d');
            
            $stmt = $conn->prepare("UPDATE sf_user_onboarding_completion 
                                   SET activity_name = ?, completion_date = ?
                                   WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ssii', $activity_name, $completion_date, $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Exercício atualizado com sucesso']);
            } else {
                throw new Exception('Exercício não encontrado ou não pode ser editado');
            }
            $stmt->close();
            break;
            
        case 'delete_mission':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            // Usar $post_data se já foi lido, senão ler novamente
            if ($post_data === null) {
                $data = json_decode(file_get_contents('php://input'), true);
            } else {
                $data = $post_data;
            }
            
            if (!$data || empty($data['id'])) {
                throw new Exception('ID da missão é obrigatório');
            }
            
            $id = intval($data['id']);
            
            // Verificar se é uma missão de sono (não pode ser excluída)
            $check_sleep = $conn->prepare("SELECT id, title, exercise_type FROM sf_user_routine_items WHERE id = ? AND user_id = ?");
            $check_sleep->bind_param('ii', $id, $patient_id);
            $check_sleep->execute();
            $sleep_check_result = $check_sleep->get_result();
            $check_sleep->close();
            
            if ($sleep_check_result->num_rows > 0) {
                $current_mission = $sleep_check_result->fetch_assoc();
                $title_lower = strtolower($current_mission['title'] ?? '');
                $exercise_type = $current_mission['exercise_type'] ?? '';
                
                // Se é uma missão de sono, bloquear exclusão
                if (stripos($title_lower, 'sono') !== false || $exercise_type === 'sleep') {
                    throw new Exception('A missão de sono não pode ser excluída. Ela é gerenciada automaticamente pelo sistema.');
                }
            }
            
            // Excluir logs primeiro
            $stmt1 = $conn->prepare("DELETE FROM sf_user_routine_log WHERE routine_item_id = ? AND user_id = ?");
            $stmt1->bind_param('ii', $id, $patient_id);
            $stmt1->execute();
            $stmt1->close();
            
            // Deletar missão personalizada
            $stmt2 = $conn->prepare("DELETE FROM sf_user_routine_items WHERE id = ? AND user_id = ?");
            $stmt2->bind_param('ii', $id, $patient_id);
            
            if ($stmt2->execute() && $stmt2->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Missão excluída com sucesso']);
            } else {
                throw new Exception('Missão não encontrada ou não pode ser excluída');
            }
            $stmt2->close();
            break;
            
        case 'delete_exercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id'])) {
                throw new Exception('ID do exercício é obrigatório');
            }
            
            $id = intval($data['id']);
            
            $stmt = $conn->prepare("DELETE FROM sf_user_onboarding_completion WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $id, $patient_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Exercício excluído com sucesso']);
            } else {
                throw new Exception('Exercício não encontrado ou não pode ser excluído');
            }
            $stmt->close();
            break;
            
        default:
            throw new Exception('Ação não reconhecida: ' . $action);
    }
} catch (Exception $e) {
    error_log('EXCEÇÃO capturada: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    error_log('Fechando conexão...');
    $conn->close();
    error_log('=== ROUTINE_CRUD.PHP FINALIZADO ===');
}
?>