## Why

O escritório executa rotinas fiscais mensais por cliente (ex.: PGDAS → "preencher PGDAS" para cada empresa) e hoje não há onde organizar esse trabalho: não existem processos, tarefas, prazos nem recorrência no sistema. A referência TaskHub (`.ref/ref-*.png`) mostra o padrão esperado: catálogo de modelos por regime/categoria, associação com exceções, tarefas com departamento/responsável/prazo e execução em cascata. O operador precisa ver o que vence, para qual cliente e em que etapa está, sem sair do dashboard.

## What Changes

- Criar Modelos de processo com blueprint completo de tasks (título, departamento texto livre, responsável padrão membro do account, prazo em dia fixo do mês 1–31, prioridade, descrição, ordem) e flag de execução em cascata (bloqueio sequencial)
- Associar cada modelo por regra dinâmica: regimes tributários (vazio = todos) + Tags da carteira como categorias (vazio = todas, senão basta uma) + exceções explícitas (`added`/`removed` por cliente), com endpoint de preview dos clientes elegíveis antes de gerar
- Gerar automaticamente todo mês, via agendamento diário, UM Processo por (modelo, cliente elegível, mês de referência), com tasks clonadas das etapas e `due_on` = dia fixo do mês (limitado ao último dia), de forma idempotente por `(account, template, client, mês)`
- Congelar o gerado: mudança posterior de regime/tag do cliente ou do modelo só vale para os próximos meses; o processo do mês mantém snapshot (título, departamento, prazo, prioridade, descrição, responsável copiados na geração)
- Evoluir `processes` com `client_id` + `template_id` + mês de referência + status + vencimento (colunas novas; registros atuais só-nome continuam válidos como manuais sem template/cliente/mês)
- Criar Tasks como etapa de um processo (cliente herdado do processo, sem `client_id` próprio), ciclo A fazer → Em progresso → Concluída com Dispensada como estado terminal alternativo, com guarda de cascata e carimbo de conclusão
- Expor seção Work no dashboard com 5 visões: calendário (só tasks com prazo), clientes (tabela agrupada Cliente > Processo > Task, padrão do snippet `getGroupedRowModel`), processos (por competência, detalhe com progresso), tarefas (board por status com cadeado de cascata) e modelos (tabela estilo TaskHub + editor em abas + preview + "gerar mês")
- Preservar isolamento por account, papéis (admin, operador, user) e auditoria das operações feitas em modo de suporte

## Capabilities

### New Capabilities
- `tenant/work-templates`: cadastro de modelos, blueprint completo com cascata, regra dinâmica (regimes + tags) com exceções, preview de elegíveis e geração (manual e agendada) de um processo por cliente por mês
- `tenant/work-processes`: processos por (modelo, cliente, competência) com status, vencimento, progresso derivado e congelamento do gerado
- `tenant/work-tasks`: tasks como etapas do processo com ciclo de vida, responsável, prazo em dia fixo, prioridade, guarda de cascata; visões de calendário (só com prazo), por cliente (agrupada) e board

### Modified Capabilities
- (nenhuma — o `Process` atual (só `name`) não tem spec vigente; suas colunas novas são aditivas e o endpoint existente continua respondendo)

## Impact

- Backend Laravel: evolução da tabela `processes` (`client_id`, `template_id`, `reference_month`, `status`, `due_on`); novas tabelas `process_templates` (+`cascade`, `generate_day`, `due_day`, `is_active`), `process_template_tasks` (+`department`, `due_day`, `priority`), `template_tag`, `template_client_exceptions`, `tasks` (sem `client_id` próprio); models, controllers tenant, policies, resources, `ProcessGenerationService`, comando `work:generate-recurrences` + agendamento diário, endpoint de preview e testes
- Frontend Nuxt: nova seção Work no sidebar, layout `work.vue`, util `workNav.ts`, composable `useWork.ts` e 5 páginas (calendário, clientes, processos, tarefas, modelos) com componentes Nuxt UI (Calendar com chips, Table com grouping do snippet, Stepper/Timeline, board com cadeado, editor em abas)
- API: novas rotas tenant (`process-templates` + blueprint + regra + exceções + preview, `processes` evoluído, `tasks`, geração manual, feed do calendário, payload agrupado); nenhuma rota existente muda de contrato
- Compatibilidade: registros atuais de `processes` (só nome) continuam válidos — processo manual sem template, sem cliente e sem mês de referência
