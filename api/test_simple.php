<?php
// API de teste muito simples

header('Content-Type: application/json; charset=utf-8');

// Dados de teste hardcoded
$results = [
    [
        'id' => 1,
        'name' => 'Arroz integral',
        'image_filename' => 'placeholder_food.jpg',
        'kcal_per_serving' => 124,
        'protein_g_per_serving' => 2.6,
        'carbs_g_per_serving' => 25.8,
        'fat_g_per_serving' => 0.9,
        'type' => 'food'
    ],
    [
        'id' => 2,
        'name' => 'Arroz branco',
        'image_filename' => 'placeholder_food.jpg',
        'kcal_per_serving' => 130,
        'protein_g_per_serving' => 2.7,
        'carbs_g_per_serving' => 28.2,
        'fat_g_per_serving' => 0.3,
        'type' => 'food'
    ]
];

echo json_encode([
    'success' => true,
    'data' => $results
]);
?>
