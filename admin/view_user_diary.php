<?php
// view_user_diary.php - Aba do Diário Alimentar
// Extraído do view_user.php original com todo HTML, CSS e JavaScript
?>

<div id="tab-diary" class="tab-content active">
    <div class="diary-slider-container">
        <div class="diary-header-redesign">
            <script>
            // Stub robusto: enfileira cliques até a função real estar disponível
            (function(){
              const isFunction = (fn) => typeof fn === 'function';
              if (!isFunction(window.navigateDiary) || window.navigateDiary.__stub === true) {
                window.navigateDiaryQueue = window.navigateDiaryQueue || [];
                const stub = function(direction){
                  if (isFunction(window.__navigateDiaryReal)) {
                    try { window.__navigateDiaryReal(direction); return; } catch(e) { console.error(e); }
                  }
                  window.navigateDiaryQueue.push(direction);
                };
                stub.__stub = true;
                window.navigateDiary = stub;
              }
            })();
            </script>
            <!-- Ano no topo -->
            <?php
                $initialTs = strtotime($endDate);
                $initialYear = date('Y', $initialTs);
                $initialDay = (int)date('d', $initialTs);
                $monthIdx = (int)date('n', $initialTs) - 1;
                $monthsShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
                $monthsShortLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
                $weekdays = ['DOMINGO','SEGUNDA','TERÇA','QUARTA','QUINTA','SEXTA','SÁBADO'];
                $weekdayUp = $weekdays[(int)date('w', $initialTs)];
                $prevTs = strtotime('-1 day', $initialTs);
                $nextTs = strtotime('+1 day', $initialTs);
            ?>
            <div class="diary-year" id="diaryYear"><?php echo $initialYear; ?></div>
            
            <!-- Navegação e data principal -->
            <div class="diary-nav-row">
                <button class="diary-nav-side diary-nav-left" onclick="navigateDiary(-1)" type="button">
                    <i class="fas fa-chevron-left"></i>
                    <span id="diaryPrevDate"><?php echo (int)date('d', $prevTs) . ' ' . $monthsShortLower[(int)date('n', $prevTs)-1]; ?></span>
                </button>
                
                <div class="diary-main-date">
                    <div class="diary-day-month" id="diaryDayMonth"><?php echo $initialDay . ' ' . $monthsShort[$monthIdx]; ?></div>
                    <div class="diary-weekday" id="diaryWeekday"><?php echo $weekdayUp; ?></div>
        </div>
                
                <button class="diary-nav-side diary-nav-right" onclick="navigateDiary(1)" type="button">
                    <span id="diaryNextDate"><?php echo (int)date('d', $nextTs) . ' ' . $monthsShortLower[(int)date('n', $nextTs)-1]; ?></span>
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
        
        <div class="diary-slider-wrapper" id="diarySliderWrapper">
            <?php 
                // Garantir que $all_dates exista antes de usar
                $all_dates = [];
                for ($i = 0; $i < $daysToShow; $i++) {
                    $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
                    $all_dates[] = $current_date;
                }
                // Inverter ordem: mais antigo à esquerda, mais recente à direita
                $all_dates = array_reverse($all_dates);

                // total de dias para posicionar o slider inicialmente no último dia
                $initial_index = max(count($all_dates) - 1, 0);
                $initial_offset = $initial_index * 100; 
            ?>
            <div class="diary-slider-track" id="diarySliderTrack" style="transform: translateX(-<?php echo $initial_offset; ?>%);">
                <?php 
                $current_active_date = $all_dates[$initial_index] ?? null;
                foreach ($all_dates as $date): 
                    $meals = $meal_history[$date] ?? [];
                    $day_total_kcal = 0;
                    $day_total_prot = 0;
                    $day_total_carb = 0;
                    $day_total_fat = 0;
                    
                    if (!empty($meals)) {
                        foreach ($meals as $meal_type_slug => $items) {
                            $day_total_kcal += array_sum(array_column($items, 'kcal_consumed'));
                            $day_total_prot += array_sum(array_column($items, 'protein_consumed_g'));
                            $day_total_carb += array_sum(array_column($items, 'carbs_consumed_g'));
                            $day_total_fat += array_sum(array_column($items, 'fat_consumed_g'));
                        }
                    }
                    
                    // Formatar data por extenso
                    $timestamp = strtotime($date);
                    $day_of_week = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][date('w', $timestamp)];
                    $day_number = date('d', $timestamp);
                    $month_name_abbr = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][date('n', $timestamp) - 1];
                    $year = date('Y', $timestamp);
                ?>
                <div class="diary-day-card<?php echo ($date === $current_active_date ? ' active' : ''); ?>" data-date="<?php echo $date; ?>">
                    <!-- Dados escondidos para o JavaScript buscar -->
                    <div class="diary-day-summary" style="display: none;">
                        <div class="diary-summary-item">
                            <i class="fas fa-fire"></i>
                            <span><?php echo round($day_total_kcal); ?> kcal</span>
                        </div>
                        <div class="diary-summary-macros">
                            P: <?php echo round($day_total_prot); ?>g • 
                            C: <?php echo round($day_total_carb); ?>g • 
                            G: <?php echo round($day_total_fat); ?>g
                        </div>
                    </div>
                    
                    <div class="diary-day-meals">
                        <?php if (empty($meals)): ?>
                            <div class="diary-empty-state">
                                <i class="fas fa-utensils"></i>
                                <p>Nenhum registro neste dia</p>
                            </div>
            <?php else: ?>
                        <?php foreach ($meals as $meal_type_slug => $items): 
                            $total_kcal = array_sum(array_column($items, 'kcal_consumed'));
                            $total_prot = array_sum(array_column($items, 'protein_consumed_g'));
                            $total_carb = array_sum(array_column($items, 'carbs_consumed_g'));
                            $total_fat  = array_sum(array_column($items, 'fat_consumed_g'));
                        ?>
                                <div class="diary-meal-card">
                                    <div class="diary-meal-header">
                                        <div class="diary-meal-icon">
                                            <?php
                                            $meal_icons = [
                                                'breakfast' => 'fa-coffee',
                                                'morning_snack' => 'fa-apple-alt',
                                                'lunch' => 'fa-drumstick-bite',
                                                'afternoon_snack' => 'fa-cookie-bite',
                                                'dinner' => 'fa-pizza-slice',
                                                'evening_snack' => 'fa-ice-cream'
                                            ];
                                            $icon = $meal_icons[$meal_type_slug] ?? 'fa-utensils';
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="diary-meal-info">
                                    <h5><?php echo $meal_type_names[$meal_type_slug] ?? ucfirst($meal_type_slug); ?></h5>
                                            <span class="diary-meal-totals">
                                                <strong><?php echo round($total_kcal); ?> kcal</strong> • 
                                                P:<?php echo round($total_prot); ?>g • 
                                                C:<?php echo round($total_carb); ?>g • 
                                                G:<?php echo round($total_fat); ?>g
                                            </span>
                                    </div>
                                </div>
                                    <ul class="diary-food-list">
                                    <?php foreach ($items as $item): ?>
                                        <li>
                                            <span class="food-name"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                            <span class="food-quantity"><?php echo htmlspecialchars($item['quantity_display']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
                <?php endforeach; ?>
</div>
                </div>
            </div>
        </div>

<script>
// Diário v2: lógica simples, sem AJAX, 1 slide por vez
(function(){
  const monthNamesShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
  const monthNamesLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
  const weekdayNames = ['DOMINGO','SEGUNDA','TERÇA','QUARTA','QUINTA','SEXTA','SÁBADO'];

  let track, cards, index = 0;

  function collect(){
    track = document.getElementById('diarySliderTrack');
    cards = Array.from(document.querySelectorAll('.diary-day-card'));
  }

  function getLatestNonFutureIndex(){
    const today = new Date(); today.setHours(0,0,0,0);
    let latest = 0;
    for (let i=0;i<cards.length;i++){
      const d = new Date(cards[i].getAttribute('data-date') + 'T00:00:00');
      if (d <= today) latest = i;
    }
    return latest;
  }

  function setTransform(){
    if (!track) return;
    track.style.transition = 'transform .3s ease-in-out';
    track.style.transform = `translateX(${-index*100}%)`;
  }

  function updateHeader(){
    const card = cards[index]; if (!card) return;
    const dateStr = card.getAttribute('data-date');
    const d = new Date(dateStr + 'T00:00:00');
    document.getElementById('diaryYear').textContent = d.getFullYear();
    document.getElementById('diaryDayMonth').textContent = `${d.getDate()} ${monthNamesShort[d.getMonth()]}`;
    document.getElementById('diaryWeekday').textContent = weekdayNames[d.getDay()];

    const prevBtn = document.getElementById('diaryPrevDate');
    const nextBtn = document.getElementById('diaryNextDate');
    const prev = new Date(d); prev.setDate(prev.getDate()-1);
    prevBtn.textContent = `${prev.getDate()} ${monthNamesLower[prev.getMonth()]}`;

    const today = new Date(); today.setHours(0,0,0,0);
    if (index < cards.length-1){
      const nextDate = new Date(cards[index+1].getAttribute('data-date') + 'T00:00:00');
      nextBtn.textContent = `${nextDate.getDate()} ${monthNamesLower[nextDate.getMonth()]}`;
      nextBtn.parentElement.style.visibility = nextDate <= today ? 'visible' : 'hidden';
    } else {
      nextBtn.parentElement.style.visibility = 'hidden';
    }

    const summaryDiv = card.querySelector('.diary-day-summary');
    const kcalText = summaryDiv?.querySelector('.diary-summary-item span')?.textContent || '0 kcal';
    const macrosText = summaryDiv?.querySelector('.diary-summary-macros')?.textContent || 'P: 0g • C: 0g • G: 0g';
    document.getElementById('diarySummaryKcal').innerHTML = `<i class="fas fa-fire"></i><span>${kcalText}</span>`;
    document.getElementById('diarySummaryMacros').textContent = macrosText;
  }

  function render(){ setTransform(); updateHeader(); }

  function prev(){ if (index>0){ index--; render(); } }
  function next(){ if (index<cards.length-1){
      const nextDate = new Date(cards[index+1].getAttribute('data-date') + 'T00:00:00');
      const today = new Date(); today.setHours(0,0,0,0);
      if (nextDate <= today){ index++; render(); }
    }
  }

  function init(){
    collect(); if (!cards.length) return;
    index = getLatestNonFutureIndex();
    render(); if (track) track.style.visibility = 'visible';
    document.querySelector('.diary-nav-left')?.addEventListener('click', prev);
    document.querySelector('.diary-nav-right')?.addEventListener('click', next);
    document.addEventListener('keydown', (e)=>{ if (e.key==='ArrowLeft') prev(); if (e.key==='ArrowRight') next(); });
    let sx=0; track.addEventListener('touchstart',e=>{ sx=e.changedTouches[0].screenX; });
    track.addEventListener('touchend',e=>{ const dx=sx-e.changedTouches[0].screenX; if (Math.abs(dx)>50){ if (dx>0) prev(); else next(); } });
  }

  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.navigateDiary = (d)=>{ if (d<0) prev(); else if (d>0) next(); };
})();
</script>

<style>
/* Normaliza o slider para funcionar com transform (sem esconder cards) */
#diarySliderTrack { display: flex; visibility: hidden; will-change: transform; }
#diarySliderTrack .diary-day-card {
  display: block !important; /* não usar display:none */
  min-width: 100%;
  flex: 0 0 100%;
}
#diarySliderTrack { flex-wrap: nowrap; }
</style>

