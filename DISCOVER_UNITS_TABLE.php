<?php
// DISCOVER_UNITS_TABLE.php - Descobrir o nome correto da tabela de unidades

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔍 DESCOBRINDO TABELA DE UNIDADES CORRETA 🔍\n\n";

// Listar todas as tabelas do banco
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_array()) {
    $tables[] = $row[0];
}

echo "Tabelas encontradas no banco:\n";
foreach ($tables as $table) {
    echo "- {$table}\n";
}

echo "\n=== PROCURANDO TABELA DE UNIDADES ===\n";

// Procurar por tabelas que contenham "unit" no nome
$unit_tables = array_filter($tables, function($table) {
    return strpos(strtolower($table), 'unit') !== false;
});

if (!empty($unit_tables)) {
    echo "Tabelas com 'unit' no nome:\n";
    foreach ($unit_tables as $table) {
        echo "- {$table}\n";
    }
} else {
    echo "Nenhuma tabela com 'unit' no nome encontrada.\n";
}

// Procurar por tabelas que contenham "measurement" no nome
$measurement_tables = array_filter($tables, function($table) {
    return strpos(strtolower($table), 'measurement') !== false;
});

if (!empty($measurement_tables)) {
    echo "\nTabelas com 'measurement' no nome:\n";
    foreach ($measurement_tables as $table) {
        echo "- {$table}\n";
    }
} else {
    echo "\nNenhuma tabela com 'measurement' no nome encontrada.\n";
}

// Procurar por tabelas que contenham "measure" no nome
$measure_tables = array_filter($tables, function($table) {
    return strpos(strtolower($table), 'measure') !== false;
});

if (!empty($measure_tables)) {
    echo "\nTabelas com 'measure' no nome:\n";
    foreach ($measure_tables as $table) {
        echo "- {$table}\n";
    }
} else {
    echo "\nNenhuma tabela com 'measure' no nome encontrada.\n";
}

echo "\n=== VERIFICANDO ESTRUTURA DAS TABELAS SUSPEITAS ===\n";

// Verificar estrutura de cada tabela suspeita
$all_suspect_tables = array_merge($unit_tables, $measurement_tables, $measure_tables);
$all_suspect_tables = array_unique($all_suspect_tables);

foreach ($all_suspect_tables as $table) {
    echo "\n--- Estrutura da tabela '{$table}' ---\n";
    $structure_result = $conn->query("DESCRIBE {$table}");
    while ($row = $structure_result->fetch_assoc()) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
    // Verificar se tem dados
    $count_result = $conn->query("SELECT COUNT(*) as count FROM {$table}");
    $count = $count_result->fetch_assoc()['count'];
    echo "  Registros: {$count}\n";
}

echo "\n=== VERIFICANDO TABELAS QUE PODEM CONTER UNIDADES ===\n";

// Verificar se alguma tabela tem colunas que sugerem unidades
foreach ($tables as $table) {
    $structure_result = $conn->query("DESCRIBE {$table}");
    $has_abbreviation = false;
    $has_conversion = false;
    $has_name = false;
    
    while ($row = $structure_result->fetch_assoc()) {
        if (strpos(strtolower($row['Field']), 'abbreviation') !== false) {
            $has_abbreviation = true;
        }
        if (strpos(strtolower($row['Field']), 'conversion') !== false) {
            $has_conversion = true;
        }
        if (strpos(strtolower($row['Field']), 'name') !== false) {
            $has_name = true;
        }
    }
    
    if ($has_abbreviation && $has_conversion && $has_name) {
        echo "🎯 POSSÍVEL TABELA DE UNIDADES: {$table}\n";
        echo "  Tem: abbreviation, conversion, name\n";
        
        // Verificar alguns registros
        $sample_result = $conn->query("SELECT * FROM {$table} LIMIT 3");
        echo "  Amostra de dados:\n";
        while ($row = $sample_result->fetch_assoc()) {
            echo "    " . json_encode($row) . "\n";
        }
    }
}

echo "\n=== DESCOBERTA CONCLUÍDA ===\n";
$conn->close();
?>
