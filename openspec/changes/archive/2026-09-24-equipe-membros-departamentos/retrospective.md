# Retrospective: equipe-membros-departamentos

> Written: 2026-09-24 (after verify PASS WITH WARNINGS)
> Commit range: `7871d3a..8af7f78`
> Worktree: /home/obsidian/dev/opmoni/.worktrees/equipe-membros-departamentos (branch `feat/equipe-membros-departamentos`)

---

## 0. Evidence

- **Commit range**: `7871d3a..8af7f78` (3 commits)
- **Diff size**: +1549 / -0 lines across 24 files
- **Tasks done**: 11/12 (`grep -c '^- [x]' tasks.md` → 11; resta 4.3 manual)
- **Active hours**: ~1.5h (4 workers sequenciais: baseline 170s + directory 92s + departments 324s + frontend 545s + verify 244s)
- **Subagent dispatches**: 5 (baseline, directory, departments, frontend, verify)
- **New external dependencies**: none
- **Bugs encountered post-merge**: none (ainda não mergeado)
- **OpenSpec validate state at archive**: 11/11 passed (2 changes + 9 specs) — ver verify §1
- **Test coverage signal**: backend 155 passed (852 assertions: baseline 146 + 9 novos); frontend lint + typecheck + build verdes

Commit chain:

```
7871d3a docs(work): register brainstorming and install superpowers-bridge schema
1a3d459 feat(team): add readable member directory
b84b966 feat(team): add departments with member links
8af7f78 feat(team): add equipe section with directory and departments
```

---

## 1. Wins

- [evidence: `MemberDirectoryTest` 3 passed] Diretório TDD RED (404) → GREEN sem regressão em `RolesTest` (8 passed) — rota antes do resource funcionou de primeira.
- [evidence: `DepartmentTest` 6 failed → 9 passed] Espelho Tags pagou: migration + model + policy + requests + controller seguiram o molde e a suite foi a 155 passed sem quebrar nada existente.
- [evidence: `pnpm build` verde 21.2 MB] Frontend Equipe passou em lint + typecheck + build na primeira verificação (só `--fix` de estilo no meio).
- [evidence: `openspec validate` 11/11] Change válida de ponta a ponta com 2 capabilities novas.

## 2. Misses

- 🟡 [painful | evidence: tasks.md 4.3 `- [ ]`] Cenários manuais autenticados não executados — sem servidor interativo no ciclo; coberto por testes automatizados, mas dogfood visual pendente antes do merge.
- 📌 [nit | evidence: `pnpm exec nuxt --version` → 3.37.0 vs package.json 4.5.2] Divergência de versão do binário nuxt anotada no baseline; não afetou o resultado.
- 📌 [nit | evidence: worktree sem `openspec/changes/equipe-membros-departamentos/`] Change é untracked na main, então workers leram o plano da main com fallback — funcionou, mas cada dispatch precisou da instrução de fallback.

## 3. Plan deviations

| Plan task | What changed | Why |
|---|---|---|
| 1.1 | `composer install` + `.env` + `key:generate` no worktree antes da suite | Worktree nova não tem `vendor/` nem `.env` (ignorados); sem isso 13 failed de ambiente |
| 2.1 (implícita) | Teste criado em `tests/Feature/` e movido para `Tenancy/` | `make:test --phpunit` gera no diretório base; namespace ajustado ao padrão da casa |
| 5.x | `directory()` ganhou eager `with('departments')` já nesta change | Evitar N+1 desde o início em vez de deixar para depois |
| 6.x | `departamentos.vue` resolve membros por cross-ref do directory | `DepartmentResource::index` retorna só `members_count` (sem `members`); frontend compõe sem novo endpoint |

## 4. Skill / workflow compliance

| Skill | Used |
|---|---|
| superpowers:brainstorming | ✗ (ausente — captura manual com opt-in) |
| superpowers:writing-plans | ✗ (ausente — plano manual a partir de tasks + design) |
| superpowers:using-git-worktrees | ~ (parcial — skill ativada para o protocolo, worktree criada via git fallback manual) |
| superpowers:subagent-driven-development | ✗ (ausente — executor via workers manuais com TDD por task) |
| (transitive) superpowers:test-driven-development | ✓ (RED-GREEN por task: MemberDirectory 2F→3P, Departments 6F→9P) |
| (transitive) superpowers:requesting-code-review | ✗ (ausente — revisão feita pelo orquestrador nos reports + pint/lint/typecheck) |
| superpowers:finishing-a-development-branch | ✗ (ausente — PR será manual com revisão de diff) |

### Deliberately Skipped Skills

