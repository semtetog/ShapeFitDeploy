<?php
// --- INÍCIO DA SUBSTITUIÇÃO COMPLETA ---

require_once '../includes/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Opcional, mas bom para segurança: remove o token do banco de dados ao deslogar
if (isset($_SESSION['user_id'])) {
    require_once APP_ROOT_PATH . '/includes/db.php';
    $stmt_logout = $conn->prepare("UPDATE sf_users SET auth_token = NULL, auth_token_expires_at = NULL WHERE id = ?");
    if ($stmt_logout) {
        $stmt_logout->bind_param("i", $_SESSION['user_id']);
        $stmt_logout->execute();
        $stmt_logout->close();
    }
}

// Limpa a sessão do servidor
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Usa JavaScript para limpar o 'crachá' do app e redirecionar
echo "<script>
    // Remove o 'crachá' do 'porta-luvas' do app
    localStorage.removeItem('shapefitUserToken');
    // Agora redireciona para a tela de login
    window.location.href = '" . BASE_APP_URL . "/auth/login.php';
</script>";
exit();

// --- FIM DA SUBSTITUIÇÃO COMPLETA ---
?>