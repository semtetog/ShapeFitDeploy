<?php
/**
 * SCRIPT DE TESTE - Implementação do Sistema de Progresso V2
 * 
 * Execute este arquivo no navegador para verificar se tudo foi implementado corretamente.
 * URL: https://seu-dominio.com/test_implementation.php
 * 
 * ⚠️ IMPORTANTE: Remova este arquivo após testar!
 */

require_once 'includes/config.php';
require_once 'includes/db.php';

// Estilo simples
echo <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Implementação - Progresso V2</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e1e1e 0%, #2d2d2d 100%);
            color: #ffffff;
            padding: 40px 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #FF6B00;
            text-align: center;
        }
        
        .subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 40px;
        }
        
        .test-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .test-section h2 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #FF6B00;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-item {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.02);
            border-left: 4px solid #666;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .test-item.success {
            border-left-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }
        
        .test-item.error {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .test-item.warning {
            border-left-color: #eab308;
            background: rgba(234, 179, 8, 0.1);
        }
        
        .icon {
            font-size: 1.2rem;
        }
        
        .status {
            font-weight: 700;
            margin-right: 10px;
        }
        
        .details {
            font-size: 0.9rem;
            color: #aaa;
            margin-top: 5px;
        }
        
        code {
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }
        
        .summary {
            background: linear-gradient(135deg, rgba(255, 107, 0, 0.1) 0%, rgba(255, 133, 51, 0.1) 100%);
            border: 2px solid rgba(255, 107, 0, 0.3);
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            margin-top: 30px;
        }
        
        .summary h2 {
            color: #FF6B00;
            margin-bottom: 15px;
        }
        
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat.success .stat-number { color: #22c55e; }
        .stat.error .stat-number { color: #ef4444; }
        .stat.warning .stat-number { color: #eab308; }
        
        .stat-label {
            font-size: 0.9rem;
            color: #aaa;
        }
        
        .warning-box {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Implementação</h1>
        <p class="subtitle">Progresso V2.0 - ShapeFit</p>
HTML;

// Contadores
$total_tests = 0;
$passed_tests = 0;
$failed_tests = 0;
$warnings = 0;

function printTest($label, $status, $details = '') {
    global $total_tests, $passed_tests, $failed_tests, $warnings;
    $total_tests++;
    
    $class = 'test-item';
    $icon = '⏳';
    $statusText = 'TESTANDO';
    
    if ($status === 'success') {
        $class .= ' success';
        $icon = '✅';
        $statusText = 'OK';
        $passed_tests++;
    } elseif ($status === 'error') {
        $class .= ' error';
        $icon = '❌';
        $statusText = 'ERRO';
        $failed_tests++;
    } elseif ($status === 'warning') {
        $class .= ' warning';
        $icon = '⚠️';
        $statusText = 'ATENÇÃO';
        $warnings++;
    }
    
    echo "<div class='$class'>";
    echo "<div><span class='icon'>$icon</span> <span class='status'>$statusText</span> $label</div>";
    if ($details) {
        echo "<div class='details'>$details</div>";
    }
    echo "</div>";
}

// ================================
// TESTE 1: CONEXÃO COM BANCO
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>🔌</span> 1. Conexão com Banco de Dados</h2>";

try {
    if ($conn && $conn->ping()) {
        printTest('Conexão com banco de dados', 'success', 'Conectado com sucesso');
    } else {
        printTest('Conexão com banco de dados', 'error', 'Falha na conexão');
    }
} catch (Exception $e) {
    printTest('Conexão com banco de dados', 'error', 'Erro: ' . $e->getMessage());
}

echo "</div>";

// ================================
// TESTE 2: ESTRUTURA DO BANCO
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>🗄️</span> 2. Estrutura do Banco de Dados</h2>";

// Verificar colunas da sf_user_daily_tracking
$columns_to_check = ['steps_daily', 'workout_hours', 'cardio_hours', 'sleep_hours'];
$result = $conn->query("DESCRIBE sf_user_daily_tracking");

if ($result) {
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    foreach ($columns_to_check as $column) {
        if (in_array($column, $existing_columns)) {
            printTest("Coluna <code>$column</code> em sf_user_daily_tracking", 'success', 'Coluna existe');
        } else {
            printTest("Coluna <code>$column</code> em sf_user_daily_tracking", 'error', 'Coluna não encontrada! Execute o script SQL.');
        }
    }
} else {
    printTest('Verificação de colunas', 'error', 'Não foi possível verificar a tabela');
}

// Verificar tabela sf_user_goals
$result = $conn->query("SHOW TABLES LIKE 'sf_user_goals'");
if ($result && $result->num_rows > 0) {
    printTest('Tabela <code>sf_user_goals</code>', 'success', 'Tabela existe');
    
    // Verificar se tem dados
    $result = $conn->query("SELECT COUNT(*) as total FROM sf_user_goals");
    if ($result) {
        $row = $result->fetch_assoc();
        $total = $row['total'];
        if ($total > 0) {
            printTest('Metas de usuários', 'success', "$total metas cadastradas");
        } else {
            printTest('Metas de usuários', 'warning', 'Tabela existe mas está vazia');
        }
    }
} else {
    printTest('Tabela <code>sf_user_goals</code>', 'error', 'Tabela não encontrada! Execute o script SQL.');
}

echo "</div>";

// ================================
// TESTE 3: ARQUIVOS PHP
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>📄</span> 3. Arquivos PHP</h2>";

$files_to_check = [
    'progress_v2.php' => 'Página de visualização de progresso',
    'update_daily_tracking.php' => 'Página de entrada manual de dados'
];

foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        printTest("<code>$file</code>", 'success', "$description - Arquivo existe");
    } else {
        printTest("<code>$file</code>", 'error', "$description - Arquivo não encontrado!");
    }
}

echo "</div>";

// ================================
// TESTE 4: DADOS DE TESTE
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>📊</span> 4. Dados de Teste</h2>";

// Verificar se existem usuários
$result = $conn->query("SELECT COUNT(*) as total FROM sf_users");
if ($result) {
    $row = $result->fetch_assoc();
    $total_users = $row['total'];
    if ($total_users > 0) {
        printTest('Usuários cadastrados', 'success', "$total_users usuários encontrados");
    } else {
        printTest('Usuários cadastrados', 'warning', 'Nenhum usuário encontrado');
    }
}

// Verificar dados em sf_user_daily_tracking
$result = $conn->query("SELECT COUNT(*) as total FROM sf_user_daily_tracking WHERE steps_daily > 0 OR workout_hours > 0 OR cardio_hours > 0 OR sleep_hours > 0");
if ($result) {
    $row = $result->fetch_assoc();
    $total_tracking = $row['total'];
    if ($total_tracking > 0) {
        printTest('Registros de atividades', 'success', "$total_tracking registros com dados de atividades");
    } else {
        printTest('Registros de atividades', 'warning', 'Ainda não há dados de atividades. Use update_daily_tracking.php para adicionar.');
    }
}

echo "</div>";

// ================================
// TESTE 5: PERMISSÕES E ACESSO
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>🔒</span> 5. Permissões e Acesso</h2>";

// Verificar se as páginas são acessíveis
$pages_to_check = [
    'progress_v2.php' => 'Página de progresso',
    'update_daily_tracking.php' => 'Página de entrada de dados'
];

foreach ($pages_to_check as $page => $description) {
    if (file_exists($page)) {
        if (is_readable($page)) {
            printTest("Leitura de <code>$page</code>", 'success', 'Arquivo legível pelo servidor');
        } else {
            printTest("Leitura de <code>$page</code>", 'error', 'Arquivo existe mas não é legível! Verifique permissões.');
        }
    }
}

echo "</div>";

// ================================
// TESTE 6: FUNCIONALIDADES
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>⚙️</span> 6. Funcionalidades</h2>";

// Verificar índices
$result = $conn->query("SHOW INDEX FROM sf_user_daily_tracking WHERE Key_name = 'idx_user_date'");
if ($result && $result->num_rows > 0) {
    printTest('Índice de performance', 'success', 'Índices criados para melhor performance');
} else {
    printTest('Índice de performance', 'warning', 'Índices podem não ter sido criados. Performance pode ser afetada.');
}

// Testar query de exemplo
try {
    $test_query = "SELECT 
        u.id, 
        u.email,
        COALESCE(udt.steps_daily, 0) as steps,
        COALESCE(udt.workout_hours, 0) as workout,
        COALESCE(ug.target_steps_daily, 0) as goal_steps
    FROM sf_users u
    LEFT JOIN sf_user_daily_tracking udt ON u.id = udt.user_id AND udt.date = CURDATE()
    LEFT JOIN sf_user_goals ug ON u.id = ug.user_id
    LIMIT 1";
    
    $result = $conn->query($test_query);
    if ($result) {
        printTest('Query de teste', 'success', 'Queries funcionando corretamente');
    } else {
        printTest('Query de teste', 'error', 'Erro na query de teste: ' . $conn->error);
    }
} catch (Exception $e) {
    printTest('Query de teste', 'error', 'Erro: ' . $e->getMessage());
}

echo "</div>";

// ================================
// RESUMO FINAL
// ================================
$total_percentage = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100) : 0;

echo "<div class='summary'>";
echo "<h2>📈 Resumo dos Testes</h2>";
echo "<div class='summary-stats'>";
echo "<div class='stat success'><div class='stat-number'>$passed_tests</div><div class='stat-label'>Testes Passaram</div></div>";
echo "<div class='stat error'><div class='stat-number'>$failed_tests</div><div class='stat-label'>Testes Falharam</div></div>";
echo "<div class='stat warning'><div class='stat-number'>$warnings</div><div class='stat-label'>Avisos</div></div>";
echo "<div class='stat'><div class='stat-number'>{$total_percentage}%</div><div class='stat-label'>Taxa de Sucesso</div></div>";
echo "</div>";

if ($failed_tests === 0 && $warnings === 0) {
    echo "<div style='margin-top: 20px; font-size: 1.2rem; color: #22c55e;'>";
    echo "🎉 <strong>Perfeito!</strong> Tudo está funcionando corretamente!";
    echo "</div>";
} elseif ($failed_tests === 0) {
    echo "<div style='margin-top: 20px; font-size: 1.1rem; color: #eab308;'>";
    echo "⚠️ <strong>Quase lá!</strong> Há alguns avisos, mas nada crítico.";
    echo "</div>";
} else {
    echo "<div style='margin-top: 20px; font-size: 1.1rem; color: #ef4444;'>";
    echo "❌ <strong>Atenção!</strong> Há $failed_tests erro(s) que precisam ser corrigidos.";
    echo "</div>";
}

echo "<div class='warning-box' style='margin-top: 20px;'>";
echo "⚠️ <strong>IMPORTANTE:</strong> Remova este arquivo (<code>test_implementation.php</code>) após concluir os testes por segurança!";
echo "</div>";

echo "</div>";

// ================================
// PRÓXIMOS PASSOS
// ================================
echo "<div class='test-section'>";
echo "<h2><span class='icon'>🚀</span> Próximos Passos</h2>";

if ($failed_tests > 0) {
    echo "<div class='test-item error'>";
    echo "1. <strong>Corrija os erros encontrados</strong> antes de prosseguir.";
    echo "<div class='details'>Se as colunas do banco estão faltando, execute o arquivo <code>DATABASE_UPDATE_PROGRESS.sql</code> no phpMyAdmin.</div>";
    echo "</div>";
}

if ($warnings > 0 && $failed_tests === 0) {
    echo "<div class='test-item warning'>";
    echo "1. <strong>Revise os avisos</strong> se necessário.";
    echo "<div class='details'>Os avisos não são críticos, mas é bom verificá-los.</div>";
    echo "</div>";
}

echo "<div class='test-item'>";
echo "2. <strong>Teste a aplicação</strong> manualmente:";
echo "<ul style='margin: 10px 0 0 20px; color: #aaa;'>";
echo "<li>Acesse <code>update_daily_tracking.php</code> e registre alguns dados</li>";
echo "<li>Acesse <code>progress_v2.php</code> e veja se os dados aparecem</li>";
echo "<li>Teste em mobile para verificar responsividade</li>";
echo "</ul>";
echo "</div>";

echo "<div class='test-item'>";
echo "3. <strong>Ajuste as metas padrão</strong> se necessário na tabela <code>sf_user_goals</code>.";
echo "</div>";

echo "<div class='test-item'>";
echo "4. <strong>Remova este arquivo</strong> (<code>test_implementation.php</code>) por segurança.";
echo "</div>";

echo "</div>";

echo "</div>"; // container
echo "</body></html>";

$conn->close();
?>





