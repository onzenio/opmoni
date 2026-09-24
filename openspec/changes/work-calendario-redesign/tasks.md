## 1. Backend — reagendamento de `due_on`

- [x] 1.1 Aceitar `due_on` opcional em `UpdateTaskRequest` + atribuir em `TaskController::update` sem tocar no fluxo de status/cascata, e verificar com `php artisan test --compact --filter=WorkTaskTest`
- [x] 1.2 Cobrir reagendar/limpar/403/422/cascata-não-bloqueia/auditoria-suporte em `WorkTaskTest` (ou novo `WorkCalendarRescheduleTest`) e verificar com `php artisan test --compact --filter=<nome>`
- [x] 1.3 Rodar `vendor/bin/pint --dirty --format agent` após editar PHP e verificar com `git diff --stat`

## 2. Frontend — grade mensal + shell

- [x] 2.1 Criar `app/components/work/calendar/WorkMonthGrid.vue` (6×7 fixo, chips `dot + título` por status, `+N more` em `UPopover`, today destacado, skeletons/empty) e verificar com `pnpm typecheck`
- [x] 2.2 Criar `app/components/work/calendar/WorkCalendarSidebar.vue` (mini `UCalendar`, toggles de status default-on, selects compactos de processo/cliente/responsável/departamento/prioridade com fontes existentes) e verificar com `pnpm typecheck`
- [x] 2.3 Reescrever `app/pages/work/calendario.vue` com header estilo template (título, switcher Dia/Semana/Mês, `< Hoje >`, refresh) + query `?view=&date=` com fallback para o mês atual + remoção do card de filtros por ID, e verificar com `pnpm dev` navegando entre visões e copiando a URL
- [x] 2.4 Remover dots/`UCalendar` legados sem deixar imports mortos e verificar com `pnpm lint`

## 3. Frontend — semana, dia e popover

- [x] 3.1 Criar visões Semana (7 colunas verticais) e Dia (coluna única, empty state) como lista ordenada por status+título, sem grade horária, e verificar com `pnpm dev` em données com e sem tasks
- [x] 3.2 Criar `app/components/work/calendar/WorkCalendarTaskPopover.vue` reutilizando `updateTask` (avançar/retornar com toast 422 + lock, atribuir, dispensar com motivo, link `/work/processos/{id}`, gate `canManageWork`) e verificar com `pnpm dev` executando cada ação no popover
- [x] 3.3 Ligar atalhos `t`/setas (fora de inputs) e navegação do mini-calendário com `replace` da query, e verificar com `pnpm dev` via teclado e clique no mini

## 4. Frontend — drag-to-reschedule otimista

- [x] 4.1 Implementar arraste de chip entre dias (só `due_on`, nunca status; `dismissed` não arrastável; guarda por chip) com update otimista + rollback + toast no erro, e verificar com `pnpm dev` arrastando entre dias do mês e entre dias de borda
- [x] 4.2 Convergir via `refresh()` do range após cada persistência/rollback sem duplicar tasks na grade, e verificar com `pnpm dev` repetindo arrastes e recarregando a página na URL compartilhada

## 5. Verificação final

- [x] 5.1 Rodar `composer test` (backend), `pnpm lint` e `pnpm typecheck` (frontend) e verificar que tudo passa
- [x] 5.2 Rodar `openspec validate --change work-calendario-redesign --strict` e verificar que proposal/specs/design/tasks estão consistentes
