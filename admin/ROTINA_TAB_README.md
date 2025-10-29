# Aba Rotina - Painel Administrativo ShapeFit

## Visão Geral

A aba "Rotina" foi completamente refatorada para permitir que nutricionistas visualizem e gerenciem o comportamento diário dos pacientes, incluindo missões, atividades físicas e sono.

## Características Principais

### 1. Card de Resumo da Rotina
- **Missões concluídas**: Porcentagem de adesão semanal com ícone check-circle
- **Tempo médio de sono**: Média das últimas 7 noites com ícone moon
- **Dias com treino**: Contagem de dias com treino registrado com ícone dumbbell
- Estilo visual: três mini cards dentro do card principal, seguindo o padrão dos cards "macro" da aba Nutrientes

### 2. Calendário de Rotina
- Interface idêntica ao calendário da aba "Diário"
- Dias com registros destacados em laranja
- Dias sem registros em cinza translúcido
- Dia atual com destaque especial
- Seleção de dia atualiza os detalhes abaixo

### 3. Rotina do Dia Selecionado

#### 3.1. Missões Diárias
- Lista todas as missões disponíveis para o usuário
- Cada missão mostra:
  - Ícone vetorial (SVG)
  - Nome da missão
  - Status: concluída (✓ verde) ou pendente (✕ cinza)
  - Duração (quando aplicável)
- Barra de progresso no topo do card

#### 3.2. Atividades Físicas
- Exibe treinos realizados no dia selecionado
- Informações por atividade:
  - Ícone do tipo de treino
  - Nome da atividade
  - Duração (ex: 42min, 1h05)

#### 3.3. Sono
- Tempo total de sono registrado
- Comparação com meta (8h padrão)
- Barra de progresso visual
- Indicação se está dentro/abaixo da meta

### 4. Gerenciador de Missões (CRUD)

Card administrativo fixo na parte inferior da aba que permite:
- **Adicionar** novas missões personalizadas
- **Editar** missões existentes
- **Excluir** missões (soft delete)

#### Campos das Missões
- **Nome**: Nome descritivo da missão
- **Tipo**: 
  - Binária (Sim/Não) - para missões simples
  - Com Duração - para missões que requerem tempo (treino, sono, etc.)
- **Ícone**: Seletor de ícone vetorial

## Instalação

### 1. Executar Script SQL

Execute o arquivo SQL para criar a tabela de missões:

```bash
mysql -u seu_usuario -p seu_banco < admin/migrations/create_routine_missions_table.sql
```

Ou execute diretamente no phpMyAdmin/MySQL Workbench.

### 2. Verificar Estrutura de Diretórios

Certifique-se de que a estrutura está correta:

```
admin/
├── view_user.php                    # Arquivo principal (atualizado)
├── api/
│   └── routine_missions_crud.php    # Backend CRUD (novo)
└── migrations/
    └── create_routine_missions_table.sql  # Script SQL (novo)
```

### 3. Configurar Permissões

O arquivo `routine_missions_crud.php` verifica se o usuário é admin:

```php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Acesso negado
}
```

Certifique-se de que a sessão está configurada corretamente.

## Estrutura do Banco de Dados

### Tabela: `sf_routine_missions`

```sql
CREATE TABLE `sf_routine_missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_name` varchar(50) DEFAULT 'clock',
  `mission_type` enum('binary','duration') DEFAULT 'binary',
  `default_duration_minutes` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Missões Padrão Incluídas

O script SQL inclui 7 missões pré-configuradas:
1. Tomar água suficiente
2. Consumir proteína adequada
3. Treinar (com duração)
4. Dormir bem (com duração)
5. Meditar (com duração)
6. Tomar sol (com duração)
7. Fazer alongamento (com duração)

### Tabelas Relacionadas

- `sf_user_routine_log`: Armazena os registros diários de missões dos usuários
- `sf_user_daily_tracking`: Dados gerais de rastreamento diário (passos, sono, treinos)
- `sf_user_exercise_durations`: Durações específicas de exercícios

## API Endpoints

### Base URL
```
admin/api/routine_missions_crud.php
```

### Listar Missões
```
GET ?action=list
```

Resposta:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Tomar água suficiente",
      "description": "Beber pelo menos 2 litros de água",
      "icon_name": "water-drop",
      "mission_type": "binary",
      "default_duration_minutes": null,
      "is_active": 1
    }
  ]
}
```

