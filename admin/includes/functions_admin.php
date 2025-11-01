<?php
// Arquivo: admin/includes/functions_admin.php (VERSÃO FINAL COM ORDER BY CORRETO)

function getGroupedMealHistory(mysqli $conn, int $user_id, string $startDate, string $endDate): array
{
    $sql = "
        SELECT 
            log.id,
            log.date_consumed,
            log.meal_type,
            log.logged_at,
            COALESCE(log.custom_meal_name, recipe.name, 'Alimento Registrado') as food_name,
            CASE 
                WHEN log.servings_consumed = 1 THEN '1 porção'
                WHEN log.servings_consumed = 2 THEN '2 porções'
                WHEN log.servings_consumed = 3 THEN '3 porções'
                WHEN log.servings_consumed = 4 THEN '4 porções'
                WHEN log.servings_consumed = 5 THEN '5 porções'
                WHEN log.servings_consumed = 6 THEN '6 porções'
                WHEN log.servings_consumed = 7 THEN '7 porções'
                WHEN log.servings_consumed = 8 THEN '8 porções'
                WHEN log.servings_consumed = 9 THEN '9 porções'
                WHEN log.servings_consumed = 10 THEN '10 porções'
                WHEN log.servings_consumed > 10 THEN CONCAT(log.servings_consumed, ' porções')
                ELSE CONCAT(log.servings_consumed, ' porção(ões)')
            END as quantity_display,
            log.kcal_consumed,
            log.protein_consumed_g,
            log.carbs_consumed_g,
            log.fat_consumed_g
        FROM 
            sf_user_meal_log AS log
            LEFT JOIN sf_recipes AS recipe ON log.recipe_id = recipe.id
        WHERE 
            log.user_id = ? AND log.date_consumed BETWEEN ? AND ?
        ORDER BY 
            log.date_consumed DESC, log.logged_at ASC
    ";
    // A linha acima foi corrigida de 'log.created_at' para 'log.logged_at'

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar getGroupedMealHistory: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("iss", $user_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['date_consumed'];
        $meal_type_slug = $row['meal_type'];
        
        if (empty($row['food_name'])) {
            $row['food_name'] = 'Alimento Registrado';
        }
        
        $history[$date][$meal_type_slug][] = $row;
    }

    $stmt->close();
    return $history;
}

/**
 * Gera o HTML para o avatar de um usuário (imagem ou iniciais).
 * @param array $user_data Dados do usuário, deve conter 'name' e 'profile_image_filename'.
 * @param string $size_class Classe de tamanho ('small', 'medium', 'large').
 * @return string HTML do avatar.
 */
function getAdminUserAvatar(array $user_data, string $size_class = 'medium'): string
{
    // Verifica se há uma imagem de perfil válida
    if (!empty($user_data['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $user_data['profile_image_filename'])) {
        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user_data['profile_image_filename']);
        return '<img src="' . $avatar_url . '" alt="Foto de ' . htmlspecialchars($user_data['name']) . '" class="avatar-image ' . $size_class . '">';
    }

    // Fallback para iniciais
    $name_parts = explode(' ', trim($user_data['name'] ?? ''));
    $initials = count($name_parts) > 1 
        ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
        : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : '??');
    
    // Gera uma cor de fundo com base no nome para consistência
    $hash = md5($user_data['name'] ?? 'default');
    $r = hexdec(substr($hash, 0, 2)) % 156 + 50;
    $g = hexdec(substr($hash, 2, 2)) % 156 + 50;
    $b = hexdec(substr($hash, 4, 2)) % 156 + 50;

    // Garante que a cor não seja muito clara para manter o contraste
    if (max($r, $g, $b) > 180) {
        $r = (int)($r * 0.7);
        $g = (int)($g * 0.7);
        $b = (int)($b * 0.7);
    }
    $bgColor = sprintf('#%02x%02x%02x', $r, $g, $b);

    return '<div class="initials-avatar ' . $size_class . '" style="background-color: ' . $bgColor . ';">' . $initials . '</div>';
}
?>