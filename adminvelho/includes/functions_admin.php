<?php
// Arquivo: admin/includes/functions_admin.php (VERSÃO FINAL COM ORDER BY CORRETO)

function getGroupedMealHistory(mysqli $conn, int $user_id, string $startDate, string $endDate): array
{
    $sql = "
        SELECT 
            log.id,
            log.date_consumed,
            log.meal_type,
            COALESCE(log.custom_meal_name, recipe.name, 'Alimento Registrado') as food_name,
            CONCAT(log.servings_consumed, ' porção(ões)') as quantity_display,
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
?>