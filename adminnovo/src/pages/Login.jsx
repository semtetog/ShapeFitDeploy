import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const Login = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const { login, loading } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    const result = await login(username, password);
    
    if (result.success) {
      navigate('/');
    } else {
      setError(result.message || 'Erro ao fazer login');
    }
  };

  return (
    <div className="login-container">
      {/* Logo ShapeFit - Fora do card */}
      <img src="/SHAPE-FIT-LOGO.png" alt="Shape Fit Logo" className="login-logo" />
      
      {/* Título */}
      <h1 className="page-title">Acesse sua conta</h1>
      
      {/* Card com glassmorphism */}
      <div className="login-card">
        <form className="login-form" onSubmit={handleSubmit}>
          {error && (
            <div className="alert alert-danger">
              <svg className="alert-icon" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
              </svg>
              {error}
            </div>
          )}
          
          <div className="form-group">
            <div className="input-container">
              <div className="username-input-wrapper">
                <input
                  type="text"
                  className="form-control username-input"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Usuário"
                  required
                />
                <i className="fa-solid fa-user icon"></i>
              </div>
            </div>
          </div>
          
          <div className="form-group">
            <div className="input-container">
              <input
                type={showPassword ? "text" : "password"}
                className="form-control password-input"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Senha"
                required
              />
              <i className="fa-solid fa-lock icon"></i>
              <button
                type="button"
                className="password-toggle"
                onClick={() => setShowPassword(!showPassword)}
              >
                <i className={showPassword ? "fa-solid fa-eye-slash" : "fa-solid fa-eye"}></i>
              </button>
            </div>
          </div>
          
          <button type="submit" className="btn-login" disabled={loading}>
            {loading ? 'Entrando...' : 'Entrar'}
          </button>
          
          {/* Versão do sistema */}
          <div className="login-version">
            ShapeFit Admin Panel V2.0
          </div>
        </form>
      </div>
    </div>
  );
};

export default Login;
