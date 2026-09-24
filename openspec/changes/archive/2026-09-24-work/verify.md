# Relatório de Verificação — change `work`

> Gerado após a fase apply, para confirmar a conformidade entre
> implementação, specs, design e tasks. Skill `openspec-verify-change`
> indisponível neste repo — checagens executadas manualmente conforme
> `openspec instructions verify`.

**Change**: `work`
**Verificado em**: `2026-09-24`
**Verificador**: agente (sessão de verificação pré-archive)
**Branch**: `feat/work` sobre `6807f1e` (19 commits)

---

## 1. Validação estrutural (`openspec validate --all --json`)

- [x] Todos os items com `"valid": true`

**Comando**:

```bash
openspec validate --all --json
```

**Resultado** (resumo):

```text
manage-client-portfolio (change): valid=true
multi-tenant-accounts  (change): valid=true
work                   (change): valid=true
totals: 3 items, 3 passed, 0 failed
```

```bash
openspec validate work --strict
# Change 'work' is valid
```

| Item | Type | Issues |
|---|---|---|
| work | change | — |
| manage-client-portfolio | change | — |
| multi-tenant-accounts | change | — |

---

## 2. Conclusão das tasks (`tasks.md`)

- [x] Todos os 20 checkboxes em `- [x]` (`grep -c '^- \[x\]'` → 20)

Ticks marcados somente após comprovação contra o código; o que é
parcial ou humano-dependente está listado abaixo como **não-bloqueante**.

| Task / sub-item | Estado | Evidência / motivo da ressalva |
|---|---|---|
| 1.1 baseline | [x] comprovado | base `6807f1e`, 19 commits; `composer.json`: PHP `^8.3`, Laravel `^13.17`; `package.json`: Nuxt `^4.5.2`, UI `^4.11.1`, `packageManager pnpm@12.5.1`; diff vazio em `composer.json/lock` + `package.json/pnpm-lock` (sem dependências novas) |
| 1.2 testes TDD | [x] comprovado | 11 métodos em `WorkTemplate/Generation/Process/TaskTest.php` + matriz 5.3 temporária (ver §5.3); fase RED é histórica, não re-verificável post-hoc |
| 1.3 enums+migrations | [x] comprovado | `TaskStatus`/`TaskPriority` + 6 migrations `2026_09_24_000001–6`; `test_work_tables_exist_with_expected_columns` verde; borda `due_day 31 → 2026-02-28` com assert verde |
| 1.4 models/factories | [x] comprovado | `ProcessTemplate`, `ProcessTemplateTask`, `Task`, `TemplateClientException` + relações em `Process`/`Account`/`Client`; `test_tasks_order_due_nulls_last_then_order` verde; ownership via `BelongsToAccount` (suite `IsolationTest` verde) |
| 1.5 geração+agendamento | [x] comprovado | `ProcessGenerationService::generate/preview/eligibleClients`, comando `work:generate-recurrences`, `Schedule::command(...)->daily()` em `routes/console.php:11`; idempotência + comando + congelamento via matriz 5.3 |
| 2.1 API templates | [x] comprovado c/ ressalva | CRUD+blueprint+regra+preview+generate, policies, `SupportAudit::logWrite` (4 pontos), suite verde; **ressalva**: sub-caso `422 p/ exceção de cliente alheio` — regra presente no Request, sem teste dedicado |
| 2.2 API processos | [x] comprovado | filtros template/mês/cliente/status, ordem mês desc, detalhe c/ `progress`; teste dedicado + `M-progress` verdes |
| 2.3 API tasks | [x] comprovado c/ ressalva | ciclo, cascata, motivo obrigatório, 403 `user`, reatribuição; **ressalva**: reabertura limpando `completed_at` — código presente (`TaskController`), sem assert dedicado |
| 2.4 generate/preview/calendar/grouped | [x] comprovado | geração manual p/ 10 clientes, idempotência, feed só c/ prazo, agrupado Cliente›Processo›Task (matriz `M-*` verde) |
| 2.5 policies+rotas+suite | [x] comprovado | policies registradas em `AppServiceProvider`, rotas em `routes/api.php:64-69`, suite completa verde |
| 3.1 shell+nav+composable | [x] comprovado | `work.vue`, `workNav.ts` (5 visões), `useWork.ts`, tipos `work.ts`, seção Work no sidebar; `typecheck` verde; **ressalva**: clique manual entre abas pendente de humano |
| 3.2 páginas | [x] comprovado | 7 páginas + estados loading/empty/error pt-BR; `lint`+`typecheck` verdes |
| 4.1 calendário | [x] comprovado c/ ressalva | `UCalendar` + `calendar()` real (`useWork`); **ressalva**: inspeção visual no navegador pendente de humano |
| 4.2 visão cliente | [x] comprovado c/ ressalva | `getGroupedRowModel` + `grouping ['client_id','process_id']` sobre `grouped()` real (6 refs); visual pendente |
| 4.3 processos | [x] comprovado c/ ressalva | detalhe c/ Stepper/Timeline + `progress` (8 refs); visual pendente |
| 4.4 kanban | [x] comprovado c/ ressalva | 4 colunas `todo/doing/done/dismissed`, botões via API, `user` sem ações; clique visual pendente |
| 4.5 modelos | [x] comprovado c/ ressalva | tabela TaskHub + `UTabs` 5 abas + `preview` + gerar mês (23 refs); fim-a-fim visual pendente (equivalente HTTP comprovado) |
| 5.1 pint+suite | [x] comprovado | ver Gates abaixo |
| 5.2 lint+typecheck+build | [x] comprovado | ver Gates abaixo |
| 5.3 cenários manuais | [x] comprovado c/ ressalva | matriz HTTP verde (ver §5.3); **ressalvas**: inspeção visual (board/calendário/agrupado) pendente de humano; drift 403-vs-404 (ver §4) |

