// admin/assets/js/charts_logic.js (VERSÃO PROFISSIONAL E COMPLETA)

document.addEventListener('DOMContentLoaded', function() {
    
    // Verifica se a biblioteca Chart.js e os dados do PHP foram carregados
    if (typeof Chart === 'undefined' || typeof chartData === 'undefined') {
        console.error("Chart.js ou a variável chartData não estão definidos. Verifique os includes no seu HTML/PHP.");
        return;
    }

    // =========================================================================
    // CONFIGURAÇÕES GLOBAIS E PALETA DE CORES
    // =========================================================================

    // Define uma paleta de cores consistente com o tema do app
    const chartColors = {
        orange: '#FF6B00',
        blue: '#36A2EB',
        green: '#4CAF50',
        pink: '#FF6384',
        yellow: '#FFCE56',
        purple: '#9966FF',
        grey: '#A0A0A0',
        surface: '#1E1E1E',
        grid: '#282828',
        text: '#A0A0A0',
        textTitle: '#E0E0E0'
    };

    // Aplica configurações padrão para todos os gráficos
    Chart.defaults.font.family = "'Poppins', sans-serif";
    Chart.defaults.color = chartColors.text;
    Chart.defaults.borderColor = chartColors.grid;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.padding = 20;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.8)';
    Chart.defaults.plugins.tooltip.titleFont = { size: 14, weight: '600' };
    Chart.defaults.plugins.tooltip.bodyFont = { size: 12 };
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.animation.duration = 800; // Animação de entrada mais suave

    // =========================================================================
    // RENDERIZAÇÃO DOS GRÁFICOS
    // =========================================================================
    
    // 1. Gráfico de Novos Usuários (Linha)
    const newUsersCtx = document.getElementById('newUsersChart');
    if (newUsersCtx && chartData.newUsers) {
        new Chart(newUsersCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Novos Usuários',
                    data: chartData.newUsers,
                    borderColor: chartColors.orange,
                    backgroundColor: 'rgba(255, 107, 0, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: chartColors.orange,
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: chartColors.orange,
                    pointHoverBorderWidth: 2
                }]
            },
           options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    // A legenda ainda é exibida
                    display: true, 
                    labels: {
                        color: chartColors.textTitle
                    },
                    // A função onClick é DEFINIDA COMO NULA, o que desativa o clique.
                    onClick: null
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ` ${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: chartColors.grid } },
                x: { grid: { display: false } }
            }
        }
    });
}


    // 2. Gráfico de Distribuição por Gênero (Rosca/Doughnut)
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx && chartData.genderDistribution) {
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.genderDistribution.labels,
                datasets: [{
                    data: chartData.genderDistribution.data,
                    backgroundColor: [chartColors.blue, chartColors.pink, chartColors.yellow],
                    borderColor: chartColors.surface,
                    borderWidth: 4,
                    hoverOffset: 10
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
        });
    }

    // 3. Gráfico de Objetivos dos Usuários (Barras Horizontais)
    const objectivesCtx = document.getElementById('objectivesChart');
    if (objectivesCtx && chartData.objectivesDistribution) {
        new Chart(objectivesCtx, {
            type: 'bar',
            data: {
                labels: chartData.objectivesDistribution.labels,
                datasets: [{
                    label: 'Nº de Usuários',
                    data: chartData.objectivesDistribution.data,
                    backgroundColor: chartColors.green,
                    borderRadius: 4,
                    barThickness: 20
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y', // Barras horizontais
                plugins: { legend: { display: false } },
                scales: { 
                    x: { beginAtZero: true, grid: { color: chartColors.grid } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // 4. Gráfico de Faixa Etária (Barras Verticais)
    const ageCtx = document.getElementById('ageChart');
    if (ageCtx && chartData.ageDistribution) {
        new Chart(ageCtx, {
            type: 'bar',
            data: {
                labels: chartData.ageDistribution.labels,
                datasets: [{
                    label: 'Nº de Usuários',
                    data: chartData.ageDistribution.data,
                    backgroundColor: chartColors.purple,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { color: chartColors.grid } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 5. Gráfico de Distribuição por IMC (Barras Verticais com Labels Retos)
    const imcCtx = document.getElementById('imcChart');
    if (imcCtx && chartData.imcDistribution) {
        new Chart(imcCtx, {
            type: 'bar',
            data: {
                labels: chartData.imcDistribution.labels,
                datasets: [{
                    label: 'Nº de Usuários',
                    data: chartData.imcDistribution.data,
                    backgroundColor: [chartColors.blue, chartColors.green, chartColors.yellow, chartColors.orange],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: chartColors.grid } },
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 0, // Impede a rotação máxima
                            minRotation: 0, // Impede a rotação mínima
                            autoSkip: false // Garante que todos os labels apareçam
                        }
                    }
                }
            }
        });
    }
});