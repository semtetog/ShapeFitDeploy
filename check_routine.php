<?php
// check_available_routines.php - Verificar rotinas disponíveis

require_once 'includes/config.php';
require_once 'includes/db.php';

$user_id = 77; // Substitua pelo ID do seu usuário

echo "<h2>📋 Rotinas Disponíveis</h2>";

try {
    // 1. Verificar todas as rotinas no banco
    echo "<h3>🗂️ Todas as Rotinas no Banco:</h3>";
    $stmt = $conn->prepare("SELECT id, title, description FROM sf_routine_items ORDER BY id");
    $stmt->execute();
    $all_routines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($all_routines)) {
        echo "<p style='color: red;'>❌ Nenhuma rotina encontrada no banco!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Descrição</th></tr>";
        foreach ($all_routines as $routine) {
            echo "<tr>";
            echo "<td>{$routine['id']}</td>";
            echo "<td>" . htmlspecialchars($routine['title']) . "</td>";
            echo "<td>" . htmlspecialchars($routine['description']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Verificar rotinas do usuário (onboarding)
    echo "<h3>👤 Rotinas do Usuário (Onboarding):</h3>";
    
    // Buscar perfil do usuário
    $stmt = $conn->prepare("SELECT exercise_type FROM sf_user_onboarding_completion WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($profile && !empty($profile['exercise_type'])) {
        $activities = preg_split('/,\s*/', trim($profile['exercise_type']), -1, PREG_SPLIT_NO_EMPTY);
        
        if (!empty($activities)) {
            echo "<p><strong>Exercícios definidos no onboarding:</strong></p>";
            echo "<ul>";
            foreach ($activities as $activity) {
                $clean_activity = trim($activity);
                $mission_id = 'onboarding_' . $clean_activity;
                echo "<li><strong>{$clean_activity}</strong> (ID: {$mission_id})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ Nenhum exercício definido no onboarding</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Perfil de onboarding não encontrado</p>";
    }
    
    // 3. Verificar rotinas já completadas hoje
    echo "<h3>✅ Rotinas Completadas Hoje:</h3>";
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT 
            url.routine_item_id,
            ri.title,
            url.date
        FROM sf_user_routine_log url
        LEFT JOIN sf_routine_items ri ON url.routine_item_id = ri.id
        WHERE url.user_id = ? AND url.date = ? AND url.is_completed = 1
        ORDER BY url.routine_item_id
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $completed_today = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($completed_today)) {
        echo "<p style='color: blue;'>ℹ️ Nenhuma rotina completada hoje</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Data</th></tr>";
        foreach ($completed_today as $completed) {
            echo "<tr>";
            echo "<td>{$completed['routine_item_id']}</td>";
            echo "<td>" . htmlspecialchars($completed['title'] ?? 'Rotina não encontrada') . "</td>";
            echo "<td>{$completed['date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Sugestões de teste
    echo "<h3>🧪 Sugestões para Teste:</h3>";
    
    if (!empty($all_routines)) {
        $first_routine = $all_routines[0];
        echo "<p><strong>Para testar, use a rotina ID:</strong> {$first_routine['id']} ({$first_routine['title']})</p>";
        
        // Verificar se esta rotina já foi completada hoje
        $stmt = $conn->prepare("SELECT id FROM sf_user_routine_log WHERE user_id = ? AND routine_item_id = ? AND date = ?");
        $stmt->bind_param("iis", $user_id, $first_routine['id'], $today);
        $stmt->execute();
        $already_completed = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($already_completed) {
            echo "<p style='color: orange;'>⚠️ Esta rotina já foi completada hoje</p>";
            echo "<p><strong>Solução:</strong> Use <a href='clear_today_logs.php'>clear_today_logs.php</a> para limpar os logs de hoje</p>";
        } else {
            echo "<p style='color: green;'>✅ Esta rotina pode ser testada agora!</p>";
        }
    }
    
    // 5. Verificar pontos atuais
    $stmt = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $current_points = $stmt->get_result()->fetch_assoc()['points'];
    $stmt->close();
    
    echo "<p><strong>Pontos atuais:</strong> {$current_points}</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Data/Hora:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
