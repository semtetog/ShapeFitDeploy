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
/**
 * Sincroniza dados do tracking diário para desafios ativos do usuário
 * Esta função copia dados de sf_user_daily_tracking para sf_challenge_group_daily_progress
 */
function syncChallengeGroupProgress($conn, $user_id, $date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Buscar dados do tracking diário do usuário
    $daily_tracking = getDailyTrackingRecord($conn, $user_id, $date);
    if (!$daily_tracking) {
        return false;
    }
    
    // Buscar desafios ativos do usuário
    $stmt = $conn->prepare("
        SELECT cg.id, cg.goals, cg.start_date, cg.end_date
        FROM sf_challenge_groups cg
        INNER JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
        WHERE cgm.user_id = ? 
          AND cg.status = 'active'
          AND cg.start_date <= ?
          AND cg.end_date >= ?
    ");
    $stmt->bind_param("iss", $user_id, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $challenges = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($challenges)) {
        return false;
    }
    
    // Converter dados do tracking para formato dos desafios
    $calories_consumed = (float)($daily_tracking['kcal_consumed'] ?? 0);
    $water_cups = (int)($daily_tracking['water_consumed_cups'] ?? 0);
    $water_ml = $water_cups * 250; // Converter copos para ml
    $exercise_minutes = 0;
    
    // Calcular minutos de exercício (workout + cardio)
    $workout_hours = (float)($daily_tracking['workout_hours'] ?? 0);
    $cardio_hours = (float)($daily_tracking['cardio_hours'] ?? 0);
    $exercise_minutes = (int)(($workout_hours + $cardio_hours) * 60);
    
    // Também verificar exercícios completados nas rotinas
    $stmt_exercise = $conn->prepare("
        SELECT SUM(COALESCE(url.exercise_duration_minutes, 0)) as total_minutes
        FROM sf_user_routine_log url
        INNER JOIN sf_user_routine_items uri ON url.routine_item_id = uri.id
        WHERE url.user_id = ? 
          AND url.date = ?
          AND url.is_completed = 1
          AND uri.is_exercise = 1
          AND uri.exercise_type = 'duration'
    ");
    $stmt_exercise->bind_param("is", $user_id, $date);
    $stmt_exercise->execute();
    $exercise_result = $stmt_exercise->get_result();
    if ($exercise_row = $exercise_result->fetch_assoc()) {
        $exercise_minutes += (int)($exercise_row['total_minutes'] ?? 0);
    }
    $stmt_exercise->close();
    
    $sleep_hours = (float)($daily_tracking['sleep_hours'] ?? 0);
    $steps_count = (int)($daily_tracking['steps_daily'] ?? 0);
    
    // Para cada desafio, atualizar progresso e calcular pontos
    foreach ($challenges as $challenge) {
        $challenge_id = $challenge['id'];
        $goals = json_decode($challenge['goals'] ?? '[]', true);
        
        // Inserir ou atualizar progresso diário
        $stmt_progress = $conn->prepare("
            INSERT INTO sf_challenge_group_daily_progress 
            (challenge_group_id, user_id, date, calories_consumed, water_ml, exercise_minutes, sleep_hours, steps_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                calories_consumed = VALUES(calories_consumed),
                water_ml = VALUES(water_ml),
                exercise_minutes = VALUES(exercise_minutes),
                sleep_hours = VALUES(sleep_hours),
                steps_count = VALUES(steps_count)
        ");
        $stmt_progress->bind_param("iisddidi", 
            $challenge_id, 
            $user_id, 
            $date,
            $calories_consumed,
            $water_ml,
            $exercise_minutes,
            $sleep_hours,
            $steps_count
        );
        $stmt_progress->execute();
        $stmt_progress->close();
        
        // Calcular e atualizar pontos
        calculateAndUpdateChallengePoints($conn, $challenge_id, $user_id, $date, $goals);
    }
    
    return true;
}

/**
 * Calcula multiplicador baseado na data (fins de semana, feriados, etc)
 */
function getChallengePointsMultiplier($date) {
    $date_obj = new DateTime($date);
    $day_of_week = (int)$date_obj->format('w'); // 0 = Domingo, 6 = Sábado
    
    // Multiplicador 2x para fins de semana
    if ($day_of_week == 0 || $day_of_week == 6) {
        return 2.0;
    }
    
    // Multiplicador padrão
    return 1.0;
}

/**
 * Configuração de pontos por tipo de meta
 */
function getChallengePointsConfig() {
    return [
        'calories' => [
            'points_per_percent' => 2, // 2 pontos por cada 10% da meta
            'percent_increment' => 10, // Incremento de 10%
            'bonus_completion' => 25, // Bônus por atingir 100%
            'label' => 'Calorias'
        ],
        'water' => [
            'points_per_unit' => 1, // 1 ponto por copo (250ml)
            'unit_size' => 250, // Tamanho da unidade em ml
            'bonus_completion' => 20, // Bônus por atingir 100%
            'label' => 'Água'
        ],
        'exercise' => [
            'points_per_unit' => 2, // 2 pontos por 10 minutos
            'unit_size' => 10, // Tamanho da unidade em minutos
            'bonus_completion' => 30, // Bônus por atingir 100%
            'label' => 'Exercício'
        ],
        'sleep' => [
            'points_per_unit' => 4, // 4 pontos por hora
            'unit_size' => 1, // Tamanho da unidade em horas
            'bonus_completion' => 25, // Bônus por atingir 100%
            'label' => 'Sono'
        ]
    ];
}

/**
 * Calcula pontos do desafio baseado nas metas e atualiza no banco
 * Agora com suporte a multiplicadores e valores configuráveis
 */
function calculateAndUpdateChallengePoints($conn, $challenge_id, $user_id, $date, $goals) {
    // Buscar progresso atual
    $stmt = $conn->prepare("
        SELECT calories_consumed, water_ml, exercise_minutes, sleep_hours, steps_count
        FROM sf_challenge_group_daily_progress
        WHERE challenge_group_id = ? AND user_id = ? AND date = ?
    ");
    $stmt->bind_param("iis", $challenge_id, $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    
    if (!$progress) {
        return 0;
    }
    
    // Obter multiplicador do dia
    $multiplier = getChallengePointsMultiplier($date);
    
    // Obter configuração de pontos
    $points_config = getChallengePointsConfig();
    
    $total_points = 0;
    $points_breakdown = [];
    
    // Calcular pontos para cada meta do desafio
    foreach ($goals as $goal) {
        $goal_type = $goal['type'] ?? '';
        $goal_value = (float)($goal['value'] ?? 0);
        
        if ($goal_value <= 0) {
            continue;
        }
        
        $config = $points_config[$goal_type] ?? null;
        if (!$config) {
            continue;
        }
        
        $base_points = 0;
        $percentage = 0;
        
        switch ($goal_type) {
            case 'calories':
                $consumed = (float)$progress['calories_consumed'];
                $percentage = min(100, ($consumed / $goal_value) * 100);
                // Pontos progressivos baseado em percentual
                $base_points = floor($percentage / $config['percent_increment']) * $config['points_per_percent'];
                if ($percentage >= 100) {
                    $base_points += $config['bonus_completion'];
                }
                break;
                
            case 'water':
                $consumed_ml = (float)$progress['water_ml'];
                $percentage = min(100, ($consumed_ml / $goal_value) * 100);
                // Pontos baseados em unidades (copos)
                $units = floor($consumed_ml / $config['unit_size']);
                $goal_units = floor($goal_value / $config['unit_size']);
                $base_points = min($units, $goal_units) * $config['points_per_unit'];
                if ($percentage >= 100) {
                    $base_points += $config['bonus_completion'];
                }
                break;
                
            case 'exercise':
                $consumed_minutes = (int)$progress['exercise_minutes'];
                $percentage = min(100, ($consumed_minutes / $goal_value) * 100);
                // Pontos baseados em unidades (blocos de 10min)
                $units = floor(min($consumed_minutes, $goal_value) / $config['unit_size']);
                $base_points = $units * $config['points_per_unit'];
                if ($percentage >= 100) {
                    $base_points += $config['bonus_completion'];
                }
                break;
                
            case 'sleep':
                $consumed_hours = (float)$progress['sleep_hours'];
                $percentage = min(100, ($consumed_hours / $goal_value) * 100);
                // Pontos baseados em unidades (horas)
                $units = floor(min($consumed_hours, $goal_value) / $config['unit_size']);
                $base_points = $units * $config['points_per_unit'];
                if ($percentage >= 100) {
                    $base_points += $config['bonus_completion'];
                }
                break;
        }
        
        // Aplicar multiplicador
        $final_points = round($base_points * $multiplier);
        
        $points_breakdown[$goal_type] = [
            'base_points' => $base_points,
            'multiplier' => $multiplier,
            'final_points' => $final_points,
            'percentage' => round($percentage, 1)
        ];
        
        $total_points += $final_points;
    }
    
    // Atualizar pontos no banco (incluindo breakdown JSON)
    $breakdown_json = json_encode($points_breakdown);
    $stmt_update = $conn->prepare("
        UPDATE sf_challenge_group_daily_progress
        SET points_earned = ?, points_breakdown = ?
        WHERE challenge_group_id = ? AND user_id = ? AND date = ?
    ");
    $stmt_update->bind_param("issis", $total_points, $breakdown_json, $challenge_id, $user_id, $date);
    $stmt_update->execute();
    $stmt_update->close();
    
    return $total_points;
}

/**
 * Retorna pontos totais do usuário em um desafio específico
 */
function getChallengeGroupTotalPoints($conn, $challenge_id, $user_id) {
    $stmt = $conn->prepare("
        SELECT SUM(points_earned) as total_points
        FROM sf_challenge_group_daily_progress
        WHERE challenge_group_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $challenge_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['total_points'] ?? 0);
}

/**
 * Atualiza pontos de desafio quando o usuário faz ações no app
 * Esta função deve ser chamada após atualizações no tracking diário
 * Agora também verifica mudanças de ranking e cria notificações
 */
function updateChallengePoints($conn, $user_id, $action_type = null) {
    // Sincronizar progresso para todos os desafios ativos
    syncChallengeGroupProgress($conn, $user_id);
    
    // Verificar mudanças de ranking e criar notificações
    checkChallengeRankChanges($conn, $user_id);
    
    return true;
}

/**
 * Verifica mudanças de ranking nos desafios e cria notificações
 */
function checkChallengeRankChanges($conn, $user_id) {
    // Verificar se a tabela de snapshot existe
    $snapshot_table_exists = false;
    try {
        $check_snapshot = $conn->query("SHOW TABLES LIKE 'sf_challenge_user_rank_snapshot'");
        if ($check_snapshot && $check_snapshot->num_rows > 0) {
            $snapshot_table_exists = true;
        }
        if ($check_snapshot) {
            $check_snapshot->close();
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar tabela de snapshot: " . $e->getMessage());
        return;
    }
    
    // Se a tabela de snapshot não existe, não fazer nada (tabelas ainda não foram criadas)
    if (!$snapshot_table_exists) {
        return;
    }
    
    try {
        // Buscar desafios ativos do usuário
        $stmt = $conn->prepare("
            SELECT DISTINCT cg.id
            FROM sf_challenge_groups cg
            INNER JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
            WHERE cgm.user_id = ? 
              AND cg.status = 'active'
        ");
        if (!$stmt) {
            error_log("Erro ao preparar query de desafios: " . $conn->error);
            return;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $challenges = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($challenges as $challenge) {
            $challenge_id = $challenge['id'];
            
            // Buscar ranking atual de todos os participantes
            $stmt_rank = $conn->prepare("
                SELECT 
                    u.id,
                    COALESCE(SUM(cgdp.points_earned), 0) as total_points
                FROM sf_challenge_group_members cgm
                INNER JOIN sf_users u ON cgm.user_id = u.id
                LEFT JOIN sf_challenge_group_daily_progress cgdp ON cgdp.user_id = u.id AND cgdp.challenge_group_id = ?
                WHERE cgm.group_id = ?
                GROUP BY u.id
                ORDER BY total_points DESC
            ");
            if (!$stmt_rank) {
                error_log("Erro ao preparar query de ranking: " . $conn->error);
                continue;
            }
            $stmt_rank->bind_param("ii", $challenge_id, $challenge_id);
            $stmt_rank->execute();
            $rank_result = $stmt_rank->get_result();
            
            $rankings = [];
            $rank = 1;
            while ($row = $rank_result->fetch_assoc()) {
                $rankings[$row['id']] = [
                    'rank' => $rank++,
                    'points' => (int)$row['total_points']
                ];
            }
            $stmt_rank->close();
            
            // Para cada usuário no desafio, verificar mudanças
            foreach ($rankings as $check_user_id => $current_data) {
            $current_rank = $current_data['rank'];
            $current_points = $current_data['points'];
            
            // Buscar snapshot anterior
            $stmt_snapshot = $conn->prepare("
                SELECT last_rank, last_points
                FROM sf_challenge_user_rank_snapshot
                WHERE challenge_group_id = ? AND user_id = ?
            ");
            if (!$stmt_snapshot) {
                error_log("Erro ao preparar query de snapshot: " . $conn->error);
                continue;
            }
            $stmt_snapshot->bind_param("ii", $challenge_id, $check_user_id);
            $stmt_snapshot->execute();
            $snapshot_result = $stmt_snapshot->get_result();
            $snapshot = $snapshot_result->fetch_assoc();
            $stmt_snapshot->close();
            
            if ($snapshot && $snapshot['last_rank'] !== null) {
                $last_rank = (int)$snapshot['last_rank'];
                $last_points = (int)$snapshot['last_points'];
                
                // Verificar se subiu no ranking
                if ($current_rank < $last_rank) {
                    // Usuário subiu no ranking
                    $positions_gained = $last_rank - $current_rank;
                    $message = "🎉 Você subiu {$positions_gained} posição" . ($positions_gained > 1 ? "ões" : "") . " no ranking! Agora você está em #{$current_rank}.";
                    createChallengeNotification($conn, $challenge_id, $check_user_id, 'rank_change', $message);
                }
                
                // Verificar se foi ultrapassado
                if ($current_rank > $last_rank) {
                    // Buscar quem ultrapassou este usuário
                    foreach ($rankings as $other_user_id => $other_data) {
                        if ($other_user_id != $check_user_id && 
                            $other_data['rank'] < $current_rank && 
                            $other_data['rank'] >= $last_rank) {
                            
                            $stmt_user = $conn->prepare("SELECT name FROM sf_users WHERE id = ?");
                            if ($stmt_user) {
                                $stmt_user->bind_param("i", $other_user_id);
                                $stmt_user->execute();
                                $user_result = $stmt_user->get_result();
                                $overtaker = $user_result->fetch_assoc();
                                $stmt_user->close();
                            } else {
                                $overtaker = null;
                            }
                            
                            if ($overtaker) {
                                $overtake_message = "⚠️ {$overtaker['name']} te ultrapassou no ranking! Você está em #{$current_rank}.";
                                createChallengeNotification($conn, $challenge_id, $check_user_id, 'overtake', $overtake_message);
                            }
                        }
                    }
                }
            }
            
                // Atualizar snapshot
                $stmt_update = $conn->prepare("
                    INSERT INTO sf_challenge_user_rank_snapshot 
                    (challenge_group_id, user_id, last_rank, last_points)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    last_rank = VALUES(last_rank),
                    last_points = VALUES(last_points),
                    last_updated = NOW()
                ");
                if ($stmt_update) {
                    $stmt_update->bind_param("iiii", $challenge_id, $check_user_id, $current_rank, $current_points);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar mudanças de ranking: " . $e->getMessage());
        // Não propagar o erro para não quebrar o fluxo principal
    }
}

/**
 * Cria uma notificação de desafio
 */
function createChallengeNotification($conn, $challenge_id, $user_id, $type, $message) {
    // Verificar se a tabela existe antes de tentar inserir
    $table_exists = false;
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_challenge_notifications'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
    }
    if ($check_table) {
        $check_table->close();
    }
    
    // Se a tabela não existe, apenas retornar (não criar notificação)
    if (!$table_exists) {
        return;
    }
    
    try {
        // Verificar se já existe notificação similar recente (últimas 2 horas) para evitar spam
        $stmt_check = $conn->prepare("
            SELECT id FROM sf_challenge_notifications
            WHERE challenge_group_id = ? AND user_id = ? AND notification_type = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
            LIMIT 1
        ");
        if (!$stmt_check) {
            error_log("Erro ao preparar verificação de notificação: " . $conn->error);
            return;
        }
        $stmt_check->bind_param("iis", $challenge_id, $user_id, $type);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        if ($check_result->num_rows > 0) {
            $stmt_check->close();
            return; // Já existe notificação similar recente
        }
        $stmt_check->close();
        
        $stmt = $conn->prepare("
            INSERT INTO sf_challenge_notifications 
            (challenge_group_id, user_id, notification_type, message)
            VALUES (?, ?, ?, ?)
        ");
        if (!$stmt) {
            error_log("Erro ao preparar inserção de notificação: " . $conn->error);
            return;
        }
        $stmt->bind_param("iiss", $challenge_id, $user_id, $type, $message);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
        // Não propagar o erro para não quebrar o fluxo principal
    }
}

/**
 * Busca notificações não lidas do usuário
 */
function getChallengeNotifications($conn, $user_id, $limit = 10) {
    // Verificar se a tabela existe antes de tentar consultar
    $table_exists = false;
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_challenge_notifications'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
    }
    if ($check_table) {
        $check_table->close();
    }
    
    // Se a tabela não existe, retornar array vazio
    if (!$table_exists) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT cn.*, cg.name as challenge_name
            FROM sf_challenge_notifications cn
            INNER JOIN sf_challenge_groups cg ON cn.challenge_group_id = cg.id
            WHERE cn.user_id = ? AND cn.is_read = 0
            ORDER BY cn.created_at DESC
            LIMIT ?
        ");
        if (!$stmt) {
            error_log("Erro ao preparar query de notificações: " . $conn->error);
            return [];
        }
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        return $notifications;
    } catch (Exception $e) {
        error_log("Erro ao buscar notificações: " . $e->getMessage());
        return [];
    }
}

/**
 * Marca notificação como lida
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    // Verificar se a tabela existe antes de tentar atualizar
    $table_exists = false;
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_challenge_notifications'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
    }
    if ($check_table) {
        $check_table->close();
    }
    
    // Se a tabela não existe, apenas retornar
    if (!$table_exists) {
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE sf_challenge_notifications
            SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        if (!$stmt) {
            error_log("Erro ao preparar atualização de notificação: " . $conn->error);
            return;
        }
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
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