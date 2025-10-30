<?php
// view_user_diary.php - Aba do Diário Alimentar (REESCRITO COM AJAX DINÂMICO)
// Carrega apenas o dia atual via AJAX e navega entre dias dinamicamente
?>

<div id="tab-diary" class="tab-content active">
    <div class="diary-slider-container">
        <!-- Cabeçalho com navegação e resumo -->
        <div class="diary-header-redesign">
            <!-- Ano no topo -->
            <div class="diary-year" id="diaryYear"><?php echo date('Y'); ?></div>
            
            <!-- Navegação e data principal -->
            <div class="diary-nav-row">
                <button class="diary-nav-side diary-nav-left" onclick="navigateDiaryDate(-1)" type="button">
                    <i class="fas fa-chevron-left"></i>
                    <span id="diaryPrevDate">-</span>
                </button>
                
                <div class="diary-main-date">
                    <?php 
                    $weekdayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                    $monthsShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
                    ?>
                    <div class="diary-day-month" id="diaryDayMonth"><?php echo (int)date('d') . ' ' . $monthsShort[(int)date('n') - 1]; ?></div>
                    <div class="diary-weekday" id="diaryWeekday"><?php echo $weekdayNames[date('w')]; ?></div>
                </div>
                
                <button class="diary-nav-side diary-nav-right" onclick="navigateDiaryDate(1)" type="button">
                    <span id="diaryNextDate">-</span>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <!-- Resumo de calorias e macros -->
            <div class="diary-summary-row">
                <div class="diary-kcal" id="diarySummaryKcal">
                    <i class="fas fa-fire"></i>
                    <span>0 kcal</span>
                </div>
                <div class="diary-macros" id="diarySummaryMacros">
                    P: 0g • C: 0g • G: 0g
                </div>
            </div>
            
            <!-- Botão de calendário -->
            <button class="diary-calendar-icon-btn" onclick="openDiaryCalendar()" type="button" title="Ver calendário">
                <i class="fas fa-calendar-alt"></i>
            </button>
        </div>
        
        <!-- Container do conteúdo (substituído via AJAX) -->
        <div class="diary-slider-wrapper" id="diarySliderWrapper">
            <div class="diary-content-wrapper" id="diaryContentWrapper">
                <div class="diary-loading-state" id="diaryLoadingState">
                    <div class="loading-spinner"></div>
                    <p>Carregando diário...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============ CONFIGURAÇÃO E INICIALIZAÇÃO ============
const monthNamesShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
const monthNamesLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
const weekdayNames = ['DOMINGO','SEGUNDA','TERÇA','QUARTA','QUINTA','SEXTA','SÁBADO'];

let currentDiaryDate = new Date(); // Data atualmente exibida no diário
const userId = <?php echo $user_id; ?>;

