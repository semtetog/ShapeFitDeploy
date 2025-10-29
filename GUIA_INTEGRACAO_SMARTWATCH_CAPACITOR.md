# 📱 Guia de Integração com Smartwatch/Relógio - Capacitor

## 🎯 Objetivo
Integrar o app ShapeFit com Apple Health (iOS) e Google Fit / Samsung Health (Android) para capturar automaticamente dados de:
- ✅ Passos diários
- ✅ Distância percorrida
- ✅ Calorias queimadas
- ✅ Horas de sono
- ✅ Frequência cardíaca
- ✅ Atividades físicas (treino/cardio)

---

## 📦 Plugin Recomendado

### **@capacitor-community/health**
Link: https://github.com/capacitor-community/health

Este é o plugin mais completo e mantido ativamente pela comunidade Capacitor.

---

## 🛠️ Instalação

### 1. Instalar o plugin
```bash
npm install @capacitor-community/health
npx cap sync
```

### 2. Configurar Permissões

#### **iOS (Apple Health)**

Editar `ios/App/App/Info.plist`:

```xml
<key>NSHealthShareUsageDescription</key>
<string>O ShapeFit precisa acessar seus dados de saúde para rastrear seu progresso</string>
<key>NSHealthUpdateUsageDescription</key>
<string>O ShapeFit precisa atualizar seus dados de saúde</string>
```

#### **Android (Google Fit)**

Editar `android/app/src/main/AndroidManifest.xml`:

```xml
<manifest ...>
    <uses-permission android:name="android.permission.ACTIVITY_RECOGNITION" />
    <uses-permission android:name="android.permission.BODY_SENSORS" />
    
    <application ...>
        <!-- Google Fit API -->
        <meta-data
            android:name="com.google.android.gms.fitness.API_KEY"
            android:value="YOUR_GOOGLE_FIT_API_KEY" />
    </application>
</manifest>
```

---

## 💻 Código de Implementação

### 1. Criar Service de Health (JavaScript/TypeScript)

Criar arquivo: `src/services/HealthService.js`

