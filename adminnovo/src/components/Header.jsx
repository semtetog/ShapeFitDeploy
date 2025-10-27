import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useLocation } from 'react-router-dom';

const Header = ({ onMenuClick }) => {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [isVisible, setIsVisible] = useState(true);
  const [lastScrollY, setLastScrollY] = useState(0);
  
  // Esconder card de administrador nas páginas de usuário
  const isUserPage = location.pathname.includes('/users/');

  useEffect(() => {
    const handleScroll = () => {
      const currentScrollY = window.scrollY;
      
      if (currentScrollY > lastScrollY && currentScrollY > 100) {
        // Scrolling down
        setIsVisible(false);
      } else {
        // Scrolling up
        setIsVisible(true);
      }
      
      setLastScrollY(currentScrollY);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    
    return () => {
      window.removeEventListener('scroll', handleScroll);
    };
  }, [lastScrollY]);

  const handleLogout = () => {
    logout();
  };

  return (
    <header className={`header ${isVisible ? 'visible' : 'hidden'}`}>
      <div className="header-content">
        {!isUserPage && (
          <div className="user-profile-card">
            <div className="user-avatar">
              <img src="/default-avatar.png" alt="Avatar" onError={(e) => {
                e.target.style.display = 'none';
                e.target.nextSibling.style.display = 'flex';
              }} />
              <div className="avatar-fallback">
                <i className="fas fa-user"></i>
              </div>
            </div>
            <div className="user-details">
              <h3 className="user-name">{user?.name || 'Administrador'}</h3>
              <p className="user-role">Administrador do Sistema</p>
            </div>
            <div className="user-actions">
              <button className="action-btn edit-btn" title="Editar Perfil">
                <i className="fas fa-edit"></i>
              </button>
              <button onClick={handleLogout} className="action-btn logout-btn" title="Sair">
                <i className="fas fa-sign-out-alt"></i>
              </button>
            </div>
          </div>
        )}
      </div>
    </header>
  );
};

export default Header;
