import React, { useState, useEffect } from 'react';

const ChallengeStudio = () => {
  const [challenges, setChallenges] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchChallenges();
  }, []);

  const fetchChallenges = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/challenges.php');
      const data = await response.json();
      
      if (data.success) {
        setChallenges(data.challenges || []);
      } else {
        setError('Erro ao carregar desafios');
      }
    } catch (err) {
      console.error('Challenges error:', err);
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
          <h2 className="page-title">Estúdio de Desafios</h2>
          <p className="page-subtitle">Crie e gerencie desafios para os usuários</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Desafio
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="challenges-grid">
        {challenges.map((challenge) => (
          <div key={challenge.id} className="challenge-card">
            <div className="challenge-header">
              <div className="challenge-image">
                <i className="fas fa-dumbbell"></i>
              </div>
              <div className="challenge-info">
                <h3 className="challenge-name">{challenge.name}</h3>
                <p className="challenge-category">{challenge.category}</p>
              </div>
            </div>
            <div className="challenge-description">
              {challenge.description}
            </div>
            <div className="challenge-status">
              <span className={`status-badge ${challenge.status === 'active' ? 'active' : 'inactive'}`}>
                {challenge.status === 'active' ? 'Ativo' : 'Inativo'}
              </span>
            </div>
            <div className="challenge-actions">
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

export default ChallengeStudio;