```javascript
import { Health } from '@capacitor-community/health';
import { Capacitor } from '@capacitor/core';

class HealthService {
  constructor() {
    this.isAvailable = false;
    this.init();
  }

  async init() {
    // Verificar se está rodando em device nativo
    if (Capacitor.isNativePlatform()) {
      try {
        this.isAvailable = await Health.isAvailable();
        console.log('Health API disponível:', this.isAvailable);
      } catch (error) {
        console.error('Erro ao verificar Health API:', error);
      }
    }
  }

  /**
   * Solicitar permissões ao usuário
   */
  async requestPermissions() {
    if (!this.isAvailable) return false;

    try {
      const permissions = {
        read: [
          'steps',
          'distance',
          'calories',
          'sleep',
          'heart_rate',
          'activity',
          'active_energy_burned'
        ],
        write: []
      };

      const result = await Health.requestAuthorization(permissions);
      return result;
    } catch (error) {
      console.error('Erro ao solicitar permissões:', error);
      return false;
    }
  }

  /**
   * Buscar passos do dia
   */
  async getStepsToday() {
    if (!this.isAvailable) return 0;

    try {
      const now = new Date();
      const startOfDay = new Date(now.setHours(0, 0, 0, 0));
      const endOfDay = new Date(now.setHours(23, 59, 59, 999));

      const result = await Health.querySteps({
        startDate: startOfDay.toISOString(),
        endDate: endOfDay.toISOString()
      });

      return result.steps || 0;
    } catch (error) {
      console.error('Erro ao buscar passos:', error);
      return 0;
    }
  }

  /**
   * Buscar passos de uma data específica
   */
  async getStepsByDate(date) {
    if (!this.isAvailable) return 0;

    try {
      const startOfDay = new Date(date);
      startOfDay.setHours(0, 0, 0, 0);
      
      const endOfDay = new Date(date);
      endOfDay.setHours(23, 59, 59, 999);

      const result = await Health.querySteps({
        startDate: startOfDay.toISOString(),
        endDate: endOfDay.toISOString()
      });

      return result.steps || 0;
    } catch (error) {
      console.error('Erro ao buscar passos:', error);
      return 0;
    }
  }

  /**
   * Buscar distância percorrida (em quilômetros)
   */
  async getDistanceToday() {
    if (!this.isAvailable) return 0;

    try {
      const now = new Date();
      const startOfDay = new Date(now.setHours(0, 0, 0, 0));
      const endOfDay = new Date(now.setHours(23, 59, 59, 999));

      const result = await Health.queryDistance({
        startDate: startOfDay.toISOString(),
        endDate: endOfDay.toISOString()
      });

      // Converter metros para quilômetros
      return (result.distance || 0) / 1000;
    } catch (error) {
      console.error('Erro ao buscar distância:', error);
      return 0;
    }
  }

  /**
   * Buscar horas dormidas
   */
  async getSleepHours(date) {
    if (!this.isAvailable) return 0;

    try {
      const startOfDay = new Date(date);
      startOfDay.setHours(0, 0, 0, 0);
      
      const endOfDay = new Date(date);
      endOfDay.setHours(23, 59, 59, 999);

      const result = await Health.querySleep({
        startDate: startOfDay.toISOString(),
        endDate: endOfDay.toISOString()
      });

      // Converter minutos para horas
      return (result.duration || 0) / 60;
    } catch (error) {
      console.error('Erro ao buscar sono:', error);
      return 0;
    }
  }

  /**
   * Buscar atividades físicas (treino/cardio)
   */
  async getWorkoutData(startDate, endDate) {
    if (!this.isAvailable) return { workoutHours: 0, cardioHours: 0 };

    try {
      const result = await Health.queryActivity({
        startDate: startDate.toISOString(),
        endDate: endDate.toISOString()
      });

      let workoutHours = 0;
      let cardioHours = 0;

      if (result.activities) {
        result.activities.forEach(activity => {
          const durationHours = (activity.duration || 0) / 60; // minutos para horas

          // Categorizar atividades
          const cardioTypes = ['running', 'cycling', 'swimming', 'walking', 'hiking'];
          const workoutTypes = ['weight_training', 'strength_training', 'fitness'];

          if (cardioTypes.includes(activity.type.toLowerCase())) {
            cardioHours += durationHours;
          } else if (workoutTypes.includes(activity.type.toLowerCase())) {
            workoutHours += durationHours;
          } else {
            // Por padrão, considera como treino
            workoutHours += durationHours;
          }
        });
      }

      return {
        workoutHours: Math.round(workoutHours * 100) / 100,
        cardioHours: Math.round(cardioHours * 100) / 100
      };
    } catch (error) {
      console.error('Erro ao buscar atividades:', error);
      return { workoutHours: 0, cardioHours: 0 };
    }
  }

  /**
   * Sincronizar todos os dados do dia
   */
  async syncTodayData() {
    if (!this.isAvailable) {
      console.warn('Health API não disponível');
      return null;
    }

    try {
      const now = new Date();
      const startOfDay = new Date(now.setHours(0, 0, 0, 0));
      const endOfDay = new Date(now.setHours(23, 59, 59, 999));

      const [steps, distance, sleep, workoutData] = await Promise.all([
        this.getStepsToday(),
        this.getDistanceToday(),
        this.getSleepHours(new Date()),
        this.getWorkoutData(startOfDay, endOfDay)
      ]);

      return {
        steps,
        distance,
        sleepHours: sleep,
        workoutHours: workoutData.workoutHours,
        cardioHours: workoutData.cardioHours,
        syncDate: new Date().toISOString()
      };
    } catch (error) {
      console.error('Erro na sincronização:', error);
      return null;
    }
  }
}

export default new HealthService();
```

---

### 2. Integrar com Backend PHP

Criar arquivo: `api/sync_health_data.php`

```php
<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

try {
    // Data para sincronização (padrão: hoje)
    $sync_date = $data['date'] ?? date('Y-m-d');
    
    // Dados do Health API
    $steps = (int)($data['steps'] ?? 0);
    $workout_hours = (float)($data['workoutHours'] ?? 0);
    $cardio_hours = (float)($data['cardioHours'] ?? 0);
    $sleep_hours = (float)($data['sleepHours'] ?? 0);
    
    // Atualizar ou inserir dados
    $stmt = $conn->prepare("
        INSERT INTO sf_user_daily_tracking 
        (user_id, date, steps_daily, workout_hours, cardio_hours, sleep_hours) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            steps_daily = VALUES(steps_daily),
            workout_hours = VALUES(workout_hours),
            cardio_hours = VALUES(cardio_hours),
            sleep_hours = VALUES(sleep_hours),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param("isiddd", $user_id, $sync_date, $steps, $workout_hours, $cardio_hours, $sleep_hours);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Dados sincronizados com sucesso',
            'data' => [
                'date' => $sync_date,
                'steps' => $steps,
                'workoutHours' => $workout_hours,
                'cardioHours' => $cardio_hours,
                'sleepHours' => $sleep_hours
            ]
        ]);
    } else {
        throw new Exception('Erro ao salvar dados');
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no servidor: ' . $e->getMessage()
    ]);
}
?>
```

