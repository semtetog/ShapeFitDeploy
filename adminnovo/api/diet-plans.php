<?php
// adminnovo/api/diet-plans.php - API de Planos Alimentares

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Dados mockados para desenvolvimento
$plans = [
    [
        'id' => 1,
        'name' => 'Plano Emagrecimento',
        'goal' => 'Perda de peso',
        'description' => 'Plano focado em déficit calórico',
        'status' => 'active'
    ],
    [
        'id' => 2,
        'name' => 'Plano Hipertrofia',
        'goal' => 'Ganho de massa muscular',
        'description' => 'Plano rico em proteínas',
        'status' => 'active'
    ],
    [
        'id' => 3,
        'name' => 'Plano Manutenção',
        'goal' => 'Manter peso atual',
        'description' => 'Plano equilibrado',
        'status' => 'inactive'
    ]
];

echo json_encode([
    'success' => true,
    'plans' => $plans
]);
?>
