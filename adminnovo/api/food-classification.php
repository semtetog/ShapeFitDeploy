<?php
// adminnovo/api/food-classification.php - API de Classificação

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Dados mockados para desenvolvimento
$categories = [
    [
        'id' => 1,
        'name' => 'Frutas',
        'description' => 'Alimentos doces e nutritivos',
        'icon' => 'fas fa-apple-alt',
        'foodCount' => 150
    ],
    [
        'id' => 2,
        'name' => 'Vegetais',
        'description' => 'Vegetais frescos e saudáveis',
        'icon' => 'fas fa-carrot',
        'foodCount' => 200
    ],
    [
        'id' => 3,
        'name' => 'Proteínas',
        'description' => 'Carnes, ovos e leguminosas',
        'icon' => 'fas fa-drumstick-bite',
        'foodCount' => 100
    ],
    [
        'id' => 4,
        'name' => 'Carboidratos',
        'description' => 'Arroz, massas e pães',
        'icon' => 'fas fa-bread-slice',
        'foodCount' => 80
    ]
];

echo json_encode([
    'success' => true,
    'categories' => $categories
]);
?>
