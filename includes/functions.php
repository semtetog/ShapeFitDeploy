<?php
// Arquivo: public_html/includes/functions.php (VERSÃO FINAL E COMPLETA)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

/**
 * Função auxiliar para verificar se uma atividade de onboarding foi concluída.
 */
function hasCompletedOnboardingActivity($conn, $user_id, $activity_name, $date) {
    // CORREÇÃO 2: AGORA VERIFICA NA TABELA CORRETA (sf_user_onboarding_completion)
    $stmt = $conn->prepare("SELECT 1 FROM sf_user_onboarding_completion WHERE user_id = ? AND activity_name = ? AND completion_date = ?");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $activity_name, $date);
        $stmt->execute();
        $result = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $result;
    }
    return false;
}


function getRoutineItemsForUser($conn, $user_id, $date, $user_profile) {
    $all_missions = [];

    // --- PARTE 1: BUSCAR MISSÕES PERSONALIZADAS DO USUÁRIO ---
    $sql_personal = "
        SELECT 
            uri.id, uri.title, uri.icon_class, uri.is_exercise, uri.exercise_type,
            CASE WHEN url.id IS NOT NULL AND url.is_completed = 1 THEN 1 ELSE 0 END AS completion_status,
            CASE 
                WHEN url.id IS NOT NULL AND url.is_completed = 1 AND uri.exercise_type = 'sleep' THEN udt.sleep_hours
                WHEN url.id IS NOT NULL AND url.is_completed = 1 AND uri.exercise_type = 'duration' THEN ued.duration_minutes
                ELSE NULL
            END as duration_minutes
        FROM sf_user_routine_items uri
        LEFT JOIN sf_user_routine_log url 
            ON uri.id = url.routine_item_id AND url.user_id = ? AND url.date = ?
        LEFT JOIN sf_user_daily_tracking udt ON udt.user_id = ? AND udt.date = ?
        LEFT JOIN sf_user_exercise_durations ued ON ued.user_id = ? AND ued.exercise_name COLLATE utf8mb4_unicode_ci = uri.title COLLATE utf8mb4_unicode_ci
        WHERE uri.user_id = ?
    ";
    
    $stmt_personal = $conn->prepare($sql_personal);
    if ($stmt_personal) {
        $stmt_personal->bind_param("isisii", $user_id, $date, $user_id, $date, $user_id, $user_id);
        $stmt_personal->execute();
        $result = $stmt_personal->get_result();
        while ($row = $result->fetch_assoc()) {
            $all_missions[] = $row;
        }
        $stmt_personal->close();
    }

    // --- PARTE 2: GERAR MISSÕES DINÂMICAS DE ATIVIDADE FÍSICA ---
    if (isset($user_profile['exercise_type']) && is_string($user_profile['exercise_type']) && !empty(trim($user_profile['exercise_type']))) {
        
        $activities_string = trim($user_profile['exercise_type']);
        $user_activities = preg_split('/,\s*/', $activities_string, -1, PREG_SPLIT_NO_EMPTY);
        
        if (!empty($user_activities)) {
            foreach ($user_activities as $activity) {
                $clean_activity = trim($activity);
                
                // Buscar duração se atividade foi completada
                $duration_minutes = null;
                if (hasCompletedOnboardingActivity($conn, $user_id, $clean_activity, $date)) {
                    $stmt_duration = $conn->prepare("SELECT ued.duration_minutes FROM sf_user_exercise_durations ued WHERE ued.user_id = ? AND ued.exercise_name = ?");
                    if ($stmt_duration) {
                        $stmt_duration->bind_param("is", $user_id, $clean_activity);
                        $stmt_duration->execute();
                        $result_duration = $stmt_duration->get_result();
                        if ($row_duration = $result_duration->fetch_assoc()) {
                            $duration_minutes = $row_duration['duration_minutes'];
                        }
                        $stmt_duration->close();
                    }
                }
                
                $all_missions[] = [
                    // CORREÇÃO 1: MUDANÇA DO PREFIXO DO ID PARA 'onboarding_' e SEM underscores
                    'id' => 'onboarding_' . htmlspecialchars($clean_activity),
                    'title' => htmlspecialchars($clean_activity),
                    'icon_class' => 'fas fa-dumbbell',
                    'completion_status' => hasCompletedOnboardingActivity($conn, $user_id, $clean_activity, $date) ? 1 : 0,
                    'duration_minutes' => $duration_minutes
                ];
            }
        }
    }

    return $all_missions;
}

