<?php
/**
 * Página de administração para executar a migração de missões
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Buscar todos os usuários que completaram o onboarding
        $sql = "SELECT id FROM sf_users WHERE onboarding_complete = 1";
        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception("Erro ao buscar usuários: " . $conn->error);
        }
        
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $total_users = count($users);
        
        $migrated_count = 0;
        $already_had_missions = 0;
        $error_count = 0;
        $errors = [];
        
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
                $error_count++;
                $errors[] = "Usuário #{$user_id}: Erro ao preparar statement - {$conn->error}";
                continue;
            }
            
            $copy_stmt->bind_param("i", $user_id);
            
            if ($copy_stmt->execute()) {
                $affected = $copy_stmt->affected_rows;
                $migrated_count++;
            } else {
                $error_count++;
                $errors[] = "Usuário #{$user_id}: Erro ao executar - {$copy_stmt->error}";
            }
            
            $copy_stmt->close();
        }
        
        // Verificar se há algum usuário sem missões
        $remaining_sql = "
            SELECT u.id 
            FROM sf_users u 
            LEFT JOIN sf_user_routine_items uri ON u.id = uri.user_id 
            WHERE u.onboarding_complete = 1 AND uri.id IS NULL
        ";
        $remaining_result = $conn->query($remaining_sql);
        $remaining_users = $remaining_result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Migração concluída!',
            'total_users' => $total_users,
            'migrated_count' => $migrated_count,
            'already_had_missions' => $already_had_missions,
            'error_count' => $error_count,
            'remaining_users' => count($remaining_users),
            'errors' => $errors
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração de Missões - Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
        }
        h1 {
            color: #FF6B00;
            margin-bottom: 20px;
        }
        p {
            color: #ccc;
            line-height: 1.6;
        }
        button {
            background: #FF6B00;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        button:hover {
            background: #FF8C00;
        }
        button:disabled {
            background: #666;
            cursor: not-allowed;
        }
        #result {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
            display: none;
        }
        #result.success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        #result.error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.3);
        }
        .stats {
            margin-top: 15px;
        }
        .stat {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }
        .stat-label {
            font-weight: bold;
            color: #FF6B00;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Migração de Missões de Rotina</h1>
        <p>
            Este script cria missões personalizadas para todos os usuários que completaram o onboarding 
            mas ainda não possuem missões personalizadas.
        </p>
        <p>
            As missões padrão serão copiadas para cada usuário, permitindo que cada um tenha suas próprias 
            missões editáveis independentemente.
        </p>
        
        <button id="migrateBtn" onclick="runMigration()">Executar Migração</button>
        
        <div id="result"></div>
    </div>
    
    <script>
        async function runMigration() {
            const btn = document.getElementById('migrateBtn');
            const result = document.getElementById('result');
            
            btn.disabled = true;
            btn.textContent = 'Executando migração...';
            result.style.display = 'none';
            
            try {
                const response = await fetch('migrate_routine_missions.php', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    result.className = 'success';
                    result.innerHTML = `
                        <h3>✅ Migração Concluída!</h3>
                        <div class="stats">
                            <div class="stat">
                                <span class="stat-label">Total de usuários:</span> ${data.total_users}
                            </div>
                            <div class="stat">
                                <span class="stat-label">Usuários migrados:</span> ${data.migrated_count}
                            </div>
                            <div class="stat">
                                <span class="stat-label">Já possuíam missões:</span> ${data.already_had_missions}
                            </div>
                            <div class="stat">
                                <span class="stat-label">Erros:</span> ${data.error_count}
                            </div>
                            <div class="stat">
                                <span class="stat-label">Usuários sem missões restantes:</span> ${data.remaining_users}
                            </div>
                        </div>
                        ${data.errors.length > 0 ? `<h4>Erros:</h4><pre>${data.errors.join('\n')}</pre>` : ''}
                    `;
                } else {
                    result.className = 'error';
                    result.innerHTML = `<h3>❌ Erro</h3><p>${data.message}</p>`;
                }
                
                result.style.display = 'block';
            } catch (error) {
                result.className = 'error';
                result.innerHTML = `<h3>❌ Erro</h3><p>${error.message}</p>`;
                result.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Executar Migração';
            }
        }
    </script>
</body>
</html>

