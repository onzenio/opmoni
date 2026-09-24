# Retrospective: work

> Escrita em: 2026-09-24 (após verify PASS WITH WARNINGS)
> Faixa de commits: `6807f1e..9add932` (+ commit de verificação desta sessão)
> Worktree: /home/obsidian/dev/opmoni/.worktrees/work (branch `feat/work`, sem merge)

---

## 0. Evidência

- **Faixa de commits**: `6807f1e..9add932` (19 commits) + 1 commit de verificação (`chore(work): ...`)
- **Tamanho do diff**: +7218 / −17 linhas em 72 arquivos (`git diff 6807f1e..HEAD --stat`)
- **Tasks concluídas**: 20/20 (`grep -cE '^\s*- \[x\]' tasks.md` → 20)
- **Horas ativas**: n/a (implementação em múltiplas sessões 2026-09-23→24; verificação ~1 sessão)
- **Despachos de subagentes**: n/a nesta sessão de verificação (trabalho direto)
- **Novas dependências externas**: nenhuma (diff vazio em `composer.json/lock`, `package.json/pnpm-lock`)
- **Bugs pós-merge**: nenhum (não houve merge)
- **OpenSpec validate no archive**: pass (`validate --all`: 3/3; `validate work --strict`: válida)
- **Sinal de cobertura**: backend 161 passed / 864 assertions; frontend `lint`+`typecheck`+`build` verdes; matriz 5.3 temporária: 71 assertions verdes (arquivo removido após o run, log em `verify.md` §5.3)

Cadeia de commits (resumo):

```
6807f1e feat(carteira): portfolio fiscal com painel, filtros, tags e documentos
7871d3a docs(work): register brainstorming and install superpowers-bridge schema
9818a56 feat(work): add task enums and work migrations
5d7573a feat(work): add work models, relations and factories
a3da863 fix(work): correct template_tag pivot keys
f3da21b feat(work): add per-client generation service and scheduler
03b5f9a feat(work): add process template api with rule and preview
4ed8216 feat(work): evolve process api with filters and progress
390a2cd fix(work): include task resource in process api
71f8dc3 feat(work): add task api with cascade guard calendar and grouped
760f195 fix(work): guard status checks on change only
3c8c000 feat(work): add work shell nav and typed composable
da80667 feat(work): add work pages with calendar feed
66dc655 feat(work): add grouped client view and process detail
3e43a20 feat(work): add task kanban with cascade feedback
50cf9f7 fix(work): harden kanban error feedback
da997ea feat(work): add taskhub-style model table and editor
ad17ef9 fix(work): user-readable preview and model editor polish
9add932 fix(work): address deferred review minors
+ chore(work): verify work implementation (esta sessão)
```

---

## 1. Vitórias

- [evidence: suite 161/864 verde] Backend entregue com cobertura Feature por domínio (templates, geração, processos, tasks) e gates repetíveis via container efêmero.
- [evidence: commits 03b5f9a, 4ed8216, 71f8dc3] API seguiu o padrão carteira (Form Requests + Resources + policies espelho + `SupportAudit::logWrite`), sem quebrar contratos existentes.
- [evidence: matriz 5.3] Cenários manuais da task 5.3 convertidos em matriz HTTP executável (10 empresas, idempotência, scheduler, congelamento, cascata, níveis, suporte, calendário, agrupado) — tudo verde de primeira após 3 ajustes só no teste (201-vs-200, `whereDate`, policy-antes-validação), nenhum bug na implementação.
- [evidence: 0 diff em locks] Nenhuma dependência nova no backend nem no frontend.
- [evidence: frontend build 21.1 MB ok] 5 visões integradas ao feed real (`useWork` nas 7 páginas) com `lint`+`typecheck`+`build` verdes.

## 2. Fracassos

- 🟡 [painful | evidence: verify.md §4] Specs dizem 404 p/ acesso direto cross-account, implementação (e convenção `/api`) responde 403. Sem vazamento, mas o texto da spec diverge — exigiu teste dedicado de referência (`M-ref-client=403`) para decidir.
- 📌 [nit | evidence: matriz 5.3] `POST .../generate` responde 201 (correto), mas o plano/exemplos sugeriam 200 — 3 ciclos de ajuste no teste temporário por esse detalhe.
- 📌 [nit | evidence: sessão] Arquivo temporário de verificação sumiu 2× durante a sessão (outro processo ativo na mesma árvore limpando `*Test.php` não-commitados) — exigiu recriação com nome distinto e runs imediatos.
- 📌 [nit | evidence: tasks 2.1/2.3] Dois sub-casos sem assert dedicado: `422 p/ exceção de cliente alheio` e reabertura limpando `completed_at` (código presente e inspecionado, sem teste).

## 3. Desvios do plano

