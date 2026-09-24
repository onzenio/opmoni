# Work (Rotinas Fiscais por Cliente) — Registro do Brainstorming

**Data:** 2026-09-23
**Status:** brainstorming registrado — precede e fundamenta esta change (`proposal.md`, `design.md`, 3 specs, `tasks.md`, `plan.md`).
**Origem:** sessões de brainstorming com referência TaskHub (`.ref/*.png`), demo kanban (`nuxt-dashboard-demo.nuxtcharts.com/apps/tasks`) e snippet `TableGroupedRowsExample` do usuário.

## 1. Problema e entendimento fechado

O escritório executa rotinas fiscais mensais por cliente (PGDAS → "preencher PGDAS" para cada empresa) e não há onde organizar esse trabalho: sem processos, tasks, prazos ou recorrência. O operador precisa ver o que vence, para qual cliente e em que etapa está, sem sair do dashboard.

Decisões fechadas no brainstorming:

- Work organiza rotinas do processo com recorrência mensal; calendário mostra só tasks com prazo.
- Visão cliente usa o snippet do usuário (`UTable` + `getGroupedRowModel`) com agrupamento Cliente > Processo > Task.
- Granularidade: **um processo por cliente por modelo por mês** (ex.: 10 empresas no PGDAS = 10 processos em 03/2026).
- Associação por **regra dinâmica + exceções** (não lista fixa): regimes tributários (`TaxRegime` existente, vazio = todos) + Tags da carteira como categorias (vazio = todas, senão basta uma) + exceções `added`/`removed` por cliente.
- Blueprint completo com cascata: departamento (texto livre v1), responsável (membro do account), prazo em dia fixo 1–31, prioridade (`low|medium|high|urgent`), descrição, ordem; flag `cascade` = bloqueio sequencial.
- Congelamento: mudança de regime/tag/blueprint só vale para próximos meses.
- Ciclo: A fazer (`todo`) → Em progresso (`doing`) → Concluída (`done`), com Dispensada (`dismissed`, motivo obrigatório) como terminal alternativo.
- Kanban com 4 colunas do ciclo, cards fiscais, sem drag-and-drop na v1 (botões via API).
- Modelos espelham o TaskHub: tabela (Título, Regimes, Categorias/Tags, Departamentos, Clientes, Recorrência) + editor em abas + preview + "gerar mês".

## 2. Abordagens consideradas

**A. Processo global por competência + task por cliente (descartada).**
Um processo PGDAS 03/2026 com N tasks (uma por cliente). Rejeitada: progresso, vencimento e cascata se misturam entre clientes; detalhe por empresa vira filtro artificial; auditoria e exclusão por cliente complicam.

**B. Processo por cliente + regra dinâmica + snapshot congelado (adotada).**
Cada (modelo, cliente elegível, mês) gera um processo com tasks clonadas do blueprint. Regra resolve elegíveis; snapshot congela o mês. Custa fan-out N×M na geração, mas isola progresso, cascata e auditoria por empresa — que é a unidade que o operador cobra.

**C. Lista fixa de clientes por modelo (descartada).**
Template com `template_client` fixo. Rejeitada: apodrece a cada mudança de regime/tag; exige manutenção manual; conflita com as abas Adicionar/Remover do TaskHub.

## 3. Design

### Backend (Laravel 13, `cd backend`)

