<?php
/**
 * Teste simples da API de rotina
 */

session_start();
require_once __DIR__ . '/../../includes/config.php';

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Debug básico
error_log('DEBUG - Teste da API iniciado');
error_log('DEBUG - DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : 'NÃO DEFINIDO'));
error_log('DEBUG - DB_USER: ' . (defined('DB_USER') ? DB_USER : 'NÃO DEFINIDO'));
error_log('DEBUG - DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : 'NÃO DEFINIDO'));

// Verificar se as constantes estão definidas
if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    error_log('DEBUG - Constantes de banco não definidas');
    echo json_encode(['success' => false, 'message' => 'Configuração de banco não encontrada']);
    exit;
}

// Testar conexão
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log('DEBUG - Erro de conexão: ' . $conn->connect_error);
        echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $conn->connect_error]);
        exit;
    }
    
    error_log('DEBUG - Conexão bem-sucedida');
    echo json_encode(['success' => true, 'message' => 'API funcionando corretamente']);
    
    $conn->close();
} catch (Exception $e) {
    error_log('DEBUG - Exceção: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>
