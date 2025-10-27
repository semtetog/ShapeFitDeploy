<?php
/**
 * Script de Atualização Automática de Status dos Desafios
 * 
 * Este script deve ser executado uma vez por dia via cron job para:
 * - Ativar desafios agendados que já começaram
 * - Completar desafios ativos que já terminaram
 * 
 * Para configurar o cron job no servidor, adicione esta linha no crontab:
 * 0 0 * * * php /caminho/para/seu/site/cron/update_challenge_status.php
 * 
 * Ou execute manualmente para testar:
 * php /caminho/para/seu/site/cron/update_challenge_status.php
 */

// Configurar fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Log de execução
$log_file = dirname(__DIR__) . '/logs/challenge_status_update.log';

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    echo $log_message;
}

try {
    writeLog("=== INICIANDO ATUALIZAÇÃO DE STATUS DOS DESAFIOS ===");
    
    $current_date = date('Y-m-d');
    writeLog("Data atual: $current_date");
    
    // 1. Ativar desafios agendados que já começaram
    $stmt_activate = $conn->prepare("
        UPDATE sf_challenges 
        SET status = 'active' 
        WHERE start_date <= ? AND status = 'scheduled'
    ");
    $stmt_activate->bind_param("s", $current_date);
    $stmt_activate->execute();
    $activated_count = $stmt_activate->affected_rows;
    $stmt_activate->close();
    
    if ($activated_count > 0) {
        writeLog("Desafios ativados: $activated_count");
    } else {
        writeLog("Nenhum desafio foi ativado hoje");
    }
    
    // 2. Completar desafios ativos que já terminaram
    $stmt_complete = $conn->prepare("
        UPDATE sf_challenges 
        SET status = 'completed' 
        WHERE end_date < ? AND status = 'active'
    ");
    $stmt_complete->bind_param("s", $current_date);
    $stmt_complete->execute();
    $completed_count = $stmt_complete->affected_rows;
    $stmt_complete->close();
    
    if ($completed_count > 0) {
        writeLog("Desafios completados: $completed_count");
    } else {
        writeLog("Nenhum desafio foi completado hoje");
    }
    
    // 3. Buscar estatísticas atuais
    $stats = $conn->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM sf_challenges 
        GROUP BY status
    ")->fetch_all(MYSQLI_ASSOC);
    
    writeLog("=== ESTATÍSTICAS ATUAIS ===");
    foreach ($stats as $stat) {
        writeLog("- {$stat['status']}: {$stat['count']} desafios");
    }
    
    // 4. Verificar desafios próximos do fim (opcional - para notificações futuras)
    $upcoming_end = $conn->query("
        SELECT name, end_date
        FROM sf_challenges 
        WHERE status = 'active' 
        AND end_date BETWEEN '$current_date' AND DATE_ADD('$current_date', INTERVAL 3 DAY)
        ORDER BY end_date ASC
    ")->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($upcoming_end)) {
        writeLog("=== DESAFIOS TERMINANDO EM BREVE ===");
        foreach ($upcoming_end as $challenge) {
            $days_left = (strtotime($challenge['end_date']) - strtotime($current_date)) / (60 * 60 * 24);
            writeLog("- '{$challenge['name']}' termina em " . ceil($days_left) . " dias ({$challenge['end_date']})");
        }
    }
    
    writeLog("=== ATUALIZAÇÃO CONCLUÍDA COM SUCESSO ===");
    writeLog("");
    
} catch (Exception $e) {
    $error_message = "ERRO na atualização de status dos desafios: " . $e->getMessage();
    writeLog($error_message);
    error_log($error_message);
    exit(1);
}

$conn->close();
?>
