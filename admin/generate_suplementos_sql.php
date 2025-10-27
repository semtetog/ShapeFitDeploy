<?php
/**
 * Script para gerar SQL dos suplementos do FatSecret
 * Gera arquivo SQL que pode ser executado no phpMyAdmin
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
    echo "<h2>🚀 Gerando SQL dos Suplementos do FatSecret</h2>";
    
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
    
    // Gera arquivo SQL
    $timestamp = date('Ymd_His');
    $sql_file = __DIR__ . "/suplementos_fatsecret_{$timestamp}.sql";
    
    $sql_content = "-- Suplementos do FatSecret - Gerado em " . date('Y-m-d H:i:s') . "\n";
    $sql_content .= "-- Total de produtos: " . count($products) . "\n\n";
    
    $sql_content .= "INSERT INTO `sf_food_items` (`name_pt`, `brand`, `food_type`, `energy_kcal_100g`, `protein_g_100g`, `fat_g_100g`, `carbohydrate_g_100g`, `source_table`, `created_at`, `updated_at`) VALUES\n";
    
    $values = [];
    $processed = 0;
    
    foreach ($products as $product) {
        // Converte valores para 100g
        $calories_100g = convertTo100g($product['calories'], $product['serving_value'], $product['serving_unit']);
        $protein_100g = convertTo100g($product['protein'], $product['serving_value'], $product['serving_unit']);
        $fat_100g = convertTo100g($product['fat'], $product['serving_value'], $product['serving_unit']);
        $carbs_100g = convertTo100g($product['carbs'], $product['serving_value'], $product['serving_unit']);
        
        // Determina tipo de alimento
        $food_type = getFoodType($product['name'], $product['brand']);
        
        // Escapa aspas no nome
        $name_escaped = str_replace("'", "''", $product['name']);
        $brand_escaped = str_replace("'", "''", $product['brand']);
        
        $values[] = "('$name_escaped', '$brand_escaped', '$food_type', $calories_100g, $protein_100g, $fat_100g, $carbs_100g, 'FatSecret', NOW(), NOW())";
        
        $processed++;
        
        if ($processed % 100 == 0) {
            echo "<p>📝 Processados: $processed produtos</p>";
        }
    }
    
    $sql_content .= implode(",\n", $values);
    $sql_content .= ";\n";
    
    // Salva arquivo SQL
    file_put_contents($sql_file, $sql_content);
    
    echo "<hr>";
    echo "<h3>📈 Resumo da Geração:</h3>";
    echo "<p><strong>✅ Processados:</strong> $processed produtos</p>";
    echo "<p><strong>📁 Arquivo SQL gerado:</strong> <code>$sql_file</code></p>";
    echo "<p><strong>📊 Tamanho do arquivo:</strong> " . number_format(filesize($sql_file) / 1024, 2) . " KB</p>";
    
    echo "<h3>🚀 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Faça download do arquivo SQL: <a href='" . basename($sql_file) . "' download>📥 Download SQL</a></li>";
    echo "<li>Acesse o phpMyAdmin da Hostinger</li>";
    echo "<li>Selecione seu banco de dados</li>";
    echo "<li>Vá na aba 'SQL'</li>";
    echo "<li>Cole o conteúdo do arquivo SQL</li>";
    echo "<li>Execute a query</li>";
    echo "</ol>";
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Arquivo SQL gerado com sucesso!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
p { margin: 5px 0; }
code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
ol { margin: 10px 0; padding-left: 20px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
