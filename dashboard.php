<?php
// dashboard.php (VERSÃO FINAL COM O ESTILO CORRETO DO APP)

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// --- BUSCA DE DADOS UNIFICADA E COMPLETA ---
$stmt_profile = $conn->prepare("
    SELECT
        u.name,
        p.dob, p.gender, p.height_cm, p.weight_kg, p.objective,
        p.exercise_frequency,
        p.custom_calories_goal,
        p.custom_protein_goal_g,
        p.custom_carbs_goal_g,
        p.custom_fat_goal_g,
        p.custom_water_goal_ml
    FROM sf_users u
    LEFT JOIN sf_user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$result_profile = $stmt_profile->get_result();

if ($result_profile->num_rows === 0) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php?error=profile_missing");
    exit();
}
$profile = $result_profile->fetch_assoc();
$stmt_profile->close();

// --- CÁLCULOS E DADOS DO DIA ---
try {
    // Cálculo de idade simples
    $dob = $profile['dob'] ?? '1990-01-01';
    $age_years = date_diff(date_create($dob), date_create('today'))->y;
    
    // Cálculo correto de calorias usando a função atualizada
    $weight_kg = (float)($profile['weight_kg'] ?? 70);
    $height_cm = (int)($profile['height_cm'] ?? 170);
    $gender = $profile['gender'] ?? 'female';
    $objective = $profile['objective'] ?? 'maintain_weight';
    $exercise_frequency = $profile['exercise_frequency'] ?? 'sedentary';
    
    // PRIORIZAR METAS CUSTOMIZADAS se existirem
    if (!empty($profile['custom_calories_goal'])) {
        $daily_calories = (int)$profile['custom_calories_goal'];
    } else {
        $daily_calories = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
    }
    
    // Macros: usar customizadas se existirem, senão calcular
    if (!empty($profile['custom_protein_goal_g']) && !empty($profile['custom_carbs_goal_g']) && !empty($profile['custom_fat_goal_g'])) {
        $protein_g = (float)$profile['custom_protein_goal_g'];
        $carbs_g = (float)$profile['custom_carbs_goal_g'];
        $fat_g = (float)$profile['custom_fat_goal_g'];
    } else {
        // Cálculo básico de macros (40% carb, 30% prot, 30% gordura)
        $carbs_g = round(($daily_calories * 0.4) / 4);
        $protein_g = round(($daily_calories * 0.3) / 4);
        $fat_g = round(($daily_calories * 0.3) / 9);
    }
    
    $macros = [
        'carbs_g' => $carbs_g,
        'protein_g' => $protein_g,
        'fat_g' => $fat_g
    ];

    $current_date = date('Y-m-d');
    // Valores padrão para consumo diário
    $protein_consumed = 0;
    $carbs_consumed = 0;
    $fat_consumed = 0;
    $water_consumed_cups = 0;

    // Meta de água: priorizar customizada se existir
    if (!empty($profile['custom_water_goal_ml'])) {
        $water_goal_ml = (int)$profile['custom_water_goal_ml'];
        $water_goal_cups = ceil($water_goal_ml / 250);
    } else {
        $weight_kg = (float)($profile['weight_kg'] ?? 70);
        $water_goal_data = getWaterIntakeSuggestion($weight_kg);
        $water_goal_ml = $water_goal_data['total_ml'];
        $water_goal_cups = $water_goal_data['cups'];
    }

    define('ML_PER_CUP', 250);
    $water_consumed_ml = $water_consumed_cups * ML_PER_CUP;
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    // Valores padrão em caso de erro
    $daily_calories = 2000;
    $macros = ['carbs_g' => 250, 'protein_g' => 150, 'fat_g' => 67];
    $protein_consumed = 0;
    $carbs_consumed = 0;
    $fat_consumed = 0;
    $water_consumed_ml = 0;
    $water_goal_ml = 2000;
}

