<?php
// view_user_diary.php - Aba do Diário Alimentar
// Extraído do view_user.php original com todo HTML, CSS e JavaScript
?>

<div id="tab-diary" class="tab-content active">
    <div class="diary-slider-container">
        <div class="diary-header-redesign">
            <!-- Ano no topo -->
            <div class="diary-year" id="diaryYear">2025</div>
            
            <!-- Navegação e data principal -->
            <div class="diary-nav-row">
                <button class="diary-nav-side diary-nav-left" onclick="navigateDiary(-1)" type="button">
                    <i class="fas fa-chevron-left"></i>
                    <span id="diaryPrevDate">26 out</span>
                </button>
                
                <div class="diary-main-date">
                    <div class="diary-day-month" id="diaryDayMonth">27 OUT</div>
                    <div class="diary-weekday" id="diaryWeekday">SEGUNDA</div>
        </div>
                
                <button class="diary-nav-side diary-nav-right" onclick="navigateDiary(1)" type="button">
                    <span id="diaryNextDate">28 out</span>
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
            <div class="diary-slider-track" id="diarySliderTrack">
                <?php 
                // Gerar array com TODOS os dias, mesmo se não houver dados
                $all_dates = [];
                for ($i = 0; $i < $daysToShow; $i++) {
                    $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
                    $all_dates[] = $current_date;
                }
                
                // Debug: verificar intervalo gerado
                // Primeira data (mais antiga) será $all_dates[0] após reverse
                // Última data (mais recente) será $all_dates[count-1] após reverse
                
                // Inverter ordem: mais antigo à esquerda, mais recente à direita
                $all_dates = array_reverse($all_dates);
                
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
                <div class="diary-day-card" data-date="<?php echo $date; ?>">
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
// Sistema de navegação do diário
let diaryCards = document.querySelectorAll('.diary-day-card');
let currentDiaryIndex = diaryCards.length - 1; // Iniciar no último (dia mais recente)
const diaryTrack = document.getElementById('diarySliderTrack');
let isLoadingMoreDays = false; // Flag para evitar múltiplas chamadas

// Função para atualizar referência aos cards
function updateDiaryCards() {
    diaryCards = document.querySelectorAll('.diary-day-card');
}

