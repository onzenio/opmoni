## Context

Ver `proposal.md` e `brainstorm.md`. O Work precisa de responsável (membro do account) + departamento (Fiscal, Pessoal...) por tarefa, mas hoje `AccountMemberController@index` exige `manageMembers` (só `admin`) e não existe tabela de departamento — o backend confirma com grep `department` vazio, e o TaskHub (`.ref/*.png`) mostra tarefa com departamento + responsável, coluna Departamentos nos modelos, filtro por Departamento e tela Equipe com departamentos e membros. O padrão Tags (`tags` + `client_tag`, `TagPolicy`, Form Requests + Resources + `SupportAudit::logWrite`) é o molde da casa para este cadastro. Frontend tem shell `customers.vue`/`monitoring.vue`, `useClients.ts` com `queryOf` e sidebar em `layouts/default.vue`.

## Goals / Non-Goals

**Goals:**
- Dar a qualquer membro (`admin|operador|user`) leitura do diretório de membros do próprio account, sem expor email e sem abrir gestão de membros.
- Criar o cadastro de departamentos com vínculo N:N de membros, gerenciado por `admin|operador`.
- Entregar a seção Equipe (Membros + Departamentos) reutilizável pelo Work (responsável via `USelectMenu`, departamento via referência).
- Manter isolamento por account, papéis e auditoria de suporte intactos.

**Non-Goals:**
- Histórico de Ações (exige novo endpoint/contrato — adiado).
- Limite de plano para departamentos (planos limitam users/clients/monitorings — decisão de produto pendente).
- Alterar CRUD de membros, `PlanLimits`, `ResolveTenant` ou scheduler.
- Implementar o snapshot de departamento na task (decisão registrada aqui, execução na change Work).

## Decisions

### D1: Departamentos espelham Tags, sem entidade nova de vínculo além do pivot
- **Escolha:** `departments(id, account_id, name, color)` + `unique(account_id,name)` e pivot `department_user(department_id, account_id, user_id)` + `unique(department_id,user_id)`; `Department` com `BelongsToAccount`, `members() BelongsToMany User`; `User::departments()`, `Account::departments()`; validação de `member_ids` checa `account_user` no mesmo account.
- **Razão:** mesmo padrão já testado de `tags`/`client_tag`; `BelongsToAccount` dá escopo + binding 404 cross-account de graça.
- **Alternativa recusada:** departamento como enum ou texto livre na tarefa. Recusada porque apodrece (duplicatas) e quebra o filtro por departamento do Work.

### D2: Departamento é da tarefa; membro só executa
- **Escolha:** blueprint do Work referencia `department_id` (FK); `tasks` guardam `department_id nullable + department snapshot string` (congelamento do mês).
- **Razão:** departamento é do escritório, não da pessoa; membro pode estar em N departamentos; mês gerado não pode mudar ao renomear/excluir.
- **Alternativa recusada:** departamento herdado do membro. Recusada porque membro em N departamentos deixa a herança ambígua.

### D3: Leitura liberada, escrita restrita, sem email no diretório
- **Escolha:** nova abilidade `AccountPolicy::viewMembers` (qualquer `tenantRole != null`, inclui super_admin em suporte como `admin`); `GET /account/members/directory` retorna `[{id,name,role,departments[]}]` ordenado por `name`, sem `email`; rota registrada antes do `apiResource('account/members')`; `DepartmentPolicy` espelha `TagPolicy` (ler: qualquer membro; escrever: `admin|operador` + mesmo account); leitura não audita.
- **Razão:** operador/user precisam montar o `USelectMenu` de responsáveis; email é dado sensível desnecessário; CRUD admin continua fechado (`RolesTest::test_operador_cannot_manage_members` segue verde).
- **Alternativa recusada:** liberar o `index` atual para operador/user. Recusada porque exporia email e misturaria gestão com leitura.

### D4: Seção Equipe própria com 2 abas v1
- **Escolha:** trigger Equipe após Clientes no sidebar + `equipe.vue` (shell `UDashboardPanel/Navbar/Toolbar`) + `equipeNav.ts` (Membros `/equipe`, Departamentos `/equipe/departamentos`) + `useMembers.ts`/`useDepartments.ts` com `queryOf` padrão `useClients.ts`; Membros = diretório + filtro por departamento; Departamentos = lista A-Z + modal criar/editar + assign múltiplo; textos pt-BR; `UModal`/`USelectMenu` já usados na carteira.
- **Razão:** não mexe em Settings/Clientes/Monitoramento; reaproveita padrões provados.
- **Alternativa recusada:** pendurar Equipe dentro de Settings. Recusada porque Equipe é uso diário do operador, não configuração esporádica.

## Risks / Trade-offs

- [Risco] `directory` colidir com `show {member}` no resource → Mitigação: registrar `GET account/members/directory` antes do `apiResource`, com teste de 200 para `directory` e 404 cross-account.
- [Risco] Nome de departamento duplicado com caixa diferente ("Fiscal" vs "fiscal") → Mitigação: `trim` no Request + `unique(account_id,name)`; teste de 422.
- [Risco] `member_ids` com usuário de outro account → Mitigação: regra `after` checando `account_user` do tenant; teste de 422.
- [Trade-off] Snapshot de departamento implementado só no Work → aceito porque esta change não toca em `processes`/`tasks`; decisão registrada aqui vira REQUIREMENT lá.
- [Trade-off] Sem Histórico de Ações na v1 → aceito; tela Equipe entra com estado vazio documentado em vez de endpoint improvisado.

## Migration Plan

1. Migrar: criar `departments` + `department_user` (aditivas, FKs com cascata); nenhum dado existente é tocado.
2. Deploy backend, depois frontend; nenhuma rota existente muda de contrato.
3. Rollback: dropar as duas tabelas novas apenas se nenhum departamento tiver sido criado; esconder a seção Equipe atrás do middleware `auth` existente.

## Open Questions

- Nenhuma bloqueante. Limite de plano para departamentos e Histórico de Ações são decisões de produto futuras e mudariam specs se incluídas.
