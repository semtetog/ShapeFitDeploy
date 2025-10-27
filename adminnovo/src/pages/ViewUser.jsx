import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';

const ViewUser = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState('personal');
  const [currentDayIndex, setCurrentDayIndex] = useState(0);
  const [isDragging, setIsDragging] = useState(false);
  const [startX, setStartX] = useState(0);
  const [translateX, setTranslateX] = useState(0);

  const tabs = [
    { id: 'personal', label: 'Dados Pessoais', icon: 'fas fa-user' },
    { id: 'medical', label: 'Anamnese', icon: 'fas fa-stethoscope' },
    { id: 'diary', label: 'Diário', icon: 'fas fa-book' },
    { id: 'hydration', label: 'Hidratação', icon: 'fas fa-tint' },
    { id: 'nutrients', label: 'Nutrientes', icon: 'fas fa-chart-pie' },
    { id: 'weekly-analysis', label: 'Análise Semanal', icon: 'fas fa-chart-line' },
    { id: 'feedback-analysis', label: 'Análise de Feedback', icon: 'fas fa-comments' },
    { id: 'diet-comparison', label: 'Comparação Dieta', icon: 'fas fa-balance-scale' },
    { id: 'weekly-tracking', label: 'Rastreio Semanal', icon: 'fas fa-calendar-week' },
    { id: 'personalized-goals', label: 'Metas Personalizadas', icon: 'fas fa-target' },
    { id: 'progress', label: 'Progresso', icon: 'fas fa-chart-area' },
    { id: 'measurements', label: 'Medidas', icon: 'fas fa-ruler' }
  ];

  useEffect(() => {
    fetchUserData();
  }, [id]);

  const fetchUserData = async () => {
    try {
      setLoading(true);
      // Simulação de dados do usuário
      const mockUser = {
        id: id,
        name: 'João Silva',
        email: 'joao@email.com',
        phone_ddd: '11',
        phone_number: '99999-9999',
        city: 'São Paulo',
        uf: 'SP',
        dob: '1990-05-15',
        height_cm: 175,
        weight_kg: 70,
        gender: 'male',
        objective: 'lose_fat',
        total_daily_calories_goal: 2000,
        exercise_frequency: '3_4x_week',
        exercise_type: 'Musculação + Cardio',
        water_intake_liters: '2_3l',
        sleep_time_bed: '22:00',
        sleep_time_wake: '06:00',
        meat_consumption: true,
        vegetarian_type: null,
        lactose_intolerance: false,
        gluten_intolerance: false,
        meal_history: [
          {
            date: '2024-01-15',
            meals: {
              breakfast: [
                { food_name: 'Ovos', quantity: 2, unit: 'unidades', kcal_consumed: 140, protein_consumed_g: 12, carbs_consumed_g: 1, fat_consumed_g: 10 }
              ],
              lunch: [
                { food_name: 'Frango', quantity: 150, unit: 'g', kcal_consumed: 250, protein_consumed_g: 45, carbs_consumed_g: 0, fat_consumed_g: 5 }
              ]
            }
          }
        ],
        photo_history: [
          { date: '2024-01-01' },
          { date: '2024-01-15' }
        ]
      };

      setUser(mockUser);
    } catch (err) {
      console.error('Erro ao carregar usuário:', err);
      setError('Erro ao carregar dados do usuário');
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'Não informado';
    return new Date(dateString).toLocaleDateString('pt-BR');
  };

  const calculateAge = (dateString) => {
    if (!dateString) return 'Não informado';
    const today = new Date();
    const birthDate = new Date(dateString);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age;
  };

  const calculateBMI = (weight, height) => {
    if (!weight || !height) return 'Não calculado';
    const heightInMeters = height / 100;
    const bmi = weight / (heightInMeters * heightInMeters);
    return bmi.toFixed(1);
  };

  const getAvatarColor = (name) => {
    const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8'];
    const index = name.length % colors.length;
    return colors[index];
  };

  const goToPreviousDay = () => {
    if (currentDayIndex > 0) {
      setCurrentDayIndex(currentDayIndex - 1);
    }
  };

  const goToNextDay = () => {
    if (currentDayIndex < user.meal_history.length - 1) {
      setCurrentDayIndex(currentDayIndex + 1);
    }
  };

  const handleDragStart = (e) => {
    setIsDragging(true);
    setStartX(e.type === 'mousedown' ? e.clientX : e.touches[0].clientX);
    setTranslateX(0);
    e.preventDefault();
  };

  const handleDragMove = (e) => {
    if (!isDragging) return;
    
    const currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
    const diffX = currentX - startX;
    setTranslateX(diffX);
    e.preventDefault();
  };

  const handleDragEnd = (e) => {
    if (!isDragging) return;
    
    setIsDragging(false);
    const currentX = e.type === 'mouseup' ? e.clientX : e.changedTouches[0].clientX;
    const diffX = currentX - startX;
    
    if (Math.abs(diffX) > 50) {
      if (diffX > 0 && currentDayIndex > 0) {
        goToPreviousDay();
      } else if (diffX < 0 && currentDayIndex < user.meal_history.length - 1) {
        goToNextDay();
      }
    }
    
    setTranslateX(0);
    e.preventDefault();
  };

  const getMealIcon = (mealType) => {
    const icons = {
      breakfast: 'coffee',
      lunch: 'utensils',
      dinner: 'moon',
      snack: 'cookie-bite'
    };
    return icons[mealType] || 'utensils';
  };

  const getMealName = (mealType) => {
    const names = {
      breakfast: 'Café da Manhã',
      lunch: 'Almoço',
      dinner: 'Jantar',
      snack: 'Lanche'
    };
    return names[mealType] || mealType;
  };

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="alert alert-danger">
        <i className="fas fa-exclamation-triangle"></i>
        {error}
      </div>
    );
  }

  if (!user) {
    return (
      <div className="error-container">
        <h2>Usuário não encontrado</h2>
        <p>O usuário solicitado não foi encontrado.</p>
        <button className="btn btn-primary" onClick={() => navigate('/users')}>
          Voltar para Usuários
        </button>
      </div>
    );
  }

  return (
    <div className="view-user-container">
      <div className="tabs-container">
        <div className="user-header-integrated">
          <div className="user-avatar-section">
            {user.avatar ? (
              <img src={user.avatar} alt={user.name} className="user-avatar" />
            ) : (
              <div className="user-avatar-initials">
                {user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)}
              </div>
            )}
          </div>
          <h1 className="user-name">{user.name}</h1>
        </div>
        
        <div className="tabs-nav">
          {tabs.map(tab => (
            <button
              key={tab.id}
              className={`tab-button ${activeTab === tab.id ? 'active' : ''}`}
              onClick={() => setActiveTab(tab.id)}
            >
              <i className={tab.icon}></i>
              <span>{tab.label}</span>
            </button>
          ))}
        </div>

        <div className="tabs-content">
          {/* Aba Dados Pessoais */}
          {activeTab === 'personal' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-user"></i> Dados Pessoais e Físicos</h3>
              </div>
              
                <div className="stats-grid">
                  <div className="stat-card">
                    <div className="stat-icon-container users">
                      <i className="fas fa-user"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.name}</div>
                      <div className="stat-label">Nome Completo</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container diaries">
                      <i className="fas fa-envelope"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.email}</div>
                      <div className="stat-label">Email</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container users">
                      <i className="fas fa-phone"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">({user.phone_ddd}) {user.phone_number}</div>
                      <div className="stat-label">Telefone</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container foods">
                      <i className="fas fa-map-marker-alt"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.city} - {user.uf}</div>
                      <div className="stat-label">Localização</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container users">
                      <i className="fas fa-birthday-cake"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{formatDate(user.dob)}</div>
                      <div className="stat-label">Data de Nascimento</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container diaries">
                      <i className="fas fa-calendar"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{calculateAge(user.dob)} anos</div>
                      <div className="stat-label">Idade</div>
                    </div>
                  </div>
                </div>

                <div className="nutrition-stats">
                  <div className="nutrition-card">
                    <h3>Dados Físicos</h3>
                    <div className="stats-grid">
                      <div className="stat-card">
                        <div className="stat-icon-container weight">
                          <i className="fas fa-ruler-vertical"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.height_cm} cm</div>
                          <div className="stat-label">Altura</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container height">
                          <i className="fas fa-weight"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.weight_kg} kg</div>
                          <div className="stat-label">Peso Atual</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container age">
                          <i className="fas fa-calculator"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{calculateBMI(user.weight_kg, user.height_cm)}</div>
                          <div className="stat-label">IMC</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container imc">
                          <i className="fas fa-venus-mars"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.gender === 'female' ? 'Feminino' : 'Masculino'}</div>
                          <div className="stat-label">Gênero</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="nutrition-stats">
                  <div className="nutrition-card">
                    <h3>Objetivos e Metas</h3>
                    <div className="stats-grid">
                      <div className="stat-card">
                        <div className="stat-icon-container users">
                          <i className="fas fa-bullseye"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">
                            {user.objective === 'lose_fat' ? 'Emagrecimento' : 
                             user.objective === 'gain_muscle' ? 'Hipertrofia' : 
                             'Manter Peso'}
                          </div>
                          <div className="stat-label">Objetivo</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container diaries">
                          <i className="fas fa-fire"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.total_daily_calories_goal} kcal</div>
                          <div className="stat-label">Meta Calórica</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container foods">
                          <i className="fas fa-dumbbell"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">
                            {user.exercise_frequency === '3_4x_week' ? '3 a 4x/semana' : 
                             user.exercise_frequency === '1_2x_week' ? '1 a 2x/semana' : 
                             user.exercise_frequency === '5_6x_week' ? '5 a 6x/semana' : 
                             'Sedentário'}
                          </div>
                          <div className="stat-label">Frequência de Exercícios</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container users">
                          <i className="fas fa-running"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.exercise_type}</div>
                          <div className="stat-label">Tipo de Exercício</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="nutrition-stats">
                  <div className="nutrition-card">
                    <h3>Hábitos de Vida</h3>
                    <div className="stats-grid">
                      <div className="stat-card">
                        <div className="stat-icon-container users">
                          <i className="fas fa-tint"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">
                            {user.water_intake_liters === '2_3l' ? '2 a 3 Litros por dia' : 
                             user.water_intake_liters === '1_2l' ? '1 a 2 Litros por dia' : 
                             user.water_intake_liters === '_1l' ? 'Até 1 Litro por dia' : 
                             'Mais de 3 Litros por dia'}
                          </div>
                          <div className="stat-label">Consumo de Água</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container diaries">
                          <i className="fas fa-bed"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.sleep_time_bed}</div>
                          <div className="stat-label">Horário de Dormir</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container foods">
                          <i className="fas fa-sun"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">{user.sleep_time_wake}</div>
                          <div className="stat-label">Horário de Acordar</div>
                        </div>
                      </div>
                      
                      <div className="stat-card">
                        <div className="stat-icon-container users">
                          <i className="fas fa-clock"></i>
                        </div>
                        <div className="stat-content">
                          <div className="stat-value">
                            {user.sleep_time_bed && user.sleep_time_wake ? 
                              (() => {
                                const bed = new Date(`2000-01-01T${user.sleep_time_bed}`);
                                const wake = new Date(`2000-01-01T${user.sleep_time_wake}`);
                                if (wake < bed) wake.setDate(wake.getDate() + 1);
                                const diff = wake - bed;
                                const hours = Math.floor(diff / (1000 * 60 * 60));
                                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                return `${hours}h ${minutes}min`;
                              })() : 'Não informado'
                            }
                          </div>
                          <div className="stat-label">Duração do Sono</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}

          {/* Aba Diário Alimentar */}
          {activeTab === 'diary' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-utensils"></i> Diário Alimentar</h3>
                <div className="date-filter">
                  <button className="calendar-button" title="Selecionar período">
                    <i className="fas fa-calendar-alt"></i>
                  </button>
                </div>
              </div>
              
              <div className="diary-navigation-container">
                {user.meal_history.length === 0 ? (
                  <div className="empty-diary-state">
                    <div className="empty-icon">
                      <i className="fas fa-utensils"></i>
                    </div>
                    <h4>Nenhum registro encontrado</h4>
                    <p>Este usuário ainda não possui registros no diário alimentar.</p>
                  </div>
                ) : (
                  <>
                    <div className="diary-navigation-controls">
                      <button 
                        className="nav-button prev"
                        onClick={goToPreviousDay}
                        disabled={currentDayIndex === 0}
                      >
                        <i className="fas fa-chevron-left"></i>
                      </button>
                      
                      <div className="day-indicator">
                        <div className="current-day">{currentDayIndex + 1}</div>
                        <div className="total-days">de {user.meal_history.length}</div>
                      </div>
                      
                      <button 
                        className="nav-button next"
                        onClick={goToNextDay}
                        disabled={currentDayIndex === user.meal_history.length - 1}
                      >
                        <i className="fas fa-chevron-right"></i>
                      </button>
                    </div>

                      <div 
                        className="diary-cards-container"
                        onMouseDown={handleDragStart}
                        onMouseMove={handleDragMove}
                        onMouseUp={handleDragEnd}
                        onMouseLeave={handleDragEnd}
                        onTouchStart={handleDragStart}
                        onTouchMove={handleDragMove}
                        onTouchEnd={handleDragEnd}
                      >
                        <div 
                          className="cards-wrapper"
                          style={{
                            transform: `translateX(calc(${-currentDayIndex * 100}% + ${translateX}px))`,
                            transition: isDragging ? 'none' : 'transform 0.3s ease'
                          }}
                        >
                          {user.meal_history.map((day, index) => {
                            // Calcular totais do dia
                            const dayTotals = Object.values(day.meals).flat().reduce((totals, item) => ({
                              calories: totals.calories + item.kcal_consumed,
                              protein: totals.protein + item.protein_consumed_g,
                              carbs: totals.carbs + item.carbs_consumed_g,
                              fat: totals.fat + item.fat_consumed_g
                            }), { calories: 0, protein: 0, carbs: 0, fat: 0 });

                            const mealCount = Object.values(day.meals).reduce((count, meals) => count + meals.length, 0);
                            
                            return (
                              <div key={index} className="daily-card">
                                <div className="daily-card-header">
                                  <div className="daily-date">
                                    <div className="date-day">{new Date(day.date).getDate()}</div>
                                    <div className="date-month">{new Date(day.date).toLocaleDateString('pt-BR', { month: 'short' })}</div>
                                    <div className="date-year">{new Date(day.date).getFullYear()}</div>
                                  </div>
                                  <div className="daily-summary">
                                    <div className="summary-calories">
                                      <span className="calories-value">{dayTotals.calories.toFixed(0)}</span>
                                      <span className="calories-label">kcal</span>
                                    </div>
                                    <div className="summary-meals">
                                      <i className="fas fa-utensils"></i>
                                      <span>{mealCount} refeições</span>
                                    </div>
                                  </div>
                                </div>

                                <div className="daily-meals">
                                  {Object.entries(day.meals).map(([mealType, meals]) => (
                                    meals.length > 0 && (
                                      <div key={mealType} className="meal-section">
                                        <div className="meal-header">
                                          <div className="meal-title">
                                            <div className="meal-icon" style={{backgroundColor: '#f60'}}>
                                              <i className={`fas fa-${getMealIcon(mealType)}`}></i>
                                            </div>
                                            <div className="meal-info">
                                              <h5>{getMealName(mealType)}</h5>
                                              <span className="meal-time">12:00</span>
                                            </div>
                                          </div>
                                          <div className="meal-totals">
                                            <div className="meal-calories">{meals.reduce((total, item) => total + item.kcal_consumed, 0).toFixed(0)} kcal</div>
                                            <div className="meal-macros">
                                              P: {meals.reduce((total, item) => total + item.protein_consumed_g, 0).toFixed(1)}g • 
                                              C: {meals.reduce((total, item) => total + item.carbs_consumed_g, 0).toFixed(1)}g • 
                                              G: {meals.reduce((total, item) => total + item.fat_consumed_g, 0).toFixed(1)}g
                                            </div>
                                          </div>
                                        </div>
                                        <div className="food-items">
                                          {meals.map((item, itemIndex) => (
                                            <div key={itemIndex} className="food-item">
                                              <div className="food-info">
                                                <span className="food-name">{item.food_name}</span>
                                                <span className="food-quantity">{item.quantity} {item.unit}</span>
                                              </div>
                                              <div className="food-nutrition">
                                                <span className="food-calories">{item.kcal_consumed.toFixed(0)} kcal</span>
                                                <div className="food-macros">
                                                  P: {item.protein_consumed_g.toFixed(1)}g • C: {item.carbs_consumed_g.toFixed(1)}g • G: {item.fat_consumed_g.toFixed(1)}g
                                                </div>
                                              </div>
                                            </div>
                                          ))}
                                        </div>
                                      </div>
                                    )
                                  ))}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      </div>
                      
                      <div className="diary-dots-navigation">
                        {user.meal_history.map((_, index) => (
                          <button 
                            key={index}
                            className={`dot ${index === currentDayIndex ? 'active' : ''}`}
                            onClick={() => setCurrentDayIndex(index)}
                          ></button>
                        ))}
                      </div>
                    </>
                  )}
                </div>
              </div>
            )}

          {/* Aba Anamnese Médica */}
          {activeTab === 'medical' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-stethoscope"></i> Anamnese Médica</h3>
              </div>
                
                <div className="stats-grid">
                  <div className="stat-card">
                    <div className="stat-icon-container users">
                      <i className="fas fa-drumstick-bite"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.meat_consumption ? 'Sim' : 'Não'}</div>
                      <div className="stat-label">Consome Carne</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container diaries">
                      <i className="fas fa-leaf"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">
                        {user.vegetarian_type ? 
                          (user.vegetarian_type === 'strict_vegetarian' ? 'Vegetariano Estrito' :
                           user.vegetarian_type === 'ovolacto' ? 'Ovolactovegetariano' :
                           user.vegetarian_type === 'vegan' ? 'Vegano' : 'Apenas não gosta') : 
                          'Não se aplica'}
                      </div>
                      <div className="stat-label">Tipo Vegetariano</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container foods">
                      <i className="fas fa-exclamation-triangle"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.lactose_intolerance ? 'Sim' : 'Não'}</div>
                      <div className="stat-label">Intolerância à Lactose</div>
                    </div>
                  </div>
                  
                  <div className="stat-card">
                    <div className="stat-icon-container users">
                      <i className="fas fa-bread-slice"></i>
                    </div>
                    <div className="stat-content">
                      <div className="stat-value">{user.gluten_intolerance ? 'Sim' : 'Não'}</div>
                      <div className="stat-label">Intolerância ao Glúten</div>
                    </div>
                  </div>
                </div>
              </div>
            )}

          {/* Aba Hidratação */}
          {activeTab === 'hydration' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-tint"></i> Hidratação</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-tint"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Nutrientes */}
          {activeTab === 'nutrients' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-chart-pie"></i> Nutrientes</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-chart-pie"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Análise Semanal */}
          {activeTab === 'weekly-analysis' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-chart-line"></i> Análise Semanal</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-chart-line"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Análise de Feedback */}
          {activeTab === 'feedback-analysis' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-comments"></i> Análise de Feedback</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-comments"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Comparação Dieta */}
          {activeTab === 'diet-comparison' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-balance-scale"></i> Comparação Dieta</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-balance-scale"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Rastreio Semanal */}
          {activeTab === 'weekly-tracking' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-calendar-week"></i> Rastreio Semanal</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-calendar-week"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Metas Personalizadas */}
          {activeTab === 'personalized-goals' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-target"></i> Metas Personalizadas</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-target"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Progresso */}
          {activeTab === 'progress' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-chart-area"></i> Progresso</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-chart-area"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}

          {/* Aba Medidas */}
          {activeTab === 'measurements' && (
            <div className="tab-panel">
              <div className="panel-header">
                <h3><i className="fas fa-ruler"></i> Medidas Corporais</h3>
              </div>
              
              <div className="empty-state">
                <i className="fas fa-ruler"></i>
                <p>Funcionalidade a ser implementada.</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ViewUser;