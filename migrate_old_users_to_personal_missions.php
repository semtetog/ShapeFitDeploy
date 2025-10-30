<?php
/**
 * Script de migração: Copiar missões padrão para usuários antigos
 * 
 * Este script busca todos os usuários que completaram o onboarding
 * mas não possuem missões personalizadas e cria cópias das missões
 * padrão para cada um deles.
 */

// Configuração mínima para CLI
define('APP_ROOT_PATH', __DIR__);

// Configuração direta do banco de dados
$db_host = '127.0.0.1:3306';
$db_user = 'u785537399_shapefit';
$db_pass = 'Gameroficial2*';
$db_name = 'u785537399_shapefit';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error . "\n");
}
$conn->set_charset("utf8mb4");

echo "=== Migração de Missões para Usuários Antigos ===\n\n";

// Buscar todos os usuários que completaram o onboarding
$sql = "SELECT id FROM sf_users WHERE onboarding_complete = 1";
$result = $conn->query($sql);

if (!$result) {
    die("Erro ao buscar usuários: " . $conn->error . "\n");
}

$users = $result->fetch_all(MYSQLI_ASSOC);
$total_users = count($users);
echo "Total de usuários encontrados: {$total_users}\n\n";

$migrated_count = 0;
$already_had_missions = 0;
$error_count = 0;

foreach ($users as $user) {
    $user_id = $user['id'];
    
    // Verificar se o usuário já possui missões personalizadas
    $check_sql = "SELECT COUNT(*) as count FROM sf_user_routine_items WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    $check_stmt->close();
    
    if ($check_row['count'] > 0) {
        echo "Usuário #{$user_id}: Já possui missões personalizadas ({$check_row['count']} missões)\n";
        $already_had_missions++;
        continue;
    }
    
    // Copiar missões padrão para o usuário
    $copy_sql = "
        INSERT INTO sf_user_routine_items (user_id, title, icon_class, description, is_exercise, exercise_type)
        SELECT ?, title, icon_class, description, is_exercise, exercise_type
        FROM sf_routine_items
        WHERE is_active = 1 AND default_for_all_users = 1
    ";
    
    $copy_stmt = $conn->prepare($copy_sql);
    if (!$copy_stmt) {
        echo "Usuário #{$user_id}: ERRO ao preparar statement - {$conn->error}\n";
        $error_count++;
        continue;
    }
    
    $copy_stmt->bind_param("i", $user_id);
    
    if ($copy_stmt->execute()) {
        $affected = $copy_stmt->affected_rows;
        echo "Usuário #{$user_id}: Migrado com sucesso ({$affected} missões criadas)\n";
        $migrated_count++;
    } else {
        echo "Usuário #{$user_id}: ERRO ao executar - {$copy_stmt->error}\n";
        $error_count++;
    }
    
    $copy_stmt->close();
}

echo "\n=== Resumo da Migração ===\n";
echo "Total de usuários processados: {$total_users}\n";
echo "Usuários migrados com sucesso: {$migrated_count}\n";
echo "Usuários que já possuíam missões: {$already_had_missions}\n";
echo "Usuários com erro: {$error_count}\n";

// Verificar se há algum usuário sem missões
$remaining_sql = "
    SELECT u.id 
    FROM sf_users u 
    LEFT JOIN sf_user_routine_items uri ON u.id = uri.user_id 
    WHERE u.onboarding_complete = 1 AND uri.id IS NULL
";
$remaining_result = $conn->query($remaining_sql);
$remaining_users = $remaining_result->fetch_all(MYSQLI_ASSOC);

if (count($remaining_users) > 0) {
    echo "\n⚠️  ATENÇÃO: {$remaining_result->num_rows} usuários ainda não possuem missões:\n";
    foreach ($remaining_users as $user) {
        echo "  - Usuário #{$user['id']}\n";
    }
} else {
    echo "\n✅ Todos os usuários possuem missões personalizadas!\n";
}

$conn->close();
echo "\n=== Migração Concluída ===\n";

