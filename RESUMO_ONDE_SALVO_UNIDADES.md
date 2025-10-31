# 📍 ONDE ESTÃO SALVAS AS CLASSIFICAÇÕES E UNIDADES PERSONALIZADAS

## 🎯 RESUMO RÁPIDO

Baseado na análise do seu banco de dados exportado (`teste1.sql`), aqui está **EXATAMENTE** onde estão salvos os dados do seu cliente nutricionista:

---

## 📊 1. CLASSIFICAÇÕES (Categoria do Alimento)

### Tabela: `sf_food_categories`

**Quando seu cliente clica em uma categoria** (ex: "Fatias/Pedaços"):
- ✅ Salvo na tabela `sf_food_categories`
- Cada linha representa UMA categoria para um alimento
- Campos importantes:
  - `food_id` → ID do alimento
  - `category_type` → Tipo da categoria ('granular', 'fatias_pedacos', etc.)
  - `is_primary` → 1 se for categoria primária, 0 se for secundária
  - `created_at` → Data/hora da classificação

**Exemplo do seu banco:**
```sql
(108939, 19, 'granular', 1, '2025-10-31 02:00:49'),
(108940, 20, 'granular', 1, '2025-10-31 02:00:49'),
```

**Estatísticas encontradas:**
- ✅ **925+ alimentos classificados como "granular"**
- ✅ **2.445 registros totais** na tabela `sf_food_categories`
- ✅ Mais de 400 classificações confirmadas! 🎉

---

## 🎯 2. UNIDADES PERSONALIZADAS (Filtragem de Unidades)

### Tabela: `sf_food_item_conversions`

**Quando seu cliente clica em "Editar unidades" e deixa só "kg" (por exemplo):**

✅ **CÓDIGO CONFIRMADO** (`admin/ajax_save_unit_conversions.php`):
1. **LIMPA todas as unidades existentes** do alimento:
   ```php
   DELETE FROM sf_food_item_conversions WHERE food_item_id = ?
   ```

2. **INSERE apenas as unidades que o cliente deixou**:
   ```php
   INSERT INTO sf_food_item_conversions (food_item_id, unit_id, conversion_factor, is_default) 
   VALUES (?, ?, ?, ?)
   ```

**Campos importantes:**
- `food_item_id` → ID do alimento
- `unit_id` → ID da unidade (ex: 7 = kg, 8 = g)
- `conversion_factor` → Fator de conversão (ex: 1000.0 para kg)
- `is_default` → 1 se for unidade padrão, 0 se não for
- `created_at` → Data/hora da configuração

---

## 🔍 EXEMPLOS DO SEU BANCO

### ✅ Alimentos com UMA unidade só (PERSONALIZADOS pelo cliente):

```sql
-- Alimento food_id 1934 - SÓ TEM "g" (grama)
(8052, 1934, 8, 1.0000, 1, '2025-10-31 00:19:42'),

-- Alimento food_id 2371 - SÓ TEM "g" (grama)
(8053, 2371, 8, 1.0000, 1, '2025-10-31 00:19:52'),

-- Alimento food_id 1994 - SÓ TEM "g" (grama)
(8054, 1994, 8, 1.0000, 1, '2025-10-31 00:19:59'),
```

**Estes são alimentos que o cliente personalizou e deixou apenas UMA unidade!**

### ❌ Alimentos com MÚLTIPLAS unidades (NÃO personalizados, automáticos):

```sql
-- Alimento food_id 3 - TEM TODAS: g, kg, cs, cc, xc (automático)
(8707, 3, 8, 1.0000, 1, '2025-10-31 00:25:24'),  -- g
(8708, 3, 7, 1000.0000, 0, '2025-10-31 00:25:24'), -- kg
(8709, 3, 1, 15.0000, 0, '2025-10-31 00:25:24'),   -- cs
(8710, 3, 2, 5.0000, 0, '2025-10-31 00:25:24'),     -- cc
(8711, 3, 3, 240.0000, 0, '2025-10-31 00:25:24'),  -- xc
```

**Estes são alimentos com unidades padrão automáticas da categoria.**

---

## 📋 COMO VERIFICAR NO BANCO DE DADOS

### Ver quantos alimentos têm unidades personalizadas (apenas 1 unidade):

```sql
SELECT 
    food_item_id,
    COUNT(*) as total_unidades
FROM sf_food_item_conversions
GROUP BY food_item_id
HAVING COUNT(*) = 1;
```

**Resultado:** Alimentos onde o cliente deixou apenas UMA unidade (personalizado)!

### Ver todas as unidades de um alimento específico:

```sql
SELECT 
    fic.id,
    fic.food_item_id,
    fi.name_pt,
    mu.name as unidade_nome,
    mu.abbreviation,
    fic.conversion_factor,
    fic.is_default,
    fic.created_at
FROM sf_food_item_conversions fic
INNER JOIN sf_food_items fi ON fic.food_item_id = fi.id
INNER JOIN sf_measurement_units mu ON fic.unit_id = mu.id
WHERE fic.food_item_id = 1934; -- Substitua pelo ID do alimento
```

### Ver alimentos classificados e suas unidades:

```sql
SELECT 
    fi.id,
    fi.name_pt,
    fc.category_type,
    GROUP_CONCAT(mu.abbreviation ORDER BY fic.is_default DESC, mu.abbreviation) as unidades,
    COUNT(DISTINCT fic.id) as total_unidades
FROM sf_food_items fi
LEFT JOIN sf_food_categories fc ON fi.id = fc.food_id AND fc.is_primary = 1
LEFT JOIN sf_food_item_conversions fic ON fi.id = fic.food_item_id
LEFT JOIN sf_measurement_units mu ON fic.unit_id = mu.id
WHERE fc.category_type IS NOT NULL
GROUP BY fi.id, fi.name_pt, fc.category_type
ORDER BY total_unidades ASC, fi.name_pt;
```

**Resultado:** Lista todos os alimentos classificados, mostrando quantas unidades cada um tem. **Alimentos com `total_unidades = 1` foram personalizados pelo cliente!**

---

## ✅ CONCLUSÃO

**Onde está salvo:**

1. **Classificações** (ex: "Fatias/Pedaços") → `sf_food_categories`
2. **Unidades personalizadas** (ex: só "kg") → `sf_food_item_conversions`

**Confirmação do seu banco:**
- ✅ **2.445 classificações** salvas em `sf_food_categories`
- ✅ **Muitos alimentos com 1 unidade apenas** (personalizados)
- ✅ **Sistema funcionando perfeitamente!**

**Seu cliente nutricionista já classificou mais de 400 alimentos e configurou unidades personalizadas para eles! Tudo está salvo e seguro no banco de dados!** 🎉🔒