function getMealSuggestions(mysqli $conn): array {
    $current_hour = (int)date('G');
    $meal_info = ['db_param' => 'dinner', 'display_name' => 'Jantar', 'category_name' => 'Jantar', 'greeting' => 'Hora do Jantar!', 'category_id' => 0];
    if ($current_hour >= 5 && $current_hour < 10) { $meal_info = ['db_param' => 'breakfast', 'display_name' => 'Café da Manhã', 'category_name' => 'Café da Manhã', 'greeting' => 'Bom dia!', 'category_id' => 0]; } 
    elseif ($current_hour >= 10 && $current_hour < 12) { $meal_info = ['db_param' => 'morning_snack', 'display_name' => 'Lanche da Manhã', 'category_name' => 'Lanche', 'greeting' => 'Hora do Lanche!', 'category_id' => 0]; } 
    elseif ($current_hour >= 12 && $current_hour < 15) { $meal_info = ['db_param' => 'lunch', 'display_name' => 'Almoço', 'category_name' => 'Almoço', 'greeting' => 'Hora do Almoço!', 'category_id' => 0]; } 
    elseif ($current_hour >= 15 && $current_hour < 18) { $meal_info = ['db_param' => 'afternoon_snack', 'display_name' => 'Lanche da Tarde', 'category_name' => 'Lanche', 'greeting' => 'Hora do Lanche!', 'category_id' => 0]; }
    $recipes = [];
    $sql_attempt_1 = "SELECT r.id, r.name, r.image_filename, r.kcal_per_serving, c.id as category_id FROM sf_recipes r JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id JOIN sf_categories c ON rhc.category_id = c.id WHERE c.name = ? AND r.is_public = 1 ORDER BY RAND() LIMIT 5";
    $stmt = $conn->prepare($sql_attempt_1);
    if ($stmt) {
        $stmt->bind_param("s", $meal_info['category_name']);
        $stmt->execute();
        $result = $stmt->get_result();
        $first_row = true;
        while ($row = $result->fetch_assoc()) {
            if ($first_row) { $meal_info['category_id'] = $row['category_id']; $first_row = false; }
            unset($row['category_id']);
            $recipes[] = $row;
        }
        $stmt->close();
    }
    if (empty($recipes)) {
        $sql_fallback = "SELECT id, name, image_filename, kcal_per_serving FROM sf_recipes WHERE is_public = 1 ORDER BY RAND() LIMIT 5";
        $result_fallback = $conn->query($sql_fallback);
        if ($result_fallback) { while ($row = $result_fallback->fetch_assoc()) { $recipes[] = $row; } }
    }
    $meal_info['recipes'] = $recipes;
    return $meal_info;
}

function getUserProfileData(mysqli $conn, int $user_id): ?array {
    $sql = "SELECT u.*, p.* FROM sf_users u LEFT JOIN sf_user_profiles p ON u.id = p.user_id WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed in getUserProfileData: " . $conn->error); return null; }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) { error_log("Execute failed in getUserProfileData: " . $stmt->error); $stmt->close(); return null; }
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data;
}

