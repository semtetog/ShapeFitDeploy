<?php
/**
 * Script para debugar o cálculo de calorias de um usuário específico
 * Versão com visual melhorado para apresentação ao cliente
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = 36; // Usuário específico para debug

// Buscar dados do usuário
$stmt_user = $conn->prepare("
    SELECT 
        u.id, u.name,
        p.dob, p.gender, p.height_cm, p.weight_kg, p.objective, p.exercise_frequency
    FROM sf_users u
    INNER JOIN sf_user_profiles p ON u.id = p.user_id
    WHERE u.id = ?
");

$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

if (!$user_data) {
    echo "<p style='color: red;'>❌ Usuário não encontrado!</p>";
    exit;
}

// Calcular dados
$age_years = calculateAge($user_data['dob']);
$height_m = $user_data['height_cm'] / 100;
$imc = $user_data['weight_kg'] / ($height_m * $height_m);
$imc_category = getIMCCategory($imc);

// Mapear frequência de exercício para fatores de atividade
$activity_factors = [
    'sedentary' => 1.1,
    '1_2x_week' => 1.3,
    '3_4x_week' => 1.6,
    '5_6x_week' => 1.7,
    '6_7x_week' => 1.7,
    '7plus_week' => 1.7
];
$activity_factor = $activity_factors[$user_data['exercise_frequency']] ?? 1.1;

// Calcular TMB
$tmb = 0;
$formula_used = "";

if ($imc > 30) {
    $formula_used = "Mifflin (IMC > 30)";
    if (strtolower($user_data['gender']) == 'male') {
        $tmb = (10 * $user_data['weight_kg']) + (6.25 * $user_data['height_cm']) - (5 * $age_years) + 5;
    } else {
        $tmb = (10 * $user_data['weight_kg']) + (6.25 * $user_data['height_cm']) - (5 * $age_years) - 161;
    }
} elseif (strtolower($user_data['gender']) == 'female' && $imc <= 30) {
    $formula_used = "Harris-Benedict (Mulher, IMC ≤ 30)";
    $tmb = 447.593 + (9.247 * $user_data['weight_kg']) + (3.098 * $user_data['height_cm']) - (4.330 * $age_years);
} elseif (strtolower($user_data['gender']) == 'male' && $imc <= 30) {
    $formula_used = "Tinsley (Homem, IMC ≤ 30)";
    $tmb = (24.8 * $user_data['weight_kg']) + 10;
}

$get = $tmb * $activity_factor;

// Ajuste por objetivo
$calorie_adjustment = 0;
$adjustment_reason = "";

switch (strtolower($user_data['objective'])) {
    case 'lose_fat':
        if (strtolower($user_data['gender']) == 'male') {
            $calorie_adjustment = -700;
            $adjustment_reason = "Emagrecimento (Homem: -700 kcal)";
        } else {
            $calorie_adjustment = -500;
            $adjustment_reason = "Emagrecimento (Mulher: -500 kcal)";
        }
        break;
    case 'gain_muscle':
        if (strtolower($user_data['gender']) == 'male') {
            $calorie_adjustment = 500;
            $adjustment_reason = "Ganho de Massa (Homem: +500 kcal)";
        } else {
            $calorie_adjustment = 300;
            $adjustment_reason = "Ganho de Massa (Mulher: +300 kcal)";
        }
        break;
    case 'maintain':
    case 'maintain_weight':
        $calorie_adjustment = 0;
        $adjustment_reason = "Manutenção (sem ajuste)";
        break;
}

$final_calories = $get + $calorie_adjustment;
$min_calories = (strtolower($user_data['gender']) == 'male') ? 1500 : 1200;
$target_calories = max($min_calories, $final_calories);

// Verificar meta atual no banco
$stmt_goal = $conn->prepare("SELECT * FROM sf_user_goals WHERE user_id = ? AND goal_type = 'nutrition'");
$stmt_goal->bind_param("i", $user_id);
$stmt_goal->execute();
$current_goal = $stmt_goal->get_result()->fetch_assoc();
$stmt_goal->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análise de Cálculo Calórico - Usuário <?php echo $user_id; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            border-left: 5px solid #4facfe;
        }
        
        .section h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #4facfe;
        }
        
        .info-card h3 {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-card p {
            color: #7f8c8d;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .calculation-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .step-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-top: 4px solid #4facfe;
            transition: transform 0.3s ease;
        }
        
        .step-card:hover {
            transform: translateY(-5px);
        }
        
        .step-card h3 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #4facfe;
            margin: 10px 0;
        }
        
        .step-card .formula {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #2c3e50;
            margin-top: 10px;
        }
        
        .final-result {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-top: 30px;
        }
        
        .final-result h2 {
            color: white;
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .final-result .calories {
            font-size: 3rem;
            font-weight: 700;
            margin: 20px 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-correct {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .comparison-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .comparison-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .comparison-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4facfe;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .content {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .comparison {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Análise de Cálculo Calórico</h1>
            <p>Usuário: <?php echo htmlspecialchars($user_data['name']); ?> (ID: <?php echo $user_id; ?>)</p>
        </div>
        
        <div class="content">
            <!-- Dados do Usuário -->
            <div class="section">
                <h2>📊 Dados do Usuário</h2>
                <div class="user-info">
                    <div class="info-card">
                        <h3>👤 Informações Pessoais</h3>
                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($user_data['name']); ?></p>
                        <p><strong>Idade:</strong> <?php echo $age_years; ?> anos</p>
                        <p><strong>Gênero:</strong> <?php echo ucfirst($user_data['gender']); ?></p>
                    </div>
                    <div class="info-card">
                        <h3>📏 Medidas Corporais</h3>
                        <p><strong>Altura:</strong> <?php echo $user_data['height_cm']; ?> cm</p>
                        <p><strong>Peso:</strong> <?php echo $user_data['weight_kg']; ?> kg</p>
                        <p><strong>IMC:</strong> <?php echo round($imc, 1); ?> kg/m² (<?php echo $imc_category; ?>)</p>
                    </div>
                    <div class="info-card">
                        <h3>🎯 Objetivos</h3>
                        <p><strong>Objetivo:</strong> <?php echo ucfirst(str_replace('_', ' ', $user_data['objective'])); ?></p>
                        <p><strong>Frequência de Exercício:</strong> <?php echo ucfirst(str_replace('_', ' ', $user_data['exercise_frequency'])); ?></p>
                        <p><strong>Fator de Atividade:</strong> <?php echo $activity_factor; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Cálculo Passo a Passo -->
            <div class="section">
                <h2>🧮 Cálculo Passo a Passo</h2>
                <div class="calculation-steps">
                    <div class="step-card">
                        <h3>🔬 Fórmula Utilizada</h3>
                        <p><strong><?php echo $formula_used; ?></strong></p>
                        <div class="formula">
                            <?php if ($imc > 30): ?>
                                <?php if (strtolower($user_data['gender']) == 'male'): ?>
                                    TMB = (10 × peso) + (6,25 × altura) - (5 × idade) + 5
                                <?php else: ?>
                                    TMB = (10 × peso) + (6,25 × altura) - (5 × idade) - 161
                                <?php endif; ?>
                            <?php elseif (strtolower($user_data['gender']) == 'female' && $imc <= 30): ?>
                                TMB = 447,593 + (9,247 × peso) + (3,098 × altura) - (4,330 × idade)
                            <?php else: ?>
                                TMB = (24,8 × peso) + 10
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <h3>⚡ Taxa Metabólica Basal (TMB)</h3>
                        <div class="value"><?php echo round($tmb, 1); ?> kcal</div>
                        <p>Energia necessária para funções básicas do corpo</p>
                    </div>
                    
                    <div class="step-card">
                        <h3>🏃 Gasto Energético Total (GET)</h3>
                        <div class="value"><?php echo round($get, 1); ?> kcal</div>
                        <p>TMB × Fator de Atividade (<?php echo $activity_factor; ?>)</p>
                    </div>
                    
                    <div class="step-card">
                        <h3>🎯 Ajuste por Objetivo</h3>
                        <div class="value"><?php echo $calorie_adjustment; ?> kcal</div>
                        <p><?php echo $adjustment_reason; ?></p>
                    </div>
                    
                    <div class="step-card">
                        <h3>⚠️ Limite Mínimo de Segurança</h3>
                        <div class="value"><?php echo $min_calories; ?> kcal</div>
                        <p>Valor mínimo para manter saúde</p>
                    </div>
                </div>
            </div>
            
            <!-- Resultado Final -->
            <div class="final-result">
                <h2>🎯 Meta Calórica Final</h2>
                <div class="calories"><?php echo round($target_calories); ?> kcal/dia</div>
                <p>Baseado em fórmulas científicas validadas</p>
                
                <?php if ($target_calories == $min_calories): ?>
                    <span class="status-badge status-warning">⚠️ Limitado ao mínimo de segurança</span>
                <?php else: ?>
                    <span class="status-badge status-correct">✅ Cálculo dentro dos parâmetros normais</span>
                <?php endif; ?>
            </div>
            
            <!-- Comparação com Banco -->
            <?php if ($current_goal): ?>
            <div class="section">
                <h2>💾 Comparação com Banco de Dados</h2>
                <div class="comparison">
                    <div class="comparison-card">
                        <h3>📊 Meta Atual no Banco</h3>
                        <div class="value"><?php echo $current_goal['target_kcal']; ?> kcal</div>
                        <p>Valor armazenado no sistema</p>
                    </div>
                    <div class="comparison-card">
                        <h3>🧮 Cálculo Atual</h3>
                        <div class="value"><?php echo round($target_calories); ?> kcal</div>
                        <p>Valor calculado agora</p>
                    </div>
                </div>
                
                <?php if ($current_goal['target_kcal'] == round($target_calories)): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <span class="status-badge status-correct">✅ Valores coincidem perfeitamente!</span>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <span class="status-badge status-warning">⚠️ Há diferença entre os valores</span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Macros Calculados -->
            <?php 
            $macros = calculateMacronutrients($target_calories, $user_data['objective']);
            $water_goal = getWaterIntakeSuggestion($user_data['weight_kg']);
            ?>
            <div class="section">
                <h2>🥗 Distribuição de Macronutrientes</h2>
                <div class="calculation-steps">
                    <div class="step-card">
                        <h3>🥩 Proteínas</h3>
                        <div class="value"><?php echo $macros['protein_g']; ?>g</div>
                        <p>Meta diária de proteínas</p>
                    </div>
                    <div class="step-card">
                        <h3>🍞 Carboidratos</h3>
                        <div class="value"><?php echo $macros['carbs_g']; ?>g</div>
                        <p>Meta diária de carboidratos</p>
                    </div>
                    <div class="step-card">
                        <h3>🥑 Gorduras</h3>
                        <div class="value"><?php echo $macros['fat_g']; ?>g</div>
                        <p>Meta diária de gorduras</p>
                    </div>
                    <div class="step-card">
                        <h3>💧 Água</h3>
                        <div class="value"><?php echo $water_goal['total_ml']; ?>ml</div>
                        <p>Meta diária de hidratação</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