- `processes` evolui de forma aditiva: `client_id`/`template_id`/`reference_month` (dia 01)/`status`/`due_on` nullable + `UNIQUE(account_id, template_id, client_id, reference_month)` full-column. Registros só-nome seguem válidos como manuais.
- Novas tabelas: `process_templates` (`cascade`, `generate_day`, `due_day`, `is_active`, `regimes` JSON), `process_template_tasks` (`department`, `due_day` 1–31, `priority`, `order`, `default_assignee_member_id`), `template_tag`, `template_client_exceptions` (`added|removed`), `tasks` (só `process_id`, sem `client_id`; `dismissal_reason` nullable).
- `ProcessGenerationService::generate(template, mês)`: resolve elegíveis em query única (ativos × regime × tag + `added` − `removed`), find-or-create transacional com lock por tripla, clona snapshot (`due_on` = dia fixo capped, status `todo`). Comando `work:generate-recurrences` (diário) e `POST .../generate` usam o mesmo serviço; `GET .../preview` só resolve, sem criar.
- Cascata como guarda de transição: além de `todo`, recusa se etapa de `order` menor não está `done`/`dismissed`; `dismissed` libera a seguinte.
- API espelha o padrão carteira: Form Requests + Resources + policies espelho `ProcessPolicy` (ler: qualquer membro; escrever: admin|operador) + `SupportAudit::logWrite` + `BelongsToAccount` em tudo.
- Rotas: `apiResource process-templates` (+ preview/generate), `processes` evoluído (filtros template/mês/cliente/status, progresso derivado), `tasks` (7 filtros, ciclo, cascata), `GET /work/calendar` (só com `due_on`), `GET /work/grouped` (Cliente > Processo > Task).

### Frontend (Nuxt 4 + Nuxt UI 4, `cd frontend`, pnpm)

- Seção Work no sidebar + `work.vue` (UDashboardPanel/Navbar/Toolbar) + `workNav.ts` + `useWork.ts`, espelhando `customers.vue`/`monitoring.vue` e `useClients.ts`.
- Calendário: `UCalendar` slot `#day` + `UChip` por status + lista do dia; só tasks com prazo.
- Clientes: `UTable` com `getGroupedRowModel`, `grouping ['client_id','process_id']`, coluna `title` com expand + `UBadge` (`todo:info`, `doing:warning`, `done:success`, `dismissed:neutral`).
- Processos: tabela por competência/cliente + detalhe Stepper/Timeline com progresso total/concluídas/dispensadas/abertas + tasks ordenadas com cadeado.
- Tarefas: kanban 4 colunas (A fazer/Em progresso/Concluída/Dispensada), cards fiscais, botões avançar/retornar/dispensar (motivo obrigatório), `user` sem ações.
- Modelos: tabela TaskHub + editor em 5 abas (Associação, Clientes e Exceções, Prazo, Tarefas, Recorrência) + preview com motivo + "gerar mês".

### Testes e QA

- Backend: Feature TDD (templates, geração idempotente, comando, congelamento, cascata, ciclo, policies, isolamento, calendário); `composer test`; `vendor/bin/pint --dirty --format agent`.
- Frontend: `pnpm lint`, `pnpm typecheck`, `pnpm build`; cenários manuais (PGDAS Simples + exceção, 10 empresas, repetição sem duplicar, scheduler, troca de regime, cascata, isolamento, auditoria, board, agrupado).
- `openspec validate work --strict` precisa continuar válido.

## 4. Fora de escopo v1

Anexo de documento na etapa, drag-and-drop (calendário e kanban), dependências arbitrárias entre tasks, SLA/notificações, automação e-CAC, reaproveitar `monitorings`/`documents` como tasks, calendários externos, inferência de responsável.

## 5. Riscos

- Fan-out modelo × clientes × etapas: geração fora do hot path (scheduler) + feedback no manual.
- `due_day` 29–31 em mês curto: cap determinístico + teste de borda (31 → 28 em fev/2026).
- Responsável sai do account: mantém id, lista mostra "sem responsável", reatribuição valida membro atual.
- Mudança de regime/tag no meio do mês: congelado por desenho.
- Unicidade com NULLs: full-column unique (PostgreSQL e SQLite tratam NULL como distinto; sem partial index).

## 6. Apontadores (dentro desta change)

- `proposal.md`, `design.md`, `specs/tenant/work-templates/spec.md`, `specs/tenant/work-processes/spec.md`, `specs/tenant/work-tasks/spec.md`, `tasks.md`, `plan.md`.
- Brainstormings anteriores (contexto externo, não versionado aqui): `~/.factory/specs/2026-09-23-work-5-vis-es-modelos-com-recorr-ncia-mensal.md`, `~/.factory/specs/2026-09-23-work-pivot-taskhub-processo-por-cliente-regra-din-mica.md`.