| Task do plano | O que mudou | Por quê |
|---|---|---|
| 5.3 cenários manuais | Executados como matriz HTTP automatizada temporária, não como cliques manuais | Sem PHP no host e sem navegador nesta sessão; equivalentes HTTP cobrem estado persistido + audit log; inspeção visual registrada como pendência humana |
| 2.1 (404 isolamento) | Mantido 403 da implementação | Convenção das rotas `/api` existentes é 403 (política nega antes do binding); mudar p/ 404 seria divergir do codebase |
| 5.3 (10 empresas) | Cobertura foi além do commitado (committed tests geram 1–2 clientes) | Matriz temporária gerou 10 p/ fidelidade ao cenário descrito |

## 4. Conformidade de skills / workflow

| Skill | Usada |
|---|---|
| superpowers:brainstorming | ✓ (`brainstorm.md` registrado em 2026-09-23, referenciado por proposal/design/specs) |
| superpowers:writing-plans | ✓ (`plan.md` com 13 tasks e handoff de execução) |
| superpowers:using-git-worktrees | ✓ (trabalho isolado em `.worktrees/work`, branch `feat/work`) |
| superpowers:subagent-driven-development | ? (histórico sugere execução por lotes — commits pequenos por task — mas sem evidência direta nos artefatos; não afirmado) |
| (transitiva) superpowers:test-driven-development | ✓ (testes `Work*Test` precedem/ancoram cada task do plano; suíte verde) |
| (transitiva) superpowers:requesting-code-review | ✓ (commit `9add932 fix(work): address deferred review minors` evidencia revisão com follow-ups) |
| superpowers:finishing-a-development-branch | ✗ (ver subseção) |

### Deliberately Skipped Skills

- **`superpowers:finishing-a-development-branch`**
  - **What was skipped**: a skill inteira (decisão de integração: merge/push/PR).
  - **Why this cycle**: ordem explícita do solicitante nesta sessão — "NÃO faça merge, push ou PR; pare antes disso e relate" — trigger concreto no pedido, não julgamento de escopo próprio.
  - **How to prevent recurrence**: `scope-judgment rule` — quando o pedido delimita "verificação + archive, sem integração", o archive do OpenSpec continua válido (move diretório, não faz merge); a integração fica para ciclo seguinte com `finishing-a-development-branch` então aplicável.

## 5. Surpresas

- Convenção real de isolamento das rotas `/api` é 403 (policy), não 404 — o teste de isolamento commitado cobre 404 só nas rotas `/_test/`. Suposição "spec diz 404, logo implementação retorna 404" estava errada; o seguro (negar sem vazar) estava certo.
- Coluna `date` persiste como datetime no SQLite (`2026-02-28 00:00:00`) — exige `whereDate` nos testes (o teste commitado já documentava isso em comentário).
- Policy executa antes da validação: `user` dispensando sem motivo recebe 403, não 422 — comportamento correto, expectativa inicial do teste errada.
- Interferência de sessão concorrente deletando arquivos de teste não-commitados — trabalhar com nomes distintos e runs imediatos mitigou.

## 6. Candidatos a promoção → aprendizado de longo prazo

- [ ] 🟡 **Specs novas devem declarar o código de isolamento real das rotas `/api` (403), não 404 idealizado** → **Promote to project CLAUDE.md** (seção de convenções de API)
  > **Why**: divergência 403-vs-404 custou um teste de referência dedicado para decidir o que já era convenção.
  > **How to apply**: ao escrever specs de capabilities tenant novas, copiar o parágrafo de isolamento de capability existente em vez de redigir do zero.
- [ ] 📌 **Comentar no teste quando SQLite persiste `date` como datetime** → **Promote to memory** (type: feedback)
  > **Why**: o comentário existente em `WorkGenerationTest` economizou um ciclo de depuração; sem ele, o `whereDate` seria redescoberto.
  > **How to apply**: todo assert em coluna `date`/`datetime` sob SQLite leva `whereDate` ou comentário equivalente.
- [ ] 📌 **Matriz HTTP temporária é substituto válido p/ cenários manuais quando há gates equivalentes** → **Promote to memory** (type: feedback)
  > **Why**: a matriz 5.3 (71 assertions) deu evidência auditável sem navegador e sem poluir a suíte commitada.
  > **How to apply**: em verificações pré-archive sem ambiente visual, criar `*MatrixCheckTest`, rodar, colar log no `verify.md` e deletar.
- [ ] 📌 **Não deixar `*Test.php` temporários ociosos em árvore compartilhada** → **One-off** (registrar apenas, sem promote)
  > **Why**: arquivos temporários atraíram limpeza concorrente e geraram ruído (run inicial falhou por arquivo alheio transitório).
  > **How to apply**: criar, executar e deletar na mesma sequência de comandos sempre que possível.
