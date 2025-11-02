<?php
// admin/import_external_foods.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';

requireAdminLogin();

echo "<h1>Importador de Alimentos Externos</h1>";
echo "<p>Este script busca por alimentos registrados pelos usuários que não existem na sua tabela principal ('sf_food_items') e os importa.</p>";
echo "<hr>";

try {
    // Passo 1: Encontrar alimentos no log que não estão na tabela principal
    // Vamos focar em alimentos que têm um 'custom_meal_name' mas não têm 'recipe_id' ou 'food_item_id'
    // E que tenham uma marca (um bom indicador de fonte externa)
    $sql = "
        SELECT DISTINCT 
            l.custom_meal_name,
            l.brand_name,
            l.kcal_consumed,
            l.protein_consumed_g,
            l.carbs_consumed_g,
            l.fat_consumed_g
        FROM sf_user_meal_log l
        LEFT JOIN sf_food_items fi ON l.custom_meal_name = fi.name_pt AND l.brand_name = fi.brand
        WHERE l.recipe_id IS NULL 
          AND l.food_item_id IS NULL
          AND l.custom_meal_name IS NOT NULL AND l.custom_meal_name != ''
          AND l.brand_name IS NOT NULL AND l.brand_name != ''
          AND fi.id IS NULL
        LIMIT 500; -- Limitar para não sobrecarregar
    ";

    $result = $conn->query($sql);
    $foods_to_import = $result->fetch_all(MYSQLI_ASSOC);

    if (empty($foods_to_import)) {
        echo "<p>✅ Nenhum novo alimento externo para importar no momento.</p>";
        exit;
    }

    echo "<h3>Encontrados " . count($foods_to_import) . " novos alimentos para importar:</h3>";
    echo "<ul>";

    // Passo 2: Inserir os alimentos na tabela sf_food_items
    $insert_sql = "
        INSERT INTO sf_food_items 
            (name_pt, brand, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g, food_group, source, food_type)
        VALUES
            (?, ?, ?, ?, ?, ?, 'Importado', 'user_log', 'granular')
    ";
    $stmt = $conn->prepare($insert_sql);

    $imported_count = 0;
    foreach ($foods_to_import as $food) {
        // Simulação de valores por 100g (precisaria de uma lógica mais robusta se a porção for conhecida)
        // Por agora, vamos assumir que os valores são por porção e precisamos estimar por 100g.
        // Se a porção média for 120g, por exemplo:
        $assumed_portion_g = 120;
        $factor = 100 / $assumed_portion_g;

        $kcal_100g = $food['kcal_consumed'] * $factor;
        $protein_100g = $food['protein_consumed_g'] * $factor;
        $carbs_100g = $food['carbs_consumed_g'] * $factor;
        $fat_100g = $food['fat_consumed_g'] * $factor;
        
        $stmt->bind_param("ssdddd", 
            $food['custom_meal_name'],
            $food['brand_name'],
            $kcal_100g,
            $protein_100g,
            $carbs_100g,
            $fat_100g
        );

        if ($stmt->execute()) {
            echo "<li>✔️ Importado: " . htmlspecialchars($food['custom_meal_name']) . " (" . htmlspecialchars($food['brand_name']) . ")</li>";
            $imported_count++;
        } else {
            echo "<li>❌ Erro ao importar " . htmlspecialchars($food['custom_meal_name']) . ": " . $stmt->error . "</li>";
        }
    }

    echo "</ul>";
    echo "<hr>";
    echo "<h3>🎉 Processo concluído!</h3>";
    echo "<p><b>{$imported_count}</b> alimentos foram importados com sucesso para a sua base de dados principal.</p>";
    echo "<p>Agora você pode ir para a página de <a href='food_classification.php'>Classificação de Alimentos</a> para categorizá-los.</p>";

    $stmt->close();

} catch (Exception $e) {
    echo "<p style='color: red;'><b>Erro durante o processo:</b> " . $e->getMessage() . "</p>";
}


