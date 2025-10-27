import React, { useState, useEffect } from 'react';

const Foods = () => {
  const [foods, setFoods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchFoods();
  }, []);

  const fetchFoods = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/foods.php');
      const data = await response.json();
      
      if (data.success) {
        setFoods(data.foods || []);
      } else {
        setError('Erro ao carregar alimentos');
      }
    } catch (err) {
      console.error('Foods error:', err);
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
          <h2 className="page-title">Alimentos</h2>
          <p className="page-subtitle">Gerencie o banco de dados de alimentos</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Alimento
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="foods-grid">
        {foods.map((food) => (
          <div key={food.id} className="food-card">
            <div className="food-header">
              <div className="food-image">
                {food.image ? (
                  <img src={food.image} alt={food.name} />
                ) : (
                  <i className="fas fa-apple-alt"></i>
                )}
              </div>
              <div className="food-info">
                <h3 className="food-name">{food.name}</h3>
                <p className="food-category">{food.category}</p>
              </div>
            </div>
            <div className="food-nutrition">
              <div className="nutrition-item">
                <span className="nutrition-label">Calorias</span>
                <span className="nutrition-value">{food.calories || 'N/A'}</span>
              </div>
              <div className="nutrition-item">
                <span className="nutrition-label">Proteína</span>
                <span className="nutrition-value">{food.protein || 'N/A'}g</span>
              </div>
            </div>
            <div className="food-actions">
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

export default Foods;
