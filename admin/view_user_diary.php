<!-- view_user_diary.php -->
<!-- Conteúdo completo da aba Diário: HTML, CSS e JS -->

<div id="tab-diary" class="tab-content active">
    <div class="view-user-tab">
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
// ======== DIÁRIO: VARS GLOBAIS ========
let diaryCards = [];
let currentDiaryIndex = 0;
let diaryTrack = null;
let isLoadingMoreDays = false; // se você usa lazy load

// ======== DIÁRIO: MOSTRAR APENAS O CARD ATIVO ========
function setActiveDiaryCard(index) {
  diaryCards.forEach((card, i) => {
    if (i === index) {
      card.classList.add('active');
      card.style.display = 'block';
    } else {
      card.classList.remove('active');
      card.style.display = 'none';
    }
  });
}

function updateDiaryDisplay() {
    diaryTrack = document.getElementById('diarySliderTrack');
    if (!diaryTrack) return; // <-- evita erro se o elemento não existir ainda
    
    const currentCard = diaryCards[currentDiaryIndex];
    if (!currentCard) return;
    
    // Adicionar transição suave para o slider
    diaryTrack.style.transition = 'transform 0.3s ease-in-out';
    
    const offset = -currentDiaryIndex * 100;
    diaryTrack.style.transform = `translateX(${offset}%)`;
    
    // CORREÇÃO PRINCIPAL: Ajustar altura do slider dinamicamente
    // Garantir que apenas o card ativo fique visível
    diaryCards.forEach((card, index) => {
        card.classList.toggle('active', index === currentDiaryIndex);
    });
    
    // Resetar altura para auto primeiro
    diaryTrack.style.height = 'auto';
    
    // Aguardar um frame para o layout se ajustar
    requestAnimationFrame(() => {
        // Definir altura baseada no card atual (agora só o ativo)
        const cardHeight = currentCard.scrollHeight;
        diaryTrack.style.height = cardHeight + 'px';
    });
    
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
    updateDiaryNavButtons();
}

