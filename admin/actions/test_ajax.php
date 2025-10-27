<?php
// Teste AJAX SIMPLES
ob_start();
ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Teste funcionou!', 'data' => $_POST]);
ob_end_flush();
exit;