### Obter Missão Específica
```
GET ?action=get&id=1
```

### Criar Missão
```
POST ?action=create
Content-Type: application/json

{
  "name": "Nova Missão",
  "description": "Descrição opcional",
  "icon_name": "clock",
  "mission_type": "binary"
}
```

### Atualizar Missão
```
POST ?action=update
Content-Type: application/json

{
  "id": 1,
  "name": "Missão Atualizada",
  "mission_type": "duration",
  "default_duration_minutes": 30
}
```

### Excluir Missão
```
POST ?action=delete
Content-Type: application/json

{
  "id": 1
}
```

## Design e Estilo

### Paleta de Cores
- **Fundo**: `#101010` / `#1C1C1C`
- **Destaque**: `#ff6f00` (laranja ShapeFit)
- **Texto primário**: Branco forte
- **Texto secundário**: Cinza claro
- **Bordas**: `var(--border-color)`

### Ícones
Todos os ícones são SVG vetoriais, seguindo o padrão do painel.

### Responsividade
- Grid do calendário adapta-se a telas menores
- Cards empilham-se verticalmente em dispositivos móveis
- Tabela de missões mantém legibilidade em todas as resoluções

## Funcionalidades JavaScript

### Calendário
- `initRoutineCalendar()`: Inicializa o calendário do mês atual
- `showRoutineDayDetails(date)`: Exibe detalhes do dia selecionado

### Dados do Dia
- `updateMissionsList(routines)`: Atualiza lista de missões com status
- `updateActivitiesList(exercises)`: Renderiza atividades físicas
- `updateSleepInfo(sleepData)`: Exibe informações de sono

### CRUD de Missões
- `loadMissionsAdminList()`: Carrega lista de missões via AJAX
- `renderMissionsTable(missions)`: Renderiza tabela administrativa
- `editMission(id)`: Abre modal de edição
- `deleteMission(id, name)`: Exclui missão com confirmação

## Compatibilidade

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Navegadores modernos (Chrome, Firefox, Safari, Edge)

## Manutenção

### Adicionar Novos Tipos de Ícones

Edite o campo `icon_name` no formulário de missão em `view_user.php`.

### Personalizar Meta de Sono

Atualmente definida em 8h. Para alterar, modifique a constante em `updateSleepInfo()`:

```javascript
const sleepGoal = 8; // Alterar aqui
```

### Adicionar Validações

Para adicionar validações extras, edite `routine_missions_crud.php` nas funções `createMission()` e `updateMission()`.

## Resolução de Problemas

### Erro 403 ao acessar API
- Verifique se o usuário está logado e tem role 'admin'
- Confirme que a sessão está ativa

### Calendário não carrega
- Verifique se os dados PHP estão sendo passados corretamente
- Abra o console do navegador para ver erros JavaScript

### Missões não aparecem
- Execute o script SQL de migração
- Verifique se há missões ativas (`is_active = 1`) no banco

### Dados de rotina não aparecem
- Verifique se há registros em `sf_user_routine_log` para o usuário
- Confirme que as datas estão no formato correto (YYYY-MM-DD)

## Melhorias Futuras

1. **Notificações**: Alertar nutricionista sobre baixa adesão
2. **Gráficos**: Visualização de tendências de adesão ao longo do tempo
3. **Metas Personalizadas**: Permitir meta de sono por usuário
4. **Biblioteca de Ícones**: Interface visual para selecionar ícones
5. **Reordenação**: Drag-and-drop para ordenar missões
6. **Categorias**: Agrupar missões por categoria (alimentação, exercício, etc.)

## Suporte

Para dúvidas ou problemas, consulte:
- Documentação principal do ShapeFit
- Arquivo `bancao11.sql` para estrutura completa do banco
- Código-fonte comentado em `view_user.php` e `routine_missions_crud.php`

---

**Desenvolvido para o Painel Administrativo ShapeFit**
*Versão 1.0 - Outubro 2024*


