import React, { useState, useEffect } from 'react';

const Recipes = () => {
  const [recipes, setRecipes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchRecipes();
  }, []);

  const fetchRecipes = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/recipes.php');
      const data = await response.json();
      
      if (data.success) {
        setRecipes(data.recipes || []);
      } else {
        setError('Erro ao carregar receitas');
      }
    } catch (err) {
      console.error('Recipes error:', err);
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
          <h2 className="page-title">Receitas</h2>
          <p className="page-subtitle">Gerencie as receitas do sistema</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Nova Receita
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="recipes-grid">
        {recipes.map((recipe) => (
          <div key={recipe.id} className="recipe-card">
            <div className="recipe-header">
              <div className="recipe-image">
                {recipe.image ? (
                  <img src={recipe.image} alt={recipe.name} />
                ) : (
                  <i className="fas fa-utensils"></i>
                )}
              </div>
              <div className="recipe-info">
                <h3 className="recipe-name">{recipe.name}</h3>
                <p className="recipe-category">{recipe.category}</p>
              </div>
            </div>
            <div className="recipe-meta">
              <div className="recipe-author">
                <i className="fas fa-user"></i>
                {recipe.author || 'Admin'}
              </div>
              <div className="recipe-date">
                <i className="fas fa-calendar"></i>
                {new Date(recipe.created_at).toLocaleDateString('pt-BR')}
              </div>
            </div>
            <div className="recipe-actions">
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

export default Recipes;
