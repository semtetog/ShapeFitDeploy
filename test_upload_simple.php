<?php
require_once 'includes/config.php';

echo "<h2>Teste de Upload</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Dados recebidos:</h3>";
    echo "<pre>";
    echo "POST: " . print_r($_POST, true);
    echo "FILES: " . print_r($_FILES, true);
    echo "</pre>";
    
    if (isset($_FILES['test_photo']) && $_FILES['test_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = APP_ROOT_PATH . '/uploads/measurements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'test_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['test_photo']['tmp_name'], $upload_path)) {
            echo "<p style='color: green;'>Upload bem-sucedido! Arquivo salvo em: $upload_path</p>";
        } else {
            echo "<p style='color: red;'>Falha no upload!</p>";
        }
    } else {
        echo "<p style='color: red;'>Erro no upload: " . ($_FILES['test_photo']['error'] ?? 'não definido') . "</p>";
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <p>
        <label>Selecione uma foto:</label><br>
        <input type="file" name="test_photo" accept="image/*" required>
    </p>
    <p>
        <button type="submit">Testar Upload</button>
    </p>
</form>
