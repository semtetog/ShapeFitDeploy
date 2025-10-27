<?php
// Teste simples para verificar se a página está funcionando

echo "Teste: Página carregada com sucesso!<br>";

// Verificar se as constantes estão definidas
if (defined('BASE_APP_URL')) {
    echo "BASE_APP_URL: " . BASE_APP_URL . "<br>";
} else {
    echo "BASE_APP_URL não definida<br>";
}

// Verificar se a sessão está funcionando
session_start();
if (isset($_SESSION['user_id'])) {
    echo "Usuário logado: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "Usuário não logado<br>";
}

// Verificar se o ID está sendo passado
if (isset($_GET['id'])) {
    echo "ID da refeição: " . $_GET['id'] . "<br>";
} else {
    echo "ID da refeição não fornecido<br>";
}

echo "Teste concluído!";
?>
