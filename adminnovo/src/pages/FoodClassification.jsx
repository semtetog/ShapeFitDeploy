import React, { useState, useEffect } from 'react';

const FoodClassification = () => {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/food-classification.php');
      const data = await response.json();
      
      if (data.success) {
        setCategories(data.categories || []);
      } else {
        setError('Erro ao carregar categorias');
      }
    } catch (err) {
      console.error('Categories error:', err);
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
          <h2 className="page-title">Classificação de Alimentos</h2>
          <p className="page-subtitle">Sistema de categorias de alimentos</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Nova Categoria
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="categories-grid">
        {categories.map((category) => (
          <div key={category.id} className="category-card">
            <div className="category-header">
              <div className="category-icon">
                <i className={category.icon}></i>
              </div>
              <div className="category-info">
                <h3 className="category-name">{category.name}</h3>
                <p className="category-description">{category.description}</p>
              </div>
            </div>
            <div className="category-stats">
              <div className="stat-item">
                <span className="stat-label">Alimentos</span>
                <span className="stat-value">{category.foodCount || 0}</span>
              </div>
            </div>
            <div className="category-actions">
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

export default FoodClassification;
