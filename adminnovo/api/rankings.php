<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$rankings = [
    ['id' => 1, 'name' => 'Ranking Semanal', 'category' => 'Pontuação', 'description' => 'Classificação semanal de usuários', 'status' => 'active'],
    ['id' => 2, 'name' => 'Ranking Mensal', 'category' => 'Pontuação', 'description' => 'Classificação mensal de usuários', 'status' => 'active']
];

echo json_encode(['success' => true, 'rankings' => $rankings]);
?>