---

## 3. Estado de sincronização das delta specs

Sem diretório `openspec/specs/` neste repo — as 3 capabilities são novas
e serão criadas no sync durante o archive.

| Capability | Sync | Observação |
|---|---|---|
| `tenant/work-templates` | ✗ a sincronizar | delta em `changes/work/specs/tenant/work-templates/spec.md` |
| `tenant/work-processes` | ✗ a sincronizar | delta em `changes/work/specs/tenant/work-processes/spec.md` |
| `tenant/work-tasks` | ✗ a sincronizar | delta em `changes/work/specs/tenant/work-tasks/spec.md` |

---

## 4. Coerência design/specs (amostragem)

| Amostra | design | specs | Diferença |
|---|---|---|---|
| unique full-column c/ NULLs distintos | §1 (`UNIQUE(account,template,client,mês)`, sem partial index) | work-processes: tripla única; manuais sem template/cliente/mês válidos | nenhuma |
| guarda de cascata (`dismissed` libera) | §3 | work-tasks: cenários furar-bloqueado / dispensada-libera / sem-cascata | nenhuma |
| snapshot congelado | §2 + §4 riscos | work-templates (mudança de regime) + work-processes (blueprint) | nenhuma |
| `due_day` 31 → último dia do mês | § riscos | work-tasks: dia 31 em fev → 28 | nenhuma |
| agrupado `grouping ['client_id','process_id']` | §5 | work-tasks: agregação por cliente | nenhuma |
| isolamento acesso direto | — | specs dizem **404** p/ id estrangeiro | **DRIFT (warning, não-bloqueante)**: implementação nega com **403** via policy, consistente com a convenção das rotas `/api` existentes (comprovado: `GET /api/clients/{id-alheio}` → 403 em teste dedicado verde). Nenhum dado vaza; listagens omitem (escopo `BelongsToAccount`). Recomenda-se alinhar o texto da spec no sync (403) ou aceitar 403 como padrão. |

**Avisos de drift (não-bloqueantes)**:
- 403-vs-404 acima — sem vazamento, convenção do codebase prevalece.

---