function updateDiaryDisplay() {
    // Adicionar transição suave para o slider
    diaryTrack.style.transition = 'transform 0.3s ease-in-out';
    
    const offset = -currentDiaryIndex * 100;
    diaryTrack.style.transform = `translateX(${offset}%)`;
    
    const currentCard = diaryCards[currentDiaryIndex];
    if (!currentCard) return;
    
    const date = currentCard.getAttribute('data-date');
    const dateObj = new Date(date + 'T00:00:00');
    
    // Nomes dos meses e dias da semana
    const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    const monthNamesLower = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const weekdayNames = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    
    // Debug
    console.log('Diary index:', currentDiaryIndex, 'Date:', date, 'Month:', dateObj.getMonth());
    
    // Atualizar ano
    document.getElementById('diaryYear').textContent = dateObj.getFullYear();
    
    // Atualizar dia e mês principal
    const day = dateObj.getDate();
    const month = monthNamesShort[dateObj.getMonth()];
    document.getElementById('diaryDayMonth').textContent = `${day} ${month}`;
    
    // Atualizar dia da semana
    document.getElementById('diaryWeekday').textContent = weekdayNames[dateObj.getDay()];
    
    // Atualizar datas de navegação (anterior e próximo)
    const prevIndex = currentDiaryIndex - 1;
    const nextIndex = currentDiaryIndex + 1;
    
    // Atualizar data anterior (sempre mostrar o dia anterior real)
    const prevBtn = document.getElementById('diaryPrevDate');
    if (prevBtn) {
        // Calcular sempre o dia anterior baseado na data atual
        const currentDate = new Date(date + 'T00:00:00');
        const prevDate = new Date(currentDate);
        prevDate.setDate(prevDate.getDate() - 1);
        
        prevBtn.textContent = `${prevDate.getDate()} ${monthNamesLower[prevDate.getMonth()]}`;
        prevBtn.parentElement.style.visibility = 'visible';
    }
    
    // Atualizar data próxima (se existir e não for futuro)
    const nextBtn = document.getElementById('diaryNextDate');
    if (nextBtn) {
        if (nextIndex < diaryCards.length && diaryCards[nextIndex]) {
            const nextDate = new Date(diaryCards[nextIndex].getAttribute('data-date') + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (nextDate <= today) {
                nextBtn.textContent = `${nextDate.getDate()} ${monthNamesLower[nextDate.getMonth()]}`;
                nextBtn.parentElement.style.visibility = 'visible';
            } else {
                nextBtn.parentElement.style.visibility = 'hidden';
            }
        } else {
            nextBtn.parentElement.style.visibility = 'hidden';
        }
    }
    
    // Buscar e atualizar resumo de calorias e macros do card atual
    const summaryDiv = currentCard.querySelector('.diary-day-summary');
    if (summaryDiv) {
        const kcalText = summaryDiv.querySelector('.diary-summary-item span');
        const macrosText = summaryDiv.querySelector('.diary-summary-macros');
        
        if (kcalText) {
            document.getElementById('diarySummaryKcal').innerHTML = 
                `<i class="fas fa-fire"></i><span>${kcalText.textContent}</span>`;
        }
        
        if (macrosText) {
            document.getElementById('diarySummaryMacros').textContent = macrosText.textContent;
        }
    } else {
        // Sem dados
        document.getElementById('diarySummaryKcal').innerHTML = 
            `<i class="fas fa-fire"></i><span>0 kcal</span>`;
        document.getElementById('diarySummaryMacros').textContent = 'P: 0g • C: 0g • G: 0g';
    }
    
    // Atualizar estado dos botões de navegação
    updateNavigationButtons();
}

function updateNavigationButtons() {
    const currentCard = diaryCards[currentDiaryIndex];
    if (!currentCard) return;
    
    const currentDate = currentCard.getAttribute('data-date');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const currentDateObj = new Date(currentDate + 'T00:00:00');
    
    console.log('Current date:', currentDate, 'Today:', today.toISOString().split('T')[0]);
    
    // Botão de avançar (direita) - desabilitar se estiver no dia atual ou futuro
    const nextBtn = document.querySelector('.diary-nav-right');
    if (nextBtn) {
        // Verificar se existe um próximo card e se ele não é futuro
        const nextIndex = currentDiaryIndex + 1;
        if (nextIndex < diaryCards.length) {
            const nextCard = diaryCards[nextIndex];
            const nextDate = nextCard.getAttribute('data-date');
            const nextDateObj = new Date(nextDate + 'T00:00:00');
            
            if (nextDateObj > today) {
                nextBtn.classList.add('disabled');
                nextBtn.disabled = true;
            } else {
                nextBtn.classList.remove('disabled');
                nextBtn.disabled = false;
            }
        } else {
            // Não há próximo card
            nextBtn.classList.add('disabled');
            nextBtn.disabled = true;
        }
    }
}

function navigateDiary(direction) {
    let newIndex = currentDiaryIndex + direction;
    
    // Se tentar ir para frente
    if (direction > 0) {
        // Verificar se o próximo dia seria futuro
        if (newIndex >= diaryCards.length) {
            // Já está no último, não faz nada
            return;
        }
        
        const nextCard = diaryCards[newIndex];
        if (nextCard) {
            const nextDate = nextCard.getAttribute('data-date');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const nextDateObj = new Date(nextDate + 'T00:00:00');
            
            // Se o próximo dia for futuro, não permite
            if (nextDateObj > today) {
                return; // Bloqueia navegação
            }
        }
    }
    
    // Se tentar ir para trás
    if (direction < 0) {
        // Se já está carregando, ignora
        if (isLoadingMoreDays) {
            console.log('Já está carregando mais dias...');
            return;
        }
        
        // Calcular a data do dia anterior
        const currentCard = diaryCards[currentDiaryIndex];
        if (currentCard) {
            const currentDate = currentCard.getAttribute('data-date');
            const dateObj = new Date(currentDate + 'T00:00:00');
            dateObj.setDate(dateObj.getDate() - 1);
            const prevDate = dateObj.toISOString().split('T')[0];
            
            // Verificar se já existe um card para essa data
            const existingCardIndex = Array.from(diaryCards).findIndex(card => 
                card.getAttribute('data-date') === prevDate
            );
            
            if (existingCardIndex !== -1) {
                // Se existe, navegar diretamente
                currentDiaryIndex = existingCardIndex;
                updateDiaryDisplay();
                return;
            } else {
                // Se não existe, carregar via AJAX
                console.log('Carregando 1 dia anterior via AJAX. Data atual:', currentDate, 'Nova end_date:', prevDate);
                loadMoreDiaryDays(prevDate, 1);
                return;
            }
        }
    }
    
    // Se tentar ir para frente e já está no último card (mais recente)
    if (direction > 0 && newIndex >= diaryCards.length) {
        console.log('Já está no dia mais recente');
        return;
    }
    
    currentDiaryIndex = newIndex;
    updateDiaryDisplay();
}

       async function loadMoreDiaryDays(endDate, daysToLoad = 1) {
           if (isLoadingMoreDays) {
               console.log('Já está carregando, ignorando chamada duplicada...');
               return;
           }
           
           isLoadingMoreDays = true;
           
           try {
               // Buscar apenas 1 dia via AJAX (sem loading visual)
               const userId = <?php echo $user_id; ?>;
               const url = `actions/load_diary_days.php?user_id=${userId}&end_date=${endDate}&days=${daysToLoad}`;
               
               console.log('Fazendo requisição AJAX para:', url);
               
               const response = await fetch(url);
               console.log('Resposta recebida, status:', response.status);
               
               if (response.ok) {
                   const html = await response.text();
                   console.log('HTML recebido, tamanho:', html.length);
                   
                   if (html.trim().length === 0) {
                       throw new Error('Resposta vazia do servidor');
                   }
                   
                   // Adicionar novo card ANTES dos existentes
                   const diaryTrack = document.getElementById('diarySliderTrack');
                   
                   // Criar container temporário
                   const tempDiv = document.createElement('div');
                   tempDiv.innerHTML = html;
                   const newCards = tempDiv.querySelectorAll('.diary-day-card');
                   
                   console.log('Novos cards encontrados:', newCards.length);
                   
                   if (newCards.length > 0) {
                       // Adicionar novo card no início (mais antigo primeiro)
                       const fragment = document.createDocumentFragment();
                       while (tempDiv.firstChild) {
                           fragment.appendChild(tempDiv.firstChild);
                       }
                       diaryTrack.insertBefore(fragment, diaryTrack.firstChild);
                       
                       // Atualizar referência aos cards
                       updateDiaryCards();
                       
                       // Navegar automaticamente para o dia carregado (primeiro card = mais antigo)
                       currentDiaryIndex = 0;
                       
                       console.log(`Adicionado 1 novo card. Total: ${diaryCards.length}`);
                       console.log('Primeira data após adição:', diaryCards[0]?.getAttribute('data-date'));
                       console.log('Última data após adição:', diaryCards[diaryCards.length - 1]?.getAttribute('data-date'));
                       console.log('Navegando para o dia carregado, índice:', currentDiaryIndex);
                       
                       // Manter URL inalterada - não atualizar endDate na URL
                       // const urlParams = new URLSearchParams(window.location.search);
                       // urlParams.set('end_date', endDate);
                       // window.history.replaceState({}, '', window.location.pathname + '?' + urlParams.toString());
                       
                       // Simular swipe: primeiro ir para posição anterior, depois para a correta
                       const previousIndex = currentDiaryIndex + 1;
                       const previousOffset = -previousIndex * 100;
                       
                       // Posicionar no card anterior (como se estivesse vindo da direita)
                       diaryTrack.style.transition = 'none';
                       diaryTrack.style.transform = `translateX(${previousOffset}%)`;
                       
                       // Forçar reflow
                       diaryTrack.offsetHeight;
                       
                       // Agora animar para a posição correta
                       diaryTrack.style.transition = 'transform 0.3s ease-in-out';
                       diaryTrack.style.transform = `translateX(${-currentDiaryIndex * 100}%)`;
                       
                       // Atualizar display
                       updateDiaryDisplay();
                   } else {
                       console.log('Nenhum novo card encontrado na resposta');
                   }
               } else {
                   throw new Error(`HTTP error! status: ${response.status}`);
               }
           } catch (error) {
               console.error('Erro ao carregar mais dias:', error);
               alert('Erro ao carregar mais dias: ' + error.message);
           } finally {
               isLoadingMoreDays = false;
           }
       }


function goToDiaryIndex(index) {
    currentDiaryIndex = index;
    updateDiaryDisplay();
}

// Suporte a swipe/touch
let touchStartX = 0;
let touchEndX = 0;

diaryTrack.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
});

