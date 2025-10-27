<?php
// Arquivo: public_html/shapefit/includes/ajax_handler.php

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit();
}

$action = $_POST['action'] ?? null;
$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Ação desconhecida.', 'points_added' => 0];

// A verificação de CSRF Token é feita dentro de cada 'case'.

switch ($action) {
    
    
    
    
    
     // =======================================================
    //      NOVA LÓGICA PARA FAVORITAR/DESFAVORITAR
    // =======================================================
    case 'toggle_favorite':
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido.']); exit();
        }

        $recipe_id_fav = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
        if (!$recipe_id_fav) {
            $response['message'] = 'ID de receita inválido.';
            break;
        }

        // Verificar se já é favorito
        $stmt_check = $conn->prepare("SELECT recipe_id FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
        $stmt_check->bind_param("ii", $user_id, $recipe_id_fav);
        $stmt_check->execute();
        $is_currently_favorited = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if ($is_currently_favorited) {
            // Se já for, remover
            $stmt_toggle = $conn->prepare("DELETE FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
            $stmt_toggle->bind_param("ii", $user_id, $recipe_id_fav);
            if ($stmt_toggle->execute()) {
                $response = ['success' => true, 'message' => 'Receita removida dos favoritos.'];
            } else {
                $response['message'] = 'Erro ao remover dos favoritos.';
            }
            $stmt_toggle->close();
        } else {
            // Se não for, adicionar
            $stmt_toggle = $conn->prepare("INSERT INTO sf_user_favorite_recipes (user_id, recipe_id) VALUES (?, ?)");
            $stmt_toggle->bind_param("ii", $user_id, $recipe_id_fav);
            if ($stmt_toggle->execute()) {
                $response = ['success' => true, 'message' => 'Receita adicionada aos favoritos!'];
            } else {
                $response['message'] = 'Erro ao adicionar aos favoritos.';
            }
            $stmt_toggle->close();
        }
        break;

    // =======================================================

    

   case 'update_profile_details':
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido.']); exit();
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? 'other');
    $height_cm = filter_input(INPUT_POST, 'height_cm', FILTER_VALIDATE_INT);
    $weight_kg = filter_input(INPUT_POST, 'weight_kg', FILTER_VALIDATE_FLOAT);
    $objective = trim($_POST['objective'] ?? '');
    $activity_level = trim($_POST['activity_level'] ?? '');
    $bowel_movement = trim($_POST['bowel_movement'] ?? '');
    $restrictions = $_POST['restrictions'] ?? [];

    if (empty($full_name) || empty($dob) || empty($height_cm) || empty($weight_kg)) {
        $response['message'] = 'Por favor, preencha todos os campos obrigatórios.';
        break;
    }

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("UPDATE sf_users SET name = ? WHERE id = ?");
        $stmt1->bind_param("si", $full_name, $user_id);
        $stmt1->execute();
        $stmt1->close();
        
        
        
        // =============================================================
        //  ↓↓↓ ADICIONE O BLOCO DE CÓDIGO ABAIXO EXATAMENTE AQUI ↓↓↓
        // =============================================================

        // Se um novo peso foi enviado, registra no histórico também
        if ($weight_kg !== null && $weight_kg > 0) {
            $current_date_str = date('Y-m-d');
            $stmt_log_weight = $conn->prepare(
                "INSERT INTO sf_user_weight_history (user_id, weight_kg, date_recorded) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE weight_kg = VALUES(weight_kg)"
            );
            if ($stmt_log_weight) {
                $stmt_log_weight->bind_param("ids", $user_id, $weight_kg, $current_date_str);
                $stmt_log_weight->execute();
                $stmt_log_weight->close();
            }
        }

        // =============================================================
        //  ↑↑↑ FIM DO BLOCO A SER ADICIONADO ↑↑↑
        // =============================================================
        

        $stmt2 = $conn->prepare("UPDATE sf_user_profiles SET dob = ?, gender = ?, height_cm = ?, weight_kg = ?, objective = ?, activity_level = ?, bowel_movement = ?, has_dietary_restrictions = ? WHERE user_id = ?");
        $has_restrictions = !empty($restrictions) ? 1 : 0;
        
        // [CORREÇÃO FINALÍSSIMA]
        // A string de tipos correta é "ssidsssii" (9 letras para 9 variáveis)
        $stmt2->bind_param("ssidsssii", $dob, $gender, $height_cm, $weight_kg, $objective, $activity_level, $bowel_movement, $has_restrictions, $user_id);
        
        $stmt2->execute();
        $stmt2->close();

        $stmt3_del = $conn->prepare("DELETE FROM sf_user_selected_restrictions WHERE user_id = ?");
        $stmt3_del->bind_param("i", $user_id);
        $stmt3_del->execute();
        $stmt3_del->close();
        
        if ($has_restrictions) {
            $stmt3_ins = $conn->prepare("INSERT INTO sf_user_selected_restrictions (user_id, restriction_id) VALUES (?, ?)");
            foreach ($restrictions as $restriction_id) {
                $stmt3_ins->bind_param("ii", $user_id, $restriction_id);
                $stmt3_ins->execute();
            }
            $stmt3_ins->close();
        }

        addPointsToUser($conn, $user_id, 20, 'profile_details_updated');
        $conn->commit();
        $_SESSION['user_name'] = $full_name;
        $response = ['success' => true, 'message' => 'Perfil atualizado!', 'points_added' => 20];
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $response['message'] = 'Ocorreu um erro ao salvar o perfil.';
        error_log("AJAX update_profile_details failed for user {$user_id}: " . $e->getMessage());
    }
    break;
    
    
