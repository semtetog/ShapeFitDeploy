<?php
/**
 * Helper para atualização automática de status dos desafios
 * Esta função verifica e atualiza status baseado nas datas
 */

function updateChallengeStatusAutomatically($conn) {
    try {
        $current_date = date('Y-m-d');
        
        // 1. Ativar desafios agendados que já começaram (mas não inativos)
        $stmt_activate = $conn->prepare("
            UPDATE sf_challenge_groups 
            SET status = 'active', updated_at = NOW()
            WHERE start_date <= ? 
              AND status = 'scheduled'
              AND status != 'inactive'
        ");
        $stmt_activate->bind_param("s", $current_date);
        $stmt_activate->execute();
        $stmt_activate->close();
        
        // 2. Completar desafios ativos que já terminaram
        $stmt_complete = $conn->prepare("
            UPDATE sf_challenge_groups 
            SET status = 'completed', updated_at = NOW()
            WHERE end_date < ? 
              AND status = 'active'
        ");
        $stmt_complete->bind_param("s", $current_date);
        $stmt_complete->execute();
        $stmt_complete->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao atualizar status dos desafios automaticamente: " . $e->getMessage());
        return false;
    }
}
?>

