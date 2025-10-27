import React, { useState, useEffect } from 'react';

const Ranks = () => {
  const [rankings, setRankings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchRankings();
  }, []);

  const fetchRankings = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/rankings.php');
      const data = await response.json();
      
      if (data.success) {
        setRankings(data.rankings || []);
      } else {
        setError('Erro ao carregar rankings');
      }
    } catch (err) {
      console.error('Rankings error:', err);
      setError('Erro de conexão');
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

  return (
    <div>
      <div className="page-header">
        <div className="page-title-section">
          <h2 className="page-title">Sistema de Rankings</h2>
          <p className="page-subtitle">Gerencie rankings e classificações</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Ranking
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="rankings-grid">
        {rankings.map((ranking) => (
          <div key={ranking.id} className="ranking-card">
            <div className="ranking-header">
              <div className="ranking-image">
                <i className="fas fa-trophy"></i>
              </div>
              <div className="ranking-info">
                <h3 className="ranking-name">{ranking.name}</h3>
                <p className="ranking-category">{ranking.category}</p>
              </div>
            </div>
            <div className="ranking-description">
              {ranking.description}
            </div>
            <div className="ranking-status">
              <span className={`status-badge ${ranking.status === 'active' ? 'active' : 'inactive'}`}>
                {ranking.status === 'active' ? 'Ativo' : 'Inativo'}
              </span>
            </div>
            <div className="ranking-actions">
              <button className="btn btn-sm btn-secondary">
                <i className="fas fa-eye"></i>
                Ver
              </button>
              <button className="btn btn-sm btn-secondary">
                <i className="fas fa-edit"></i>
                Editar
              </button>
              <button className="btn btn-sm btn-danger">
                <i className="fas fa-trash"></i>
                Excluir
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Ranks;
