<?php
// Arquivo: extract_sonia_web.php
// Script para extrair dados do PDF da Sonia Tucunduva via navegador

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

$page_title = "Extrair Dados - Sonia Tucunduva";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
.extract-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.step {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.step-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--accent-orange);
    margin-bottom: 12px;
}

.upload-area {
    border: 2px dashed rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: rgba(255, 255, 255, 0.02);
    transition: all 0.3s ease;
}

.upload-area:hover {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.05);
}

.upload-area.dragover {
    border-color: var(--accent-orange);
    background: rgba(255, 107, 0, 0.1);
}

.code-block {
    background: #1a1a1a;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 12px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: #00ff00;
    overflow-x: auto;
    margin: 10px 0;
    max-height: 300px;
    overflow-y: auto;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin: 5px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
}

.btn:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.result-area {
    background: rgba(0, 255, 0, 0.05);
    border: 1px solid rgba(0, 255, 0, 0.2);
    border-radius: 8px;
    padding: 16px;
    margin: 10px 0;
    font-family: monospace;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}

.food-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 6px;
    margin: 4px 0;
    font-size: 12px;
}

.food-name {
    font-weight: 600;
    color: var(--text-primary);
    flex: 1;
}

.food-macros {
    color: var(--text-secondary);
    font-size: 11px;
}

