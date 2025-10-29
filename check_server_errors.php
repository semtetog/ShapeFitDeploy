<?php
// check_server_errors.php - Verificar erros do servidor

echo "<h2>🔍 Verificação de Erros do Servidor</h2>";

// 1. Verificar se há erros de PHP
echo "<h3>📋 Configurações PHP:</h3>";
echo "<ul>";
echo "<li><strong>display_errors:</strong> " . (ini_get('display_errors') ? 'On' : 'Off') . "</li>";
echo "<li><strong>log_errors:</strong> " . (ini_get('log_errors') ? 'On' : 'Off') . "</li>";
echo "<li><strong>error_log:</strong> " . ini_get('error_log') . "</li>";
echo "<li><strong>error_reporting:</strong> " . error_reporting() . "</li>";
echo "</ul>";

// 2. Verificar se há erros recentes
echo "<h3>📝 Últimos Erros PHP:</h3>";

$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    $lines = file($error_log_path);
    $recent_lines = array_slice($lines, -10); // Últimas 10 linhas
    
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    foreach ($recent_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<p>❌ Arquivo de log não encontrado ou não configurado.</p>";
}

// 3. Verificar se há problemas de sintaxe
echo "<h3>🔧 Verificação de Sintaxe:</h3>";

$files_to_check = [
    'actions/complete_routine_item.php',
    'includes/functions.php',
    'includes/db.php',
    'includes/config.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $output = shell_exec("php -l " . escapeshellarg($file) . " 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "<p style='color: green;'>✅ {$file} - Sintaxe OK</p>";
        } else {
            echo "<p style='color: red;'>❌ {$file} - Erro de sintaxe:</p>";
            echo "<pre style='background: #ffe6e6; padding: 5px; border-radius: 3px;'>" . htmlspecialchars($output) . "</pre>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ {$file} - Arquivo não encontrado</p>";
    }
}

// 4. Verificar permissões
echo "<h3>🔐 Verificação de Permissões:</h3>";

$dirs_to_check = [
    'actions/',
    'includes/',
    'logs/'
];

foreach ($dirs_to_check as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "<p><strong>{$dir}:</strong> {$perms}</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ {$dir} - Diretório não encontrado</p>";
    }
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>