function updateDiaryNavButtons() {
  const today = new Date(); today.setHours(0,0,0,0);
  const nextBtn = document.querySelector('.diary-nav-right');
  if (!nextBtn) return;
  const nextIdx = currentDiaryIndex + 1;
  if (nextIdx >= diaryCards.length) {
    nextBtn.classList.add('disabled'); nextBtn.disabled = true; return;
  }
  const nextDateObj = new Date(diaryCards[nextIdx].getAttribute('data-date') + 'T00:00:00');
  const allow = nextDateObj <= today;
  nextBtn.classList.toggle('disabled', !allow);
  nextBtn.disabled = !allow;
}
function navigateDiary(direction) {
  if (!Array.isArray(diaryCards) || diaryCards.length === 0) return;

  const nextIdx = currentDiaryIndex + direction;

  // Bloqueia avançar para futuro
  if (direction > 0) {
    if (nextIdx >= diaryCards.length) return;
    const nd = new Date(diaryCards[nextIdx].getAttribute('data-date') + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    if (nd > today) return;
  }

  // Válida voltar
  if (nextIdx < 0) return;

  currentDiaryIndex = nextIdx;
  setActiveDiaryCard(currentDiaryIndex);
  updateDiaryDisplay();
}

// ======== DIÁRIO: CALENDÁRIO ========
function openDiaryCalendarSafely() {
  // chama sua função existente de calendário, só garantimos que não quebre
  if (typeof openDiaryCalendar === 'function') openDiaryCalendar();
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
                       diaryTrack = document.getElementById('diarySliderTrack');
                       if (diaryTrack) {
                           diaryTrack.style.transition = 'none';
                           diaryTrack.style.transform = `translateX(${previousOffset}%)`;
                           
                           // Forçar reflow
                           diaryTrack.offsetHeight;
                           
                           // Agora animar para a posição correta
                           diaryTrack.style.transition = 'transform 0.3s ease-in-out';
                           diaryTrack.style.transform = `translateX(${-currentDiaryIndex * 100}%)`;
                       }
                       
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

// Suporte a swipe/touch (movido para dentro do DOMContentLoaded)
let touchStartX = 0;
let touchEndX = 0;

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


// Inicializar quando a aba de diário estiver ativa
function initDiary() {
    if (diaryCards.length > 0) {
        updateDiaryDisplay();
    }
}

// ======== DIÁRIO: POLLING GARANTIDO ========
function waitForDiaryElements() {
  const track = document.getElementById('diarySliderTrack');
  const calendar =
    document.getElementById('calendarButton') ||
    document.querySelector('.diary-calendar-icon-btn') ||
    document.querySelector('.calendar-btn');

  if (track && calendar) {
    console.log("🎯 Diário pronto — inicializando");
    initDiaryListeners();
  } else {
    setTimeout(waitForDiaryElements, 300); // tenta novamente a cada 300ms
  }
}

function initDiaryListeners() {
  diaryCards = Array.from(document.querySelectorAll('#diarySliderTrack .diary-day-card'));
  diaryTrack = document.getElementById('diarySliderTrack');

  if (!diaryTrack || diaryCards.length === 0) {
    console.warn('Nenhum card de diário encontrado.');
    return;
  }

  const todayStr = new Date().toISOString().slice(0, 10);
  const todayIdx = diaryCards.findIndex(c => c.getAttribute('data-date') === todayStr);
  currentDiaryIndex = (todayIdx !== -1) ? todayIdx : (diaryCards.length - 1);

  // Garantir que apenas o card ativo seja visível
  diaryCards.forEach((card, index) => {
    card.classList.toggle('active', index === currentDiaryIndex);
  });

  setActiveDiaryCard(currentDiaryIndex);
  updateDiaryDisplay();

  const prevBtn = document.querySelector('.diary-nav-left');
  const nextBtn = document.querySelector('.diary-nav-right');
  const calendarBtn =
    document.getElementById('calendarButton') ||
    document.querySelector('.diary-calendar-icon-btn') ||
    document.querySelector('.calendar-btn');

  if (prevBtn) prevBtn.addEventListener('click', () => navigateDiary(-1));
  if (nextBtn) nextBtn.addEventListener('click', () => navigateDiary(1));
  if (calendarBtn) calendarBtn.addEventListener('click', openDiaryCalendarSafely);

  let touchStartX = 0, touchEndX = 0;
  diaryTrack.addEventListener('touchstart', e => (touchStartX = e.changedTouches[0].screenX));
  diaryTrack.addEventListener('touchend', e => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') navigateDiary(-1);
    if (e.key === 'ArrowRight') navigateDiary(1);
  });

  // ====== Quando trocar de aba ======
  const tabLinks = document.querySelectorAll('.tab-link');
  tabLinks.forEach(link => {
    link.addEventListener('click', function() {
      if (this.getAttribute('data-tab') === 'diary') {
        setTimeout(initDiary, 150);
      }
    });
  });

  console.log("✅ Listeners do diário iniciados");
}

// inicia o loop até encontrar os elementos
waitForDiaryElements();

// ============ INICIALIZAÇÃO SEGURA DO CALENDÁRIO DO DIÁRIO ============
document.addEventListener("DOMContentLoaded", () => {
  const nextBtn = document.getElementById('diaryNextMonthBtn');
  const prevBtn = document.getElementById('diaryPrevMonthBtn');
  const closeBtn = document.getElementById('diaryCloseCalendar');
  const openBtn =
    document.getElementById('diaryCalendarButton') ||
    document.querySelector('.diary-calendar-icon-btn') ||
    document.querySelector('.calendar-btn');

  if (nextBtn) nextBtn.addEventListener('click', () => changeDiaryCalendarMonth(1));
  if (prevBtn) prevBtn.addEventListener('click', () => changeDiaryCalendarMonth(-1));
  if (closeBtn) closeBtn.addEventListener('click', closeDiaryCalendar);
  if (openBtn) openBtn.addEventListener('click', openDiaryCalendarSafely);

  console.log('📅 Listeners do calendário do Diário inicializados com segurança');
});
</script>

<?php
// Calcular insights automáticos
$days_with_goal = $water_stats_7['excellent_days'] + $water_stats_7['good_days'];
$total_days_7 = $water_stats_7['total_days'];
$avg_ml_7 = $water_stats_7['avg_ml'];
$avg_percentage_7 = $water_stats_7['avg_percentage'];

// Determinar status geral
if ($avg_percentage_7 >= 90) {
    $status_text = 'Excelente';
    $status_class = 'excellent';
    $status_icon = 'fa-check-circle';
} elseif ($avg_percentage_7 >= 70) {
    $status_text = 'Bom';
    $status_class = 'good';
    $status_icon = 'fa-check';
} elseif ($avg_percentage_7 >= 50) {
    $status_text = 'Regular';
    $status_class = 'fair';
    $status_icon = 'fa-exclamation-triangle';
} elseif ($avg_percentage_7 >= 30) {
    $status_text = 'Abaixo da meta';
    $status_class = 'poor';
    $status_icon = 'fa-exclamation';
} else {
    $status_text = 'Crítico';
    $status_class = 'critical';
    $status_icon = 'fa-times-circle';
}

// Gerar insights em linguagem natural
$insights = [];
$insights[] = "O paciente atingiu a meta em <strong>{$days_with_goal} de {$total_days_7} dias</strong> analisados.";

// Comparar com semana anterior se houver dados
$avg_ml_14 = $water_stats_15['avg_ml'] ?? 0;
if ($avg_ml_14 > 0 && count($hydration_data) >= 14) {
    $diff = $avg_ml_7 - $avg_ml_14;
    if (abs($diff) > 100) {
        if ($diff > 0) {
            $insights[] = "Houve <strong class='text-success'>melhora de " . round($diff) . "ml</strong> em relação aos 7 dias anteriores.";
        } else {
            $insights[] = "Houve <strong class='text-danger'>redução de " . round(abs($diff)) . "ml</strong> em relação aos 7 dias anteriores.";
        }
    }
}

// Analisar padrão de dias da semana (se houver dados suficientes)
if (count($hydration_data) >= 7) {
    $weekend_avg = 0;
    $weekday_avg = 0;
    $weekend_count = 0;
    $weekday_count = 0;
    
    foreach (array_slice($hydration_data, 0, 14) as $day) {
        $dayOfWeek = date('N', strtotime($day['date']));
        if ($dayOfWeek >= 6) {
            $weekend_avg += $day['ml'];
            $weekend_count++;
        } else {
            $weekday_avg += $day['ml'];
            $weekday_count++;
        }
    }
    
    if ($weekend_count > 0 && $weekday_count > 0) {
        $weekend_avg = $weekend_avg / $weekend_count;
        $weekday_avg = $weekday_avg / $weekday_count;
        $diff_weekend = $weekend_avg - $weekday_avg;
        
        if (abs($diff_weekend) > 300) {
            if ($diff_weekend < 0) {
                $insights[] = "Consumo <strong>reduzido nos fins de semana</strong> (em média " . round(abs($diff_weekend)) . "ml a menos).";
            } else {
                $insights[] = "Consumo <strong>maior nos fins de semana</strong> (em média " . round($diff_weekend) . "ml a mais).";
            }
        }
    }
}
?>
    </div>
</div>
