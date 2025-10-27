<?php
/**
 * Script para verificar se o arquivo JSON está no local correto
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();

echo "<h2>🔍 Debug - Localização do Arquivo JSON</h2>";

// Caminho esperado
$json_file = __DIR__ . '/../suplementos_fatsecret_final_20251021_131514.json';
$json_file_alt = __DIR__ . '/../suplementos_fatsecret_final_20251021_131514.json';

echo "<p><strong>Caminho esperado:</strong> <code>$json_file</code></p>";
echo "<p><strong>Arquivo existe:</strong> " . (file_exists($json_file) ? "✅ SIM" : "❌ NÃO") . "</p>";

if (file_exists($json_file)) {
    $size = filesize($json_file);
    echo "<p><strong>Tamanho do arquivo:</strong> " . number_format($size / 1024, 2) . " KB</p>";
    
    // Tenta ler o arquivo
    $content = file_get_contents($json_file);
    if ($content) {
        $data = json_decode($content, true);
        echo "<p><strong>JSON válido:</strong> " . (is_array($data) ? "✅ SIM" : "❌ NÃO") . "</p>";
        echo "<p><strong>Total de produtos:</strong> " . (is_array($data) ? count($data) : "N/A") . "</p>";
    } else {
        echo "<p><strong>Erro ao ler arquivo:</strong> ❌</p>";
    }
} else {
    echo "<p><strong>Listando arquivos na pasta raiz:</strong></p>";
    $files = scandir(__DIR__ . '/../');
    foreach ($files as $file) {
        if (strpos($file, 'suplementos_fatsecret') !== false) {
            echo "<p>📁 $file</p>";
        }
    }
}

echo "<hr>";
echo "<h3>📁 Estrutura de Pastas:</h3>";
echo "<p><strong>Pasta atual (admin):</strong> " . __DIR__ . "</p>";
echo "<p><strong>Pasta pai (public_html):</strong> " . dirname(__DIR__) . "</p>";

// Lista arquivos JSON na pasta pai
$parent_dir = dirname(__DIR__);
$json_files = glob($parent_dir . '/*.json');
echo "<p><strong>Arquivos JSON encontrados:</strong></p>";
foreach ($json_files as $file) {
    echo "<p>📄 " . basename($file) . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; }
code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
</style>
