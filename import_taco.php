<?php
// Arquivo: import_taco.php
// Versão Final com LOTES e Lógica de Leitura Corrigida

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(60); // Define limite de tempo para CADA execução de lote
ini_set('memory_limit', '128M');

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

$lines_per_batch = 100;
$start_line = isset($_GET['start']) ? (int)$_GET['start'] : 0; // Começar a contagem em 0

echo "<!DOCTYPE html><html><head><title>Importando Tabela TACO em Lotes</title><meta charset='UTF-8'></head><body><pre>";
echo "<h1>Importando Tabela TACO</h1>";
echo "Lote atual começando após a linha: {$start_line}\n";

$csvFilePath = APP_ROOT_PATH . '/data_import/taco_data.csv';
if (!file_exists($csvFilePath)) { die("ERRO CRÍTICO: Arquivo CSV não encontrado."); }

$fileHandle = fopen($csvFilePath, "r");
if ($fileHandle === false) { die("ERRO CRÍTICO: Não foi possível abrir o arquivo CSV."); }

// Pula o cabeçalho
fgetcsv($fileHandle); 

// Pula as linhas já processadas
if ($start_line > 0) {
    for ($i = 0; $i < $start_line; $i++) {
        if (fgetcsv($fileHandle) === false) {
            echo "\n\nFim do arquivo alcançado. IMPORTAÇÃO CONCLUÍDA!\n";
            fclose($fileHandle);
            exit;
        }
    }
}

// Prepara a query SQL
$stmt = $conn->prepare("INSERT INTO sf_food_items (taco_id, name_pt, moisture_percent, energy_kcal_100g, protein_g_100g, fat_g_100g, cholesterol_mg_100g, carbohydrate_g_100g, fiber_g_100g, ash_g_100g, calcium_mg_100g, magnesium_mg_100g, sodium_mg_100g, potassium_mg_100g, vitamin_c_mg_100g, source_table) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'TACO') ON DUPLICATE KEY UPDATE name_pt=VALUES(name_pt), moisture_percent=VALUES(moisture_percent), energy_kcal_100g=VALUES(energy_kcal_100g), protein_g_100g=VALUES(protein_g_100g), fat_g_100g=VALUES(fat_g_100g), cholesterol_mg_100g=VALUES(cholesterol_mg_100g), carbohydrate_g_100g=VALUES(carbohydrate_g_100g), fiber_g_100g=VALUES(fiber_g_100g), ash_g_100g=VALUES(ash_g_100g), calcium_mg_100g=VALUES(calcium_mg_100g), magnesium_mg_100g=VALUES(magnesium_mg_100g), sodium_mg_100g=VALUES(sodium_mg_100g), potassium_mg_100g=VALUES(potassium_mg_100g), vitamin_c_mg_100g=VALUES(vitamin_c_mg_100g);");
if ($stmt === false) { die("ERRO ao preparar statement SQL: " . $conn->error); }
$bind_types = "ssdddddddddddds";

// Função para limpar os valores
function clean_value_import($value) {
    $val = trim($value);
    if ($val === '' || is_null($val) || !is_numeric($val)) {
        $upperVal = strtoupper($val);
        if ($upperVal === 'NA' || $upperVal === 'TR' || $upperVal === '*') {
            return null;
        }
        return null;
    }
    return (float)$val;
}

$lines_processed_in_batch = 0;
$conn->begin_transaction();

while (($data = fgetcsv($fileHandle, 2048, ",")) !== false && $lines_processed_in_batch < $lines_per_batch) {
    if (count($data) < 16) continue;
    $taco_id = trim($data[0]);
    if (empty($taco_id) || !is_numeric($taco_id)) continue;

    $params = [ $taco_id, trim($data[1]), clean_value_import($data[2]), clean_value_import($data[3]), clean_value_import($data[5]), clean_value_import($data[6]), clean_value_import($data[7]), clean_value_import($data[8]), clean_value_import($data[9]), clean_value_import($data[10]), clean_value_import($data[11]), clean_value_import($data[12]), clean_value_import($data[13]), clean_value_import($data[14]), clean_value_import($data[15]) ];
    
    $stmt->bind_param($bind_types, ...$params);
    $stmt->execute();
    $lines_processed_in_batch++;
}

$conn->commit();
fclose($fileHandle);
$stmt->close();
$conn->close();

echo "Lote processado. {$lines_processed_in_batch} linhas foram tratadas.\n";

if ($lines_processed_in_batch < $lines_per_batch) {
    echo "\n\n=======================================\n";
    echo "    IMPORTAÇÃO CONCLUÍDA!\n";
    echo "=======================================\n";
    echo "Todos os dados foram importados.";
} else {
    $next_start_line = $start_line + $lines_processed_in_batch;
    $next_url = strtok($_SERVER["REQUEST_URI"],'?') . "?start=" . $next_start_line;
    echo "Agendando próximo lote... (Iniciando após a linha {$next_start_line})\n";
    echo "<script> setTimeout(function() { window.location.href = '{$next_url}'; }, 1000); </script>";
}

echo "</pre></body></html>";
?>