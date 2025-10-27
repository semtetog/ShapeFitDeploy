import React, { useState, useEffect } from 'react';

const UserGroups = () => {
  const [groups, setGroups] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchGroups();
  }, []);

  const fetchGroups = async () => {
    try {
      setLoading(true);
      const response = await fetch('/admin/api/user-groups.php');
      const data = await response.json();
      
      if (data.success) {
        setGroups(data.groups || []);
      } else {
        setError('Erro ao carregar grupos');
      }
    } catch (err) {
      console.error('Groups error:', err);
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
          <h2 className="page-title">Grupos de Usuários</h2>
          <p className="page-subtitle">Gerencie grupos e desafios de usuários</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary">
            <i className="fas fa-plus"></i>
            Novo Grupo
          </button>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger">
          <i className="fas fa-exclamation-triangle"></i>
          {error}
        </div>
      )}

      <div className="user-groups-grid">
        {groups.map((group) => (
          <div key={group.id} className="user-group-card">
            <div className="group-header">
              <div className="group-image">
                <i className="fas fa-layer-group"></i>
              </div>
              <div className="group-info">
                <h3 className="group-name">{group.name}</h3>
                <p className="group-challenge">{group.challenge}</p>
              </div>
            </div>
            <div className="group-description">
              {group.description}
            </div>
            <div className="group-status">
              <span className={`status-badge ${group.status === 'active' ? 'active' : 'inactive'}`}>
                {group.status === 'active' ? 'Ativo' : 'Inativo'}
              </span>
            </div>
            <div className="group-actions">
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

export default UserGroups;
