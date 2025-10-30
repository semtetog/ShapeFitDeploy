<?php
/**
 * Script para criar tabela de missões personalizadas por usuário
 * Execute este script UMA VEZ para criar a estrutura necessária
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
requireAdminLogin();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Criando tabela sf_user_routine_items...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS sf_user_routine_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    icon_class VARCHAR(100) DEFAULT 'fa-check-circle',
    description TEXT,
    is_exercise TINYINT(1) DEFAULT 0,
    exercise_type VARCHAR(50) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "<p style='color: green;'>✓ Tabela sf_user_routine_items criada com sucesso!</p>";
} else {
    echo "<p style='color: red;'>✗ Erro ao criar tabela: " . $conn->error . "</p>";
}

// Verificar se já existe
$check = $conn->query("SELECT COUNT(*) as count FROM sf_user_routine_items");
if ($check) {
    $row = $check->fetch_assoc();
    echo "<p>Total de missões personalizadas existentes: " . $row['count'] . "</p>";
}

$conn->close();
?>
