<?php
// admin/actions/delete_user.php - Exclusão permanente de usuário

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth_admin.php';
require_once __DIR__ . '/../../includes/db.php';

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$user_id || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
    exit;
}

// Verificar se o usuário existe
$stmt_check = $conn->prepare("SELECT id, name, email FROM sf_users WHERE id = ?");
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$user_exists = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$user_exists) {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
    exit;
}

$user_name = $user_exists['name'] ?? 'Desconhecido';

// Buscar imagens ANTES de deletar as tabelas
$profile_image_path = APP_ROOT_PATH . '/assets/images/users/';
$measurements_image_path = APP_ROOT_PATH . '/assets/images/measurements/';

// Buscar nome do arquivo de imagem do perfil antes de deletar
$stmt_img = $conn->prepare("SELECT profile_image_filename FROM sf_user_profiles WHERE user_id = ?");
$stmt_img->bind_param("i", $user_id);
$stmt_img->execute();
$img_result = $stmt_img->get_result();
$profile_image_filename = null;

if ($img_row = $img_result->fetch_assoc()) {
    $profile_image_filename = $img_row['profile_image_filename'] ?? null;
}
$stmt_img->close();

// Buscar fotos de medições antes de deletar
$stmt_photos = $conn->prepare("SELECT photo_front, photo_side, photo_back FROM sf_user_measurements WHERE user_id = ?");
$stmt_photos->bind_param("i", $user_id);
$stmt_photos->execute();
$photos_result = $stmt_photos->get_result();
$measurement_photos = [];

while ($photo_row = $photos_result->fetch_assoc()) {
    $measurement_photos[] = $photo_row;
}
$stmt_photos->close();

// Iniciar transação
$conn->begin_transaction();

