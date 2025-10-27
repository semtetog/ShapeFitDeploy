<?php
// admin/generate_thumbs.php - RODE APENAS UMA VEZ E DEPOIS APAGUE

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h1>Gerador de Miniaturas de Perfil</h1>";

$images_dir = APP_ROOT_PATH . '/assets/images/users/';
$files = scandir($images_dir);
$processed_count = 0;

if (!$files) {
    die("Não foi possível ler o diretório de imagens.");
}

foreach ($files as $file) {
    // Ignora os diretórios '.' e '..' e os arquivos que já são thumbnails
    if ($file === '.' || $file === '..' || str_starts_with($file, 'thumb_')) {
        continue;
    }

    $source_path = $images_dir . $file;
    $thumb_filename = 'thumb_' . $file;
    $thumb_path = $images_dir . $thumb_filename;

    // Verifica se é um arquivo de imagem e se a thumbnail ainda não existe
    if (is_file($source_path) && !file_exists($thumb_path)) {
        echo "Processando: " . htmlspecialchars($file) . "... ";
        if (create_thumbnail($source_path, $thumb_path, 200)) {
            echo "<span style='color:green;'>OK!</span><br>";
            $processed_count++;
        } else {
            echo "<span style='color:red;'>FALHOU!</span><br>";
        }
    }
}

echo "<h2>Concluído! {$processed_count} novas miniaturas foram criadas.</h2>";
echo "<p><strong>IMPORTANTE:</strong> Apague este arquivo ('generate_thumbs.php') do seu servidor agora.</p>";

?>