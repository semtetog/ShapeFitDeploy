<?php
// Arquivo: includes/auth.php (VERSÃO CORRIGIDA E FINAL)

require_once __DIR__ . '/config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário tem uma sessão ativa.
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Função "Gatekeeper": Exige que o usuário esteja logado.
 * Se não estiver logado, redireciona para o login.
 * Se estiver logado mas não completou o onboarding, redireciona para o onboarding.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_APP_URL . "/auth/login.php");
        exit();
    }

    $current_page = basename($_SERVER['PHP_SELF']);

    // Se a sessão diz que o onboarding NÃO está completo E o usuário
    // não está na página de onboarding, força o redirecionamento para lá.
    if (empty($_SESSION['onboarding_complete']) && $current_page !== 'onboarding.php') {
        header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
        exit();
    }
}

/**
 * Exige que o usuário seja um visitante (não logado).
 * Se estiver logado, redireciona para o dashboard principal (main_app.php).
 * A partir dali, a função requireLogin() fará a checagem do onboarding.
 */
function requireGuest() {
    if (isLoggedIn()) {
        // --- CORREÇÃO APLICADA AQUI ---
        // Redireciona para main_app.php. A lógica em requireLogin() cuidará do resto.
        header("Location: " . BASE_APP_URL . "/main_app.php"); 
        exit();
    }
}

/**
 * Função adicional para proteger a página de onboarding.
 * Se o usuário já completou, não deve poder acessá-la novamente.
 */
function redirectIfOnboardingComplete() {
    if (isset($_SESSION['onboarding_complete']) && $_SESSION['onboarding_complete'] === true) {
        header("Location: " . BASE_APP_URL . "/main_app.php");
        exit();
    }
}

/**
 * Regenera o ID da sessão para prevenir ataques de session fixation.
 */
function regenerateSession() {
    if (isset($_SESSION['SESSION_REGENERATED_RECENTLY']) && $_SESSION['SESSION_REGENERATED_RECENTLY'] > time() - 5) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['SESSION_REGENERATED_RECENTLY'] = time();
}

/**
 * Busca os dados de um usuário com base em um token de autenticação.
 */
function getUserByAuthToken(mysqli $conn, ?string $token): ?array {
    if (empty($token)) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, name, email, points FROM sf_users WHERE auth_token = ? AND auth_token_expires_at > NOW()");
    if (!$stmt) return null;
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

?>