$page_title = "Minha Meta";
$extra_js = ['script.js'];
$extra_css = ['pages/_dashboard.css'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, shrink-to-fit=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ShapeFit">
    
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo BASE_APP_URL; ?>/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo BASE_APP_URL; ?>/favicon.ico" type="image/x-icon">
    
    <!-- Variáveis Globais para JavaScript -->
    <script>
        const isUserLoggedInPHP = <?php echo json_encode(isLoggedIn()); ?>;
        const BASE_APP_URL = "<?php echo rtrim(BASE_APP_URL, '/'); ?>";
    </script>
    
    <style>
        body {
            -webkit-user-select: none;
            -ms-user-select: none;
            user-select: none;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            height: 100vh;
            font-family: 'Montserrat', sans-serif;
            overflow: hidden;
            width: 100%;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
        }
        
        html {
            overflow: hidden;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .fixed-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="fixed-background"></div>
    <input type="hidden" id="csrf_token_main_app" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <div id="alert-container"></div>

<style>
/* CSS do layout nativo para mobile - Dashboard */
.dashboard-page-grid {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20px;
    padding: 0px 8px 60px 8px;
    height: 100vh;
    overflow: hidden;
    box-sizing: border-box;
}

/* Título da página */
.page-title {
    text-align: center;
    margin-bottom: 10px;
}

.page-title h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* Card principal de calorias */
.calories-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.calories-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.calories-header {
    margin-bottom: 24px;
}

.calories-title {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.calories-title {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.calories-value {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text-primary);
    margin: 0;
}

/* Grid de macros */
.macros-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
    width: 100%;
    justify-items: center;
}

.macro-item {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    width: 100%;
    max-width: 160px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.macro-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.macro-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 12px;
    width: 100%;
}

.macro-header .icon {
    font-size: 1.2rem;
    width: 24px;
    text-align: center;
    line-height: 1;
}

.macro-header h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.macro-values {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    white-space: nowrap;
    text-align: center;
    width: 100%;
}

.macro-values .total {
    color: var(--text-secondary);
    font-weight: 500;
}

/* Botões */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
    align-items: center;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 24px;
    background: var(--accent-orange);
    color: white;
    border: none;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    min-height: 48px;
    width: 100%;
    max-width: 300px;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.btn:hover {
    background: #ff8c00;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.03);
    color: var(--text-primary);
    border: 1px solid rgba(255, 255, 255, 0.05);
    width: 100%;
    max-width: 300px;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    -khtml-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.08);
}

/* Responsividade */
@media (max-width: 768px) {
    .dashboard-page-grid {
        padding: 0px 6px 60px 6px;
    }
    
    .page-title h1 {
        font-size: 1.8rem;
    }
    
    .calories-card {
        padding: 24px 20px;
        max-width: 350px;
    }
    
    .calories-value {
        font-size: 3rem;
    }
    
    .macro-item {
        padding: 16px 12px;
        max-width: 140px;
    }
    
    .macro-values {
        font-size: 1rem;
    }
    
    .btn, .btn-secondary {
        max-width: 280px;
    }
}
</style>

<div class="app-container">
    <section class="dashboard-page-grid">
        <!-- Título da página -->
        <div class="page-title">
            <h1>Minha Meta</h1>
        </div>
        
        <!-- Card principal de calorias -->
        <div class="calories-card">
            <div class="calories-header">
                <h3 class="calories-title">
                    🔥 Calorias diárias
                </h3>
                <div class="calories-value">
                    <?php echo number_format($daily_calories, 0, ',', '.'); ?>
                </div>
            </div>
            
            <!-- Grid de macros -->
            <div class="macros-grid">
                <div class="macro-item">
                    <div class="macro-header">
                        <span class="icon">🍞</span>
                        <h4>Carboidratos</h4>
                    </div>
                    <div class="macro-values">
                        <span><?php echo round($carbs_consumed); ?>g</span>
                        <span class="total">/<?php echo round($macros['carbs_g']); ?>g</span>
                    </div>
                </div>
                
                <div class="macro-item">
                    <div class="macro-header">
                        <span class="icon">🥩</span>
                        <h4>Proteínas</h4>
                    </div>
                    <div class="macro-values">
                        <span><?php echo round($protein_consumed); ?>g</span>
                        <span class="total">/<?php echo round($macros['protein_g']); ?>g</span>
                    </div>
                </div>
                
                <div class="macro-item">
                    <div class="macro-header">
                        <span class="icon">🥑</span>
                        <h4>Gorduras</h4>
                    </div>
                    <div class="macro-values">
                        <span><?php echo round($fat_consumed); ?>g</span>
                        <span class="total">/<?php echo round($macros['fat_g']); ?>g</span>
                    </div>
                </div>
                
                <div class="macro-item">
                    <div class="macro-header">
                        <span class="icon">💧</span>
                        <h4>Água</h4>
                    </div>
                    <div class="macro-values">
                        <span><?php echo $water_consumed_ml; ?>ml</span>
                        <span class="total">/<?php echo $water_goal_ml; ?>ml</span>
                    </div>
                </div>
            </div>

            <!-- Botões de ação -->
            <div class="action-buttons">
                <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="btn">
                    <i class="fas fa-arrow-right"></i>
                    Continuar para o App
                </a>
                
                <a href="<?php echo BASE_APP_URL; ?>/onboarding/onboarding.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i>
                    Refazer questionário
                </a>
            </div>
        </div>
    </section>
</div>

    <!-- JavaScript Principal -->
    <script src="<?php echo BASE_APP_URL; ?>/assets/js/script.js"></script>
    
    <!-- Prevenir scroll e comportamentos indesejados -->
    <script>
        // Prevenir scroll em dispositivos móveis
        document.addEventListener('touchmove', function(e) {
            e.preventDefault();
        }, { passive: false });
        
        // Prevenir zoom
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
        
        // Prevenir menu de contexto em botões
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        // Prevenir seleção de texto
        document.addEventListener('selectstart', function(e) {
            e.preventDefault();
        });
        
        // Prevenir drag and drop
        document.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });
        
        // Prevenir scroll com teclado
        document.addEventListener('keydown', function(e) {
            if([32, 33, 34, 35, 36, 37, 38, 39, 40].indexOf(e.keyCode) > -1) {
                e.preventDefault();
            }
        }, false);
        
        // Prevenir comportamento de long press em botões
        let touchStartTime = 0;
        document.addEventListener('touchstart', function(e) {
            touchStartTime = Date.now();
        });
        
        document.addEventListener('touchend', function(e) {
            const touchDuration = Date.now() - touchStartTime;
            if (touchDuration > 500) { // Se segurou por mais de 500ms
                e.preventDefault();
            }
        });
    </script>
    
</body>
</html>