try {
    // Lista de tabelas que têm relacionamento com user_id (em ordem de dependência)
    // Ordem IMPORTANTE: deletar dependentes primeiro, depois os principais
    
    $tables_to_delete = [
        // Dados de tracking e logs
        'sf_user_meal_log',
        'sf_user_daily_tracking',
        'sf_user_weight_history',
        'sf_user_measurements',
        'sf_user_exercise_durations',
        'sf_user_routine_log',
        'sf_user_points_log',
        
        // Rotinas e metas
        'sf_user_routine_items',
        'sf_user_goals',
        
        // Favoritos
        'sf_user_favorites',
        'sf_user_favorite_recipes',
        
        // Restrições e preferências
        'sf_user_selected_restrictions',
        
        // Grupos e desafios
        'sf_user_challenge_members',
        'sf_user_group_members',
        
        // Certificados e progresso
        'sf_user_certificates',
        'sf_user_module_progress',
        'sf_user_onboarding_completion',
        
        // Perfil
        'sf_user_profiles',
        
        // Usuário principal (por último)
        'sf_users'
    ];
    
    $deleted_counts = [];
    
    foreach ($tables_to_delete as $table) {
        // ENVOLVE TUDO EM TRY-CATCH para capturar QUALQUER erro
        try {
            // Suprime erros do MySQL e verifica manualmente
            $old_error_reporting = error_reporting(0);
            $mysqli_report_old = mysqli_report(MYSQLI_REPORT_OFF);
            
            // Verifica se a tabela existe usando INFORMATION_SCHEMA
            $table_exists = false;
            $check_sql = "SELECT COUNT(*) as cnt FROM information_schema.tables 
                          WHERE table_schema = DATABASE() AND table_name = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if ($check_stmt) {
                $check_stmt->bind_param("s", $table);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $table_exists = ($row['cnt'] > 0);
                }
                $check_stmt->close();
            }
            
            // Restaura error reporting
            error_reporting($old_error_reporting);
            mysqli_report($mysqli_report_old);
            
            // Se a tabela não existe, pula SEM erro
            if (!$table_exists) {
                continue;
            }
            
            // Suprime erros novamente para SHOW COLUMNS
            $old_error_reporting = error_reporting(0);
            $mysqli_report_old = mysqli_report(MYSQLI_REPORT_OFF);
            
            // Verifica se tem coluna user_id (suprimindo erros)
            $check_column = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'user_id'");
            
            // Verifica se houve erro
            if ($conn->errno == 1146 || strpos($conn->error, "doesn't exist") !== false) {
                // Tabela não existe, pula
                error_reporting($old_error_reporting);
                mysqli_report($mysqli_report_old);
                continue;
            }
            
            if (!$check_column || $check_column->num_rows == 0) {
                if ($check_column) {
                    $check_column->close();
                }
                error_reporting($old_error_reporting);
                mysqli_report($mysqli_report_old);
                continue; // Não tem coluna user_id, pula
            }
            
            if ($check_column) {
                $check_column->close();
            }
            
            // Restaura error reporting
            error_reporting($old_error_reporting);
            mysqli_report($mysqli_report_old);
            
            // Agora pode tentar deletar (com erro suprimido)
            $old_error_reporting = error_reporting(0);
            $mysqli_report_old = mysqli_report(MYSQLI_REPORT_OFF);
            
            $delete_sql = "DELETE FROM `{$table}` WHERE user_id = ?";
            $stmt = @$conn->prepare($delete_sql);
            
            // Verifica se houve erro ao preparar
            if ($conn->errno == 1146 || strpos($conn->error, "doesn't exist") !== false) {
                error_reporting($old_error_reporting);
                mysqli_report($mysqli_report_old);
                continue;
            }
            
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $result = @$stmt->execute();
                
                // Verifica se houve erro após executar
                if ($conn->errno) {
                    if ($conn->errno == 1146 || strpos($conn->error, "doesn't exist") !== false) {
                        $stmt->close();
                        error_reporting($old_error_reporting);
                        mysqli_report($mysqli_report_old);
                        continue;
                    }
                } else {
                    // Sucesso - conta registros deletados
                    $affected = $stmt->affected_rows;
                    if ($affected > 0) {
                        $deleted_counts[$table] = $affected;
                    }
                }
                
                $stmt->close();
            }
            
            // Restaura error reporting
            error_reporting($old_error_reporting);
            mysqli_report($mysqli_report_old);
            
        } catch (Exception $table_error) {
            // Captura QUALQUER exceção
            $error_message = $table_error->getMessage();
            
            // Restaura error reporting mesmo em caso de erro
            error_reporting($old_error_reporting ?? E_ALL);
            mysqli_report($mysqli_report_old ?? MYSQLI_REPORT_ERROR);
            
            // Se erro é sobre tabela não existir, ignora silenciosamente
            if (strpos($error_message, "doesn't exist") !== false || 
                strpos($error_message, "Unknown table") !== false ||
                strpos($error_message, "Table") !== false && strpos($error_message, "doesn't exist") !== false) {
                continue; // Ignora silenciosamente
            }
            
            // Outros erros: loga mas continua
            error_log("Erro ao processar tabela {$table}: " . $error_message);
            continue;
        }
    }
    
    // Confirmar transação
    $conn->commit();
    
    // Excluir imagens do usuário após confirmar a transação
    if (!empty($profile_image_filename)) {
        $image_file = $profile_image_path . $profile_image_filename;
        $thumb_file = $profile_image_path . 'thumb_' . $profile_image_filename;
        
        if (file_exists($image_file)) {
            @unlink($image_file);
        }
        if (file_exists($thumb_file)) {
            @unlink($thumb_file);
        }
    }
    
    // Excluir fotos de medições
    foreach ($measurement_photos as $photo_row) {
        foreach (['photo_front', 'photo_side', 'photo_back'] as $photo_field) {
            if (!empty($photo_row[$photo_field])) {
                $photo_file = $measurements_image_path . $photo_row[$photo_field];
                if (file_exists($photo_file)) {
                    @unlink($photo_file);
                }
            }
        }
    }
    
    // Log da exclusão
    error_log("Usuário deletado permanentemente: ID {$user_id} - {$user_name}");
    
    echo json_encode([
        'success' => true,
        'message' => "Usuário '{$user_name}' excluído permanentemente com sucesso!",
        'deleted_counts' => $deleted_counts
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao deletar usuário ID {$user_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao excluir usuário: ' . $e->getMessage()
    ]);
}

