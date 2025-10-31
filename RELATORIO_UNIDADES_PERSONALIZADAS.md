# 📊 RELATÓRIO: UNIDADES PERSONALIZADAS vs AUTOMÁTICAS

## 🎯 RESUMO DO SEU BANCO DE DADOS

### ✅ **CONFIRMAÇÃO:**

Baseado na análise do seu banco (`teste1.sql`), encontrei:

---

## 📈 **1. ALIMENTOS CLASSIFICADOS**

- **Total:** 990 classificações na tabela `sf_food_categories`
- **Alimentos únicos classificados:** Mais de **400+ alimentos**

---

## 🔧 **2. ALIMENTOS COM UNIDADES PERSONALIZADAS** 

### ✅ **Personalizados (1-2 unidades apenas):**

Estes são os alimentos onde o cliente **personalizou** e deixou apenas **1 ou 2 unidades específicas**:

#### **Exemplos encontrados no banco:**

**Com 1 unidade (só "g"):**
```sql
(8052, 1934, 8, 1.0000, 1)  -- food_id 1934: só "g"
(8053, 2371, 8, 1.0000, 1)  -- food_id 2371: só "g"
(8054, 1994, 8, 1.0000, 1)  -- food_id 1994: só "g"
(8055, 1985, 8, 1.0000, 1)  -- food_id 1985: só "g"
(8056, 2388, 8, 1.0000, 1)  -- food_id 2388: só "g"
... E MUITOS OUTROS
```

**Com 2 unidades (ex: "g" + "kg"):**
```sql
(10515, 660, 8, 1.0000, 1)  -- food_id 660: "g"
(10516, 660, 7, 1000.0000, 0) -- food_id 660: "kg"
-- Total: 2 unidades personalizadas
```

**Timestamps de personalização:**
- `00:19:42` até `00:46:25` → Personalizações feitas pelo cliente
- Diferente das automáticas que são todas `00:25:24` ou `00:25:32`

---

## ⚙️ **3. ALIMENTOS COM UNIDADES AUTOMÁTICAS**

### ❌ **Automáticos (5 unidades - não personalizados):**

Estes são alimentos classificados mas que **ainda não foram personalizados** - têm todas as 5 unidades padrão:

```sql
-- food_id 3: TODAS as 5 unidades (automático)
(8707, 3, 8, 1.0000, 1)     -- g
(8708, 3, 7, 1000.0000, 0)  -- kg
(8709, 3, 1, 15.0000, 0)    -- cs
(8710, 3, 2, 5.0000, 0)     -- cc
(8711, 3, 3, 240.0000, 0)   -- xc

-- food_id 4: TODAS as 5 unidades (automático)
(8712, 4, 8, 1.0000, 1)     -- g
(8713, 4, 7, 1000.0000, 0)  -- kg
(8714, 4, 1, 15.0000, 0)    -- cs
(8715, 4, 2, 5.0000, 0)     -- cc
(8716, 4, 3, 240.0000, 0)   -- xc
```

**Padrão identificado:**
- ✅ Todos têm unit_id: 8, 7, 1, 2, 3 (g, kg, cs, cc, xc)
- ✅ Timestamps: `00:25:24` ou `00:25:32` (aplicação automática em lote)

---

## 📋 **COMO IDENTIFICAR NO BANCO:**

### SQL para contar personalizados vs automáticos:

```sql
-- ALIMENTOS COM UNIDADES PERSONALIZADAS (1-2 unidades)
SELECT 
    food_item_id,
    COUNT(*) as total_unidades,
    GROUP_CONCAT(unit_id ORDER BY unit_id) as unidades_ids
FROM sf_food_item_conversions
GROUP BY food_item_id
HAVING COUNT(*) <= 2
ORDER BY COUNT(*) ASC, food_item_id;

-- ALIMENTOS COM UNIDADES AUTOMÁTICAS (5 unidades)
SELECT 
    food_item_id,
    COUNT(*) as total_unidades,
    GROUP_CONCAT(unit_id ORDER BY unit_id) as unidades_ids
FROM sf_food_item_conversions
GROUP BY food_item_id
HAVING COUNT(*) = 5
ORDER BY food_item_id;
```

### SQL para ver distribuição completa:

```sql
SELECT 
    COUNT(DISTINCT food_item_id) as total_alimentos,
    CASE 
        WHEN COUNT(*) = 1 THEN '1 unidade (personalizado)'
        WHEN COUNT(*) = 2 THEN '2 unidades (personalizado)'
        WHEN COUNT(*) = 3 THEN '3 unidades'
        WHEN COUNT(*) = 4 THEN '4 unidades'
        WHEN COUNT(*) = 5 THEN '5 unidades (automático)'
        ELSE CONCAT(COUNT(*), ' unidades')
    END as tipo,
    COUNT(*) as quantidade_alimentos
FROM sf_food_item_conversions
GROUP BY food_item_id
ORDER BY COUNT(*);
```

---

## ✅ **CONCLUSÃO:**

**No seu banco encontrei:**

1. ✅ **Muitos alimentos com 1 unidade** (personalizados - só "g")
   - Exemplos: food_id 1934, 2371, 1994, 1985, 2388, 2370, 2329, etc.
   - Timestamps variados (personalizações feitas manualmente)

2. ✅ **Alguns com 2 unidades** (personalizados - ex: "g" + "kg")
   - Exemplo: food_id 660

3. ⚙️ **Muitos com 5 unidades** (automáticos - ainda não personalizados)
   - Exemplos: food_id 3, 4, 5, 6, 685, 690, 708, 713, 738, 747, 756, 799
   - Timestamps em lote (`00:25:24`, `00:25:32`)

---

## 🎯 **RESPOSTA DIRETA:**

**SIM, as unidades personalizadas estão salvas!**

- ✅ Alimentos com **1 unidade** = Personalizados pelo cliente
- ✅ Alimentos com **2 unidades** = Personalizados pelo cliente  
- ⚙️ Alimentos com **5 unidades** = Ainda automáticos (não personalizados)

**O trabalho de personalização do cliente está salvo e preservado!** 🎉

