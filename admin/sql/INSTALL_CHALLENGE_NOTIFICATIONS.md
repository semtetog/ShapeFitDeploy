# Instalação do Sistema de Notificações de Desafios

## ⚠️ IMPORTANTE
Execute os scripts SQL na seguinte ordem para criar as tabelas necessárias:

## 1. Adicionar coluna de breakdown de pontos
Execute: `add_points_breakdown_column.sql`

Este script adiciona a coluna `points_breakdown` na tabela `sf_challenge_group_daily_progress` para armazenar o detalhamento de pontos por meta.

## 2. Criar tabelas de notificações
Execute: `create_challenge_notifications.sql`

Este script cria:
- `sf_challenge_notifications` - Tabela de notificações
- `sf_challenge_user_rank_snapshot` - Tabela de snapshot de ranking

## ✅ Após executar os scripts
O sistema funcionará completamente:
- Pontos serão calculados com multiplicadores
- Notificações serão criadas automaticamente
- Dashboard de progresso funcionará
- Ranking será atualizado em tempo real

## 📝 Notas
- Se as tabelas não existirem, o sistema continuará funcionando normalmente, mas sem notificações
- Os pontos ainda serão calculados e atualizados
- O dashboard de progresso funcionará normalmente


