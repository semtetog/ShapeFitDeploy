<?php
// ARQUIVO: process_delete_account.php (VERSÃO FINAL E CORRIGIDA COM BASE NO PHPMYADMIN)

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// 1. SEGURANÇA BÁSICA
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: delete_account.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Erro de validação de segurança.");
}
if (!isset($_POST['confirmation_text']) || $_POST['confirmation_text'] !== 'EXCLUIR') {
    header("Location: delete_account.php?error=confirmation_failed");
    exit();
}

// 2. PEGA O ID DO USUÁRIO DA SESSÃO
$user_id_to_delete = $_SESSION['user_id'];
if (empty($user_id_to_delete)) {
    die("Erro: ID de usuário não encontrado.");
}


// 3. LÓGICA DE EXCLUSÃO (COM A LISTA CORRETA DE TABELAS)
$conn->begin_transaction();

try {
    // ESTA É A LISTA CORRETA E COMPLETA DE TABELAS COM DADOS DO USUÁRIO
    $tables_to_delete_from = [
        'sf_user_daily_tracking',
        'sf_user_favorite_recipes',
        'sf_user_meal_log',          // Nome correto do diário alimentar
        'sf_user_measurements',
        'sf_user_points_log',
        'sf_user_profiles',
        'sf_user_routine_log',       // Nome correto do log da rotina
        'sf_user_selected_restrictions',
        'sf_user_weight_history',
        'sf_recipe_ratings'
    ];

    foreach ($tables_to_delete_from as $table) {
        $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_delete);
        $stmt->execute();
        $stmt->close();
    }

    // POR ÚLTIMO: exclui o registro principal da tabela de usuários
    $stmt_main = $conn->prepare("DELETE FROM `sf_users` WHERE id = ?");
    $stmt_main->bind_param("i", $user_id_to_delete);
    $stmt_main->execute();
    $stmt_main->close();

    // Se tudo deu certo, confirma as exclusões no banco
    $conn->commit();

} catch (Exception $e) {
    // Se qualquer comando falhar, desfaz TODAS as alterações
    $conn->rollback();
    
    // Loga o erro para você poder investigar, caso algo ainda dê errado
    error_log("Falha ao excluir conta para o user_id {$user_id_to_delete}: " . $e->getMessage());
    
    // Redireciona de volta com uma mensagem de erro
    header("Location: delete_account.php?error=db_failure");
    exit();
}

// 4. LIMPEZA FINAL DA SESSÃO
session_unset();
session_destroy();

// 5. REDIRECIONA PARA A PÁGINA DE LOGIN
header("Location: " . BASE_APP_URL . "/account_deleted.php");
exit();
?>