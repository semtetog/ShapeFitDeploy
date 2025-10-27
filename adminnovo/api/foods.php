<?php
// adminnovo/api/foods.php - API de Alimentos

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Dados mockados para desenvolvimento
$foods = [
    [
        'id' => 1,
        'name' => 'Maçã',
        'category' => 'Frutas',
        'calories' => 52,
        'protein' => 0.3
    ],
    [
        'id' => 2,
        'name' => 'Banana',
        'category' => 'Frutas',
        'calories' => 89,
        'protein' => 1.1
    ],
    [
        'id' => 3,
        'name' => 'Frango Grelhado',
        'category' => 'Proteínas',
        'calories' => 165,
        'protein' => 31
    ],
    [
        'id' => 4,
        'name' => 'Arroz Integral',
        'category' => 'Carboidratos',
        'calories' => 111,
        'protein' => 2.6
    ],
    [
        'id' => 5,
        'name' => 'Brócolis',
        'category' => 'Vegetais',
        'calories' => 34,
        'protein' => 2.8
    ]
];

echo json_encode([
    'success' => true,
    'foods' => $foods
]);
?>
