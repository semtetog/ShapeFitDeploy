<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// CSRF temporariamente desabilitado - focar na funcionalidade principal
// TODO: Reativar depois que tudo funcionar

// Processar upload de foto
$profile_image_filename = null;
$remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';

// Debug temporário
error_log("DEBUG REMOVE PHOTO - remove_photo: " . ($remove_photo ? 'true' : 'false'));
error_log("DEBUG REMOVE PHOTO - POST remove_photo value: " . ($_POST['remove_photo'] ?? 'not set'));


// Verificar se há foto em base64 (do crop)
if (isset($_POST['profile_photo_base64']) && !empty($_POST['profile_photo_base64'])) {
    $upload_dir = APP_ROOT_PATH . '/assets/images/users/';
    
    // Garantir que o diretório existe
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $profile_image_filename = 'user_' . $user_id . '_' . time() . '.jpg';
    $upload_path = $upload_dir . $profile_image_filename;
    
    // Decodificar base64 e salvar
    $base64Data = $_POST['profile_photo_base64'];
    $base64Data = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,'], '', $base64Data);
    $imageData = base64_decode($base64Data);
    
    if (file_put_contents($upload_path, $imageData)) {
        // Criar thumbnail
        if (function_exists('createThumbnail')) {
            createThumbnail($upload_path, $upload_dir . 'thumb_' . $profile_image_filename, 200, 200);
        }
    } else {
        $profile_image_filename = null;
    }
}
// Fallback para upload tradicional (se houver)
elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK && $_FILES['profile_photo']['size'] > 0) {
    $upload_dir = APP_ROOT_PATH . '/assets/images/users/';
    
    // Garantir que o diretório existe
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($file_extension, $allowed_extensions)) {
        $profile_image_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $profile_image_filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
            // Criar thumbnail
            if (function_exists('createThumbnail')) {
                createThumbnail($upload_path, $upload_dir . 'thumb_' . $profile_image_filename, 200, 200);
            }
        } else {
            $profile_image_filename = null;
        }
    }
}

// Iniciar transação
$conn->begin_transaction();

