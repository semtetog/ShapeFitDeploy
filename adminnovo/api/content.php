<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

$content = [
    ['id' => 1, 'title' => 'Guia de Nutrição', 'category' => 'Educativo', 'description' => 'Dicas de alimentação saudável', 'status' => 'published'],
    ['id' => 2, 'title' => 'Exercícios em Casa', 'category' => 'Fitness', 'description' => 'Rotina de exercícios sem equipamentos', 'status' => 'published']
];

echo json_encode(['success' => true, 'content' => $content]);
?>
