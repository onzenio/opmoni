## Why

A página atual `work/calendario` usa um `UCalendar` mensal com dots sem título mais uma lista lateral do dia: em meses cheios vira ruído, sem escaneabilidade, sem URL compartilhável e com filtros por ID digitado. O template `nuxt-ui-templates/calendar` mostra o padrão a seguir — grade mensal com chips legíveis, sidebar com mini-calendário, popover por tarefa e rotas por visão/data.

## What Changes

- Substitui o `UCalendar` + dots por grade mensal própria 6×7 com chips `dot + título truncado` e overflow `+N more` em popover.
- Adiciona shell interno de duas colunas: sidebar (mini-calendário, toggles de status/visibilidade, filtros compactos) + área principal com header estilo template (título, segmentado Dia/Semana/Mês, `< Hoje >`).
- Adiciona visões Semana (7 colunas em lista vertical por dia) e Dia (lista única); sem grade horária porque tasks têm só `due_on` (data, sem hora).
- Clique no chip abre popover com as mesmas ações do board (`tarefas.vue`): avançar/retornar, atribuir responsável, dispensar com motivo, link para o processo.
- Arrastar chip entre dias reagenda `due_on` com update otimista e rollback (drag só muda data, nunca status).
- URL passa a ser compartilhável via query (`?view=month|week|day&date=AAAA-MM-DD`); ausência de query cai no mês atual.
- Backend passa a aceitar `due_on` em `PATCH /tasks/{id}` (somente reagendamento, sem mexer em cascata/status).

## Capabilities

### New Capabilities

- `tenant/work-calendar`: experiência do calendário do Work — visões mês/semana/dia, sidebar com mini-calendário e filtros híbridos, chips com overflow, popover com ações, URL compartilhável, drag-to-reschedule otimista.

### Modified Capabilities

- `tenant/work-tasks`: permitir reagendamento de `due_on` via `PATCH /tasks/{id}` (validação, autorização, auditoria); feed do calendário continua retornando só tasks com prazo.

## Impact

- Frontend: `app/pages/work/calendario.vue` (rework), novos `app/components/work/calendar/*`, ajustes em `useWork`/tipos; sem mudar `default.vue` global.
- Backend: `TaskController::update` + `UpdateTaskRequest` (aceitar `due_on`), testes `WorkTaskTest`/`WorkCalendarTest`.
- Sem migração de banco (`due_on` já existe); sem novos endpoints de listagem (reusa processos/grouped/members).
