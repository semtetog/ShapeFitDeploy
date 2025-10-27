<?php
/**
 * Script para inserir suplementos - FORA da pasta admin
 * Acesse: https://appshapefit.com/insert_suplementos.php
 */

// ✅ CREDENCIAIS DO BANCO (do arquivo db.php)
$host = '127.0.0.1:3306';
$dbname = 'u785537399_shapefit';
$username = 'u785537399_shapefit';
$password = 'Gameroficial2*';

echo "<h2>🚀 Inserindo Suplementos do FatSecret</h2>";

// Tenta conectar ao banco
try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("❌ Erro de conexão: " . $conn->connect_error);
    }
    echo "<p>✅ Conectado ao banco de dados</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p><strong>⚠️ Configure as credenciais do banco no início do arquivo!</strong></p>";
    die();
}

// Função para converter valores por porção para valores por 100g
function convertTo100g($value, $serving_value, $serving_unit) {
    if ($serving_value <= 0) return 0;
    
    $grams = $serving_value;
    switch (strtolower($serving_unit)) {
        case 'ml':
        case 'g':
            $grams = $serving_value;
            break;
        case 'scoop':
        case 'dosador':
        case 'porção':
            $grams = $serving_value * 30;
            break;
        case 'barra':
            $grams = $serving_value * 45;
            break;
        case 'unidade':
            $grams = $serving_value * 30;
            break;
        default:
            $grams = $serving_value;
    }
    
    return ($value / $grams) * 100;
}

// Função para determinar o tipo de alimento
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
    
    return 'granular';
}

try {
    // Verifica se o arquivo JSON existe
    $json_file = 'suplementos_fatsecret_final_20251021_131514.json';
    
    if (!file_exists($json_file)) {
        echo "<p>❌ Arquivo JSON não encontrado: <code>$json_file</code></p>";
        echo "<p><strong>📁 Arquivos JSON disponíveis:</strong></p>";
        $json_files = glob('*.json');
        foreach ($json_files as $file) {
            echo "<p>📄 $file</p>";
        }
        die();
    }
    
    echo "<p>✅ Arquivo JSON encontrado</p>";
    
    $json_content = file_get_contents($json_file);
    $products = json_decode($json_content, true);
    
    if (!$products) {
        throw new Exception("❌ Erro ao decodificar JSON");
    }
    
    echo "<p>📊 Total de produtos: " . count($products) . "</p>";
    
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
        throw new Exception("❌ Erro ao preparar query: " . $conn->error);
    }
    
    echo "<p>🔄 Processando produtos...</p>";
    
    foreach ($products as $index => $product) {
        try {
            // Verifica se já existe
            $check_sql = "SELECT id FROM sf_food_items WHERE name_pt = ? AND brand = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $product['name'], $product['brand']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                if ($index < 5) { // Mostra apenas os primeiros 5 pulados
                    echo "<p>⚠️ [PULADO] {$product['name']} - {$product['brand']}</p>";
                }
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
                if ($index < 10) { // Mostra apenas os primeiros 10 inseridos
                    echo "<p>✅ [INSERIDO] {$product['name']} - {$product['brand']}</p>";
                }
                $inserted++;
            } else {
                echo "<p>❌ [ERRO] {$product['name']}: " . $stmt->error . "</p>";
                $errors++;
            }
            
        } catch (Exception $e) {
            echo "<p>❌ [ERRO] {$product['name']}: " . $e->getMessage() . "</p>";
            $errors++;
        }
        
        // Mostra progresso a cada 50 produtos
        if (($index + 1) % 50 == 0) {
            echo "<p>📊 Progresso: " . ($index + 1) . "/" . count($products) . " produtos processados</p>";
        }
    }
    
    $stmt->close();
    $conn->close();
    
    echo "<hr>";
    echo "<h3>📈 Resumo Final:</h3>";
    echo "<p><strong>✅ Inseridos:</strong> $inserted produtos</p>";
    echo "<p><strong>⚠️ Pulados (duplicados):</strong> $skipped produtos</p>";
    echo "<p><strong>❌ Erros:</strong> $errors produtos</p>";
    echo "<p><strong>📊 Total processados:</strong> " . ($inserted + $skipped + $errors) . " produtos</p>";
    
    if ($inserted > 0) {
        echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 SUCESSO! $inserted suplementos inseridos no banco!</p>";
        echo "<p><a href='admin/foods_management_new.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Alimentos no Admin</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f5f5f5;
}
h2 { color: #333; }
h3 { color: #666; }
code { 
    background: #f4f4f4; 
    padding: 2px 4px; 
    border-radius: 3px; 
    color: #d63384;
}
a { 
    display: inline-block; 
    margin-top: 10px;
}
</style>