// ============ FUNÇÃO PRINCIPAL DE CARREGAMENTO ============
async function loadDiaryForDate(targetDate, direction = 0) {
    const dateStr = targetDate.toISOString().split('T')[0];
    console.log('[diary] Carregando data:', dateStr, 'direction:', direction);
    
    const wrapper = document.getElementById('diaryContentWrapper');
    
    // Verificar se tem conteúdo para animar
    const hasContent = wrapper.innerHTML.trim() && !wrapper.querySelector('.diary-loading-state');
    
    // Se tiver conteúdo E direção, animar SAÍDA imediatamente
    if (hasContent && direction !== 0) {
        const translateX = direction > 0 ? '-100%' : '100%';
        wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
        wrapper.style.transform = `translateX(${translateX})`;
        wrapper.style.opacity = '0';
    }
    
    try {
        // Atualizar cabeçalho IMEDIATAMENTE (antes do AJAX)
        updateDiaryHeader(targetDate);
        
        // Chamar API
        const url = `actions/load_diary_days.php?user_id=${userId}&date=${dateStr}`;
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const html = await response.text();
        
        if (html.trim()) {
            // Parse HTML e extrair dados
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const dayContent = tempDiv.querySelector('.diary-content-day');
            
            if (dayContent) {
                // Extrair data-attributes do resumo
                const kcal = parseInt(dayContent.dataset.kcal || '0', 10);
                const protein = parseInt(dayContent.dataset.protein || '0', 10);
                const carbs = parseInt(dayContent.dataset.carbs || '0', 10);
                const fat = parseInt(dayContent.dataset.fat || '0', 10);
                
                // Se já animamos a saída, agora só inserir conteúdo e animar entrada
                if (hasContent && direction !== 0) {
                    // Inserir novo conteúdo fora da tela
                    wrapper.innerHTML = dayContent.querySelector('.diary-day-meals').innerHTML;
                    wrapper.style.transition = 'none';
                    wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                    wrapper.style.opacity = '1';
                    
                    // Forçar reflow
                    void wrapper.offsetHeight;
                    
                    // Animar entrada
                    wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                    wrapper.style.transform = 'translateX(0)';
                    
                    updateDiarySummary(kcal, protein, carbs, fat);
                    
                    // Resetar estilos
                    setTimeout(() => {
                        wrapper.style.transition = '';
                        wrapper.style.transform = '';
                        wrapper.style.opacity = '';
                    }, 200);
                } else {
                    // Primeira carga ou sem direção - sem animação
                    wrapper.innerHTML = dayContent.querySelector('.diary-day-meals').innerHTML;
                    wrapper.style.opacity = '1';
                    updateDiarySummary(kcal, protein, carbs, fat);
                }
            } else {
                // Dia sem dados
                if (hasContent && direction !== 0) {
                    wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-utensils"></i><p>Nenhum registro neste dia</p></div>';
                    wrapper.style.transition = 'none';
                    wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                    wrapper.style.opacity = '1';
                    void wrapper.offsetHeight;
                    wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                    wrapper.style.transform = 'translateX(0)';
                    setTimeout(() => { wrapper.style.transition = ''; wrapper.style.transform = ''; wrapper.style.opacity = ''; }, 200);
                    updateDiarySummary(0, 0, 0, 0);
                } else {
                    wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-utensils"></i><p>Nenhum registro neste dia</p></div>';
                    wrapper.style.opacity = '1';
                    updateDiarySummary(0, 0, 0, 0);
                }
            }
        } else {
            // Resposta vazia = sem registros
            if (hasContent && direction !== 0) {
                wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-utensils"></i><p>Nenhum registro neste dia</p></div>';
                wrapper.style.transition = 'none';
                wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                wrapper.style.opacity = '1';
                void wrapper.offsetHeight;
                wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                wrapper.style.transform = 'translateX(0)';
                setTimeout(() => { wrapper.style.transition = ''; wrapper.style.transform = ''; wrapper.style.opacity = ''; }, 200);
                updateDiarySummary(0, 0, 0, 0);
            } else {
                wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-utensils"></i><p>Nenhum registro neste dia</p></div>';
                wrapper.style.opacity = '1';
                updateDiarySummary(0, 0, 0, 0);
            }
        }
        
    } catch (error) {
        console.error('[diary] Erro ao carregar:', error);
        wrapper.innerHTML = '<div class="diary-error-state"><i class="fas fa-exclamation-triangle"></i><p>Erro ao carregar diário. Tente novamente.</p></div>';
    }
}

// ============ ATUALIZAR CABEÇALHO (DATA) ============
function updateDiaryHeader(targetDate) {
    const year = targetDate.getFullYear();
    const day = targetDate.getDate();
    const monthIdx = targetDate.getMonth();
    const weekdayIdx = targetDate.getDay();
    
    // Atualizar elementos
    document.getElementById('diaryYear').textContent = year;
    document.getElementById('diaryDayMonth').textContent = `${day} ${monthNamesShort[monthIdx]}`;
    document.getElementById('diaryWeekday').textContent = weekdayNames[weekdayIdx];
    
    // Atualizar botões de navegação
    const prevDate = new Date(targetDate);
    prevDate.setDate(prevDate.getDate() - 1);
    document.getElementById('diaryPrevDate').textContent = `${prevDate.getDate()} ${monthNamesLower[prevDate.getMonth()]}`;
    
    const nextDate = new Date(targetDate);
    nextDate.setDate(nextDate.getDate() + 1);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const nextBtn = document.querySelector('.diary-nav-right');
    if (nextDate <= today) {
        document.getElementById('diaryNextDate').textContent = `${nextDate.getDate()} ${monthNamesLower[nextDate.getMonth()]}`;
        nextBtn.style.visibility = 'visible';
    } else {
        nextBtn.style.visibility = 'hidden';
    }
}

