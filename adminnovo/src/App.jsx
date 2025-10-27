import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Login from './pages/Login';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Users from './pages/Users';
import ViewUser from './pages/ViewUser';
import Foods from './pages/Foods';
import Recipes from './pages/Recipes';
import FoodClassification from './pages/FoodClassification';
import DietPlans from './pages/DietPlans';
import ChallengeStudio from './pages/ChallengeStudio';
import ContentManagement from './pages/ContentManagement';
import Ranks from './pages/Ranks';
import UserGroups from './pages/UserGroups';

function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();
  
  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
      </div>
    );
  }
  
  return isAuthenticated ? children : <Navigate to="/login" />;
}

function App() {
  return (
    <AuthProvider>
      <Router>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={
            <ProtectedRoute>
              <Layout>
                <Dashboard />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/users" element={
            <ProtectedRoute>
              <Layout>
                <Users />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/users/:id" element={
            <ProtectedRoute>
              <Layout>
                <ViewUser />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/foods" element={
            <ProtectedRoute>
              <Layout>
                <Foods />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/recipes" element={
            <ProtectedRoute>
              <Layout>
                <Recipes />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/food-classification" element={
            <ProtectedRoute>
              <Layout>
                <FoodClassification />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/diet-plans" element={
            <ProtectedRoute>
              <Layout>
                <DietPlans />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/challenge-studio" element={
            <ProtectedRoute>
              <Layout>
                <ChallengeStudio />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/content-management" element={
            <ProtectedRoute>
              <Layout>
                <ContentManagement />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/ranks" element={
            <ProtectedRoute>
              <Layout>
                <Ranks />
              </Layout>
            </ProtectedRoute>
          } />
          <Route path="/user-groups" element={
            <ProtectedRoute>
              <Layout>
                <UserGroups />
              </Layout>
            </ProtectedRoute>
          } />
        </Routes>
      </Router>
    </AuthProvider>
  );
}

export default App;