// --- BLOCO NOVO E CORRETO --- (VERIFIQUE SE ESTE CÓDIGO ESTÁ NO SEU FICHEIRO)
    case 'save_measurements':
        // 1. Validação de segurança (inalterado)
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']);
            exit();
        }
    
        // 2. Coleta e validação dos dados (inalterado)
        $date_recorded = trim($_POST['date_recorded'] ?? date('Y-m-d'));
        if (empty($date_recorded)) { $date_recorded = date('Y-m-d'); }
    
        $measurement_fields = ['weight_kg', 'neck', 'chest', 'waist', 'abdomen', 'hips', 'right_arm', 'left_arm', 'right_thigh', 'left_thigh', 'right_calf', 'left_calf'];
        $measurements = [];
        foreach ($measurement_fields as $field) {
            $value = filter_input(INPUT_POST, $field, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $measurements[$field] = ($value === false || $value === null) ? null : $value;
        }
    
        // 3. Processamento das fotos (inalterado)
        $photo_filenames = ['photo_front' => null, 'photo_side' => null, 'photo_back' => null];
        $upload_dir = APP_ROOT_PATH . '/assets/images/progress/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
    
        foreach ($photo_filenames as $key => &$filename) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$key];
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
                if (in_array(mime_content_type($file['tmp_name']), $allowed_mime_types) && $file['size'] <= 5 * 1024 * 1024) {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'user_' . $user_id . '_' . $key . '_' . time() . '.' . $extension;
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        // Sucesso
                    } else {
                        $filename = null; // Falha no upload
                    }
                }
            }
        }
        unset($filename);

        $conn->begin_transaction();
        try {
            // 4. Verificar se já existe um registro para esta data
            $stmt_check = $conn->prepare("SELECT * FROM sf_user_measurements WHERE user_id = ? AND date_recorded = ?");
            $stmt_check->bind_param("is", $user_id, $date_recorded);
            $stmt_check->execute();
            $existing_record = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($existing_record) {
                // 5. SE EXISTIR: FAZ UM UPDATE INTELIGENTE
                $final_photo_front = $photo_filenames['photo_front'] ?? $existing_record['photo_front'];
                $final_photo_side = $photo_filenames['photo_side'] ?? $existing_record['photo_side'];
                $final_photo_back = $photo_filenames['photo_back'] ?? $existing_record['photo_back'];

                $sql = "UPDATE sf_user_measurements SET 
                            weight_kg = ?, neck = ?, chest = ?, waist = ?, abdomen = ?, hips = ?, 
                            right_arm = ?, left_arm = ?, right_thigh = ?, left_thigh = ?, right_calf = ?, left_calf = ?,
                            photo_front = ?, photo_side = ?, photo_back = ?
                        WHERE user_id = ? AND date_recorded = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ddddddddddddsssis",
                    $measurements['weight_kg'], $measurements['neck'], $measurements['chest'], $measurements['waist'], $measurements['abdomen'], $measurements['hips'],
                    $measurements['right_arm'], $measurements['left_arm'], $measurements['right_thigh'], $measurements['left_thigh'], $measurements['right_calf'], $measurements['left_calf'],
                    $final_photo_front, $final_photo_side, $final_photo_back,
                    $user_id, $date_recorded
                );

            } else {
                // 6. SE NÃO EXISTIR: FAZ UM INSERT NORMAL
                $sql = "INSERT INTO sf_user_measurements 
                            (user_id, date_recorded, weight_kg, neck, chest, waist, abdomen, hips, right_arm, left_arm, right_thigh, left_thigh, right_calf, left_calf, photo_front, photo_side, photo_back)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isddddddddddddsss",
                    $user_id, $date_recorded,
                    $measurements['weight_kg'], $measurements['neck'], $measurements['chest'], $measurements['waist'], $measurements['abdomen'],
                    $measurements['hips'], $measurements['right_arm'], $measurements['left_arm'], $measurements['right_thigh'],
                    $measurements['left_thigh'], $measurements['right_calf'], $measurements['left_calf'],
                    $photo_filenames['photo_front'], $photo_filenames['photo_side'], $photo_filenames['photo_back']
                );
            }

            if ($stmt->execute()) {
                addPointsToUser($conn, $user_id, 30, 'measurements_updated');
                $conn->commit();
                $response = ['success' => true, 'message' => 'Progresso salvo com sucesso!', 'points_added' => 30];
            } else {
                throw new Exception("Erro ao executar a query: " . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            $response['message'] = 'Ocorreu um erro ao salvar as medidas.';
            error_log("AJAX save_measurements failed for user {$user_id}: " . $e->getMessage());
        }
        break;    
    

    case 'update_profile_picture':
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token de segurança inválido.']); exit();
        }

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array(mime_content_type($file['tmp_name']), $allowed_mime_types)) { $response['message'] = 'Formato de arquivo inválido. Use JPG, PNG ou WEBP.'; break; }
            if ($file['size'] > 5 * 1024 * 1024) { $response['message'] = 'O arquivo é muito grande (máximo 5MB).'; break; }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
            $upload_path = APP_ROOT_PATH . '/assets/images/users/' . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $stmt = $conn->prepare("UPDATE sf_user_profiles SET profile_image_filename = ? WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_filename, $user_id);
                    if ($stmt->execute()) {
                        addPointsToUser($conn, $user_id, 25, 'profile_picture_updated');
                        $response = ['success' => true, 'message' => 'Foto de perfil atualizada!', 'new_image_url' => BASE_ASSET_URL . '/assets/images/users/' . $new_filename, 'points_added' => 25];
                    } else { $response['message'] = 'Erro ao salvar no banco de dados.'; }
                    $stmt->close();
                }
            } else { $response['message'] = 'Erro ao fazer upload do arquivo.'; }
        } else { $response['message'] = 'Nenhum arquivo enviado ou erro no upload.'; }
        break;

    case 'update_routine':
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Token inválido.']); exit(); }
        $routine_id = filter_input(INPUT_POST, 'routine_id', FILTER_VALIDATE_INT); $status_input = $_POST['status'] ?? null;
        if ($routine_id) {
            if ($status_input !== null && in_array($status_input, ['0', '1'])) {
                $stmt = $conn->prepare("INSERT INTO sf_user_routine_log (user_id, routine_item_id, date, is_completed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_completed = VALUES(is_completed)");
                if ($stmt && $stmt->bind_param("iisi", $user_id, $routine_id, date('Y-m-d'), $status_input) && $stmt->execute()) {
                    $points_to_add_routine = 0;
                    if ($status_input == '1') {
                        addPointsToUser($conn, $user_id, 15, "routine_{$routine_id}_completed");
                        $points_to_add_routine = 15;
                    }
                    $response = ['success' => true, 'message' => 'Rotina atualizada.', 'points_added' => $points_to_add_routine];
                } else { http_response_code(500); $response['message'] = 'Erro ao salvar rotina.'; }
            } else {
                $stmt = $conn->prepare("DELETE FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?");
                if ($stmt && $stmt->bind_param("iis", $user_id, $routine_id, date('Y-m-d')) && $stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Rotina desmarcada.'];
                } else { http_response_code(500); $response['message'] = 'Erro ao desmarcar.'; }
            }
            if (isset($stmt)) $stmt->close();
        } else { http_response_code(400); $response['message'] = 'ID de rotina inválido.'; }
        break;
        


    default:
        http_response_code(400);
        break;
}

echo json_encode($response);
$conn->close();