<?php
// adminnovo/api/users.php - API de Usuários

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Dados mockados para desenvolvimento
$users = [
    [
        'id' => 1,
        'name' => 'João Silva',
        'email' => 'joao@email.com',
        'created_at' => '2024-10-20 10:30:00'
    ],
    [
        'id' => 2,
        'name' => 'Maria Santos',
        'email' => 'maria@email.com',
        'created_at' => '2024-10-19 15:45:00'
    ],
    [
        'id' => 3,
        'name' => 'Pedro Costa',
        'email' => 'pedro@email.com',
        'created_at' => '2024-10-18 09:15:00'
    ],
    [
        'id' => 4,
        'name' => 'Ana Oliveira',
        'email' => 'ana@email.com',
        'created_at' => '2024-10-17 14:20:00'
    ],
    [
        'id' => 5,
        'name' => 'Carlos Lima',
        'email' => 'carlos@email.com',
        'created_at' => '2024-10-16 11:30:00'
    ]
];

echo json_encode([
    'success' => true,
    'users' => $users,
    'totalPages' => 1
]);
?>
