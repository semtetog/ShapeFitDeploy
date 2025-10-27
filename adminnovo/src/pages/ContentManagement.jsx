import React, { useState, useEffect } from 'react';

const ContentManagement = () => {
  const [content, setContent] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchContent();
  }, []);

  const fetchContent = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/content.php');
      const data = await response.json();
      
      if (data.success) {
        setContent(data.content || []);
      } else {
        setError('Erro ao carregar conteúdo');
      }
    } catch (err) {
      console.error('Content error:', err);
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
          <h2 className="page-title">Gerenciamento de Conteúdo</h2>
          <p className="page-subtitle">Gerencie artigos, tutoriais e conteúdo educativo</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Conteúdo
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="content-grid">
        {content.map((item) => (
          <div key={item.id} className="content-card">
            <div className="content-header">
              <div className="content-image">
                <i className="fas fa-edit"></i>
              </div>
              <div className="content-info">
                <h3 className="content-title">{item.title}</h3>
                <p className="content-category">{item.category}</p>
              </div>
            </div>
            <div className="content-description">
              {item.description}
            </div>
            <div className="content-status">
              <span className={`status-badge ${item.status === 'published' ? 'active' : 'inactive'}`}>
                {item.status === 'published' ? 'Publicado' : 'Rascunho'}
              </span>
            </div>
            <div className="content-actions">
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

export default ContentManagement;