## 5. Sinal de implementação + Gates

Worktree sem arquivos não-staged além dos 5 artefatos desta change
(`design.md`, `plan.md`, `proposal.md`, `specs/`, `tasks.md`), que serão
commitados antes do archive.

**Faixa de commits**: `6807f1e..HEAD` (19 commits `feat/work` + `fix(work)`).

### Gate 1 — suite backend (exit 0)

```bash
docker run --rm -v /home/obsidian/dev/opmoni/.worktrees/work/backend:/app \
  -v opmoni_backend_vendor:/app/vendor -w /app --network opmoni_opmoni \
  --entrypoint php opmoni-backend:latest artisan test --compact
# exit=0
# Tests: 161 passed (864 assertions) — Duration: 17.99s
```

### Gate 2 — pint (exit 0)

```bash
docker run --rm ... --entrypoint ./vendor/bin/pint opmoni-backend:latest --test --format agent
# exit=0
# {"tool":"pint","result":"passed"}
```

### Gate 3 — frontend (exit 0 em todos)

```bash
cd frontend && pnpm lint      # exit=0 ($ eslint .)
pnpm typecheck                # exit=0 (nuxt typecheck ok)
pnpm build                    # exit=0 (Build complete, 21.1 MB / 6.05 MB gzip)
```

### Gate 4 — matriz 5.3 via testes Feature (temporário, removido após o run)

Arquivo temporário `Verify53MatrixCheckTest.php` (2 métodos, deletado após
execução; equivalente HTTP dos cenários manuais da task 5.3):

```bash
artisan test --compact --filter=Verify53MatrixCheckTest
# M-template=1 | M-preview=10 | M-generate=10 | M-idempotent | M-scheduler
# M-removed-exception | M-freeze | M-cascade | M-levels | M-isolation-setup
# M-support-audit | M-calendar=20 | M-grouped=10
# M-progress={"total":3,"done":0,"dismissed":1,"open":2,"ratio":0}
# test_matrix_53: PASS (71 assertions)
# test_cross_account_reference_behaviour: PASS (M-ref-client=403 p/ /api/clients — convenção do codebase)
```

Cobertura da matriz: modelo PGDAS Simples + exceção `removed` via API,
`POST generate` → 10 processos, repetição sem duplicar, scheduler
`work:generate-recurrences --month=2026-03`, troca de regime congelando
março (abril gera 9), cascata 422→dispensa c/ motivo→avanço 200, `user` 403
(policy antes da validação), suporte (`enter` + PATCH task → log
`tasks/update` em `support_access_logs`), calendário omitindo sem-prazo,
agrupado 10 clientes, progresso `total/done/dismissed/open/ratio`.

**Impossível sem humano** (registrado como pendente, não-bloqueante):
inspeção visual no navegador do board kanban, do calendário com chips e do
agrupado Cliente›Processo›Task (equivalentes HTTP todos verdes acima).

---

## 6. Detector de vazamento front-door (warning, não-bloqueante)

```bash
ls docs/superpowers/specs/*.md 2>/dev/null
# (diretório inexistente — sem vazamento)
```

- [x] Sem arquivos; nada a mover para `brainstorm.md`/`design.md`.

---

## 7. Dogfood manual adiado vs cobertura automatizada

`plan.md` não contém nenhuma linha marcada `[~]` (`grep -c` → 0).

> Seção N/A — sem dogfood adiado declarado; a matriz HTTP do §5.3 cobre os
> cenários manuais da task 5.3, exceto inspeção visual (pendência humana
> registrada no §5.3, com follow-up na retrospective).

---

## Decisão geral

- [ ] ✅ PASS
- [x] ⚠️ PASS WITH WARNINGS — sincronizar specs no archive; drift 403-vs-404 documentado; inspeção visual humana pendente (board/calendário/agrupado)
- [ ] ❌ FAIL

**Próximo passo**: escrever `retrospective.md`, sincronizar as 3 delta specs
para `openspec/specs/`, validar e arquivar a change (sem merge/push/PR).
