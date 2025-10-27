import React, { useState, useEffect } from 'react';

const DietPlans = () => {
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchPlans();
  }, []);

  const fetchPlans = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/diet-plans.php');
      const data = await response.json();
      
      if (data.success) {
        setPlans(data.plans || []);
      } else {
        setError('Erro ao carregar planos alimentares');
      }
    } catch (err) {
      console.error('Diet plans error:', err);
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
          <h2 className="page-title">Planos Alimentares</h2>
          <p className="page-subtitle">Gerencie os planos alimentares</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Plano
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="diet-plans-grid">
        {plans.map((plan) => (
          <div key={plan.id} className="diet-plan-card">
            <div className="plan-header">
              <div className="plan-image">
                <i className="fas fa-clipboard-list"></i>
              </div>
              <div className="plan-info">
                <h3 className="plan-name">{plan.name}</h3>
                <p className="plan-goal">{plan.goal}</p>
              </div>
            </div>
            <div className="plan-description">
              {plan.description}
            </div>
            <div className="plan-status">
              <span className={`status-badge ${plan.status === 'active' ? 'active' : 'inactive'}`}>
                {plan.status === 'active' ? 'Ativo' : 'Inativo'}
              </span>
            </div>
            <div className="plan-actions">
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

export default DietPlans;
