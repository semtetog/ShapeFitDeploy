<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$groups = [
    ['id' => 1, 'name' => 'Grupo Premium', 'challenge' => 'Desafio VIP', 'description' => 'Grupo exclusivo para usuários premium', 'status' => 'active'],
    ['id' => 2, 'name' => 'Grupo Iniciantes', 'challenge' => 'Primeiros Passos', 'description' => 'Grupo para usuários iniciantes', 'status' => 'active']
];

echo json_encode(['success' => true, 'groups' => $groups]);
?>