function calculateTargetDailyCalories(string $gender, float $weight_kg, int $height_cm, int $age_years, string $exercise_frequency_key, string $objective_key): int {
    // Passo 1: Calcular IMC
    $height_m = $height_cm / 100;
    $imc = $weight_kg / ($height_m * $height_m);
    
    // Mapear frequência de exercício para fatores de atividade
    $activity_factors = [
        'sedentary' => 1.1,      // sedentário a 1x na semana
        '1_2x_week' => 1.3,      // treino até 3x
        '3_4x_week' => 1.6,      // treino 3 a 5x
        '5_6x_week' => 1.7,      // treino 5 a 7x
        '6_7x_week' => 1.7,      // treino 5 a 7x
        '7plus_week' => 1.7     // treino 5 a 7x
    ];
    $activity_factor = $activity_factors[$exercise_frequency_key] ?? 1.1;
    
    $tmb = 0;
    $get = 0;
    
    // Escolher fórmula baseada no IMC e gênero
    if ($imc > 30) {
        // Fórmula de Mifflin para homens e mulheres com IMC acima de 30
        if (strtolower($gender) == 'male') {
            $tmb = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age_years) + 5;
        } else {
            $tmb = (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age_years) - 161;
        }
    } elseif (strtolower($gender) == 'female' && $imc <= 30) {
        // Fórmula de Harris-Benedict para mulheres com IMC abaixo de 30
        $tmb = 447.593 + (9.247 * $weight_kg) + (3.098 * $height_cm) - (4.330 * $age_years);
    } elseif (strtolower($gender) == 'male' && $imc <= 30) {
        // Fórmula de Tinsley para homens com IMC abaixo de 30
        $tmb = (24.8 * $weight_kg) + 10;
    }
    
    if ($tmb <= 0) { return 2000; }
    
    // Calcular GET (Gasto Energético Total)
    $get = $tmb * $activity_factor;
    
    // Ajustar baseado no objetivo
    switch (strtolower($objective_key)) {
        case 'lose_fat':
            if (strtolower($gender) == 'male') {
                $get -= 700; // Homem: subtrai 700
            } else {
                $get -= 500; // Mulher: subtrai 500
            }
            break;
        case 'gain_muscle':
            if (strtolower($gender) == 'male') {
                $get += 500; // Homem: adiciona 500
            } else {
                $get += 300; // Mulher: adiciona 300
            }
            break;
        case 'maintain':
        case 'maintain_weight':
            // Manter o resultado da última conta (sem ajuste)
            break;
    }
    
    // Valores mínimos de segurança
    $min_calories = (strtolower($gender) == 'male') ? 1500 : 1200;
    return (int)round(max($min_calories, $get));
}

function create_thumbnail($source_path, $destination_path, $thumb_size = 200) {
    if (!file_exists($source_path)) { return false; }
    list($width, $height, $type) = getimagesize($source_path);
    if ($width == 0 || $height == 0) return false;
    $image_resource = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $image_resource = imagecreatefromjpeg($source_path); break;
        case IMAGETYPE_PNG: $image_resource = imagecreatefrompng($source_path); break;
        case IMAGETYPE_WEBP: $image_resource = imagecreatefromwebp($source_path); break;
        default: return false;
    }
    if (!$image_resource) return false;
    $thumb_resource = imagecreatetruecolor($thumb_size, $thumb_size);
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($thumb_resource, false);
        imagesavealpha($thumb_resource, true);
        $transparent = imagecolorallocatealpha($thumb_resource, 255, 255, 255, 127);
        imagefilledrectangle($thumb_resource, 0, 0, $thumb_size, $thumb_size, $transparent);
    }
    $src_x = 0; $src_y = 0;
    if ($width > $height) { $src_x = ($width - $height) / 2; $width = $height; } 
    elseif ($height > $width) { $src_y = ($height - $width) / 2; $height = $width; }
    imagecopyresampled($thumb_resource, $image_resource, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $width, $height);
    $success = false;
    $extension = strtolower(pathinfo($destination_path, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpeg': case 'jpg': $success = imagejpeg($thumb_resource, $destination_path, 85); break;
        case 'png': $success = imagepng($thumb_resource, $destination_path, 7); break;
        case 'webp': $success = imagewebp($thumb_resource, $destination_path, 85); break;
    }
    imagedestroy($image_resource);
    imagedestroy($thumb_resource);
    return $success;
}

