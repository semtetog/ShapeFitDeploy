// ==============================================================
//  TESTE DE CARREGAMENTO: Verifique a aba "Console" (F12)
// ==============================================================
console.log("[user_view_logic] loaded v5");

// Log erros globais para confirmar execução do script
window.addEventListener('error', function(e){
  console.log('[user_view_logic] window error captured:', e?.message || e);
});

// Garantir handlers mesmo se inline for bloqueado (CSP)
document.addEventListener('DOMContentLoaded', function(){
  console.log('[user_view_logic] DOMContentLoaded');
  // Delegação para botão de reverter metas
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-revert-goals');
    if (btn){
      console.log('[user_view_logic] delegated click .btn-revert-goals');
      e.preventDefault();
      e.stopPropagation();
      if (typeof window.showRevertModal === 'function') {
        try { window.showRevertModal(parseInt(btn.getAttribute('onclick')?.match(/\d+/)?.[0] || btn.dataset.userId || '0', 10)); }
        catch(err){ console.log('[user_view_logic] showRevertModal call error', err); }
      } else {
        console.log('[user_view_logic] showRevertModal not found; toggling modal fallback');
        const m = document.getElementById('revertGoalsModal');
        if (m){ m.classList.add('active'); document.body.style.overflow = 'hidden'; }
      }
    }
  }, true);
});

window.addEventListener('load', function(){
  console.log('[user_view_logic] window load');
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('[user_view_logic] weight chart init');
    
    // --- LÓGICA DAS ABAS ---
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    tabLinks.forEach(link => {
        link.addEventListener('click', () => {
            const tabId = link.dataset.tab;
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            link.classList.add('active');
            const activeContent = document.getElementById(`tab-${tabId}`);
            if (activeContent) activeContent.classList.add('active');
        });
    });

    // --- LÓGICA DO GRÁFICO DE PESO ---
    const weightChartCtx = document.getElementById('weightHistoryChart');
    
    // console.log("Dados recebidos:", userViewData); // Comentado - não usado na nova implementação

    if (weightChartCtx && typeof userViewData !== 'undefined' && userViewData && userViewData.weightHistory && userViewData.weightHistory.data.length >= 1) {
        
        console.log("CONDIÇÃO ATENDIDA. Desenhando o gráfico...");

        const weightData = userViewData.weightHistory.data;
        const weightLabels = userViewData.weightHistory.labels;
        let suggestedMin, suggestedMax;
        if (weightData.length === 1) {
            suggestedMin = weightData[0] - 2;
            suggestedMax = weightData[0] + 2;
        }

        new Chart(weightChartCtx, {
            type: 'line',
            data: {
                labels: weightLabels,
                datasets: [{
                    label: 'Peso (kg)',
                    data: weightData,
                    borderColor: '#FF6B00',
                    backgroundColor: 'rgba(255, 107, 0, 0.15)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#FF6B00',
                    pointBorderColor: '#FFF',
                    pointHoverRadius: 7,
                    pointRadius: 5,
                    pointBorderWidth: 2,
                    showLine: weightData.length > 1 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#111',
                        titleColor: '#FFF',
                        bodyColor: '#DDD',
                        padding: 10,
                        cornerRadius: 5,
                        intersect: false, 
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#999' }
                    },
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            color: '#999',
                            callback: function(value) { return value + ' kg'; }
                        },
                        suggestedMin: suggestedMin,
                        suggestedMax: suggestedMax
                    }
                }
            }
        });
    } else {
        console.log("CONDIÇÃO NÃO ATENDIDA. O gráfico não será desenhado.");
    }
});