<?php
// ===========================
// DADOS PARA PROGRESSO
// ===========================

// --- HISTÓRICO DE PESO ---
$all_weights = [];
$stmt_weights = $conn->prepare("SELECT date, weight_kg FROM sf_user_weight_history WHERE user_id = ? ORDER BY date ASC");
$stmt_weights->bind_param("i", $user_id);
$stmt_weights->execute();
$weight_results = $stmt_weights->get_result();
while ($row = $weight_results->fetch_assoc()) {
    $all_weights[$row['date']] = $row['weight_kg'];
}
$stmt_weights->close();

// Adicionar peso atual do perfil se disponível
$current_weight_from_profile = $user_data['current_weight'] ?? 0;
if ($current_weight_from_profile > 0) {
    $all_weights[date('Y-m-d')] = $current_weight_from_profile;
}
ksort($all_weights);
$weight_chart_data = ['labels' => [], 'data' => []];
foreach ($all_weights as $date => $weight) {
    $weight_chart_data['labels'][] = date('d/m/Y', strtotime($date));
    $weight_chart_data['data'][] = $weight;
}

// --- FOTOS ---
$stmt_photos = $conn->prepare("SELECT date_recorded, photo_front, photo_side, photo_back FROM sf_user_measurements WHERE user_id = ? AND (photo_front IS NOT NULL OR photo_side IS NOT NULL OR photo_back IS NOT NULL) ORDER BY date_recorded DESC");
$stmt_photos->bind_param("i", $user_id);
$stmt_photos->execute();
$photo_history = $stmt_photos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_photos->close();
?>

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
</div>

<!-- Card de Medidas dentro da aba Progresso -->
<div id="tab-progress" class="tab-content">
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

<!-- Modal de Galeria Completa -->
<div id="galleryModal" class="gallery-modal" style="display: none;">
    <div class="gallery-modal-content">
        <div class="gallery-modal-header">
            <h3><i class="fas fa-images"></i> Galeria de Fotos de Progresso</h3>
            <button class="gallery-close-btn" onclick="closeGalleryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="gallery-modal-body">
            <div class="gallery-container">
                <?php 
                // Agrupar fotos por data e sessão
                $grouped_photos = [];
                foreach($photo_history as $photo_set) {
                    $date_key = date('Y-m-d', strtotime($photo_set['date_recorded']));
                    $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : false;
                    $time_key = $timestamp ? date('H:i', $timestamp) : date('H:i');
                    
                    if (!isset($grouped_photos[$date_key])) {
                        $grouped_photos[$date_key] = [];
                    }
                    if (!isset($grouped_photos[$date_key][$time_key])) {
                        $grouped_photos[$date_key][$time_key] = [
                            'time' => $time_key,
                            'photos' => []
                        ];
                    }
                    
                    foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label) {
                        if (!empty($photo_set[$photo_type])) {
                            $grouped_photos[$date_key][$time_key]['photos'][] = [
                                'filename' => $photo_set[$photo_type],
                                'label' => $label
                            ];
                        }
                    }
                }
                
                // Ordenar por data (mais recente primeiro)
                krsort($grouped_photos);
                
                foreach ($grouped_photos as $date => $sessions):
                    $date_display = date('d/m/Y', strtotime($date));
                    // Ordenar sessões por horário (mais recente primeiro)
                    krsort($sessions);
                ?>
                    <div class="gallery-date-section">
                        <h4 class="gallery-date-title"><?php echo $date_display; ?></h4>
                        
                        <?php foreach ($sessions as $session): ?>
                            <div class="gallery-session">
                                <div class="gallery-session-header">
                                    <span class="gallery-session-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $session['time']; ?>
                                    </span>
                                    <span class="gallery-session-count">
                                        <?php echo count($session['photos']); ?> foto(s)
                                    </span>
                                </div>
                                
                                <div class="gallery-session-photos">
                                    <?php foreach ($session['photos'] as $photo): ?>
                                        <div class="gallery-photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>', '<?php echo $photo['label']; ?>', '<?php echo $date_display . ' ' . $session['time']; ?>')">
                                            <img src="<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo $photo['label']; ?>" onerror="this.style.display='none'">
                                            <div class="gallery-photo-overlay">
                                                <span class="gallery-photo-type"><?php echo $photo['label']; ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Foto Individual -->