.instructions {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

.instructions h4 {
    color: #ffc107;
    margin: 0 0 8px 0;
}
</style>

<div class="extract-container">
    <h1>📄 Extrair Dados - Sonia Tucunduva</h1>
    
    <?php if ($_POST['action'] ?? '' === 'process_manual'): ?>
        <div class="step">
            <h2 class="step-title">✅ Dados Processados</h2>
            
            <?php
            // Processar dados manuais enviados pelo usuário
            $manual_data = $_POST['manual_data'] ?? '';
            $foods = [];
            
            if (!empty($manual_data)) {
                $lines = explode("\n", $manual_data);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // Tentar diferentes formatos
                    // Formato: Nome | Calorias | Proteína | Carbs | Gordura
                    if (preg_match('/(.+?)\s*\|\s*(\d+(?:\.\d+)?)\s*\|\s*(\d+(?:\.\d+)?)\s*\|\s*(\d+(?:\.\d+)?)\s*\|\s*(\d+(?:\.\d+)?)/', $line, $matches)) {
                        $foods[] = [
                            'name' => trim($matches[1]),
                            'kcal_100g' => (float)$matches[2],
                            'protein_100g' => (float)$matches[3],
                            'carbs_100g' => (float)$matches[4],
                            'fat_100g' => (float)$matches[5],
                            'source' => 'Sonia Tucunduva'
                        ];
                    }
                    // Formato: Nome - Calorias - Proteína - Carbs - Gordura
                    elseif (preg_match('/(.+?)\s*-\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $line, $matches)) {
                        $foods[] = [
                            'name' => trim($matches[1]),
                            'kcal_100g' => (float)$matches[2],
                            'protein_100g' => (float)$matches[3],
                            'carbs_100g' => (float)$matches[4],
                            'fat_100g' => (float)$matches[5],
                            'source' => 'Sonia Tucunduva'
                        ];
                    }
                    // Formato: Nome (Calorias, Proteína, Carbs, Gordura)
                    elseif (preg_match('/(.+?)\s*\((\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?),\s*(\d+(?:\.\d+)?)\)/', $line, $matches)) {
                        $foods[] = [
                            'name' => trim($matches[1]),
                            'kcal_100g' => (float)$matches[2],
                            'protein_100g' => (float)$matches[3],
                            'carbs_100g' => (float)$matches[4],
                            'fat_100g' => (float)$matches[5],
                            'source' => 'Sonia Tucunduva'
                        ];
                    }
                }
            }
            
            echo "<div class='result-area'>";
            echo "=== DADOS EXTRAÍDOS ===\n";
            echo "Total de alimentos encontrados: " . count($foods) . "\n\n";
            
            if (!empty($foods)) {
                echo "=== ALIMENTOS ENCONTRADOS ===\n";
                foreach ($foods as $food) {
                    echo sprintf("%-30s | %4.0f kcal | P: %4.1f | C: %4.1f | G: %4.1f\n",
                        $food['name'],
                        $food['kcal_100g'],
                        $food['protein_100g'],
                        $food['carbs_100g'],
                        $food['fat_100g']
                    );
                }
                
                // Salvar dados em arquivo temporário
                $json_data = json_encode($foods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents('temp_sonia_data.json', $json_data);
                
                echo "\n=== CÓDIGO PHP GERADO ===\n";
                echo "\$sonia_data = [\n";
                foreach ($foods as $food) {
                    echo "    [\n";
                    echo "        'name' => '" . addslashes($food['name']) . "',\n";
                    echo "        'kcal_100g' => {$food['kcal_100g']},\n";
                    echo "        'protein_100g' => {$food['protein_100g']},\n";
                    echo "        'carbs_100g' => {$food['carbs_100g']},\n";
                    echo "        'fat_100g' => {$food['fat_100g']},\n";
                    echo "        'source' => 'Sonia Tucunduva'\n";
                    echo "    ],\n";
                }
                echo "];\n";
                
            } else {
                echo "❌ Nenhum alimento válido encontrado.\n";
                echo "Verifique o formato dos dados inseridos.\n";
            }
            
            echo "</div>";
            ?>
            
            <?php if (!empty($foods)): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="admin_import_sonia.php" class="btn">Importar para Banco</a>
                    <a href="extract_sonia_web.php" class="btn btn-secondary">Extrair Mais Dados</a>
                </div>
            <?php endif; ?>
        </div>
        
    <?php else: ?>
        <div class="step">
            <h2 class="step-title">📋 Como Extrair Dados do PDF</h2>
            
            <div class="instructions">
                <h4>🎯 Método Recomendado (Mais Fácil):</h4>
                <ol>
                    <li><strong>Abra o PDF da Sonia Tucunduva</strong></li>
                    <li><strong>Copie os dados nutricionais</strong> (Ctrl+C)</li>
                    <li><strong>Cole no campo abaixo</strong> (Ctrl+V)</li>
                    <li><strong>Clique em "Processar Dados"</strong></li>
                </ol>
                
                <h4>📝 Formatos Aceitos:</h4>
                <ul>
                    <li><code>Nome do Alimento | Calorias | Proteína | Carbs | Gordura</code></li>
                    <li><code>Nome do Alimento - Calorias - Proteína - Carbs - Gordura</code></li>
                    <li><code>Nome do Alimento (Calorias, Proteína, Carbs, Gordura)</code></li>
                </ul>
            </div>
        </div>
        
        <div class="step">
            <h2 class="step-title">📄 Cole os Dados do PDF</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="process_manual">
                
                <div class="upload-area">
                    <h3>📋 Cole os dados nutricionais aqui:</h3>
                    <p style="color: var(--text-secondary); margin: 16px 0;">
                        Copie e cole diretamente do PDF da Sonia Tucunduva
                    </p>
                    
                    <textarea 
                        name="manual_data" 
                        style="width: 100%; height: 300px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; padding: 12px; color: var(--text-primary); font-family: monospace; font-size: 12px;"
                        placeholder="Exemplo:
Bacon cozido | 541 | 37.04 | 1.43 | 41.78
Carne de porco assada | 297 | 26.51 | 0 | 20.47
Linguiça de porco | 301 | 12.93 | 2.06 | 25.93

Ou qualquer formato similar que você copiar do PDF..."
                    ></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn">Processar Dados</button>
                </div>
            </form>
        </div>
        
        <div class="step">
            <h2 class="step-title">💡 Dicas para Extração</h2>
            
            <div class="instructions">
                <h4>🔍 O que procurar no PDF:</h4>
                <ul>
                    <li>Tabelas com dados nutricionais</li>
                    <li>Colunas: Nome, Calorias, Proteínas, Carboidratos, Gorduras</li>
                    <li>Alimentos cozidos, assados, processados</li>
                    <li>Valores por 100g</li>
                </ul>
                
                <h4>⚡ Processo Rápido:</h4>
                <ol>
                    <li>Selecione toda a tabela no PDF (Ctrl+A)</li>
                    <li>Copie (Ctrl+C)</li>
                    <li>Cole aqui (Ctrl+V)</li>
                    <li>O sistema vai interpretar automaticamente</li>
                </ol>
                
                <h4>🎯 Exemplo de Dados:</h4>
                <div class="code-block">
Bacon cozido           | 541 | 37.04 | 1.43 | 41.78
Carne de porco assada  | 297 | 26.51 | 0    | 20.47
Carne de porco cozida  | 297 | 26.51 | 0    | 20.47
Linguiça de porco      | 301 | 12.93 | 2.06 | 25.93
Salsicha de porco      | 301 | 12.93 | 2.06 | 25.93
                </div>
            </div>
        </div>
        
        <div class="step">
            <h2 class="step-title">🚀 Alternativas</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 8px;">
                    <h4>📱 Ferramentas Online:</h4>
                    <ul>
                        <li><a href="https://www.ilovepdf.com/pt/pdf_to_excel" target="_blank">PDF to Excel</a></li>
                        <li><a href="https://www.sodapdf.com/pdf-to-excel/" target="_blank">SodaPDF</a></li>
                        <li><a href="https://smallpdf.com/pdf-to-excel" target="_blank">SmallPDF</a></li>
                    </ul>
                </div>
                
                <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 8px;">
                    <h4>💻 Ferramentas Desktop:</h4>
                    <ul>
                        <li>Adobe Acrobat</li>
                        <li>LibreOffice Draw</li>
                        <li>Microsoft Excel (abrir PDF)</li>
                    </ul>
                </div>
                
                <div style="background: rgba(255, 255, 255, 0.02); padding: 16px; border-radius: 8px;">
                    <h4>🔧 Python (Avançado):</h4>
                    <ul>
                        <li>tabula-py</li>
                        <li>camelot-py</li>
                        <li>PyPDF2</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="admin_check_foods.php" class="btn btn-secondary">Ver Status Atual</a>
            <a href="add_food_to_diary.php" class="btn btn-secondary">Testar Busca</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Melhorar a experiência de colagem
document.querySelector('textarea[name="manual_data"]').addEventListener('paste', function(e) {
    setTimeout(() => {
        // Auto-formatação básica após colar
        let text = this.value;
        // Remover linhas vazias excessivas
        text = text.replace(/\n\s*\n\s*\n/g, '\n\n');
        // Limpar espaços extras
        text = text.replace(/\s+/g, ' ');
        this.value = text;
    }, 100);
});
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>





