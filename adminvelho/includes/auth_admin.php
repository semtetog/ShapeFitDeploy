<?php
// admin/includes/auth_admin.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

function requireAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Redireciona para a página de login se não estiver autenticado
        header("Location: index.php");
        exit;
    }
}

// Função para verificar se está logado (sem redirecionar)
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}