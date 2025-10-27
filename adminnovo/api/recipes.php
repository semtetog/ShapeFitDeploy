<?php
// adminnovo/api/recipes.php - API de Receitas

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Dados mockados para desenvolvimento
$recipes = [
    [
        'id' => 1,
        'name' => 'Salada de Frango Grelhado',
        'category' => 'Saladas',
        'author' => 'Admin',
        'created_at' => '2024-10-20 10:30:00'
    ],
    [
        'id' => 2,
        'name' => 'Smoothie de Banana',
        'category' => 'Bebidas',
        'author' => 'Admin',
        'created_at' => '2024-10-19 15:45:00'
    ],
    [
        'id' => 3,
        'name' => 'Arroz com Brócolis',
        'category' => 'Acompanhamentos',
        'author' => 'Admin',
        'created_at' => '2024-10-18 09:15:00'
    ]
];

echo json_encode([
    'success' => true,
    'recipes' => $recipes
]);
?>
