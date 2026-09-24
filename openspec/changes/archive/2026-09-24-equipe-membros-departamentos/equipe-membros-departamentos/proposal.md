## Why

O Work precisa atribuir cada tarefa a um responsável e a um departamento do escritório (Fiscal, Pessoal...), mas hoje `operador` e `user` nem conseguem listar os membros (só `admin` vê) e não existe cadastro de departamento — a spec do Work assumiu texto livre, que apodrece e quebra o filtro por departamento. Resolver isso agora destrava o blueprint do Work com fonte confiável para responsável + departamento.

## What Changes

- Criar cadastro de departamentos por Account (nome único, cor do enum de tags) com vínculo N:N de membros (um membro em vários departamentos), gerenciado por `admin|operador`, leitura por qualquer membro
- Expor `GET /account/members/directory` legível por qualquer membro do tenant (`id`, `name`, `role`, `departments[]`, sem `email`), mantendo o CRUD de membros só-`admin` intacto
- Criar seção Equipe no frontend (Membros + Departamentos) com modal de criar/editar e atribuição de membros via `USelectMenu` múltiplo
- Registrar snapshot do nome do departamento na task gerada pelo Work (decisão de design; implementação no Work, não aqui)

## Capabilities

### New Capabilities
- `tenant/team-departments`: cadastro de departamentos do escritório com vínculo de membros, espelhando o padrão Tags
- `tenant/member-directory`: diretório de membros legível por qualquer membro do tenant para atribuição de responsáveis

### Modified Capabilities
- (nenhuma — nenhum REQUIREMENT vigente muda; `AccountPolicy` ganha abilidade aditiva `viewMembers`, sem alterar `manageMembers`)

## Impact

- Backend Laravel: migration `departments` + pivot `department_user`; models `Department` (+ relações em `Account`/`User`); `DepartmentPolicy` espelho `TagPolicy`; Form Requests + Resources + `DepartmentController` (CRUD + sync `member_ids` + `SupportAudit::logWrite`); abilidade `viewMembers` em `AccountPolicy` + método `directory()` em `AccountMemberController`; rotas `GET account/members/directory` (antes do resource) + `apiResource departments`
- Frontend Nuxt: seção Equipe no sidebar, shell `equipe.vue`, `equipeNav.ts`, composables `useMembers.ts`/`useDepartments.ts`, tipos `team.ts`, páginas `equipe/index.vue` (diretório) + `equipe/departamentos.vue`
- API: novas rotas tenant (`departments` CRUD, `account/members/directory`); nenhuma rota existente muda de contrato
- Migração: tabelas novas, aditivas; nenhum dado existente é tocado