---

### 3. Usar no App (Exemplo - Ionic/Vue/React)

```javascript
import HealthService from './services/HealthService';
import axios from 'axios';

async function setupHealthSync() {
  // 1. Solicitar permissões
  const hasPermission = await HealthService.requestPermissions();
  
  if (!hasPermission) {
    console.warn('Usuário negou permissões de saúde');
    return;
  }

  // 2. Sincronizar dados
  const healthData = await HealthService.syncTodayData();
  
  if (healthData) {
    // 3. Enviar para o backend
    try {
      const response = await axios.post(
        'https://seu-dominio.com/api/sync_health_data.php',
        healthData,
        { withCredentials: true }
      );
      
      console.log('Sincronização concluída:', response.data);
    } catch (error) {
      console.error('Erro ao sincronizar com servidor:', error);
    }
  }
}

// Sincronizar ao abrir o app
setupHealthSync();

// Sincronizar periodicamente (a cada 1 hora)
setInterval(() => {
  setupHealthSync();
}, 60 * 60 * 1000);
```

---

## 🔄 Fluxo de Sincronização Recomendado

### 1. **Ao abrir o app**
- Solicitar permissões (apenas na primeira vez)
- Sincronizar dados do dia

### 2. **Em background (opcional)**
- Usar `@capacitor/background-task` para sync periódico
- Sincronizar a cada 2-3 horas

### 3. **Manual**
- Botão de "Sincronizar agora" na tela de progresso

---

## 📊 Exibição na Interface

Adicionar indicador de sincronização no `progress_v2.php`:

```html
<!-- Adicionar no topo da página de progresso -->
<div class="sync-status" id="syncStatus" style="display: none;">
    <span class="sync-icon">🔄</span>
    <span class="sync-text">Sincronizando dados do smartwatch...</span>
</div>

<button class="btn-sync" onclick="syncHealthData()">
    <i class="fas fa-sync-alt"></i>
    Sincronizar Smartwatch
</button>
```

---

## ⚠️ Considerações Importantes

### **Privacy & Permissões**
- ✅ Explicar claramente ao usuário por que precisa dessas permissões
- ✅ Permitir que o app funcione mesmo sem acesso ao Health
- ✅ Entrada manual sempre como fallback

### **Compatibilidade**
- **iOS**: Apple Health (nativo)
- **Android**: Google Fit, Samsung Health, Huawei Health
- **Web**: Não suportado (apenas em apps nativos)

### **Limitações**
- Dados históricos podem ter limite de acesso (depende do dispositivo)
- Alguns wearables precisam estar sincronizados com o smartphone
- Consumo de bateria ao fazer sync muito frequente

---

## 🧪 Teste

1. **iOS Simulator**: Apple Health tem dados simulados
2. **Android Emulator**: Precisa configurar Google Fit com dados de teste
3. **Devices reais**: Melhor forma de testar

---

## 📝 Checklist de Implementação

- [ ] Instalar plugin `@capacitor-community/health`
- [ ] Configurar permissões iOS (Info.plist)
- [ ] Configurar permissões Android (AndroidManifest.xml)
- [ ] Implementar HealthService.js
- [ ] Criar endpoint `api/sync_health_data.php`
- [ ] Adicionar botão de sincronização na UI
- [ ] Testar em iOS real
- [ ] Testar em Android real
- [ ] Implementar sincronização em background (opcional)
- [ ] Adicionar indicadores visuais de sync
- [ ] Documentar para usuários finais

---

## 🎉 Resultado Final

Quando implementado, seus usuários poderão:
- ✅ Ver passos automaticamente no app
- ✅ Horas de treino sincronizadas automaticamente
- ✅ Sono rastreado automaticamente
- ✅ Gráficos sempre atualizados
- ✅ Menos trabalho manual

---

## 📞 Suporte

- Documentação oficial: https://github.com/capacitor-community/health
- Issues do GitHub: https://github.com/capacitor-community/health/issues
- Stack Overflow: tag `capacitor-health`

---

**Desenvolvido para ShapeFit - Outubro 2025**






