<?php
// admin/delete_recipe.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

// Exige que o administrador esteja logado para acessar esta página
requireAdminLogin();

// 1. Validação do ID
// Verifica se o ID foi passado pela URL e se é um número inteiro válido
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Se não houver ID ou se o ID não for um número, redireciona para a lista de receitas
    header('Location: recipes.php');
    exit;
}

$recipe_id = (int)$_GET['id'];

// 2. Preparar e Executar a Query de Exclusão
// É uma boa prática deletar primeiro as associações em tabelas "ponte"
// (como sf_recipe_categories) antes de deletar o registro principal.

$conn->begin_transaction(); // Inicia uma transação para garantir a integridade dos dados

try {
    // Deleta as associações de categorias
    $stmt_cat = $conn->prepare("DELETE FROM sf_recipe_categories WHERE recipe_id = ?");
    $stmt_cat->bind_param('i', $recipe_id);
    $stmt_cat->execute();
    $stmt_cat->close();

    // Adicione aqui a exclusão de outras tabelas relacionadas se houver (ex: ingredientes, passos, etc.)
    // Exemplo:
    // $stmt_ing = $conn->prepare("DELETE FROM sf_recipe_ingredients WHERE recipe_id = ?");
    // $stmt_ing->bind_param('i', $recipe_id);
    // $stmt_ing->execute();
    // $stmt_ing->close();

    // Finalmente, deleta a receita principal
    $stmt_recipe = $conn->prepare("DELETE FROM sf_recipes WHERE id = ?");
    $stmt_recipe->bind_param('i', $recipe_id);
    $stmt_recipe->execute();
    $stmt_recipe->close();

    // Se tudo deu certo, confirma as alterações no banco de dados
    $conn->commit();

} catch (mysqli_sql_exception $exception) {
    // Se ocorreu algum erro, desfaz todas as alterações
    $conn->rollback();
    
    // Opcional: registrar o erro em um log para depuração
    // error_log("Erro ao deletar receita: " . $exception->getMessage());

    // Redireciona com uma mensagem de erro (opcional)
    header('Location: recipes.php?error=deletefailed');
    exit;
}

// 3. Redirecionar de volta para a lista de receitas
// Adiciona um parâmetro na URL para mostrar uma mensagem de sucesso (opcional)
header('Location: recipes.php?success=deleted');
exit; // Garante que o script pare a execução após o redirecionamento
?>