diaryTrack.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            // Swipe left - dia anterior
            navigateDiary(-1);
        } else {
            // Swipe right - próximo dia
            navigateDiary(1);
        }
    }
}

// Suporte a teclado
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') navigateDiary(-1);
    if (e.key === 'ArrowRight') navigateDiary(1);
});

// Inicializar quando a aba de diário estiver ativa
function initDiary() {
    if (diaryCards.length > 0) {
        updateDiaryDisplay();
    }
}

// Inicializar se a aba já estiver ativa ou quando for aberta
if (document.getElementById('tab-diary').classList.contains('active')) {
    initDiary();
}

// Observar mudanças de aba
const tabLinks = document.querySelectorAll('.tab-link');
tabLinks.forEach(link => {
    link.addEventListener('click', function() {
        if (this.getAttribute('data-tab') === 'diary') {
            setTimeout(initDiary, 100);
        }
    });
});
</script>

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

<style>
/* ========== DIÁRIO ALIMENTAR - ESTILOS (cópia do referência) ========== */
.diary-slider-container { background: linear-gradient(145deg, #1a1a1a, #2d2d2d); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); }
.diary-header-redesign { display: flex; flex-direction: column; align-items: center; margin-bottom: 2rem; position: relative; }
.diary-year { font-size: 1.2rem; font-weight: 600; color: #ff6b35; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 2px; }
.diary-nav-row { display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: 400px; margin-bottom: 1.5rem; }
.diary-nav-side { background: linear-gradient(145deg, #ff6b35, #ff8533); border: none; border-radius: 50px; padding: 12px 20px; color: #fff; font-weight: 600; cursor: pointer; transition: all .3s ease; display: flex; align-items: center; gap: 8px; font-size: .9rem; box-shadow: 0 4px 15px rgba(255,107,53,.3); }
.diary-nav-side:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,107,53,.4); }
.diary-nav-side.disabled, .diary-nav-side:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.diary-main-date { text-align: center; flex: 1; margin: 0 1rem; }
.diary-day-month { font-size: 2.5rem; font-weight: 700; color: #fff; line-height: 1; margin-bottom: .5rem; text-shadow: 0 2px 4px rgba(0,0,0,.3); }
.diary-weekday { font-size: 1rem; color: #ccc; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }
.diary-summary-row { display: flex; align-items: center; justify-content: center; gap: 2rem; margin-bottom: 1.5rem; padding: 1rem 2rem; background: rgba(255,255,255,.05); border-radius: 15px; border: 1px solid rgba(255,255,255,.1); }
.diary-kcal { display: flex; align-items: center; gap: 8px; font-size: 1.2rem; font-weight: 600; color: #ff6b35; }
.diary-kcal i { font-size: 1.4rem; }
.diary-macros { font-size: 1rem; color: #ccc; font-weight: 500; }
.diary-calendar-icon-btn { position: absolute; top: 0; right: 0; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 50%; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all .3s ease; font-size: 1.2rem; }
.diary-calendar-icon-btn:hover { background: rgba(255,107,53,.2); border-color: #ff6b35; transform: scale(1.1); }
.diary-slider-wrapper { overflow: hidden; border-radius: 15px; background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.05); }
.diary-slider-track { display: flex; transition: transform .3s ease-in-out; }
.diary-day-card { min-width: 100%; padding: 2rem; background: linear-gradient(145deg, #2a2a2a, #1e1e1e); border-radius: 15px; margin: 0 .5rem; box-shadow: 0 8px 25px rgba(0,0,0,.2); border: 1px solid rgba(255,255,255,.1); }
.diary-day-summary { display: none; }
.diary-day-meals { min-height: 300px; }
.diary-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; color: #666; text-align: center; }
.diary-empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: .5; }
.diary-empty-state p { font-size: 1.1rem; font-weight: 500; }
.diary-meal-card { background: rgba(255,255,255,.05); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,.1); transition: all .3s ease; }
.diary-meal-card:hover { background: rgba(255,255,255,.08); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,.2); }
.diary-meal-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.diary-meal-icon { width: 50px; height: 50px; background: linear-gradient(145deg, #ff6b35, #ff8533); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.2rem; box-shadow: 0 4px 15px rgba(255,107,53,.3); }
.diary-meal-info h5 { color: #fff; font-size: 1.2rem; font-weight: 600; margin: 0 0 .5rem 0; }
.diary-meal-totals { color: #ccc; font-size: .9rem; font-weight: 500; }
.diary-meal-totals strong { color: #ff6b35; font-weight: 700; }
.diary-food-list { list-style: none; padding: 0; margin: 0; }
.diary-food-list li { display: flex; justify-content: space-between; align-items: center; padding: .8rem 0; border-bottom: 1px solid rgba(255,255,255,.05); transition: all .2s ease; }
.diary-food-list li:hover { background: rgba(255,255,255,.03); padding-left: .5rem; border-radius: 8px; }
.diary-food-list li:last-child { border-bottom: none; }
.food-name { color: #fff; font-weight: 500; flex: 1; }
.food-quantity { color: #ff6b35; font-weight: 600; font-size: .9rem; }
/* Modal calendário */
.diary-calendar-wrapper { position: relative; background: linear-gradient(145deg, rgba(30,30,30,.98), rgba(20,20,20,.98)); border: 1px solid rgba(255,255,255,.1); border-radius: 20px; padding: 2.5rem; max-width: 480px; width: 90%; box-shadow: 0 25px 70px rgba(0,0,0,.8); }
.calendar-btn-close { position: absolute; top: 1.5rem; right: 1.5rem; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all .3s ease; font-size: 1.2rem; }
.calendar-btn-close:hover { background: rgba(255,107,53,.2); border-color: #ff6b35; transform: scale(1.1); }
.calendar-header-title { text-align: center; margin-bottom: 2rem; }
.calendar-year { font-size: 2rem; font-weight: 700; color: #ff6b35; text-transform: uppercase; letter-spacing: 3px; }
.calendar-nav-buttons { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
.calendar-btn-nav { background: linear-gradient(145deg, #ff6b35, #ff8533); border: none; border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all .3s ease; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(255,107,53,.3); }
.calendar-btn-nav:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,107,53,.4); }
.calendar-btn-nav:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.calendar-month { font-size: 1.5rem; font-weight: 600; color: #fff; text-transform: uppercase; letter-spacing: 2px; }
.calendar-weekdays-row { display: grid; grid-template-columns: repeat(7, 1fr); gap: .5rem; margin-bottom: 1rem; }
.calendar-weekdays-row span { text-align: center; font-weight: 600; color: #ccc; font-size: .9rem; padding: .5rem 0; text-transform: uppercase; letter-spacing: 1px; }
.calendar-days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: .5rem; margin-bottom: 2rem; }
.calendar-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 8px; cursor: pointer; transition: all .3s ease; font-weight: 500; color: #fff; background: rgba(255,255,255,.05); border: 1px solid transparent; }
.calendar-day:hover { background: rgba(255,107,53,.2); border-color: #ff6b35; transform: scale(1.1); }
.calendar-day.today { background: linear-gradient(145deg, #ff6b35, #ff8533); color: #fff; font-weight: 700; box-shadow: 0 4px 15px rgba(255,107,53,.4); }
.calendar-day.has-data { background: rgba(76,175,80,.2); border-color: #4caf50; color: #4caf50; }
.calendar-day.has-data:hover { background: rgba(76,175,80,.3); transform: scale(1.1); }
.calendar-day.other-month { color: #666; opacity: .5; }
.calendar-separator { display: flex; align-items: center; margin: 2rem 0; }
.separator-line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent); }
.separator-dots { display: flex; gap: .5rem; margin: 0 1rem; }
.dot { width: 6px; height: 6px; background: #ff6b35; border-radius: 50%; }
.calendar-footer-legend { display: flex; flex-direction: column; gap: .8rem; }
.legend-row { display: flex; align-items: center; gap: .8rem; }
.legend-marker { width: 16px; height: 16px; border-radius: 4px; flex-shrink: 0; }
.today-marker { background: linear-gradient(145deg, #ff6b35, #ff8533); }
.has-data-marker { background: rgba(76,175,80,.8); }
.no-data-marker { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); }
.legend-text { color: #ccc; font-size: .9rem; font-weight: 500; }
@media (max-width: 768px){
  .diary-slider-container { padding:1rem; margin-bottom:1rem; }
  .diary-nav-row { max-width:100%; gap:1rem; }
  .diary-nav-side { padding:10px 16px; font-size:.8rem; }
  .diary-day-month { font-size:2rem; }
  .diary-summary-row { flex-direction:column; gap:1rem; padding:1rem; }
  .diary-day-card { padding:1.5rem; margin:0 .25rem; }
  .diary-calendar-wrapper { padding:1.5rem; width:95%; }
  .calendar-days-grid { gap:.25rem; }
  .calendar-day { font-size:.9rem; }
}
</style>
<!-- fim do conteúdo da aba diário -->
