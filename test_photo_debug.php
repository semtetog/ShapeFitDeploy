<?php
// Teste específico para debug da foto
session_start();

// Simular dados do usuário
$user_id = 75;

if ($_POST) {
    echo "<h2>DEBUG - Teste de Upload de Foto</h2>";
    echo "<pre>";
    
    // Verificar se base64 está presente
    if (isset($_POST['profile_photo_base64'])) {
        echo "✅ Base64 presente!\n";
        echo "Tamanho: " . strlen($_POST['profile_photo_base64']) . " caracteres\n";
        
        // Testar decodificação
        $base64Data = $_POST['profile_photo_base64'];
        $base64Data = str_replace('data:image/jpeg;base64,', '', $base64Data);
        $base64Data = str_replace('data:image/png;base64,', '', $base64Data);
        
        $imageData = base64_decode($base64Data);
        echo "Dados da imagem: " . strlen($imageData) . " bytes\n";
        
        // Testar salvamento
        $upload_dir = __DIR__ . '/assets/images/users/';
        $filename = 'debug_' . $user_id . '_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        
        if (file_put_contents($upload_path, $imageData)) {
            echo "✅ Arquivo salvo: $filename\n";
            echo "Tamanho do arquivo: " . filesize($upload_path) . " bytes\n";
            
            // Testar se conseguimos ler o arquivo
            if (file_exists($upload_path)) {
                echo "✅ Arquivo existe e pode ser lido\n";
                
                // Testar se é uma imagem válida
                $imageInfo = getimagesize($upload_path);
                if ($imageInfo) {
                    echo "✅ Imagem válida: " . $imageInfo['mime'] . "\n";
                    echo "Dimensões: " . $imageInfo[0] . "x" . $imageInfo[1] . "\n";
                } else {
                    echo "❌ Arquivo não é uma imagem válida\n";
                }
            } else {
                echo "❌ Arquivo não pode ser lido\n";
            }
        } else {
            echo "❌ Erro ao salvar arquivo\n";
        }
    } else {
        echo "❌ Base64 NÃO presente\n";
    }
    
    echo "\n--- Dados POST ---\n";
    foreach ($_POST as $key => $value) {
        if ($key === 'profile_photo_base64') {
            echo "$key: [BASE64 - " . strlen($value) . " chars]\n";
        } else {
            echo "$key: " . (is_array($value) ? implode(',', $value) : $value) . "\n";
        }
    }
    
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Foto - Teste</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Debug Upload de Foto</h1>
    <p>Este teste verifica se o problema está no processamento da imagem ou na query do banco.</p>
    
    <form method="POST">
        <input type="file" id="photo-input" accept="image/*" style="margin: 10px 0;">
        <br>
        <button type="submit">Testar Upload</button>
    </form>
    
    <script>
    document.getElementById('photo-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            console.log('Arquivo selecionado:', file.name, file.size);
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Data = e.target.result;
                console.log('Base64 criado, tamanho:', base64Data.length);
                
                // Adicionar input hidden
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'profile_photo_base64';
                hiddenInput.value = base64Data;
                document.querySelector('form').appendChild(hiddenInput);
                
                console.log('Input hidden adicionado ao formulário');
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