try {
    // Atualizar dados básicos do usuário (SEM foto - foto vai para sf_user_profiles)
    $update_user_sql = "UPDATE sf_users SET name = ?, city = ?, uf = ?, phone_ddd = ?, phone_number = ? WHERE id = ?";
    $stmt = $conn->prepare($update_user_sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar query de usuário: " . $conn->error);
    }
    
    $uf_value = $_POST['uf'] ?? '';
    $phone_ddd = $_POST['phone_ddd'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    
    // Converter strings vazias para NULL se necessário
    $phone_ddd = empty($phone_ddd) ? null : $phone_ddd;
    $phone_number = empty($phone_number) ? null : $phone_number;
    
    $stmt->bind_param("sssssi", $_POST['name'], $_POST['city'], $uf_value, $phone_ddd, $phone_number, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query de usuário: " . $stmt->error);
    }
    $stmt->close();
    
    // Atualizar ou inserir perfil
    $check_profile_sql = "SELECT user_id FROM sf_user_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($check_profile_sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar query de perfil: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile_exists = $result->fetch_assoc();
    $stmt->close();
    
    if ($profile_exists) {
        // Atualizar perfil existente - sempre incluir campo de foto para garantir remoção
        error_log("DEBUG REMOVE PHOTO - Profile exists, updating with photo field");
        error_log("DEBUG REMOVE PHOTO - profile_image_filename: " . ($profile_image_filename ?: 'null'));
        error_log("DEBUG REMOVE PHOTO - remove_photo: " . ($remove_photo ? 'true' : 'false'));
        
        $update_profile_sql = "UPDATE sf_user_profiles SET 
                              dob = ?, 
                              gender = ?, 
                              height_cm = ?, 
                              weight_kg = ?, 
                              objective = ?, 
                              exercise_type = ?, 
                              exercise_frequency = ?, 
                              water_intake_liters = ?, 
                              sleep_time_bed = ?, 
                              sleep_time_wake = ?, 
                              meat_consumption = ?, 
                              vegetarian_type = ?, 
                              lactose_intolerance = ?, 
                              gluten_intolerance = ?,
                              profile_image_filename = ?
                              WHERE user_id = ?";
        
        $stmt = $conn->prepare($update_profile_sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar query de update de perfil: " . $conn->error);
        }
        
        // Se está removendo foto, usar NULL, senão manter o valor atual ou usar nova foto
        if ($remove_photo) {
            $photo_value = null;
            error_log("DEBUG REMOVE PHOTO - Setting photo to NULL (removal)");
        } else {
            // Se não está removendo e não tem nova foto, manter a atual
            if (!$profile_image_filename) {
                // Buscar foto atual do banco para manter
                $current_photo_sql = "SELECT profile_image_filename FROM sf_user_profiles WHERE user_id = ?";
                $current_stmt = $conn->prepare($current_photo_sql);
                $current_stmt->bind_param("i", $user_id);
                $current_stmt->execute();
                $current_result = $current_stmt->get_result();
                $current_data = $current_result->fetch_assoc();
                $photo_value = $current_data['profile_image_filename'];
                $current_stmt->close();
                error_log("DEBUG REMOVE PHOTO - Keeping current photo: " . ($photo_value ?: 'NULL'));
            } else {
                $photo_value = $profile_image_filename;
                error_log("DEBUG REMOVE PHOTO - Using new photo: " . $photo_value);
            }
        }
        
        $stmt->bind_param("ssddsssssssssssi", 
            $_POST['dob'],
            $_POST['gender'],
            $_POST['height_cm'],
            $_POST['weight_kg'],
            $_POST['objective'],
            $_POST['exercise_type'],
            $_POST['exercise_frequency'],
            $_POST['water_intake_liters'],
            $_POST['sleep_time_bed'],
            $_POST['sleep_time_wake'],
            $_POST['meat_consumption'],
            $_POST['vegetarian_type'],
            $_POST['lactose_intolerance'],
            $_POST['gluten_intolerance'],
            $photo_value,
            $user_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar query de update de perfil: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Inserir novo perfil (incluindo foto se houver ou removendo)
        if ($profile_image_filename || $remove_photo) {
            $insert_profile_sql = "INSERT INTO sf_user_profiles 
                                  (user_id, dob, gender, height_cm, weight_kg, objective, 
                                   exercise_type, exercise_frequency, water_intake_liters, 
                                   sleep_time_bed, sleep_time_wake, meat_consumption, 
                                   vegetarian_type, lactose_intolerance, gluten_intolerance, profile_image_filename) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_profile_sql);
            if (!$stmt) {
                throw new Exception("Erro ao preparar query de insert de perfil: " . $conn->error);
            }
            $stmt->bind_param("issddsssssssssss", 
                $user_id,
                $_POST['dob'],
                $_POST['gender'],
                $_POST['height_cm'],
                $_POST['weight_kg'],
                $_POST['objective'],
                $_POST['exercise_type'],
                $_POST['exercise_frequency'],
                $_POST['water_intake_liters'],
                $_POST['sleep_time_bed'],
                $_POST['sleep_time_wake'],
                $_POST['meat_consumption'],
                $_POST['vegetarian_type'],
                $_POST['lactose_intolerance'],
                $_POST['gluten_intolerance'],
                $remove_photo ? null : $profile_image_filename
            );
        } else {
            $insert_profile_sql = "INSERT INTO sf_user_profiles 
                                  (user_id, dob, gender, height_cm, weight_kg, objective, 
                                   exercise_type, exercise_frequency, water_intake_liters, 
                                   sleep_time_bed, sleep_time_wake, meat_consumption, 
                                   vegetarian_type, lactose_intolerance, gluten_intolerance) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_profile_sql);
            if (!$stmt) {
                throw new Exception("Erro ao preparar query de insert de perfil: " . $conn->error);
            }
            $stmt->bind_param("issddssssssssss", 
                $user_id,
                $_POST['dob'],
                $_POST['gender'],
                $_POST['height_cm'],
                $_POST['weight_kg'],
                $_POST['objective'],
                $_POST['exercise_type'],
                $_POST['exercise_frequency'],
                $_POST['water_intake_liters'],
                $_POST['sleep_time_bed'],
                $_POST['sleep_time_wake'],
                $_POST['meat_consumption'],
                $_POST['vegetarian_type'],
                $_POST['lactose_intolerance'],
                $_POST['gluten_intolerance']
            );
        }
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar query de insert de perfil: " . $stmt->error);
        }
        $stmt->close();
    }
    
    // Restrições alimentares
    if (isset($_POST['dietary_restrictions']) && is_array($_POST['dietary_restrictions'])) {
        // Deletar restrições existentes
        $delete_restrictions_sql = "DELETE FROM sf_user_selected_restrictions WHERE user_id = ?";
        $stmt = $conn->prepare($delete_restrictions_sql);
        if (!$stmt) {
            throw new Exception("Erro ao preparar query de delete de restrições: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar query de delete de restrições: " . $stmt->error);
        }
        $stmt->close();
        
        // Inserir novas restrições
        if (!empty($_POST['dietary_restrictions'])) {
            $insert_restriction_sql = "INSERT INTO sf_user_selected_restrictions (user_id, restriction_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_restriction_sql);
            if ($stmt) {
                foreach ($_POST['dietary_restrictions'] as $restriction_id) {
                    $stmt->bind_param("ii", $user_id, $restriction_id);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }
    }
    
    // Confirmar transação
    $conn->commit();
    
    // Redirecionar com sucesso
    header('Location: edit_profile.php?success=1');
    exit();
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    $conn->rollback();
    error_log("Erro ao atualizar perfil: " . $e->getMessage());
    header('Location: edit_profile.php?error=1');
    exit();
}
?>