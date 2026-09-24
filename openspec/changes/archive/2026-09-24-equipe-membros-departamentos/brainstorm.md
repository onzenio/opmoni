<!--
Raw capture of superpowers:brainstorming output.

本檔原樣捕捉 brainstorming skill 的產出，不強制結構。
Skill 的自然產出通常是 decision log 格式（背景 → 決議鏈 Q1-Qn → 設計取捨），
但依對話內容可能有不同組織方式。

design.md 從本檔萃取並重新整理為結構化設計文件。

不要將本檔的內容複製到 design.md — design.md 是獨立的重組產物，
兩者互補但不重疊。
-->

# Brainstorm — Equipe: membros legíveis + departamentos (pré-requisito do Work)

**Data:** 2026-09-24
**Origem:** conversa a partir da change `work` + referência TaskHub (`.ref/*.png`).
**Skill `superpowers:brainstorming` indisponível** — captura manual com opt-in explícito do usuário.

## Background

O Work (rotinas fiscais por cliente) precisa atribuir cada tarefa a um
responsável (membro do account) e a um departamento do escritório
(Fiscal, Pessoal, Comercial...). Hoje:

- `AccountMemberController@index/show` exige `manageMembers` (só `admin`);
  `operador` e `user` não conseguem listar quem vai executar a tarefa.
- Não existe tabela de departamento (grep `department` vazio no backend/frontend);
  a spec do Work assumiu `department` texto livre v1.
- TaskHub mostra: tarefa com `departamento (F/P)` + `responsável (Felipe Galvão)`;
  coluna Departamentos na lista de modelos; filtro por Departamento na visão tarefa;
  tela Equipe com departamentos e membros.

## Cadeia de decisões

### Q1 — Incluir departamentos no escopo pré-Work?
**Opções:** (a) só membros, departamento texto livre / (b) membros + autocomplete distinto / (c) membros + catálogo com CRUD.
**Decisão:** (c) parcial — membros + catálogo com CRUD, sem Histórico de Ações na v1.
**Motivo:** TaskHub tem tela de Departamentos com membros; texto livre apodrece
(duplicatas "Fiscal/fiscal/FISCAL") e quebra o filtro por departamento do Work.

### Q2 — Departamento é da tarefa ou do membro?
**Opções:** (a) da tarefa, membro só executa / (b) do membro, tarefa herda dele.
**Resposta do usuário:** departamento é do escritório (Fiscal, Financeiro,
Comercial, Pessoal...), cada um com processos (folha, PGDAS etc). Tela Equipe
lista: Administrativo, Comercial, Contábil, Fiscal (3 membros), Legalização,
Pessoal (2 membros), RH — todos com Felipe Galvão como membro visível.
**Decisão:** (a) — departamento é da tarefa (etapa do blueprint referencia
`department_id`); membro só executa. Membro pode estar em N departamentos.
Atribuição valida vínculo `account_user` no mesmo account.

### Q3 — Membro em quantos departamentos? Quem gerencia?
**Resposta do usuário:** vários departamentos; quem cria/edita = admin e operador.
**Decisão:** pivot `department_user` (membro em N departamentos);
`DepartmentPolicy` espelha `TagPolicy` (ler: qualquer membro; escrever: admin|operador).

## Abordagens consideradas

**A. Só diretório de membros (descartada como escopo único).**
`GET /account/members/directory` sem departamentos. Resolve atribuição de
responsável, mas deixa `department` como texto livre — recria o problema do
TaskHub (filtro e coluna Departamentos sem fonte confiável).

**B. Membros + departamentos espelho Tags (adotada).**
`departments` + `department_user`, mesmo padrão `tags` + `client_tag`
(`unique(account_id,name)`, pivot com `account_id`, `BelongsToAccount`,
Form Requests + Resources + policies + `SupportAudit::logWrite`).
Snapshot do nome na task gerada (congelamento do mês). Custo pequeno,
isola Equipe do Work e destrava o blueprint.

**C. Catálogo completo com Histórico de Ações (adiada).**
Incluiria endpoint de auditoria legível por membro + limite de plano.
Rejeitada para v1: Histórico exige novo endpoint/contrato; limite de plano
exige decisão de produto (planos hoje limitam users/clients/monitorings).

## Trade-offs de design

- Snapshot vs FK viva: task guarda `department_id nullable + department snapshot string`;
  renomear/excluir departamento não reescreve mês gerado (mesmo freeze do Work).
- Leitura vs gestão: diretório (`id,name,role,departments[]`, sem email) legível por
  qualquer membro; CRUD de membros continua só-admin. Evita vazar email e
  mantém `RolesTest::test_operador_cannot_manage_members` verde.
- Cor do departamento: enum fechado igual a tags
  (`neutral|primary|success|info|warning|error`) — evita paleta crua na UI.
- UI própria Equipe (`equipe.vue` + 2 abas), sem mexer em Settings/Clientes/Monitoramento.
