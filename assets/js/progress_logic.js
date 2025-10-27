document.addEventListener('DOMContentLoaded', function() {
    // Pega os dados dos scripts JSON no HTML
    const consumptionDataEl = document.getElementById('chart-data');
    const weightDataEl = document.getElementById('weight-data');

    // Estilo padrão para os gráficos
    Chart.defaults.font.family = 'Poppins, sans-serif';
    Chart.defaults.color = '#A0A0A0';

    // --- GRÁFICO DE CONSUMO (BARRAS EMPILHADAS) ---
    if (consumptionDataEl) {
        const consumptionData = JSON.parse(consumptionDataEl.innerHTML);
        const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
        
        new Chart(consumptionCtx, {
            type: 'bar',
            data: {
                labels: consumptionData.labels,
                datasets: [{
                    label: 'Proteína',
                    data: consumptionData.protein,
                    backgroundColor: '#34aadc',
                    borderColor: '#34aadc',
                    borderWidth: 1
                }, {
                    label: 'Gordura',
                    data: consumptionData.fat,
                    backgroundColor: '#f0ad4e',
                    borderColor: '#f0ad4e',
                    borderWidth: 1
                }, {
                    label: 'Carboidrato',
                    data: consumptionData.carbs,
                    backgroundColor: '#A0A0A0',
                    borderColor: '#A0A0A0',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }, // Legenda customizada no HTML
                scales: {
                    x: {
                        stacked: true, // Empilha as barras
                        grid: { display: false }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: '#333333' }
                    }
                }
            }
        });
    }

    // --- GRÁFICO DE PESO (LINHAS) ---
    if (weightDataEl) {
        const weightData = JSON.parse(weightDataEl.innerHTML);
        const weightCtx = document.getElementById('weightChart').getContext('2d');

        new Chart(weightCtx, {
            type: 'line',
            data: {
                datasets: [{
                    label: 'Peso (kg)',
                    data: weightData,
                    fill: false,
                    borderColor: '#FF6B00',
                    tension: 0.4, // Suaviza a linha
                    pointRadius: 4,
                    pointBackgroundColor: '#FF6B00'
                }]
            },
            options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 plugins: { legend: { display: false } },
                 scales: {
                    x: {
                        type: 'time',
                        time: { unit: 'day' },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: false, // Não precisa começar em 0kg
                        grid: { color: '#333333' }
                    }
                 }
            }
        });
    }
});

// Chart.js requer um adaptador de data se você estiver usando a escala de 'time'
// Mas para este caso, o carregamento automático geralmente funciona.
// Se não, precisaríamos incluir um adaptador como o 'chartjs-adapter-date-fns'.