<!-- Modal de Calendário do Diário - REDESIGN COMPLETO -->
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
// Variáveis globais para o calendário
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
    
    // Não permitir ir além do mês atual
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
    
    // Atualizar cabeçalho
    document.querySelector('.calendar-year').textContent = year;
    document.querySelector('.calendar-month').textContent = 
        ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'][month];
    
    // Desabilitar botão próximo se for o mês atual
    const nextBtn = document.getElementById('nextMonthBtn');
    const now = new Date();
    if (year === now.getFullYear() && month === now.getMonth()) {
        nextBtn.style.opacity = '0.5';
        nextBtn.disabled = true;
    } else {
        nextBtn.style.opacity = '1';
        nextBtn.disabled = false;
    }
    
    // Limpar grid
    const grid = document.getElementById('calendarDaysGrid');
    grid.innerHTML = '';
    
    // Primeiro dia do mês
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());
    
    // Gerar dias do mês anterior (se necessário)
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    const startDay = firstDay.getDay();
    
    for (let i = startDay - 1; i >= 0; i--) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day other-month';
        dayEl.textContent = daysInPrevMonth - i;
        grid.appendChild(dayEl);
    }
    
    // Gerar dias do mês atual
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const dayEl = document.createElement('div');
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        dayEl.className = 'calendar-day';
        dayEl.textContent = day;
        dayEl.setAttribute('data-date', dateStr);
        
        // Verificar se é hoje
        const today = new Date();
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
            dayEl.classList.add('today');
        }
        
        // Verificar se tem dados
        if (daysWithData.has(dateStr)) {
            dayEl.classList.add('has-data');
        }
        
        // Adicionar evento de clique
        dayEl.addEventListener('click', () => goToDiaryDate(dateStr));
        
        grid.appendChild(dayEl);
    }
    
    // Gerar dias do próximo mês (se necessário)
    const totalCells = grid.children.length;
    const remainingCells = 42 - totalCells; // 6 semanas * 7 dias
    
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
    // Encontrar o card correspondente
    const cards = document.querySelectorAll('.diary-day-card');
    let targetIndex = -1;
    
    cards.forEach((card, index) => {
        if (card.getAttribute('data-date') === dateStr) {
            targetIndex = index;
        }
    });
    
    if (targetIndex !== -1) {
        // Se o dia está nos cards carregados, navegar diretamente
        goToDiaryIndex(targetIndex);
        closeDiaryCalendar();
    } else {
        // Se o dia não estiver nos cards carregados, carregar via AJAX
        loadSpecificDate(dateStr);
        closeDiaryCalendar();
    }
}

