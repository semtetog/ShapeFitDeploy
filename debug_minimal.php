<?php
// Teste mínimo para identificar o problema
session_start();

// Simular dados básicos
$user_id = 75;

if ($_POST) {
    echo "<h2>TESTE MÍNIMO</h2>";
    echo "<pre>";
    
    // 1. Verificar se base64 está presente
    if (isset($_POST['profile_photo_base64'])) {
        echo "✅ Base64 presente: " . strlen($_POST['profile_photo_base64']) . " chars\n";
        
        // 2. Testar salvamento simples
        $upload_dir = __DIR__ . '/assets/images/users/';
        $filename = 'test_' . time() . '.jpg';
        $upload_path = $upload_dir . $filename;
        
        $base64Data = $_POST['profile_photo_base64'];
        $base64Data = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,'], '', $base64Data);
        $imageData = base64_decode($base64Data);
        
        if (file_put_contents($upload_path, $imageData)) {
            echo "✅ Arquivo salvo: $filename\n";
            echo "✅ Tamanho: " . filesize($upload_path) . " bytes\n";
        } else {
            echo "❌ Erro ao salvar arquivo\n";
        }
    } else {
        echo "❌ Base64 NÃO presente\n";
    }
    
    // 3. Verificar campos do formulário
    echo "\n--- CAMPOS RECEBIDOS ---\n";
    $required_fields = ['name', 'email', 'city', 'uf', 'phone_ddd', 'phone_number'];
    foreach ($required_fields as $field) {
        $value = $_POST[$field] ?? 'VAZIO';
        echo "$field: $value\n";
    }
    
    echo "</pre>";
    
    // 4. Teste de conexão com banco
    try {
        require_once 'includes/config.php';
        require_once 'includes/db.php';
        
        echo "<h3>Teste de Conexão com Banco</h3>";
        echo "<pre>";
        
        // Teste simples de query
        $test_sql = "SELECT id, name FROM sf_users WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($test_sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user) {
                echo "✅ Usuário encontrado: " . $user['name'] . "\n";
                
                // Teste de update simples
                $update_sql = "UPDATE sf_users SET name = ? WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $new_name = $user['name'] . '_test';
                    $stmt->bind_param("si", $new_name, $user_id);
                    if ($stmt->execute()) {
                        echo "✅ Update funcionou\n";
                        
                        // Reverter
                        $stmt->bind_param("si", $user['name'], $user_id);
                        $stmt->execute();
                        echo "✅ Revertido\n";
                    } else {
                        echo "❌ Update falhou: " . $stmt->error . "\n";
                    }
                    $stmt->close();
                } else {
                    echo "❌ Prepare falhou: " . $conn->error . "\n";
                }
            } else {
                echo "❌ Usuário não encontrado\n";
            }
        } else {
            echo "❌ Prepare falhou: " . $conn->error . "\n";
        }
        
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<h3>Erro no Banco:</h3>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Mínimo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Debug Mínimo - Identificar Problema</h1>
    
    <form method="POST">
        <p><strong>Campos obrigatórios:</strong></p>
        <input type="text" name="name" placeholder="Nome" value="teste" required><br><br>
        <input type="email" name="email" placeholder="Email" value="teste@teste.com" required><br><br>
        <input type="text" name="city" placeholder="Cidade" value="Teste" required><br><br>
        <input type="text" name="uf" placeholder="UF" value="MG" required><br><br>
        <input type="text" name="phone_ddd" placeholder="DDD" value="34"><br><br>
        <input type="text" name="phone_number" placeholder="Telefone" value="999999999"><br><br>
        
        <p><strong>Foto:</strong></p>
        <input type="file" id="photo-input" accept="image/*"><br><br>
        
        <button type="submit">Testar</button>
    </form>
    
    <script>
    document.getElementById('photo-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'profile_photo_base64';
                hiddenInput.value = e.target.result;
                document.querySelector('form').appendChild(hiddenInput);
                console.log('Base64 adicionado:', e.target.result.length);
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
