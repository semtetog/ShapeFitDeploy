import React from 'react';
import { useLocation } from 'react-router-dom';

const Sidebar = ({ isOpen, onClose }) => {
  const location = useLocation();

  const menuItems = [
    { path: '/', label: 'Dashboard', icon: 'fas fa-chart-line' },
    { path: '/users', label: 'Usuários', icon: 'fas fa-users' },
    { path: '/foods', label: 'Alimentos', icon: 'fas fa-apple-alt' },
    { path: '/recipes', label: 'Receitas', icon: 'fas fa-utensils' },
    { path: '/food-classification', label: 'Classificação', icon: 'fas fa-tags' },
    { path: '/diet-plans', label: 'Planos Alimentares', icon: 'fas fa-clipboard-list' },
    { path: '/challenge-studio', label: 'Desafios', icon: 'fas fa-dumbbell' },
    { path: '/content-management', label: 'Conteúdo', icon: 'fas fa-edit' },
    { path: '/ranks', label: 'Rankings', icon: 'fas fa-trophy' },
    { path: '/user-groups', label: 'Grupos', icon: 'fas fa-layer-group' },
  ];

  return (
    <>
      {/* Overlay para mobile */}
      {isOpen && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={onClose}
        />
      )}
      
      {/* Sidebar */}
      <aside className={`sidebar ${isOpen ? 'open' : ''}`}>
        <div className="sidebar-header">
          <a href="/" className="sidebar-logo">
            <img src="/shapefit-logo.png" alt="ShapeFit" className="sidebar-logo-icon" />
            <span>ShapeFit</span>
          </a>
        </div>
        
        <button className="sidebar-close-button" onClick={onClose}>
          <i className="fas fa-times"></i>
        </button>
        
        <nav className="sidebar-nav">
          <ul>
            {menuItems.map((item) => (
              <li key={item.path} className={location.pathname === item.path ? 'active' : ''}>
                <a href={item.path}>
                  <i className={`${item.icon} nav-icon`}></i>
                  {item.label}
                </a>
              </li>
            ))}
          </ul>
        </nav>
      </aside>
    </>
  );
};

export default Sidebar;
