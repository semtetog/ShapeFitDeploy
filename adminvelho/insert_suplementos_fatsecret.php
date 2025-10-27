<?php
/**
 * Script para inserir os 728 suplementos do FatSecret no banco de dados
 * Converte dados por porção para valores por 100g
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();

// Função para converter valores por porção para valores por 100g
function convertTo100g($value, $serving_value, $serving_unit) {
    if ($serving_value <= 0) return 0;
    
    // Converte unidade para gramas
    $grams = $serving_value;
    switch (strtolower($serving_unit)) {
        case 'ml':
        case 'g':
            $grams = $serving_value;
            break;
        case 'scoop':
        case 'dosador':
        case 'porção':
            // Assumindo que 1 scoop = 30g (padrão da indústria)
            $grams = $serving_value * 30;
            break;
        case 'barra':
            // Assumindo que 1 barra = 45g (padrão)
            $grams = $serving_value * 45;
            break;
        case 'unidade':
            // Assumindo que 1 unidade = 30g
            $grams = $serving_value * 30;
            break;
        default:
            $grams = $serving_value;
    }
    
    // Calcula valor por 100g
    return ($value / $grams) * 100;
}

// Função para determinar o tipo de alimento baseado no nome
function getFoodType($name, $brand) {
    $name_lower = strtolower($name);
    
    if (strpos($name_lower, 'whey') !== false || strpos($name_lower, 'protein') !== false) {
        return 'granular';
    }
    if (strpos($name_lower, 'barra') !== false || strpos($name_lower, 'bar') !== false) {
        return 'unidade_inteira';
    }
    if (strpos($name_lower, 'pasta') !== false || strpos($name_lower, 'creme') !== false) {
        return 'colher_cremoso';
    }
    if (strpos($name_lower, 'óleo') !== false || strpos($name_lower, 'oil') !== false) {
        return 'oleos_gorduras';
    }
    
    return 'granular'; // Padrão
}

try {
    echo "<h2>🚀 Inserindo Suplementos do FatSecret no Banco de Dados</h2>";
    
    // Lê o arquivo JSON
    $json_file = __DIR__ . '/../suplementos_fatsecret_final_20251021_131514.json';
    
    if (!file_exists($json_file)) {
        throw new Exception("Arquivo JSON não encontrado: $json_file");
    }
    
    $json_content = file_get_contents($json_file);
    $products = json_decode($json_content, true);
    
    if (!$products) {
        throw new Exception("Erro ao decodificar JSON");
    }
    
    echo "<p>📊 Total de produtos no JSON: " . count($products) . "</p>";
    
    $inserted = 0;
    $skipped = 0;
    $errors = 0;
    
    // Prepara a query de inserção
    $sql = "INSERT INTO sf_food_items (
        name_pt, 
        brand, 
        food_type, 
        energy_kcal_100g, 
        protein_g_100g, 
        fat_g_100g, 
        carbohydrate_g_100g, 
        source_table,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'FatSecret', NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }
    
    foreach ($products as $product) {
        try {
            // Verifica se já existe (por nome e marca)
            $check_sql = "SELECT id FROM sf_food_items WHERE name_pt = ? AND brand = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $product['name'], $product['brand']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "<p>⚠️ [PULADO] {$product['name']} - {$product['brand']} (já existe)</p>";
                $skipped++;
                continue;
            }
            
            // Converte valores para 100g
            $calories_100g = convertTo100g($product['calories'], $product['serving_value'], $product['serving_unit']);
            $protein_100g = convertTo100g($product['protein'], $product['serving_value'], $product['serving_unit']);
            $fat_100g = convertTo100g($product['fat'], $product['serving_value'], $product['serving_unit']);
            $carbs_100g = convertTo100g($product['carbs'], $product['serving_value'], $product['serving_unit']);
            
            // Determina tipo de alimento
            $food_type = getFoodType($product['name'], $product['brand']);
            
            // Insere no banco
            $stmt->bind_param(
                "sssdddd", 
                $product['name'],
                $product['brand'],
                $food_type,
                $calories_100g,
                $protein_100g,
                $fat_100g,
                $carbs_100g
            );
            
            if ($stmt->execute()) {
                echo "<p>✅ [INSERIDO] {$product['name']} - {$product['brand']} | {$calories_100g} kcal/100g</p>";
                $inserted++;
            } else {
                echo "<p>❌ [ERRO] {$product['name']} - {$product['brand']}: " . $stmt->error . "</p>";
                $errors++;
            }
            
        } catch (Exception $e) {
            echo "<p>❌ [ERRO] {$product['name']} - {$product['brand']}: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
    
    $stmt->close();
    
    echo "<hr>";
    echo "<h3>📈 Resumo da Inserção:</h3>";
    echo "<p><strong>✅ Inseridos:</strong> $inserted produtos</p>";
    echo "<p><strong>⚠️ Pulados (duplicados):</strong> $skipped produtos</p>";
    echo "<p><strong>❌ Erros:</strong> $errors produtos</p>";
    echo "<p><strong>📊 Total processados:</strong> " . ($inserted + $skipped + $errors) . " produtos</p>";
    
    if ($inserted > 0) {
        echo "<p style='color: green; font-weight: bold;'>🎉 Suplementos inseridos com sucesso!</p>";
        echo "<p><a href='foods_management_new.php' class='btn btn-primary'>Ver Alimentos</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
p { margin: 5px 0; }
.btn { 
    display: inline-block; 
    padding: 10px 20px; 
    background: #007bff; 
    color: white; 
    text-decoration: none; 
    border-radius: 5px; 
    margin-top: 10px;
}
</style>