async function loadSpecificDate(dateStr) {
    try {
        const userId = <?php echo $user_id; ?>;
        const url = `actions/load_diary_days.php?user_id=${userId}&end_date=${dateStr}&days=1`;
        
        console.log('Carregando data específica:', dateStr);
        
        const response = await fetch(url);
        if (response.ok) {
            const html = await response.text();
            
            if (html.trim().length > 0) {
                // Adicionar novo card
                const diaryTrack = document.getElementById('diarySliderTrack');
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const newCards = tempDiv.querySelectorAll('.diary-day-card');
                
                if (newCards.length > 0) {
                    // Adicionar no início (mais antigo primeiro)
                    const fragment = document.createDocumentFragment();
                    while (tempDiv.firstChild) {
                        fragment.appendChild(tempDiv.firstChild);
                    }
                    diaryTrack.insertBefore(fragment, diaryTrack.firstChild);
                    
                    // Atualizar referência aos cards
                    updateDiaryCards();
                    
                    // Navegar para o card carregado
                    const targetIndex = Array.from(diaryCards).findIndex(card => 
                        card.getAttribute('data-date') === dateStr
                    );
                    
                    if (targetIndex !== -1) {
                        goToDiaryIndex(targetIndex);
                    }
                }
            }
        } else {
            console.error('Erro ao carregar data específica');
        }
    } catch (error) {
        console.error('Erro ao carregar data específica:', error);
    }
}
</script>

<!-- estilos seguem no view_user_addon.css -->
<!-- fim do conteúdo da aba diário -->
