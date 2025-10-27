<?php
// admin/index.php (Página de Login - VERSÃO CORRIGIDA)

// Habilita a exibição de erros para debug (lembre-se de remover em produção)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CORREÇÃO AQUI: Captura o objeto de conexão retornado ---
    $conn = require __DIR__ . '/../includes/db.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // A linha 21, que dava o erro, agora vai funcionar porque $conn é um objeto
    $stmt = $conn->prepare("SELECT id, password_hash, full_name FROM sf_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password_hash'])) {
            // Sucesso no login
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            
            $stmt->close();
            $conn->close();
            
            header("Location: dashboard.php");
            exit;
        }
    }
    
    $error_message = 'Usuário ou senha inválidos.';
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Painel Admin</title>
    <!-- O caminho para o CSS deve ser relativo à localização do index.php -->
    <link rel="stylesheet" href="assets/css/admin_login.css">
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST" action="index.php">
            <h2>Fitlab Admin</h2>
            <p>Acesso ao painel de gerenciamento</p>
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>
    </div>
</body>
</html>