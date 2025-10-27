import React, { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Check authentication status on mount
  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    try {
      console.log('🔐 Checking authentication...');
      // Check if user is already logged in from localStorage
      const savedUser = localStorage.getItem('admin_user');
      if (savedUser) {
        setUser(JSON.parse(savedUser));
        setIsAuthenticated(true);
        console.log('✅ User already authenticated');
      } else {
        setUser(null);
        setIsAuthenticated(false);
        console.log('❌ User not authenticated');
      }
    } catch (error) {
      console.error('❌ Auth check error:', error);
      setUser(null);
      setIsAuthenticated(false);
    } finally {
      setLoading(false);
    }
  };

  const login = async (username, password) => {
    setLoading(true);
    try {
      // Simulação de login para desenvolvimento
      console.log('🔐 Attempting login...', { username, password });
      
      // Simular delay de rede
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Login de desenvolvimento - aceita admin/admin
      if (username === 'admin' && password === 'admin') {
        const user = {
          id: 1,
          name: 'Administrador',
          username: 'admin'
        };
        setUser(user);
        setIsAuthenticated(true);
        localStorage.setItem('admin_user', JSON.stringify(user));
        console.log('✅ Login successful');
        return { success: true };
      } else {
        console.error('❌ Login failed: Invalid credentials');
        return { success: false, message: 'Credenciais inválidas' };
      }
    } catch (error) {
      console.error('❌ Login error:', error);
      return { success: false, message: 'Erro de conexão' };
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    setUser(null);
    setIsAuthenticated(false);
    localStorage.removeItem('admin_user');
    console.log('✅ Logout successful');
  };

  const value = {
    user,
    isAuthenticated,
    loading,
    login,
    logout,
    checkAuth
  };

  return React.createElement(AuthContext.Provider, { value }, children);
};

export default AuthContext;