<div id="photoModal" class="photo-modal" style="display: none;">
    <div class="photo-modal-content">
        <div class="photo-modal-header">
            <h3 id="photoModalTitle">Foto de Progresso</h3>
            <button class="photo-close-btn" onclick="closePhotoModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="photo-modal-body">
            <div class="photo-navigation">
                <button class="photo-nav-btn" id="prevPhotoBtn" onclick="navigatePhoto(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="photo-container">
                    <img id="photoModalImage" src="" alt="Foto de progresso">
                </div>
                <button class="photo-nav-btn" id="nextPhotoBtn" onclick="navigatePhoto(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="photo-info">
                <div class="photo-details">
                    <span id="photoModalLabel">Tipo</span>
                    <span id="photoModalDate">Data</span>
                </div>
                <div class="photo-counter">
                    <span id="photoCounter">1 de 1</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dados para JavaScript -->
<script>
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>,
    photoHistory: <?php echo json_encode($photo_history); ?>
};

let currentPhotoIndex = 0;
let allPhotos = [];

function openPhotoModal(imageSrc, label, date) {
    // Coletar todas as fotos disponíveis
    allPhotos = [];
    document.querySelectorAll('.photo-item img').forEach((img, index) => {
        if (img.src && !img.src.includes('data:image')) {
            allPhotos.push({
                src: img.src,
                label: img.closest('.photo-item').querySelector('.photo-date span:first-child').textContent,
                date: img.closest('.photo-item').querySelector('.photo-date span:last-child').textContent
            });
        }
    });
    
    // Encontrar o índice da foto clicada
    currentPhotoIndex = allPhotos.findIndex(photo => photo.src === imageSrc);
    if (currentPhotoIndex === -1) currentPhotoIndex = 0;
    
    // Atualizar modal
    document.getElementById('photoModalImage').src = imageSrc;
    document.getElementById('photoModalLabel').textContent = label;
    document.getElementById('photoModalDate').textContent = date;
    updatePhotoCounter();
    
    // Mostrar modal
    document.getElementById('photoModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePhotoModal() {
    document.getElementById('photoModal').style.display = 'none';
    document.body.style.overflow = '';
}

function navigatePhoto(direction) {
    if (allPhotos.length === 0) return;
    
    currentPhotoIndex += direction;
    
    if (currentPhotoIndex < 0) {
        currentPhotoIndex = allPhotos.length - 1;
    } else if (currentPhotoIndex >= allPhotos.length) {
        currentPhotoIndex = 0;
    }
    
    const photo = allPhotos[currentPhotoIndex];
    document.getElementById('photoModalImage').src = photo.src;
    document.getElementById('photoModalLabel').textContent = photo.label;
    document.getElementById('photoModalDate').textContent = photo.date;
    updatePhotoCounter();
}

function updatePhotoCounter() {
    const counter = document.getElementById('photoCounter');
    counter.textContent = `${currentPhotoIndex + 1} de ${allPhotos.length}`;
}

// Funções para o modal de galeria
function openGalleryModal() {
    const modal = document.getElementById('galleryModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeGalleryModal() {
    const modal = document.getElementById('galleryModal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
}

// Fechar modais ao clicar fora
window.onclick = function(event) {
    const galleryModal = document.getElementById('galleryModal');
    const photoModal = document.getElementById('photoModal');
    
    if (event.target === galleryModal) {
        closeGalleryModal();
    }
    if (event.target === photoModal) {
        closePhotoModal();
    }
}

// Navegação por teclado
document.addEventListener('keydown', function(e) {
    const photoModal = document.getElementById('photoModal');
    if (photoModal.style.display === 'block') {
        if (e.key === 'ArrowLeft') {
            navigatePhoto(-1);
        } else if (e.key === 'ArrowRight') {
            navigatePhoto(1);
        } else if (e.key === 'Escape') {
            closePhotoModal();
        }
    }
    
    const galleryModal = document.getElementById('galleryModal');
    if (galleryModal.style.display === 'block' && e.key === 'Escape') {
        closeGalleryModal();
    }
});

// Inicializar gráfico de peso quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    const weightChartCtx = document.getElementById('weightHistoryChart');
    
    if (weightChartCtx && typeof userViewData !== 'undefined' && userViewData && userViewData.weightHistory && userViewData.weightHistory.data.length >= 1) {
        const ctx = weightChartCtx.getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: userViewData.weightHistory.labels,
                datasets: [{
                    label: 'Peso (kg)',
                    data: userViewData.weightHistory.data,
                    borderColor: '#ff6b00',
                    backgroundColor: 'rgba(255, 107, 0, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ff6b00',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
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
                            color: '#ffffff',
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#ffffff',
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#ff6b00'
                    }
                }
            }
        });
    }
});
</script>

