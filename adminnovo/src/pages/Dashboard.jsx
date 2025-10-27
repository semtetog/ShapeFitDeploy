import React, { useState, useEffect } from 'react';

const Dashboard = () => {
  const [stats, setStats] = useState({
    totalUsers: 0,
    totalDiaries: 0
  });
  const [chartData, setChartData] = useState({
    newUsers: [],
    genderDistribution: { labels: [], data: [] },
    objectivesDistribution: { labels: [], data: [] },
    ageDistribution: { labels: [], data: [] },
    imcDistribution: { labels: [], data: [] }
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      // Simulação de dados do dashboard (igual ao antigo)
      const mockData = {
        stats: {
          totalUsers: 1247,
          totalDiaries: 3421
        },
        charts: {
          newUsers: [45, 52, 38, 67, 89, 76, 54, 43, 67, 89, 76, 98],
          genderDistribution: {
            labels: ['Masculino', 'Feminino', 'Outro'],
            data: [456, 623, 168]
          },
          objectivesDistribution: {
            labels: ['Emagrecimento', 'Hipertrofia', 'Manter Peso'],
            data: [567, 423, 257]
          },
          ageDistribution: {
            labels: ['15-24', '25-34', '35-44', '45-54', '55-64', '65+'],
            data: [234, 456, 345, 123, 67, 23]
          },
          imcDistribution: {
            labels: ['Abaixo do peso', 'Peso Ideal', 'Sobrepeso', 'Obesidade'],
            data: [123, 456, 345, 234]
          }
        }
      };

      setStats(mockData.stats);
      setChartData(mockData.charts);
    } catch (err) {
      console.error('Dashboard error:', err);
      setError('Erro ao carregar dados do dashboard');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="alert alert-danger">
        <i className="fas fa-exclamation-triangle"></i>
        {error}
      </div>
    );
  }

  return (
    <div className="dashboard-page">
      {/* Header da Página */}
      <div className="page-header">
        <div className="page-title-section">
          <h1 className="page-title">Dashboard</h1>
          <p className="page-subtitle">Visão geral do sistema ShapeFit</p>
        </div>
      </div>
      
      {/* Cards de Estatísticas */}
      <div className="stats-grid">
        <div className="stat-card">
          <div className="stat-icon-container users">
            <i className="fas fa-users"></i>
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats.totalUsers.toLocaleString()}</div>
            <div className="stat-label">Total de Usuários</div>
            <div className="stat-change positive">
              <i className="fas fa-arrow-up"></i>
              +12% este mês
            </div>
          </div>
        </div>
        
        <div className="stat-card">
          <div className="stat-icon-container diaries">
            <i className="fas fa-calendar-alt"></i>
          </div>
          <div className="stat-content">
            <div className="stat-value">{stats.totalDiaries.toLocaleString()}</div>
            <div className="stat-label">Cardápios no Diário</div>
            <div className="stat-change positive">
              <i className="fas fa-arrow-up"></i>
              +8% este mês
            </div>
          </div>
        </div>
      </div>

      {/* Grid de Gráficos - Temporariamente desabilitado */}
      <div className="charts-grid">
        <div className="chart-card large-card">
          <div className="chart-header">
            <h3 className="chart-title">
              <i className="fas fa-chart-line"></i>
              Novos Usuários em {new Date().getFullYear()}
            </h3>
          </div>
          <div className="chart-container">
            <div className="chart-placeholder">
              <i className="fas fa-chart-line"></i>
              <p>Gráfico será implementado em breve</p>
            </div>
          </div>
        </div>
        
        <div className="chart-card">
          <div className="chart-header">
            <h3 className="chart-title">
              <i className="fas fa-users"></i>
              Distribuição por Gênero
            </h3>
          </div>
          <div className="chart-container">
            <div className="chart-placeholder">
              <i className="fas fa-chart-pie"></i>
              <p>Gráfico será implementado em breve</p>
            </div>
          </div>
        </div>
        
        <div className="chart-card">
          <div className="chart-header">
            <h3 className="chart-title">
              <i className="fas fa-bullseye"></i>
              Objetivos dos Usuários
            </h3>
          </div>
          <div className="chart-container">
            <div className="chart-placeholder">
              <i className="fas fa-chart-bar"></i>
              <p>Gráfico será implementado em breve</p>
            </div>
          </div>
        </div>
        
        <div className="chart-card">
          <div className="chart-header">
            <h3 className="chart-title">
              <i className="fas fa-chart-bar"></i>
              Distribuição por Faixa Etária
            </h3>
          </div>
          <div className="chart-container">
            <div className="chart-placeholder">
              <i className="fas fa-chart-bar"></i>
              <p>Gráfico será implementado em breve</p>
            </div>
          </div>
        </div>
        
        <div className="chart-card">
          <div className="chart-header">
            <h3 className="chart-title">
              <i className="fas fa-weight"></i>
              Distribuição por IMC
            </h3>
          </div>
          <div className="chart-container">
            <div className="chart-placeholder">
              <i className="fas fa-chart-bar"></i>
              <p>Gráfico será implementado em breve</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
