import React from 'react';
import { HashRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Users from './pages/Users';
import Foods from './pages/Foods';
import FoodClassification from './pages/FoodClassification';
import Recipes from './pages/Recipes';
import DietPlans from './pages/DietPlans';
import ChallengeStudio from './pages/ChallengeStudio';
import ChallengeGroups from './pages/ChallengeGroups';
import ContentManagement from './pages/ContentManagement';
import UserGroups from './pages/UserGroups';
import Ranks from './pages/Ranks';
import ComponentsDemo from './pages/ComponentsDemo';
import './index.css';

function App() {
  return (
    <AuthProvider>
      <Router>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }>
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="users" element={<Users />} />
            <Route path="foods" element={<Foods />} />
            <Route path="classification" element={<FoodClassification />} />
            <Route path="recipes" element={<Recipes />} />
            <Route path="diet-plans" element={<DietPlans />} />
            <Route path="challenges" element={<ChallengeStudio />} />
            <Route path="challenge-groups" element={<ChallengeGroups />} />
            <Route path="content" element={<ContentManagement />} />
            <Route path="user-groups" element={<UserGroups />} />
            <Route path="ranks" element={<Ranks />} />
            <Route path="components" element={<ComponentsDemo />} />
          </Route>
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </Router>
    </AuthProvider>
  );
}

export default App;