<?php
// admin/save_recipe.php (VERSÃO FINAL UNIFICADA)

// Definir fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

function sanitize_decimal($value) {
    if ($value === null || $value === '') return null;
    return floatval(str_replace(',', '.', trim($value)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recipes.php");
    exit();
}

// Coleta e limpeza dos dados do formulário
$recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$is_public = filter_input(INPUT_POST, 'is_public', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$prep_time_minutes = filter_input(INPUT_POST, 'prep_time_minutes', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
$cook_time_minutes = filter_input(INPUT_POST, 'cook_time_minutes', FILTER_VALIDATE_INT, ['options' => ['default' => null]]);
$kcal = sanitize_decimal($_POST['kcal_per_serving'] ?? null);
$carbs = sanitize_decimal($_POST['carbs_g_per_serving'] ?? null);
$fat = sanitize_decimal($_POST['fat_g_per_serving'] ?? null);
$protein = sanitize_decimal($_POST['protein_g_per_serving'] ?? null);
$servings = trim($_POST['servings'] ?? '1');
$serving_size_g = sanitize_decimal($_POST['serving_size_g'] ?? null);
$categories = $_POST['categories'] ?? [];
$meal_suggestions = $_POST['meal_type_suggestion'] ?? [];

// Converte os arrays para strings separadas por vírgula para salvar no DB
$meal_suggestions_str = !empty($meal_suggestions) ? implode(',', $meal_suggestions) : null;
// A conversão de dietary_tags foi removida.

// Lógica de Upload de Imagem
$image_filename = $_POST['existing_image_filename'] ?? null;
$upload_dir = APP_ROOT_PATH . '/assets/images/recipes/';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_path = $_FILES['image']['tmp_name'];
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('recipe_', true) . '.' . $file_extension;
    $dest_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file_tmp_path, $dest_path)) {
        if ($image_filename && file_exists($upload_dir . $image_filename)) {
            unlink($upload_dir . $image_filename);
        }
        $image_filename = $new_filename;
    }
}

// Operação no Banco de Dados
$conn->begin_transaction();
try {
    if ($recipe_id) { // ATUALIZAR
        $sql = "UPDATE sf_recipes SET 
                name = ?, description = ?, instructions = ?, is_public = ?, 
                prep_time_minutes = ?, cook_time_minutes = ?, kcal_per_serving = ?, 
                carbs_g_per_serving = ?, fat_g_per_serving = ?, protein_g_per_serving = ?, 
                servings = ?, serving_size_g = ?, 
                meal_type_suggestion = ?, image_filename = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisddddssdsi", 
            $name, $description, $instructions, $is_public, 
            $prep_time_minutes, $cook_time_minutes, $kcal, $carbs, $fat, $protein, 
            $servings, $serving_size_g, 
            $meal_suggestions_str, $image_filename, $recipe_id
        );
    } else { // INSERIR
        $current_time = date('Y-m-d H:i:s');
        $sql = "INSERT INTO sf_recipes (
                name, description, instructions, is_public, prep_time_minutes, cook_time_minutes, 
                kcal_per_serving, carbs_g_per_serving, fat_g_per_serving, protein_g_per_serving, 
                servings, serving_size_g, 
                meal_type_suggestion, image_filename, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiisddddssdss", 
            $name, $description, $instructions, $is_public, $prep_time_minutes, $cook_time_minutes, 
            $kcal, $carbs, $fat, $protein, $servings, $serving_size_g, 
            $meal_suggestions_str, $image_filename, $current_time
        );
    }
    $stmt->execute();
    if (!$recipe_id) $recipe_id = $conn->insert_id;
    $stmt->close();

    // Atualizar ingredientes
    $conn->query("DELETE FROM sf_recipe_ingredients WHERE recipe_id = $recipe_id");
    if (!empty($_POST['ingredient_description'])) {
        $stmt_ing = $conn->prepare("INSERT INTO sf_recipe_ingredients (recipe_id, ingredient_description, quantity_value, quantity_unit) VALUES (?, ?, ?, ?)");
        $ingredient_descriptions = $_POST['ingredient_description'];
        $ingredient_quantities = $_POST['ingredient_quantity'] ?? [];
        $ingredient_units = $_POST['ingredient_unit'] ?? [];
        
        foreach ($ingredient_descriptions as $index => $desc) {
            if (trim($desc)) {
                $quantity = !empty($ingredient_quantities[$index]) ? floatval($ingredient_quantities[$index]) : null;
                $unit = !empty($ingredient_units[$index]) ? trim($ingredient_units[$index]) : null;
                
                $stmt_ing->bind_param("isds", $recipe_id, $desc, $quantity, $unit);
                $stmt_ing->execute();
            }
        }
        $stmt_ing->close();
    }

    // Atualizar categorias (Esta parte já estava correta)
    $conn->query("DELETE FROM sf_recipe_has_categories WHERE recipe_id = $recipe_id");
    if (!empty($categories)) {
        $stmt_cat = $conn->prepare("INSERT INTO sf_recipe_has_categories (recipe_id, category_id) VALUES (?, ?)");
        foreach ($categories as $cat_id) {
            $stmt_cat->bind_param("ii", $recipe_id, $cat_id);
            $stmt_cat->execute();
        }
        $stmt_cat->close();
    }
    
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Erro ao salvar a receita: " . $e->getMessage());
}

// Redirecionar de volta para a página de edição, não para recipes.php
header("Location: edit_recipe.php?id=" . $recipe_id . "&status=saved");
exit();
?>