function addPointsToUser(mysqli $conn, int $user_id, float $points_to_add, string $reason): bool {
    if ($points_to_add == 0) { return true; }
    $stmt = $conn->prepare("UPDATE sf_users SET points = points + ? WHERE id = ?");
    if (!$stmt) { error_log("Erro ao preparar addPointsToUser: " . $conn->error); return false; }
    $stmt->bind_param("di", $points_to_add, $user_id);
    $success = $stmt->execute();
    if (!$success) { error_log("Erro ao executar addPointsToUser para user {$user_id} (Motivo: {$reason}): " . $stmt->error); }
    $stmt->close();
    return $success;
}

function removeAccents(string $string): string {
    $unwanted_array = ['Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r'];
    return strtr($string, $unwanted_array);
}

function calculateAge(string $dob_string): int { if (empty($dob_string)) { return 0; } try { $dob = new DateTime($dob_string); $now = new DateTime(); return ($dob > $now) ? 0 : (int)$now->diff($dob)->y; } catch (Exception $e) { return 0; } }

function calculateMacronutrients(int $total_calories, string $objective_key): array {
    $protein_perc = 0.30; $carbs_perc = 0.40; $fat_perc = 0.30;
    switch (strtolower($objective_key)) {
        case 'lose_fat': $protein_perc = 0.40; $carbs_perc = 0.30; $fat_perc = 0.30; break;
        case 'gain_muscle': $protein_perc = 0.30; $carbs_perc = 0.45; $fat_perc = 0.25; break;
    }
    $macros = ['protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
    if ($total_calories <= 0) { return $macros; }
    $macros['protein_g'] = (int)round(($total_calories * $protein_perc) / 4);
    $macros['carbs_g'] = (int)round(($total_calories * $carbs_perc) / 4);
    $macros['fat_g'] = (int)round(($total_calories * $fat_perc) / 9);
    return $macros;
}

function getWaterIntakeSuggestion(float $weight_kg, int $mls_per_kg = 45, int $cup_size_ml = 250): array {
    if ($weight_kg <= 0) { return ['total_ml' => 2000, 'cups' => 8, 'cup_size_ml' => 250]; }
    $total_ml = $weight_kg * $mls_per_kg;
    $cups = ceil($total_ml / $cup_size_ml);
    return ['total_ml' => (int) round($total_ml), 'cups' => (int) $cups, 'cup_size_ml' => $cup_size_ml];
}

function getDailyTrackingRecord($conn, $user_id, $date) {
    $stmt_find = $conn->prepare("SELECT * FROM sf_user_daily_tracking WHERE user_id = ? AND date = ?");
    if (!$stmt_find) { error_log("getDailyTrackingRecord Error - Prepare SELECT failed: " . $conn->error); return null; }
    $stmt_find->bind_param("is", $user_id, $date);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    $tracking_record = $result->fetch_assoc();
    $stmt_find->close();
    if ($tracking_record) { return $tracking_record; } 
    else {
        $stmt_create = $conn->prepare("INSERT INTO sf_user_daily_tracking (user_id, date) VALUES (?, ?)");
        if (!$stmt_create) { error_log("getDailyTrackingRecord Error - Prepare INSERT failed: " . $conn->error); return null; }
        $stmt_create->bind_param("is", $user_id, $date);
        if ($stmt_create->execute()) {
            $stmt_create->close();
            return ['id' => $conn->insert_id, 'user_id' => $user_id, 'date' => $date, 'kcal_consumed' => 0, 'protein_consumed_g' => 0.00, 'carbs_consumed_g' => 0.00, 'fat_consumed_g' => 0.00, 'water_consumed_cups' => 0];
        } else { error_log("getDailyTrackingRecord Error - Execute INSERT failed: " . $stmt_create->error); $stmt_create->close(); return null; }
    }
}

function generateSlug(string $text): string { $text = preg_replace('~[^\pL\d]+~u', '-', $text); $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); $text = preg_replace('~[^-\w]+~', '', $text); $text = trim($text, '-'); $text = preg_replace('~-+~', '-', $text); $text = strtolower($text); if (empty($text)) { return 'n-a-' . substr(md5(uniqid(rand(), true)), 0, 6); } return $text; }

