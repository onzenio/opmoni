## 1. Backend base (models, migration, diretório)

- [x] 1.1 Registrar baseline em `feat/team-departments` (git status limpo, `composer test` verde, `pnpm lint` + `pnpm typecheck` verdes, versões anotadas) sem adicionar dependências.
- [x] 1.2 Escrever `MemberDirectoryTest` failing (admin/operador/user listam só o próprio account, ordenado por nome, sem email, com departments; outro account não vaza; `POST members` continua 403 para operador).
- [x] 1.3 Implementar `AccountPolicy::viewMembers` + `AccountMemberController::directory` + `MemberDirectoryResource` + rota `GET account/members/directory` antes do resource; verificar teste verde e `RolesTest` intacto.
- [x] 1.4 Criar migration `departments` + pivot `department_user` (FKs com cascata, uniques) via `php artisan make:migration --no-interaction`; verificar migrate/rollback em SQLite.

## 2. API de departamentos

- [x] 2.1 Escrever `DepartmentTest` failing (CRUD 201/200/403/404, 422 para nome duplicado com caixa diferente e para `member_ids` de outro account, isolamento, `user` 403 na escrita, auditoria em suporte).
- [x] 2.2 Implementar `Department` (+ `Account::departments`, `User::departments`), `DepartmentPolicy` espelho `TagPolicy`, Form Requests (`name` trim + unique por account + cor do enum + `member_ids` com `after` checando `account_user`), `DepartmentResource`, `DepartmentController` (CRUD + sync + `SupportAudit::logWrite`); verificar teste verde.
- [x] 2.3 Registrar policy em `AppServiceProvider` e `apiResource departments` em `routes/api.php`; rodar `vendor/bin/pint --dirty --format agent` e suite backend completa verde.

## 3. Frontend Equipe

- [x] 3.1 Criar `team.ts` (tipos `Department`, `MemberDirectoryEntry`), `useMembers.ts` (`listDirectory`), `useDepartments.ts` (CRUD) com `queryOf` padrão `useClients.ts`, e `equipeNav.ts` (Membros `/equipe`, Departamentos `/equipe/departamentos`); verificar `pnpm typecheck`.
- [x] 3.2 Criar shell `equipe.vue` + seção Equipe no sidebar (`layouts/default.vue`, após Clientes) + páginas `equipe/index.vue` (diretório + filtro por departamento) e `equipe/departamentos.vue` (lista A-Z + modal criar/editar + `USelectMenu` múltiplo) com estados loading/empty/error pt-BR; verificar `pnpm lint` + `pnpm typecheck`.

## 4. Verificação e release

- [x] 4.1 Rodar `vendor/bin/pint --dirty --format agent` e `composer test`; verificar formatação e todos os testes verdes.
- [x] 4.2 Rodar `pnpm lint`, `pnpm typecheck` e `pnpm build`; verificar verdes ou com baseline de falhas alheias documentada.
- [x] 4.3 Executar cenários manuais autenticados (criar Fiscal/Pessoal, vincular Felipe Galvão aos dois, listar diretório como operador e user sem email, 403 de escrita para user, isolamento entre accounts, auditoria de suporte); verificar HTTP e estado persistido conformes às specs.
