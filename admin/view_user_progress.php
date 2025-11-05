<?php
// view_user_progress.php - Apenas HTML e CSS
// Os dados já são preparados no view_user.php
// O JavaScript está centralizado em user_view_logic.js
?>

<div id="tab-progress" class="tab-content">
    <div class="progress-container">
    <!-- HEADER NO ESTILO DAS OUTRAS ABAS -->
    <div class="progress-summary-card">
        <div class="summary-main">
            <div class="summary-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="summary-info">
                <h3>Progresso Geral</h3>
                <div class="summary-meta">Acompanhamento de peso e evolução visual</div>
                <div class="summary-description">Visualize o histórico de peso e fotos de progresso do paciente</div>
                <?php 
                // Calcular peso atual e inicial
                $current_weight = 0;
                $initial_weight = 0;
                if (!empty($weight_chart_data['data'])) {
                    $current_weight = end($weight_chart_data['data']);
                    $initial_weight = reset($weight_chart_data['data']);
                } elseif (!empty($user_data['weight_kg'])) {
                    $current_weight = (float)$user_data['weight_kg'];
                    $initial_weight = (float)$user_data['weight_kg'];
                }
                
                if ($current_weight > 0 || $initial_weight > 0): 
                    $weight_diff = $current_weight - $initial_weight;
                    $diff_text = '';
                    $diff_class = '';
                    if ($initial_weight > 0 && $current_weight > 0) {
                        if ($weight_diff > 0) {
                            $diff_text = '+' . number_format($weight_diff, 1) . 'kg';
                            $diff_class = 'status-poor'; // Ganho de peso
                        } elseif ($weight_diff < 0) {
                            $diff_text = number_format($weight_diff, 1) . 'kg';
                            $diff_class = 'status-excellent'; // Perda de peso
                        } else {
                            $diff_text = '0.0kg';
                            $diff_class = 'status-fair'; // Sem alteração
                        }
                    } else {
                        $diff_text = 'N/A';
                        $diff_class = 'status-fair';
                    }
                ?>
                    <div class="summary-stats">
                        <div class="summary-stat">
                            <div class="stat-value"><?php echo $current_weight > 0 ? number_format($current_weight, 1) : 'N/A'; ?>kg</div>
                            <div class="stat-label">Peso Atual</div>
                            <div class="stat-description">Último registro</div>
                        </div>
                        <div class="summary-stat">
                            <div class="stat-value"><?php echo $initial_weight > 0 ? number_format($initial_weight, 1) : 'N/A'; ?>kg</div>
                            <div class="stat-label">Peso Inicial</div>
                            <div class="stat-description">No cadastro</div>
                        </div>
                        <div class="summary-stat">
                            <div class="stat-value <?php echo $diff_class; ?>"><?php echo $diff_text; ?></div>
                            <div class="stat-label">Variação</div>
                            <div class="stat-description">Comparado ao início</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="progress-grid">
        <div class="dashboard-card weight-history-card">
            <div class="section-header">
                <h4><i class="fas fa-weight"></i> Histórico de Peso</h4>
            </div>
            <div class="weight-chart-container">
            <?php if (empty($weight_chart_data['data'])): ?>
                <p class="empty-state">O paciente ainda não registrou nenhum peso.</p>
            <?php else: ?>
                <canvas id="weightHistoryChart"></canvas>
                <?php if (count($weight_chart_data['data']) < 2): ?>
                    <p class="info-message-chart">Aguardando o próximo registro de peso para traçar a linha de progresso.</p>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        </div>
        <div class="dashboard-card photos-history-card">
            <div class="section-header">
                <h4><i class="fas fa-images"></i> Fotos de Progresso</h4>
                <?php if (count($photo_history) > 0): ?>
                    <button class="btn-view-gallery" onclick="openGalleryModal()">
                        <i class="fas fa-images"></i> Ver Galeria
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
                                // Usar created_at para horário correto, mas date_recorded para a data que o usuário escolheu
                                $recorded_date = !empty($photo_set['date_recorded']) ? date('d/m/Y', strtotime($photo_set['date_recorded'])) : date('d/m/Y');
                                $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : false;
                                $display_time = $timestamp ? date('H:i', $timestamp) : date('H:i');
                                $display_date = $recorded_date . ' ' . $display_time;
                                
                                // Coletar medidas do corpo (se existirem) - usar nome diferente para não conflitar com $label da foto
                                $measurements = [];
                                $measurement_labels_map = [
                                    'neck' => 'Pescoço',
                                    'chest' => 'Tórax',
                                    'waist' => 'Cintura',
                                    'abdomen' => 'Abdômen',
                                    'hips' => 'Quadril'
                                ];
                                foreach ($measurement_labels_map as $measure_key => $measure_label_name) {
                                    // Verificar se a medida existe e é maior que 0
                                    if (isset($photo_set[$measure_key]) && $photo_set[$measure_key] !== null && $photo_set[$measure_key] !== '' && floatval($photo_set[$measure_key]) > 0) {
                                        $measurements[] = $measure_label_name . ': ' . number_format(floatval($photo_set[$measure_key]), 1) . 'cm';
                                    }
                                }
                                $measurements_text = !empty($measurements) ? implode(' | ', $measurements) : '';
                                
                                // Debug: log das medidas coletadas (remover depois)
                                if (!empty($measurements_text)) {
                                    error_log("View Progress Debug - measurements_text para foto {$photo_type}: " . $measurements_text);
                                }
                                ?>
                                <div class="photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL . '/uploads/measurements/' . htmlspecialchars($photo_set[$photo_type]); ?>', '<?php echo $label; ?>', '<?php echo $display_date; ?>', '<?php echo htmlspecialchars($measurements_text, ENT_QUOTES); ?>')">
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