<style>
/* ========================================================================= */
/*                    CSS ABA PROGRESSO - DESIGN REALISTA                   */
/* ========================================================================= */

.progress-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.dashboard-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    min-height: auto;
}

.dashboard-card:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    border-color: var(--accent-orange);
}

.dashboard-card h3,
.dashboard-card h4 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #FFFFFF;
    margin: 0 0 1rem 0;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-card h4 {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h4 {
    margin: 0;
}

.btn-secondary {
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    color: var(--accent-orange);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-secondary:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.empty-state {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 12px;
    border: 1px dashed rgba(255, 255, 255, 0.1);
}

.info-message-chart {
    text-align: center;
    color: var(--text-secondary);
    font-size: 0.875rem;
    margin-top: 1rem;
    padding: 0.75rem;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

/* Galeria de Fotos */
.photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.photo-item:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    border-color: var(--accent-orange);
}

.photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.photo-item:hover img {
    transform: scale(1.1);
}

.photo-date {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    padding: 1rem 0.75rem 0.75rem;
    color: white;
    font-size: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.photo-date span:first-child {
    font-weight: 600;
    color: var(--accent-orange);
}

.photo-date span:last-child {
    opacity: 0.8;
    font-size: 0.7rem;
}

/* Modal de Galeria */
.gallery-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-modal-content {
    background: var(--surface-color);
    border-radius: 20px;
    width: 90%;
    max-width: 1200px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.gallery-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.gallery-modal-header h3 {
    margin: 0;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.gallery-close-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.gallery-close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.gallery-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.gallery-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.gallery-date-section {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 2rem;
}

.gallery-date-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.gallery-date-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--accent-orange);
    display: inline-block;
}

.gallery-session {
    margin-bottom: 1.5rem;
}

.gallery-session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.gallery-session-time {
    color: var(--accent-orange);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.gallery-session-count {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.gallery-session-photos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.gallery-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.gallery-photo-item:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    border-color: var(--accent-orange);
}

.gallery-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-photo-item:hover img {
    transform: scale(1.1);
}

.gallery-photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    padding: 1rem 0.75rem 0.75rem;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
}

/* Modal de Foto Individual */
.photo-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1001;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-modal-content {
    background: var(--surface-color);
    border-radius: 20px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.photo-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.photo-modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.photo-close-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.photo-close-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.photo-modal-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.photo-navigation {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
}

.photo-nav-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: var(--text-primary);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 1.25rem;
}

.photo-nav-btn:hover {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
    transform: scale(1.1);
}

.photo-container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.photo-container img {
    max-width: 100%;
    max-height: 60vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.photo-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.02);
}

.photo-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.photo-details span:first-child {
    color: var(--accent-orange);
    font-weight: 600;
    font-size: 0.875rem;
}

.photo-details span:last-child {
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.photo-counter {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* Responsivo */
@media (max-width: 768px) {
    .progress-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .dashboard-card {
        padding: 1.25rem;
    }
    
    .photo-gallery {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem;
    }
    
    .gallery-modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .gallery-session-photos {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .photo-modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .photo-navigation {
        padding: 0.75rem;
        gap: 0.75rem;
    }
    
    .photo-nav-btn {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .photo-container {
        padding: 0.75rem;
    }
    
    .photo-container img {
        max-height: 50vh;
    }
}
</style>