function calculateIMC(float $weight_kg, int $height_cm): float {
    if ($height_cm <= 0) return 0;
    $height_m = $height_cm / 100;
    return round($weight_kg / ($height_m * $height_m), 1);
}

function getIMCCategory(float $imc): string {
    if ($imc < 18.5) return 'Abaixo do peso';
    if ($imc < 25) return 'Peso Ideal';
    if ($imc < 30) return 'Sobrepeso';
    return 'Obesidade';
}

/**
 * Gera HTML para avatar do usuário (foto ou iniciais)
 */
function getUserAvatarHtml($user, $size = 'medium') {
    $sizes = [
        'small' => '20px',
        'medium' => '24px', 
        'large' => '32px'
    ];
    
    $avatar_size = $sizes[$size] ?? $sizes['medium'];
    
    // Verificar se o usuário tem foto de perfil (usando a mesma lógica do users.php)
    $has_photo = false;
    $avatar_url = '';

    if (!empty($user['profile_image_filename'])) {
        $thumb_filename = 'thumb_' . $user['profile_image_filename'];
        $thumb_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
        
        // Prioridade 1: A thumbnail existe?
        if (file_exists($thumb_path_on_server)) {
            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
            $has_photo = true;
        } else {
            // Prioridade 2: Se a thumb não existe, a imagem original existe?
            $original_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'];
            if (file_exists($original_path_on_server)) {
                $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']);
                $has_photo = true;
            }
        }
    } else {
        // Tentar buscar por ID (fallback)
        $id_path_jpg = APP_ROOT_PATH . '/assets/images/users/' . $user['id'] . '.jpg';
        $id_path_webp = APP_ROOT_PATH . '/assets/images/users/' . $user['id'] . '.webp';
        $id_path_png = APP_ROOT_PATH . '/assets/images/users/' . $user['id'] . '.png';
        
        if (file_exists($id_path_webp)) {
            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . $user['id'] . '.webp';
            $has_photo = true;
        } elseif (file_exists($id_path_png)) {
            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . $user['id'] . '.png';
            $has_photo = true;
        } elseif (file_exists($id_path_jpg)) {
            $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . $user['id'] . '.jpg';
            $has_photo = true;
        }
    }
    
    if ($has_photo) {
        return '<img src="' . $avatar_url . '" alt="' . htmlspecialchars($user['name']) . '" style="width: ' . $avatar_size . '; height: ' . $avatar_size . '; border-radius: 50%; object-fit: cover;">';
    } else {
        // SE NÃO TEM FOTO, GERA AS INICIAIS (usando a mesma lógica do users.php)
        $name_parts = explode(' ', trim($user['name']));
        $initials = '';
        if (count($name_parts) > 1) {
            $initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
        } elseif (!empty($name_parts[0])) {
            $initials = strtoupper(substr($name_parts[0], 0, 2));
        } else {
            $initials = '??';
        }
        $bgColor = '#' . substr(md5($user['name']), 0, 6);
        
        return '<div style="width: ' . $avatar_size . '; height: ' . $avatar_size . '; border-radius: 50%; background-color: ' . $bgColor . '; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: ' . (intval($avatar_size) * 0.4) . 'px;">' . $initials . '</div>';
    }
}

/**
 * Atualiza a pontuação de um usuário em todos os desafios ativos dos quais ele participa.
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @param int $user_id O ID do usuário que realizou a ação.
 * @param string $action_type O tipo de ação realizada (ex: 'mission_complete', 'water_goal').
 */
