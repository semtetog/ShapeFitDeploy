<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$challenges = [
    ['id' => 1, 'name' => 'Desafio 30 Dias', 'category' => 'Fitness', 'description' => 'Exercícios diários por 30 dias', 'status' => 'active'],
    ['id' => 2, 'name' => 'Desafio Alimentação', 'category' => 'Nutrição', 'description' => 'Alimentação saudável por 21 dias', 'status' => 'active']
];

echo json_encode(['success' => true, 'challenges' => $challenges]);
?>
