import React, { useEffect, useRef } from 'react';
import { Chart as ChartJS } from 'chart.js/auto';

const Charts = ({ chartData }) => {
  const newUsersRef = useRef(null);
  const genderRef = useRef(null);
  const objectivesRef = useRef(null);
  const ageRef = useRef(null);
  const imcRef = useRef(null);

  useEffect(() => {
    // Configuração comum para todos os gráficos
    const commonOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: '#FFFFFF',
            font: {
              family: 'Montserrat, sans-serif'
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          titleColor: '#FFFFFF',
          bodyColor: '#FFFFFF',
          borderColor: '#FF6600',
          borderWidth: 1
        }
      },
      scales: {
        x: {
          ticks: {
            color: '#CCCCCC',
            font: {
              family: 'Montserrat, sans-serif'
            }
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        },
        y: {
          ticks: {
            color: '#CCCCCC',
            font: {
              family: 'Montserrat, sans-serif'
            }
          },
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        }
      }
    };

    // Gráfico de Novos Usuários (Linha)
    if (newUsersRef.current && chartData.newUsers) {
      new ChartJS(newUsersRef.current, {
        type: 'line',
        data: {
          labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
          datasets: [{
            label: 'Novos Usuários',
            data: chartData.newUsers,
            borderColor: '#FF6600',
            backgroundColor: 'rgba(255, 102, 0, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#FF6600',
            pointBorderColor: '#FFFFFF',
            pointBorderWidth: 2,
            pointRadius: 6
          }]
        },
        options: {
          ...commonOptions,
          plugins: {
            ...commonOptions.plugins,
            title: {
              display: false
            }
          }
        }
      });
    }

    // Gráfico de Gênero (Pizza)
    if (genderRef.current && chartData.genderDistribution) {
      new ChartJS(genderRef.current, {
        type: 'doughnut',
        data: {
          labels: chartData.genderDistribution.labels,
          datasets: [{
            data: chartData.genderDistribution.data,
            backgroundColor: [
              '#FF6600',
              '#FF8533',
              '#4facfe'
            ],
            borderColor: [
              '#FFFFFF',
              '#FFFFFF',
              '#FFFFFF'
            ],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#FFFFFF',
                font: {
                  family: 'Montserrat, sans-serif'
                }
              }
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#FFFFFF',
              bodyColor: '#FFFFFF',
              borderColor: '#FF6600',
              borderWidth: 1
            }
          }
        }
      });
    }

    // Gráfico de Objetivos (Barras)
    if (objectivesRef.current && chartData.objectivesDistribution) {
      new ChartJS(objectivesRef.current, {
        type: 'bar',
        data: {
          labels: chartData.objectivesDistribution.labels,
          datasets: [{
            label: 'Usuários',
            data: chartData.objectivesDistribution.data,
            backgroundColor: [
              'rgba(255, 102, 0, 0.8)',
              'rgba(255, 133, 51, 0.8)',
              'rgba(79, 172, 254, 0.8)'
            ],
            borderColor: [
              '#FF6600',
              '#FF8533',
              '#4facfe'
            ],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
          }]
        },
        options: {
          ...commonOptions,
          plugins: {
            ...commonOptions.plugins,
            title: {
              display: false
            }
          }
        }
      });
    }

    // Gráfico de Faixa Etária (Barras)
    if (ageRef.current && chartData.ageDistribution) {
      new ChartJS(ageRef.current, {
        type: 'bar',
        data: {
          labels: chartData.ageDistribution.labels,
          datasets: [{
            label: 'Usuários',
            data: chartData.ageDistribution.data,
            backgroundColor: 'rgba(255, 102, 0, 0.8)',
            borderColor: '#FF6600',
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
          }]
        },
        options: {
          ...commonOptions,
          plugins: {
            ...commonOptions.plugins,
            title: {
              display: false
            }
          }
        }
      });
    }

    // Gráfico de IMC (Barras)
    if (imcRef.current && chartData.imcDistribution) {
      new ChartJS(imcRef.current, {
        type: 'bar',
        data: {
          labels: chartData.imcDistribution.labels,
          datasets: [{
            label: 'Usuários',
            data: chartData.imcDistribution.data,
            backgroundColor: [
              'rgba(34, 197, 94, 0.8)',
              'rgba(79, 172, 254, 0.8)',
              'rgba(255, 193, 7, 0.8)',
              'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
              '#22C55E',
              '#4facfe',
              '#FFC107',
              '#EF4444'
            ],
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false
          }]
        },
        options: {
          ...commonOptions,
          plugins: {
            ...commonOptions.plugins,
            title: {
              display: false
            }
          }
        }
      });
    }
  }, [chartData]);

  return (
    <>
      <canvas ref={newUsersRef} id="newUsersChart"></canvas>
      <canvas ref={genderRef} id="genderChart"></canvas>
      <canvas ref={objectivesRef} id="objectivesChart"></canvas>
      <canvas ref={ageRef} id="ageChart"></canvas>
      <canvas ref={imcRef} id="imcChart"></canvas>
    </>
  );
};

export default Charts;