// ============ ATUALIZAR RESUMO (KCAL/MACROS) ============
function updateDiarySummary(kcal, protein, carbs, fat) {
    document.getElementById('diarySummaryKcal').innerHTML = `<i class="fas fa-fire"></i><span>${kcal} kcal</span>`;
    document.getElementById('diarySummaryMacros').textContent = `P: ${protein}g • C: ${carbs}g • G: ${fat}g`;
}

// ============ NAVEGAÇÃO ENTRE DIAS ============
function navigateDiaryDate(direction) {
    const newDate = new Date(currentDiaryDate);
    newDate.setDate(newDate.getDate() + direction);
    
    // Verificar se não passou do dia atual
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (newDate <= today) {
        currentDiaryDate = newDate;
        loadDiaryForDate(currentDiaryDate, direction);
    }
}

// Expor para handlers inline
window.navigateDiaryDate = navigateDiaryDate;

// ============ INICIALIZAÇÃO ============
(function initDiary() {
    // Carregar dia atual ao abrir
    currentDiaryDate = new Date();
    loadDiaryForDate(currentDiaryDate);
    
    // Suporte a teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') navigateDiaryDate(-1);
        if (e.key === 'ArrowRight') navigateDiaryDate(1);
    });
    
    // Suporte a touch/swipe
    let sx = 0;
    const wrapper = document.getElementById('diarySliderWrapper');
    wrapper.addEventListener('touchstart', e => { sx = e.changedTouches[0].screenX; });
    wrapper.addEventListener('touchend', e => {
        const dx = sx - e.changedTouches[0].screenX;
        if (Math.abs(dx) > 50) {
            // Swipe para direita = dia anterior (-1), Swipe para esquerda = dia seguinte (+1)
            if (dx > 0) navigateDiaryDate(-1);
            else navigateDiaryDate(1);
        }
    });
})();
</script>

<style>
/* Loading state */
.diary-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
}

.loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid rgba(255, 152, 0, 0.1);
    border-top-color: var(--accent-orange);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.diary-loading-state p {
    color: var(--secondary-text-color);
    font-size: 0.95rem;
}

/* Error state */
.diary-error-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    color: #e74c3c;
}

.diary-error-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.7;
}

/* Empty state (já existe no CSS original) */
.diary-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
    color: var(--secondary-text-color);
}

