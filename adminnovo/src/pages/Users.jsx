import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const Users = () => {
  const navigate = useNavigate();
  const [users, setUsers] = useState([
    { id: 1, name: 'Ana Silva', email: 'ana@email.com', created_at: '2024-01-15', status: 'active', avatar: null },
    { id: 2, name: 'Carlos Santos', email: 'carlos@email.com', created_at: '2024-01-14', status: 'active', avatar: null },
    { id: 3, name: 'Maria Oliveira', email: 'maria@email.com', created_at: '2024-01-13', status: 'inactive', avatar: null },
    { id: 4, name: 'João Costa', email: 'joao@email.com', created_at: '2024-01-12', status: 'active', avatar: null },
    { id: 5, name: 'Fernanda Lima', email: 'fernanda@email.com', created_at: '2024-01-11', status: 'active', avatar: null },
    { id: 6, name: 'Pedro Alves', email: 'pedro@email.com', created_at: '2024-01-10', status: 'active', avatar: null },
    { id: 7, name: 'Lucia Ferreira', email: 'lucia@email.com', created_at: '2024-01-09', status: 'inactive', avatar: null },
    { id: 8, name: 'Rafael Souza', email: 'rafael@email.com', created_at: '2024-01-08', status: 'active', avatar: null }
  ]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [filterStatus, setFilterStatus] = useState('all');

  const handleSearch = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
  };

  const handleFilter = (status) => {
    setFilterStatus(status);
    setCurrentPage(1);
  };

  const filteredUsers = users.filter(user => {
    const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                         user.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesFilter = filterStatus === 'all' || user.status === filterStatus;
    return matchesSearch && matchesFilter;
  });

  // Função para gerar cores diferentes para cada usuário
  const getAvatarColor = (name) => {
    const colors = [
      '#FF6B6B', // Vermelho
      '#4ECDC4', // Turquesa
      '#45B7D1', // Azul
      '#96CEB4', // Verde
      '#FFEAA7', // Amarelo
      '#DDA0DD', // Roxo
      '#FFB347', // Laranja
      '#98D8C8', // Verde água
      '#F7DC6F', // Amarelo claro
      '#BB8FCE', // Lilás
      '#85C1E9', // Azul claro
      '#F8C471', // Laranja claro
      '#82E0AA', // Verde claro
      '#F1948A', // Rosa
      '#AED6F1'  // Azul muito claro
    ];
    
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
      hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    
    return colors[Math.abs(hash) % colors.length];
  };

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  return (
    <div className="users-page">
      {/* Header da Página */}
      <div className="page-header">
        <div className="page-title-section">
          <h1 className="page-title">Pacientes</h1>
          <p className="page-subtitle">Gerencie todos os pacientes cadastrados</p>
        </div>
      </div>

      {/* Barra de Busca */}
      <div className="toolbar">
        <form className="search-form" onSubmit={(e) => e.preventDefault()}>
          <input
            type="text"
            placeholder="Buscar por nome ou e-mail..."
            value={searchTerm}
            onChange={handleSearch}
          />
          <button type="submit">
            <i className="fas fa-search"></i>
          </button>
        </form>
      </div>

      {/* Grid de Cards de Usuários */}
      <div className="user-cards-grid">
        {filteredUsers.length === 0 ? (
          <p className="empty-state">Nenhum paciente encontrado.</p>
        ) : (
          filteredUsers.map((user) => (
            <div key={user.id} className="user-card" onClick={() => navigate(`/users/${user.id}`)}>
              <div className="user-card-header">
                {user.avatar ? (
                  <img 
                    src={user.avatar} 
                    alt={`Foto de ${user.name}`}
                    className="user-card-avatar"
                  />
                ) : (
                  <div 
                    className="initials-avatar"
                    style={{ backgroundColor: getAvatarColor(user.name) }}
                  >
                    {user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
                  </div>
                )}
              </div>
              
              <div className="user-card-body">
                <h3 className="user-card-name">{user.name}</h3>
                <p className="user-card-email">{user.email}</p>
              </div>
              
              <div className="user-card-footer">
                <span className="user-card-date">
                  <i className="fas fa-calendar-alt"></i>
                  Cadastro: {new Date(user.created_at).toLocaleDateString('pt-BR')}
                </span>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Paginação */}
      <div className="pagination-footer">
        <div className="pagination-info">
          Mostrando <strong>{filteredUsers.length}</strong> de <strong>{users.length}</strong> pacientes.
        </div>
        {totalPages > 1 && (
          <div className="pagination-container">
            {currentPage > 1 && (
              <button 
                className="pagination-link"
                onClick={() => setCurrentPage(currentPage - 1)}
              >
                «
              </button>
            )}
            
            {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
              <button
                key={page}
                className={`pagination-link ${page === currentPage ? 'active' : ''}`}
                onClick={() => setCurrentPage(page)}
              >
                {page}
              </button>
            ))}
            
            {currentPage < totalPages && (
              <button 
                className="pagination-link"
                onClick={() => setCurrentPage(currentPage + 1)}
              >
                »
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default Users;
