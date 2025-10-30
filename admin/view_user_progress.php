<!-- view_user_progress.php -->
<!-- Conteúdo completo da aba Progresso: HTML, CSS e JS -->
<div id="tab-progress" class="tab-content">
        <div class="progress-grid">
        <div class="dashboard-card weight-history-card">
            <h4>Histórico de Peso</h4>
            <?php if (empty($weight_chart_data['data'])): ?>
                <p class="empty-state">O paciente ainda não registrou nenhum peso.</p>
            <?php else: ?>
                <canvas id="weightHistoryChart"></canvas>
                <?php if (count($weight_chart_data['data']) < 2): ?>
                    <p class="info-message-chart">Aguardando o próximo registro de peso para traçar a linha de progresso.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="dashboard-card photos-history-card">
            <div class="section-header">
                <h4>Fotos de Progresso</h4>
                <?php if (count($photo_history) > 3): ?>
                    <button class="btn-secondary" onclick="openGalleryModal()">
                        <i class="fas fa-images"></i> Ver Todas (<?php echo count($photo_history); ?>)
                    </button>
                <?php endif; ?>
            </div>
            <?php if (empty($photo_history)): ?>
                <p class="empty-state">Nenhuma foto de progresso encontrada.</p>
            <?php else: ?>
                <div class="photo-gallery">
                    <?php 
                    $displayed_count = 0;
                    foreach($photo_history as $photo_set): 
                        if ($displayed_count >= 3) break;
                        foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label): 
                            if ($displayed_count >= 3) break;
                            if(!empty($photo_set[$photo_type])): 
                                $displayed_count++;
                    ?>
                                <?php 
                                $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : strtotime($photo_set['date_recorded']);
                                $display_date = $timestamp ? date('d/m/Y H:i', $timestamp) : date('d/m/Y H:i');
                                ?>
                                <div class="photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>', '<?php echo $label; ?>', '<?php echo $display_date; ?>')">
                                    <img src="<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>" loading="lazy" alt="Foto de progresso - <?php echo $label; ?>" onerror="this.style.display='none'">
                                    <div class="photo-date">
                                        <span><?php echo $label; ?></span>
                                        <span><?php echo $display_date; ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif; 
                        endforeach; 
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Card de Medidas Corporais -->
    <div class="dashboard-card">
        <h3><i class="fas fa-camera"></i> Histórico de Medidas Corporais</h3>
        <div class="measurements-content">
            <?php if (empty($photo_history)): ?>
                <p class="empty-state">Nenhuma foto de progresso encontrada.</p>
            <?php else: ?>
                <div class="photo-gallery">
                    <?php 
                    $displayed_count = 0;
                    foreach($photo_history as $photo_set): 
                        if ($displayed_count >= 6) break;
                        foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label): 
                            if ($displayed_count >= 6) break;
                            if(!empty($photo_set[$photo_type])): 
                                $displayed_count++;
                    ?>
                                <?php 
                                $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : strtotime($photo_set['date_recorded']);
                                $display_date = $timestamp ? date('d/m/Y H:i', $timestamp) : date('d/m/Y H:i');
                                ?>
                                <div class="photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>', '<?php echo $label; ?>', '<?php echo $display_date; ?>')">
                                    <img src="<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>" loading="lazy" alt="Foto de progresso - <?php echo $label; ?>" onerror="this.style.display='none'">
                                    <div class="photo-date">
                                        <span><?php echo $label; ?></span>
                                        <span><?php echo $display_date; ?></span>
                                    </div>
                                </div>
                            <?php 
                            endif; 
                        endforeach; 
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>
</div>

<script>
// Dados do gráfico de peso do PHP
const weightChartData = <?php echo json_encode($weight_chart_data); ?>;

// Inicializar gráfico de peso quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'progress') {
                setTimeout(() => {
                    initWeightChart();
                }, 100);
            }
        });
    });
});

// Função para inicializar gráfico de peso
function initWeightChart() {
    const ctx = document.getElementById('weightHistoryChart');
    if (!ctx || !weightChartData.data || weightChartData.data.length === 0) return;
    
    // Destruir gráfico existente se houver
    if (window.weightChart) {
        window.weightChart.destroy();
    }
    
    window.weightChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: weightChartData.labels,
            datasets: [{
                label: 'Peso (kg)',
                data: weightChartData.data,
                borderColor: '#ff6b00',
                backgroundColor: 'rgba(255, 107, 0, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ff6b00',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                }
            }
        }
    });
}

// Função para abrir modal de foto
function openPhotoModal(imageSrc, title, date) {
    // Implementar modal de foto se necessário
    console.log('Abrir modal de foto:', imageSrc, title, date);
}

// Função para abrir modal de galeria
function openGalleryModal() {
    // Implementar modal de galeria se necessário
    console.log('Abrir modal de galeria');
}
</script>