function updateChallengePoints($conn, $user_id, $action_type) {
    // TEMPORÁRIO: Tabela sf_challenge_rules não existe - desabilitado
    return;
    
    // 1. Encontrar as regras e os desafios ativos para este usuário e esta ação
    $sql = "SELECT 
                cr.challenge_id, 
                cr.points_awarded
            FROM sf_challenge_rules cr
            JOIN sf_challenge_participants cp ON cr.challenge_id = cp.challenge_id
            JOIN sf_challenges c ON cr.challenge_id = c.id
            WHERE cp.user_id = ? 
              AND cr.action_type = ?
              AND c.status = 'active'";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar a query de atualização de pontos de desafio: " . $conn->error);
        return;
    }
    $stmt->bind_param("is", $user_id, $action_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($rules)) {
        return; // Nenhuma regra encontrada para esta ação em desafios ativos
    }

    // 2. Para cada regra encontrada, atualizar a pontuação do usuário
    foreach ($rules as $rule) {
        $challenge_id = $rule['challenge_id'];
        $points_to_add = $rule['points_awarded'];

        // Usar INSERT ... ON DUPLICATE KEY UPDATE para criar ou atualizar a pontuação
        $update_sql = "INSERT INTO sf_challenge_scores (challenge_id, user_id, score)
                       VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE score = score + ?";
        
        $update_stmt = $conn->prepare($update_sql);
        if (!$update_stmt) {
            error_log("Erro ao preparar a query de INSERT/UPDATE de score: " . $conn->error);
            continue; // Pula para a próxima regra
        }
        $update_stmt->bind_param("iiii", $challenge_id, $user_id, $points_to_add, $points_to_add);
        $update_stmt->execute();
        $update_stmt->close();

        // 3. Registrar a ação no histórico (opcional, para auditoria)
        $action_sql = "INSERT INTO sf_challenge_actions (challenge_id, user_id, action_type, points_awarded) VALUES (?, ?, ?, ?)";
        $action_stmt = $conn->prepare($action_sql);
        if ($action_stmt) {
            $action_stmt->bind_param("iisi", $challenge_id, $user_id, $action_type, $points_to_add);
            $action_stmt->execute();
            $action_stmt->close();
        }
    }
}

/**
 * Busca os desafios ativos de um usuário
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @param int $user_id O ID do usuário.
 * @return array Array com os desafios do usuário.
 */
function getUserActiveChallenges($conn, $user_id) {
    $sql = "SELECT c.id, c.name, c.description, c.start_date, c.end_date, c.status, c.reward_badge
            FROM sf_challenges c
            JOIN sf_challenge_participants cp ON c.id = cp.challenge_id
            WHERE cp.user_id = ? AND c.status IN ('active', 'scheduled')
            ORDER BY c.start_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $challenges = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $challenges;
}

/**
 * Busca o ranking de um desafio
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @param int $challenge_id O ID do desafio.
 * @param int $limit Limite de resultados (padrão: 50).
 * @return array Array com o ranking.
 */
function getChallengeRanking($conn, $challenge_id, $limit = 50) {
    $sql = "SELECT u.id, u.name, up.profile_image_filename, cs.score
            FROM sf_challenge_scores cs
            JOIN sf_users u ON cs.user_id = u.id
            LEFT JOIN sf_user_profiles up ON cs.user_id = up.user_id
            WHERE cs.challenge_id = ?
            ORDER BY cs.score DESC, u.name ASC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $challenge_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $ranking = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $ranking;
}

/**
 * Busca as regras de pontuação de um desafio
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @param int $challenge_id O ID do desafio.
 * @return array Array com as regras do desafio.
 */
function getChallengeRules($conn, $challenge_id) {
    $sql = "SELECT action_type, points_awarded FROM sf_challenge_rules WHERE challenge_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rules = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $rules;
}

?>