#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import re
from collections import defaultdict

# Ler o arquivo SQL
with open('teste1.sql', 'r', encoding='utf-8') as f:
    content = f.read()

# Extrair todas as entradas de sf_food_item_conversions
pattern = r'\((\d+),\s*(\d+),\s*(\d+),\s*[\d.]+,\s*[01],'
matches = re.findall(pattern, content)

# Agrupar por food_item_id
food_units = defaultdict(list)
for match in matches:
    id_registro, food_item_id, unit_id = match
    food_units[int(food_item_id)].append(int(unit_id))

# Contar quantos alimentos têm cada quantidade de unidades
count_by_units = defaultdict(int)
for food_id, units in food_units.items():
    count = len(set(units))  # Remove duplicatas se houver
    count_by_units[count] += 1

# Imprimir resultados
print("=" * 60)
print("CONTAGEM DE ALIMENTOS POR QUANTIDADE DE UNIDADES")
print("=" * 60)
print()
print(f"Total de alimentos com unidades configuradas: {len(food_units)}")
print()
print("Distribuição:")
print("-" * 60)
for num_units in sorted(count_by_units.keys()):
    count = count_by_units[num_units]
    percentage = (count / len(food_units)) * 100
    if num_units == 1:
        print(f"  {num_units} unidade  (personalizado): {count:4d} alimentos ({percentage:5.1f}%)")
    elif num_units == 2:
        print(f"  {num_units} unidades (personalizado): {count:4d} alimentos ({percentage:5.1f}%)")
    elif num_units == 5:
        print(f"  {num_units} unidades (automático):    {count:4d} alimentos ({percentage:5.1f}%)")
    else:
        print(f"  {num_units} unidades:                  {count:4d} alimentos ({percentage:5.1f}%)")
print("-" * 60)
print()

# Detalhes adicionais
print("DETALHES:")
print("-" * 60)
total_personalizados = count_by_units.get(1, 0) + count_by_units.get(2, 0)
total_automaticos = count_by_units.get(5, 0)
print(f"Total personalizados (1-2 unidades): {total_personalizados}")
print(f"Total automáticos (5 unidades):       {total_automaticos}")
print(f"Outros (3-4 unidades):                {sum(count_by_units[k] for k in [3, 4])}")

