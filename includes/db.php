<?php
$db_host = '127.0.0.1:3306'; // Ou o host do seu DB na Hostinger
$db_user = 'u785537399_shapefit';
$db_pass = 'Gameroficial2*';
$db_name = 'u785537399_shapefit'; // O banco de dados onde as tabelas sf_* foram criadas

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);


// =========================================================================
//      CORREÇÃO DEFINITIVA PARA NÚMEROS
// =========================================================================
// Garante que o PHP receba os números do banco de dados como números (float/int),
// e não como texto (string). Isso resolve o problema de valores zerados.
if (function_exists('mysqli_get_client_stats')) {
    mysqli_options($conn, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
}
// =========================================================================


if ($conn->connect_error) {
    // Em produção, logue o erro em vez de mostrá-lo diretamente
    error_log("Connection failed: " . $conn->connect_error);
    die("Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.");
}
$conn->set_charset("utf8mb4");

return $conn;
?>