.diary-empty-state i {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<!-- Modal de Calendário (código existente mantido) -->
<div id="diaryCalendarModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeDiaryCalendar()"></div>
    <div class="diary-calendar-wrapper">
        <button class="calendar-btn-close" onclick="closeDiaryCalendar()" type="button">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="calendar-header-title">
            <div class="calendar-year">2025</div>
        </div>
        
        <div class="calendar-nav-buttons">
            <button class="calendar-btn-nav" onclick="changeCalendarMonth(-1)" type="button">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month">OUT</div>
            <button class="calendar-btn-nav" id="nextMonthBtn" onclick="changeCalendarMonth(1)" type="button">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <div class="calendar-weekdays-row">
            <span>DOM</span>
            <span>SEG</span>
            <span>TER</span>
            <span>QUA</span>
            <span>QUI</span>
            <span>SEX</span>
            <span>SÁB</span>
        </div>
        
        <div class="calendar-days-grid" id="calendarDaysGrid"></div>
        
        <div class="calendar-separator">
            <div class="separator-line"></div>
            <div class="separator-dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            <div class="separator-line"></div>
        </div>
        
        <div class="calendar-footer-legend">
            <div class="legend-row">
                <span class="legend-marker today-marker"></span>
                <span class="legend-text">Hoje</span>
            </div>
            <div class="legend-row">
                <span class="legend-marker has-data-marker"></span>
                <span class="legend-text">Com registros</span>
            </div>
            <div class="legend-row">
                <span class="legend-marker no-data-marker"></span>
                <span class="legend-text">Sem registros</span>
            </div>
        </div>
    </div>
</div>

<script>
// ============ CALENDÁRIO ============
let currentCalendarDate = new Date();
let daysWithData = new Set();

// Buscar dados de dias com registros via PHP
<?php
// Buscar todos os dias que têm registros de refeições
$stmt_all_dates = $conn->prepare("
    SELECT DISTINCT DATE(date_consumed) as date 
    FROM sf_user_meal_log 
    WHERE user_id = ? 
    ORDER BY date DESC
");
$stmt_all_dates->bind_param("i", $user_id);
$stmt_all_dates->execute();
$all_dates_result = $stmt_all_dates->get_result();
$all_dates_with_data = [];
while ($row = $all_dates_result->fetch_assoc()) {
    $all_dates_with_data[] = $row['date'];
}
$stmt_all_dates->close();
echo "const allDatesWithData = " . json_encode($all_dates_with_data) . ";\n";
?>
allDatesWithData.forEach(date => daysWithData.add(date));

function openDiaryCalendar() {
    currentCalendarDate = new Date();
    renderCalendar();
    document.body.style.overflow = 'hidden';
    document.getElementById('diaryCalendarModal').classList.add('active');
}

function closeDiaryCalendar() {
    document.getElementById('diaryCalendarModal').classList.remove('active');
    document.body.style.overflow = '';
}

function changeCalendarMonth(direction) {
    const newDate = new Date(currentCalendarDate);
    newDate.setMonth(newDate.getMonth() + direction);
    
    const now = new Date();
    if (newDate.getFullYear() > now.getFullYear() || 
        (newDate.getFullYear() === now.getFullYear() && newDate.getMonth() > now.getMonth())) {
        return;
    }
    
    currentCalendarDate = newDate;
    renderCalendar();
}

function renderCalendar() {
    const year = currentCalendarDate.getFullYear();
    const month = currentCalendarDate.getMonth();
    
    document.querySelector('.calendar-year').textContent = year;
    document.querySelector('.calendar-month').textContent = monthNamesShort[month];
    
    const nextBtn = document.getElementById('nextMonthBtn');
    const now = new Date();
    if (year === now.getFullYear() && month === now.getMonth()) {
        nextBtn.style.opacity = '0.5';
        nextBtn.disabled = true;
    } else {
        nextBtn.style.opacity = '1';
        nextBtn.disabled = false;
    }
    
    const grid = document.getElementById('calendarDaysGrid');
    grid.innerHTML = '';
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    const startDay = firstDay.getDay();
    
    for (let i = startDay - 1; i >= 0; i--) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day other-month';
        dayEl.textContent = daysInPrevMonth - i;
        grid.appendChild(dayEl);
    }
    
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const dayEl = document.createElement('div');
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        dayEl.className = 'calendar-day';
        dayEl.textContent = day;
        dayEl.setAttribute('data-date', dateStr);
        
        const today = new Date();
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
            dayEl.classList.add('today');
        }
        
        if (daysWithData.has(dateStr)) {
            dayEl.classList.add('has-data');
        }
        
        dayEl.addEventListener('click', () => goToDiaryDate(dateStr));
        
        grid.appendChild(dayEl);
    }
    
    const totalCells = grid.children.length;
    const remainingCells = 42 - totalCells;
    
    if (remainingCells > 0) {
        for (let day = 1; day <= remainingCells; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'calendar-day other-month';
            dayEl.textContent = day;
            grid.appendChild(dayEl);
        }
    }
}

function goToDiaryDate(dateStr) {
    // Fechar calendário
    closeDiaryCalendar();
    
    // Navegar para a data
    const targetDate = new Date(dateStr + 'T00:00:00');
    const direction = targetDate > currentDiaryDate ? 1 : -1; // Se for futuro = +1, passado = -1
    currentDiaryDate = targetDate;
    loadDiaryForDate(currentDiaryDate, direction);
}

// Expor funções
window.openDiaryCalendar = openDiaryCalendar;
window.closeDiaryCalendar = closeDiaryCalendar;
window.changeCalendarMonth = changeCalendarMonth;
</script>

<style>
/* === Diário: respiro garantido entre cards - À PROVA DE BALA === */

/* 1) Espaço com seletor adjacente (não depende de gap) */
#tab-diary .diary-meal-card + .diary-meal-card {
  margin-top: 24px !important;
}

/* 2) Zera qualquer reset anterior que cole os cards */
#tab-diary .diary-meal-card {
  margin-bottom: 0 !important;   /* evita espaço duplo embaixo */
  display: block !important;     /* garante que o seletor adjacente funcione */
}

/* 3) Se estiver usando o wrapper flex, mantém o gap também (não conflita) */
#tab-diary .diary-content-wrapper {
  display: flex !important;
  flex-direction: column !important;
  gap: 24px !important;
}
</style>

<!-- fim do conteúdo da aba diário -->