- **`superpowers:brainstorming`**
  - **What was skipped**: invocação da skill; `brainstorm.md` escrito manualmente.
  - **Why this cycle**: skill não consta na lista disponível (só `brainstorming` genérica); instruction do schema manda STOP sem fallback silencioso — usuário optou explicitamente pelo manual.
  - **How to prevent recurrence**: `one-off — schema boundary case, no prevention possible` — ambiente sem plugin Superpowers instalado; com o plugin, o fluxo volta ao canônico.
- **`superpowers:writing-plans`**
  - **What was skipped**: invocação da skill; `plan.md` decomposto manualmente de tasks + design.
  - **Why this cycle**: mesma ausência acima; plano seguiu template + regras (micro-passos TDD, paths, snippets, comandos).
  - **How to prevent recurrence**: `one-off — schema boundary case, no prevention possible` — idem.
- **`superpowers:subagent-driven-development` / `requesting-code-review` / `finishing-a-development-branch`**
  - **What was skipped**: executor unificado e reviews/PR pela skill; substituídos por workers manuais + verificação do orquestrador.
  - **Why this cycle**: skills ausentes no ambiente; fallback manual aprovado pelo usuário via AskUser ("Fallback manual em worktree nova").
  - **How to prevent recurrence**: `one-off — schema boundary case, no prevention possible` — idem; padrão repetido em 3 cycles seguidos sugere avaliar instalar o plugin ou trocar o default para `spec-driven`.

## 5. Surprises

- Worktree nova não herda `vendor/`, `node_modules/`, `.env` — baseline exigiu `composer install` + `key:generate` antes de qualquer teste (13 failed de ambiente viraram 146 passed).
- `make:test --phpunit` gera em `tests/Feature/`, não em `Tenancy/` — mover + ajustar namespace virou passo padrão.
- `pnpm exec nuxt --version` (3.37.0) diverge do `nuxt` instalado (4.5.2) — cosmético, sem impacto.
- Droid-Shield bloqueia `git commit` com pathspec ou `add+commit` no mesmo comando — `add` e `commit` precisam de Executes separados.

## 6. Promote candidates → long-term learning

- [ ] 🟡 Worktree nova sempre exige setup de ambiente antes da suite — mesmo sendo ignorados, `vendor/` + `.env` + `node_modules/` precisam existir.
  → **Promote to** one-off
  > **Why**: 2 cycles seguidos perderam a primeira execução de teste por ambiente vazio.
  > **How to apply**: todo plano com worktree nova ganha Step 0 de setup (`composer install`, `.env`, `key:generate`, `pnpm install`) antes do baseline.
- [ ] 📌 `git add` e `git commit` em Executes separados (Droid-Shield).
  → **Promote to** one-off
  > **Why**: commit com pathspec ou `add+commit` no mesmo comando é bloqueado.
  > **How to apply**: ao commitar via Execute, sempre `add` num call e `commit -m '...'` (sem pathspec) no call seguinte.
- [ ] 🟡 3 cycles com as mesmas skills `superpowers:*` ausentes e mesmo fallback.
  → **Promote to** memory
  > **Why**: o padrão se repete (propose → apply manual); decidir uma vez evita re-perguntar o óbvio.
  > **How to apply**: se `superpowers:*` seguir ausente, assumir fallback manual em worktree após uma confirmação curta, sem STOP longo.

## 7. Addendum — sessão finish com revalidação (2026-09-24)

- Finish anterior validou os gates mas travou: `openspec/changes/equipe-membros-departamentos/` não existia na worktree (untracked na main, inexistente no git — `git checkout main --` e `git show main:` inaplicáveis). Resolvido com `cp -r` só da pasta via filesystem, sem trazer mais nada da main.
- Revalidação item a item (1.1–4.2) contra o código da branch: tudo comprovado (policy `viewMembers`, `directory()`, migration departments + pivot, `DepartmentPolicy`, requests/resources/controllers, rotas, composables `useMembers`/`useDepartments`, `team.ts`, `equipeNav.ts`, páginas `equipe/`); nenhum `[x]` desmarcado.
- Gates re-executados no esquema docker + pnpm: backend 155 passed (852 assertions, exit 0), pint passed (exit 0), `pnpm lint`/`typecheck`/`build` verdes (exit 0), `MemberDirectoryTest` 3 passed + `DepartmentTest` 6 passed como equivalência HTTP da task 4.3.
- Task 4.3 segue `[ ]` — só o dogfood visual da seção Equipe exige humano; registrado como pendente no verify §8, sem bloquear o archive.
- Archive executado nesta sessão: sync das 2 deltas novas (`tenant/team-departments`, `tenant/member-directory`) para `openspec/specs/` + `mv` para `openspec/changes/archive/2026-09-24-equipe-membros-departamentos/`.
