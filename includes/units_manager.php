<?php
// includes/units_manager.php - Gerenciador de Unidades de Medida

class UnitsManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Busca todas as unidades de medida ativas
     */
    public function getAllUnits() {
        $sql = "SELECT * FROM sf_measurement_units WHERE is_active = TRUE ORDER BY category, name";
        $result = $this->conn->query($sql);
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        return $units;
    }
    
    /**
     * Busca unidades por categoria
     */
    public function getUnitsByCategory($category) {
        $sql = "SELECT * FROM sf_measurement_units WHERE category = ? AND is_active = TRUE ORDER BY name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        return $units;
    }
    
    /**
     * Busca unidades específicas para um alimento
     */
    public function getFoodUnits($food_id) {
        $sql = "
            SELECT mu.*, fu.conversion_factor as food_conversion_factor, fu.is_default
            FROM sf_food_units fu
            JOIN sf_measurement_units mu ON fu.unit_id = mu.id
            WHERE fu.food_id = ? AND mu.is_active = TRUE
            ORDER BY fu.is_default DESC, mu.name
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        return $units;
    }
    
    /**
     * Converte quantidade para gramas/ml baseado na unidade
     */
    public function convertToBaseUnit($quantity, $unit_id, $food_id = null) {
        // Se for um alimento específico, usar conversão personalizada
        if ($food_id) {
            $sql = "
                SELECT 
                    sfic.conversion_factor as food_factor
                FROM sf_food_item_conversions sfic
                WHERE sfic.food_item_id = ? AND sfic.unit_id = ?
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $food_id, $unit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Usar o fator de conversão específico do alimento
                return $quantity * $row['food_factor'];
            }
        }
        
        // Usar conversão padrão da unidade universal
        $sql = "SELECT conversion_factor, conversion_unit FROM sf_measurement_units WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $quantity * $row['conversion_factor'];
        }
        
        return $quantity; // Fallback
    }
    
    /**
     * Calcula valores nutricionais baseado na quantidade convertida
     */
    public function calculateNutrition($food_id, $quantity_in_base_unit, $nutrition_per_100g) {
        $factor = $quantity_in_base_unit / 100; // Fator baseado em 100g
        
        return [
            'kcal' => $nutrition_per_100g['kcal'] * $factor,
            'protein' => $nutrition_per_100g['protein'] * $factor,
            'carbs' => $nutrition_per_100g['carbs'] * $factor,
            'fat' => $nutrition_per_100g['fat'] * $factor
        ];
    }
    
    /**
     * Adiciona uma nova unidade de medida
     */
    public function addUnit($name, $abbreviation, $category, $conversion_factor, $conversion_unit = 'g') {
        $sql = "INSERT INTO sf_measurement_units (name, abbreviation, category, conversion_factor, conversion_unit) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssds", $name, $abbreviation, $category, $conversion_factor, $conversion_unit);
        
        return $stmt->execute();
    }
    
    
    /**
     * Busca unidades com sugestões inteligentes baseadas no nome do alimento
     */
    public function getSuggestedUnits($food_name) {
        $suggestions = [];
        
        // Lógica para sugerir unidades baseada no tipo de alimento
        $food_lower = strtolower($food_name);
        
        if (strpos($food_lower, 'arroz') !== false || strpos($food_lower, 'feijão') !== false) {
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs'];
            $suggestions[] = ['name' => 'Xícara', 'abbreviation' => 'xc'];
        }
        
        if (strpos($food_lower, 'óleo') !== false || strpos($food_lower, 'azeite') !== false) {
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs'];
            $suggestions[] = ['name' => 'Colher de chá', 'abbreviation' => 'cc'];
        }
        
        if (strpos($food_lower, 'pão') !== false) {
            $suggestions[] = ['name' => 'Fatia', 'abbreviation' => 'fat'];
            $suggestions[] = ['name' => 'Unidade', 'abbreviation' => 'un'];
        }
        
        if (strpos($food_lower, 'fruta') !== false || strpos($food_lower, 'maçã') !== false || strpos($food_lower, 'banana') !== false) {
            $suggestions[] = ['name' => 'Unidade', 'abbreviation' => 'un'];
            $suggestions[] = ['name' => 'Fatia', 'abbreviation' => 'fat'];
        }
        
        // Adicionar unidades padrão se não houver sugestões específicas
        if (empty($suggestions)) {
            $suggestions[] = ['name' => 'Gramas', 'abbreviation' => 'g'];
            $suggestions[] = ['name' => 'Unidade', 'abbreviation' => 'un'];
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs'];
        }
        
        return $suggestions;
    }
    
    /**
     * Adiciona conversão específica para um alimento
     */
    public function addFoodUnit($food_id, $unit_id, $conversion_factor, $conversion_unit, $is_default = false) {
        $sql = "INSERT INTO sf_food_units (food_id, unit_id, conversion_factor, conversion_unit, is_default) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                conversion_factor = VALUES(conversion_factor),
                conversion_unit = VALUES(conversion_unit),
                is_default = VALUES(is_default)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iidsi", $food_id, $unit_id, $conversion_factor, $conversion_unit, $is_default);
        return $stmt->execute();
    }
    
    /**
     * Remove conversão específica de um alimento
     */
    public function removeFoodUnit($food_id, $unit_id) {
        $sql = "DELETE FROM sf_food_units WHERE food_id = ? AND unit_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $food_id, $unit_id);
        return $stmt->execute();
    }
    
    /**
     * Define unidade padrão para um alimento
     */
    public function setDefaultUnit($food_id, $unit_id) {
        // Primeiro, remover padrão de todas as unidades deste alimento
        $sql = "UPDATE sf_food_units SET is_default = FALSE WHERE food_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        
        // Depois, definir a nova unidade como padrão
        $sql = "UPDATE sf_food_units SET is_default = TRUE WHERE food_id = ? AND unit_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $food_id, $unit_id);
        return $stmt->execute();
    }
    
    /**
     * Busca unidade por abreviação
     */
    public function getUnitByAbbreviation($abbreviation) {
        $sql = "SELECT * FROM sf_measurement_units WHERE abbreviation = ? AND is_active = TRUE LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $abbreviation);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Obtém fator de conversão de uma unidade
     */
    public function getConversionFactor($abbreviation) {
        $unit = $this->getUnitByAbbreviation($abbreviation);
        return $unit ? $unit['conversion_factor'] : 1.0;
    }
    
    /**
     * Busca unidades para um alimento específico
     */
    public function getUnitsForFood($food_id) {
        // Debug logs
        error_log("🔍 UNITS MANAGER - getUnitsForFood chamada com food_id: " . $food_id);
        error_log("🔍 UNITS MANAGER - Tipo do food_id: " . gettype($food_id));
        
        $sql = "
            SELECT 
                smu.id,
                smu.name,
                smu.abbreviation,
                sfic.conversion_factor,
                sfic.is_default
            FROM sf_food_item_conversions sfic
            JOIN sf_measurement_units smu ON sfic.unit_id = smu.id
            WHERE sfic.food_item_id = ? AND smu.is_active = TRUE
            ORDER BY sfic.is_default DESC, smu.name
        ";
        
        error_log("🔍 UNITS MANAGER - SQL: " . $sql);
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("❌ UNITS MANAGER - Erro ao preparar statement: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        
        if ($stmt->error) {
            error_log("❌ UNITS MANAGER - Erro na execução: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        error_log("🔍 UNITS MANAGER - Unidades encontradas: " . count($units));
        error_log("🔍 UNITS MANAGER - Dados: " . json_encode($units));
        
        return $units;
    }
    
    /**
     * Busca as unidades padrão (g, ml, un) como fallback.
     */
    public function getDefaultUnits() {
        error_log("🔍 UNITS MANAGER - Buscando unidades padrão (fallback)");
        
        $default_abbreviations = ['g', 'ml', 'un'];
        $placeholders = implode(',', array_fill(0, count($default_abbreviations), '?'));
        
        $sql = "
            SELECT 
                id,
                name,
                abbreviation,
                1.0 AS conversion_factor, /* Fator de conversão padrão */
                CASE 
                    WHEN abbreviation = 'g' THEN 1
                    ELSE 0
                END AS is_default
            FROM sf_measurement_units 
            WHERE abbreviation IN ($placeholders) AND is_active = TRUE
            ORDER BY FIELD(abbreviation, 'g', 'un', 'ml')
        ";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("❌ UNITS MANAGER - Erro ao preparar statement para unidades padrão: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param(str_repeat('s', count($default_abbreviations)), ...$default_abbreviations);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $units = [];
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        
        error_log("🔍 UNITS MANAGER - Unidades padrão encontradas: " . json_encode($units));
        
        return $units;
    }
    
    /**
     * Valida se uma conversão de unidade é realista
     */
    public function validateUnitConversion($unit_name, $conversion_factor) {
        $unit_lower = strtolower($unit_name);
        
        // Validações específicas por tipo de unidade
        if ($unit_lower === 'unidade' || $unit_lower === 'un') {
            // Unidades devem ter conversão entre 1g e 2000g (1g pode ser válido para gramas)
            return $conversion_factor >= 1 && $conversion_factor <= 2000;
        }
        
        if ($unit_lower === 'grama' || $unit_lower === 'g') {
            // Gramas sempre são 1g
            return $conversion_factor == 1;
        }
        
        if (strpos($unit_lower, 'fatia') !== false || strpos($unit_lower, 'fat') !== false) {
            // Fatias devem ter conversão entre 1g e 100g
            return $conversion_factor >= 1 && $conversion_factor <= 100;
        }
        
        if (strpos($unit_lower, 'colher') !== false) {
            // Colheres devem ter conversão entre 1g e 50g
            return $conversion_factor >= 1 && $conversion_factor <= 50;
        }
        
        if (strpos($unit_lower, 'xícara') !== false || strpos($unit_lower, 'copo') !== false) {
            // Xícaras devem ter conversão entre 50g e 500g
            return $conversion_factor >= 50 && $conversion_factor <= 500;
        }
        
        if ($unit_lower === 'mililitro' || $unit_lower === 'ml') {
            // Mililitros sempre são 1g (para líquidos)
            return $conversion_factor == 1;
        }
        
        if ($unit_lower === 'litro' || $unit_lower === 'l') {
            // Litros sempre são 1000g
            return $conversion_factor == 1000;
        }
        
        if ($unit_lower === 'quilograma' || $unit_lower === 'kg') {
            // Quilogramas sempre são 1000g
            return $conversion_factor == 1000;
        }
        
        // Validação geral: entre 0.1g e 5000g
        return $conversion_factor >= 0.1 && $conversion_factor <= 5000;
    }
    
    /**
     * Sugere conversões baseadas no tipo de alimento
     */
    public function suggestUnitConversions($food_name, $categories) {
        $suggestions = [];
        $food_lower = strtolower($food_name);
        
        // Sugestões baseadas no nome do alimento
        if (strpos($food_lower, 'fruta') !== false || strpos($food_lower, 'maçã') !== false || 
            strpos($food_lower, 'banana') !== false || strpos($food_lower, 'laranja') !== false) {
            $suggestions[] = ['name' => 'Unidade', 'abbreviation' => 'un', 'conversion_factor' => 150];
            $suggestions[] = ['name' => 'Fatia', 'abbreviation' => 'fat', 'conversion_factor' => 25];
        }
        
        if (strpos($food_lower, 'pão') !== false) {
            $suggestions[] = ['name' => 'Fatia', 'abbreviation' => 'fat', 'conversion_factor' => 30];
            $suggestions[] = ['name' => 'Unidade', 'abbreviation' => 'un', 'conversion_factor' => 30];
        }
        
        if (strpos($food_lower, 'arroz') !== false || strpos($food_lower, 'feijão') !== false) {
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs', 'conversion_factor' => 15];
            $suggestions[] = ['name' => 'Xícara', 'abbreviation' => 'xc', 'conversion_factor' => 150];
        }
        
        if (strpos($food_lower, 'óleo') !== false || strpos($food_lower, 'azeite') !== false) {
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs', 'conversion_factor' => 15];
            $suggestions[] = ['name' => 'Colher de chá', 'abbreviation' => 'cc', 'conversion_factor' => 5];
        }
        
        // Sugestões baseadas nas categorias
        if (in_array('líquido', $categories)) {
            $suggestions[] = ['name' => 'Mililitro', 'abbreviation' => 'ml', 'conversion_factor' => 1];
            $suggestions[] = ['name' => 'Litro', 'abbreviation' => 'l', 'conversion_factor' => 1000];
        }
        
        if (in_array('granular', $categories)) {
            $suggestions[] = ['name' => 'Grama', 'abbreviation' => 'g', 'conversion_factor' => 1];
            $suggestions[] = ['name' => 'Colher de sopa', 'abbreviation' => 'cs', 'conversion_factor' => 15];
        }
        
        return $suggestions;
    }
}
?>