<!-- Modal de Galeria Completa -->
<div id="galleryModal" class="gallery-modal" style="display: none;">
    <div class="gallery-modal-content">
        <div class="gallery-modal-header">
            <h3><i class="fas fa-images"></i> Galeria de Fotos de Progresso</h3>
            <button class="sleep-modal-close" onclick="closeGalleryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="gallery-modal-body">
            <div class="gallery-container">
                <?php 
                // Agrupar fotos por data e sessão
                $grouped_photos = [];
                foreach($photo_history as $photo_set) {
                    // Usar date_recorded para agrupar por data (data que o usuário escolheu)
                    $date_key = !empty($photo_set['date_recorded']) ? date('Y-m-d', strtotime($photo_set['date_recorded'])) : date('Y-m-d');
                    // Usar created_at para o horário correto (quando foi salvo)
                    $timestamp = !empty($photo_set['created_at']) ? strtotime($photo_set['created_at']) : false;
                    $time_key = $timestamp ? date('H:i', $timestamp) : date('H:i');
                    
                    // Coletar medidas do corpo - usar nome diferente para não conflitar
                    $measurements = [];
                    $measurement_labels_map = [
                        'neck' => 'Pescoço',
                        'chest' => 'Tórax',
                        'waist' => 'Cintura',
                        'abdomen' => 'Abdômen',
                        'hips' => 'Quadril'
                    ];
                    foreach ($measurement_labels_map as $measure_key => $measure_label_name) {
                        // Verificar se a medida existe e é maior que 0
                        if (isset($photo_set[$measure_key]) && $photo_set[$measure_key] !== null && $photo_set[$measure_key] !== '' && floatval($photo_set[$measure_key]) > 0) {
                            $measurements[] = $measure_label_name . ': ' . number_format(floatval($photo_set[$measure_key]), 1) . 'cm';
                        }
                    }
                    $measurements_text = !empty($measurements) ? implode(' | ', $measurements) : '';
                    
                    // Debug: log das medidas coletadas para galeria (remover depois)
                    if (!empty($measurements_text)) {
                        error_log("View Progress Debug - measurements_text para galeria: " . $measurements_text);
                    }
                    
                    if (!isset($grouped_photos[$date_key])) {
                        $grouped_photos[$date_key] = [];
                    }
                    if (!isset($grouped_photos[$date_key][$time_key])) {
                        $grouped_photos[$date_key][$time_key] = [
                            'time' => $time_key,
                            'photos' => [],
                            'measurements' => $measurements_text
                        ];
                    }
                    
                    foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label) {
                        if (!empty($photo_set[$photo_type])) {
                            $grouped_photos[$date_key][$time_key]['photos'][] = [
                                'filename' => $photo_set[$photo_type],
                                'label' => $label,
                                'measurements' => $measurements_text
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
                                        <div class="gallery-photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>', '<?php echo $photo['label']; ?>', '<?php echo $date_display . ' ' . $session['time']; ?>', '<?php echo htmlspecialchars($photo['measurements'] ?? '', ENT_QUOTES); ?>')">
                                            <img src="<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo $photo['label']; ?>" onerror="this.style.display='none'">
                                            <div class="gallery-photo-overlay">
                                                <span class="gallery-photo-type"><?php echo $photo['label']; ?></span>
                                                <span class="gallery-photo-date"><?php echo $date_display . ' ' . $session['time']; ?></span>
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
            <button class="sleep-modal-close" onclick="closePhotoModal()">
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
                <div class="photo-info-top">
                    <div class="photo-details">
                        <span id="photoModalLabel">Tipo</span>
                        <span id="photoModalDate">Data</span>
                    </div>
                    <div class="photo-actions">
                        <button class="btn-view-gallery-in-modal" onclick="closePhotoModal(); setTimeout(() => openGalleryModal(), 100);">
                            <i class="fas fa-images"></i> Ver Galeria
                        </button>
                        <div class="photo-counter">
                            <span id="photoCounter">1 de 1</span>
                        </div>
                    </div>
                </div>
                <div id="photoModalMeasurements" class="photo-modal-measurements" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        console.log('[view_user_progress] DOMContentLoaded iniciado');
        const progressTab = document.getElementById('tab-progress');
        if (!progressTab) {
            console.log('[view_user_progress] Aba progress não encontrada, saindo...');
            return;
        }
        console.log('[view_user_progress] Aba progress encontrada, inicializando...');
        
        let currentPhotoIndex = 0;
        let allPhotos = [];
        
        // Função para bloquear scroll do body
        function lockBodyScroll() {
            document.body.style.overflow = 'hidden';
            document.body.style.paddingRight = window.innerWidth - document.documentElement.clientWidth + 'px';
        }
        
        // Função para desbloquear scroll do body
        function unlockBodyScroll() {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        
        // Abrir modal de galeria
        window.openGalleryModal = function() {
            const modal = document.getElementById('galleryModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                lockBodyScroll();
            }
        };
        
        // Fechar modal de galeria
        window.closeGalleryModal = function() {
            const modal = document.getElementById('galleryModal');
            if (modal) {
                modal.style.display = 'none';
                unlockBodyScroll();
            }
        };
        
        // Função auxiliar para extrair nome do arquivo do src (compartilhada)
        // SEMPRE remove querystring ANTES de processar para evitar duplicatas
        function getFileName(src) {
            if (!src) return '';
            
            // Remove qualquer querystring ANTES de processar (ESSENCIAL para evitar duplicatas)
            src = src.split('?')[0];
            
            try {
                const url = new URL(src);
                return url.pathname.split('/').pop();
            } catch (e) {
                // Fallback para caminhos relativos ou absolutos sem protocolo
                return src.split('/').pop();
            }
        }
        
        // Coletar todas as fotos da galeria e do card inicial
        // SEMPRE usar parse do onclick (fonte confiável com todas as informações)
        function collectAllPhotos() {
            allPhotos = [];
            const seenFiles = new Set(); // Set para rastrear arquivos já vistos (garantir unicidade)
            
            // PRIORIDADE 1: Coletar fotos da galeria usando onclick (fonte oficial com todas as infos)
            const galleryItems = document.querySelectorAll('.gallery-photo-item');
            
            if (galleryItems.length > 0) {
                // Se a galeria existe, usar onclick de cada item (fonte confiável)
                galleryItems.forEach(item => {
                    const onclick = item.getAttribute('onclick');
                    if (onclick) {
                        // Formato: openPhotoModal('src', 'label', 'date', 'measurements')
                        const match = onclick.match(/openPhotoModal\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*)'\)/);
                        if (match) {
                            const parsedSrc = match[1];
                            const parsedLabel = match[2];
                            const parsedDate = match[3];
                            const parsedMeasurements = match[4] || '';
                            
                            // Usar nome do arquivo como chave única (getFileName já remove querystring)
                            const fileName = getFileName(parsedSrc);
                            
                            if (fileName && !seenFiles.has(fileName)) {
                                seenFiles.add(fileName);
                                allPhotos.push({
                                    src: parsedSrc,
                                    label: parsedLabel,
                                    date: parsedDate,
                                    measurements: parsedMeasurements
                                });
                            }
                        }
                    }
                });
            }
            
            // PRIORIDADE 2 (somente se a galeria estiver vazia): coletar do card inicial usando onclick
            if (allPhotos.length === 0) {
                const photoItems = document.querySelectorAll('.photo-item');
                photoItems.forEach(item => {
                    const onclick = item.getAttribute('onclick');
                    if (onclick) {
                        // Formato: openPhotoModal('src', 'label', 'date', 'measurements')
                        const match = onclick.match(/openPhotoModal\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*)'\)/);
                        if (match) {
                            const parsedSrc = match[1];
                            const parsedLabel = match[2];
                            const parsedDate = match[3];
                            const parsedMeasurements = match[4] || '';
                            
                            // Usar nome do arquivo como chave única (getFileName já remove querystring)
                            const fileName = getFileName(parsedSrc);
                            
                            if (fileName && !seenFiles.has(fileName)) {
                                seenFiles.add(fileName);
                                allPhotos.push({
                                    src: parsedSrc,
                                    label: parsedLabel,
                                    date: parsedDate,
                                    measurements: parsedMeasurements
                                });
                            }
                        }
                    }
                });
            }
        }
        
        // Abrir modal de foto individual
        window.openPhotoModal = function(imageSrc, label, date, measurements = '') {
            // Se o array estiver vazio, coletar fotos uma única vez
            if (allPhotos.length === 0) {
                collectAllPhotos();
            }
            
            // Encontrar a foto atual
            const fileName = getFileName(imageSrc);
            currentPhotoIndex = allPhotos.findIndex(photo => getFileName(photo.src) === fileName);
            
            if (currentPhotoIndex === -1) {
                // Se não encontrou, adicionar com informações do onclick
                allPhotos.push({
                    src: imageSrc,
                    label: label,
                    date: date,
                    measurements: measurements || ''
                });
                currentPhotoIndex = allPhotos.length - 1;
            } else {
                // Atualizar com informações do onclick (garantir dados mais recentes)
                allPhotos[currentPhotoIndex].label = label;
                allPhotos[currentPhotoIndex].date = date;
                if (measurements && measurements.trim() !== '') {
                    allPhotos[currentPhotoIndex].measurements = measurements;
                }
            }
            
            // Atualizar o conteúdo do modal
            updatePhotoModalContent();
            
            const modal = document.getElementById('photoModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                lockBodyScroll();
            }
        };
        
        // Fechar modal de foto individual
        window.closePhotoModal = function() {
            const modal = document.getElementById('photoModal');
            if (modal) {
                modal.style.display = 'none';
                unlockBodyScroll();
            }
        };
        
        // Navegar entre fotos
        window.navigatePhoto = function(direction) {
            if (allPhotos.length === 0) return;
            
            currentPhotoIndex += direction;
            
            if (currentPhotoIndex < 0) {
                currentPhotoIndex = allPhotos.length - 1;
            } else if (currentPhotoIndex >= allPhotos.length) {
                currentPhotoIndex = 0;
            }
            
            updatePhotoModalContent();
        };
        
        // Atualizar conteúdo do modal de foto
        function updatePhotoModalContent() {
            if (allPhotos.length === 0) {
                return;
            }
            
            // REMOVER DUPLICATAS antes de usar o array
            const seenFileNames = new Set();
            const uniquePhotos = [];
            
            allPhotos.forEach(photo => {
                const fileName = getFileName(photo.src);
                if (fileName && !seenFileNames.has(fileName)) {
                    seenFileNames.add(fileName);
                    uniquePhotos.push(photo);
                }
            });
            
            // Atualizar allPhotos com array sem duplicatas
            if (uniquePhotos.length !== allPhotos.length) {
                allPhotos = uniquePhotos;
                
                // Ajustar currentPhotoIndex se necessário
                if (currentPhotoIndex >= allPhotos.length) {
                    currentPhotoIndex = allPhotos.length - 1;
                }
                if (currentPhotoIndex < 0) {
                    currentPhotoIndex = 0;
                }
            }
            
            if (currentPhotoIndex < 0 || currentPhotoIndex >= allPhotos.length) {
                return;
            }
            
            const photo = allPhotos[currentPhotoIndex];
            const modalImage = document.getElementById('photoModalImage');
            const modalLabel = document.getElementById('photoModalLabel');
            const modalDate = document.getElementById('photoModalDate');
            const modalMeasurements = document.getElementById('photoModalMeasurements');
            const photoCounter = document.getElementById('photoCounter');
            const prevBtn = document.getElementById('prevPhotoBtn');
            const nextBtn = document.getElementById('nextPhotoBtn');
            
            if (modalImage) modalImage.src = photo.src;
            if (modalLabel) modalLabel.textContent = photo.label || '';
            if (modalDate) modalDate.textContent = photo.date || '';
            if (modalMeasurements) {
                const measurementsText = photo.measurements || '';
                const shouldShow = measurementsText && measurementsText.trim() !== '';
                modalMeasurements.textContent = measurementsText;
                modalMeasurements.style.display = shouldShow ? 'block' : 'none';
            }
            
            if (photoCounter) {
                photoCounter.textContent = `${currentPhotoIndex + 1} de ${allPhotos.length}`;
            }
            
            // Mostrar/ocultar botões de navegação
            if (prevBtn) prevBtn.style.visibility = allPhotos.length > 1 ? 'visible' : 'hidden';
            if (nextBtn) nextBtn.style.visibility = allPhotos.length > 1 ? 'visible' : 'hidden';
        }
        
        // Event listeners para fechar modais ao clicar no overlay
        const galleryModal = document.getElementById('galleryModal');
        const photoModal = document.getElementById('photoModal');
        
        if (galleryModal) {
            galleryModal.addEventListener('click', function(event) {
                if (event.target === galleryModal) {
                    window.closeGalleryModal();
                }
            });
        }
        
        if (photoModal) {
            photoModal.addEventListener('click', function(event) {
                if (event.target === photoModal) {
                    window.closePhotoModal();
                }
            });
        }
        
        // Event listener para teclado (ESC e setas)
        document.addEventListener('keydown', function(e) {
            if (photoModal && photoModal.style.display === 'flex') {
                if (e.key === 'ArrowLeft') {
                    window.navigatePhoto(-1);
                } else if (e.key === 'ArrowRight') {
                    window.navigatePhoto(1);
                } else if (e.key === 'Escape') {
                    window.closePhotoModal();
                }
            }
            if (galleryModal && galleryModal.style.display === 'flex' && e.key === 'Escape') {
                window.closeGalleryModal();
            }
        });
        
        console.log('[view_user_progress] Inicialização completa!');
    } catch (error) {
        console.error('[view_user_progress] ERRO FATAL na inicialização:', error);
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

.progress-container {
    width: 100%;
}

.dashboard-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
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
    margin: 0;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-card h4 {
    font-size: 1.1rem;
}

.dashboard-card h4 i {
    color: var(--accent-orange);
    font-size: 1rem;
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

/* ========================================================================= */
/*       CSS DEFINITIVO E ROBUSTO PARA OS CARDS DE PROGRESSO               */
/* ========================================================================= */

/* 1. O Grid principal. Deixamos ele esticar os itens (comportamento padrão) */
.progress-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    /* A propriedade 'align-items' é removida para que os cards tenham a mesma altura */
}

/* 2. Transformamos AMBOS os cards em containers flexíveis */
.dashboard-card.weight-history-card,
.dashboard-card.photos-history-card {
    display: flex;
    flex-direction: column;
}

/* 3. O container do GRÁFICO vai crescer para preencher o espaço */
.weight-chart-container {
    position: relative;
    flex-grow: 1; /* Faz o container do gráfico se esticar para preencher a altura do card */
}

.btn-view-gallery {
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
    opacity: 1;
    pointer-events: auto;
}

.btn-view-gallery:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.btn-view-gallery-in-modal {
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

.btn-view-gallery-in-modal:hover {
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

/* 4. A galeria de fotos com layout simples e adaptável */
.photo-gallery {
    /* Esta margem é a chave: ela centraliza verticalmente a galeria de fotos
       dentro do espaço que o flex-grow cria. */
    margin: auto 0;
    
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

/* Item de foto individual */
.photo-item {
    position: relative;
    aspect-ratio: 1; /* Mantém o formato quadrado perfeito */
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

/* Legenda da foto */
.photo-date {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    padding: 0.75rem;
    color: white;
    font-size: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    text-align: left;
}

.photo-date span:first-child {
    font-weight: 600;
    color: var(--accent-orange);
}

.photo-date span:nth-child(2) {
    opacity: 0.8;
    font-size: 0.7rem;
}

/* Removido: photo-measurements não é mais exibido no card inicial */

/* Modal de Galeria */
.gallery-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 5vh 0;
}

.gallery-modal[style*="display: flex"] {
    display: flex !important;
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
    position: relative;
    margin: auto;
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

.sleep-modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
}

.sleep-modal-close:hover {
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
    grid-template-columns: repeat(auto-fill, minmax(200px, 200px));
    gap: 1rem;
    justify-content: start;
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
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.gallery-photo-type {
    color: var(--accent-orange);
    font-weight: 600;
}

.gallery-photo-date {
    font-size: 0.65rem;
    opacity: 0.9;
    font-weight: 400;
}

/* Removido: gallery-photo-measurements não é mais exibido na galeria */

/* Modal de Foto Individual */
.photo-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1001;
    display: none;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 5vh 0;
}

.photo-modal[style*="display: flex"] {
    display: flex !important;
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
    position: relative;
    margin: auto;
    box-sizing: border-box;
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


.photo-modal-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow-y: auto;
    box-sizing: border-box;
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
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.02);
    max-width: 100%;
    box-sizing: border-box;
    overflow-wrap: break-word;
}

.photo-info-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.photo-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
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

.photo-modal-measurements {
    width: 100%;
    padding: 1rem 1.25rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 8px;
    color: var(--accent-orange);
    font-size: 0.875rem;
    line-height: 1.6;
    text-align: center;
    margin-top: 1rem;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
    box-sizing: border-box;
}

.photo-counter {
    color: var(--text-secondary);
    font-size: 0.875rem;
}

/* === HEADER SUMMARY (ESTILO DAS OUTRAS ABAS) === */
.progress-summary-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.progress-summary-card .summary-main {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.progress-summary-card .summary-icon {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.05)) !important;
    border: 1px solid rgba(139, 92, 246, 0.2) !important;
    color: #8B5CF6 !important;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.progress-summary-card .summary-info {
    display: flex;
    flex-direction: column;
    gap: 0;
    flex: 1;
}

.progress-summary-card .summary-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.progress-summary-card .summary-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.progress-summary-card .summary-meta {
    margin: 0 0 0.25rem 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-summary-card .summary-description {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-style: italic;
    opacity: 0.8;
}

.progress-summary-card .summary-stat {
    text-align: center;
}

.progress-summary-card .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.progress-summary-card .stat-value.status-excellent {
    color: #4CAF50;
}

.progress-summary-card .stat-value.status-poor {
    color: #F44336;
}

.progress-summary-card .stat-value.status-fair {
    color: var(--text-secondary);
}

.progress-summary-card .stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.progress-summary-card .stat-description {
    font-size: 0.65rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
    opacity: 0.8;
}

/* Responsivo */
@media (max-width: 768px) {
    .progress-summary-card .summary-stats {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .progress-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .dashboard-card {
        padding: 1.25rem;
    }
    
    .photo-gallery {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    
    .photo-item {
        max-width: 100%;
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







