## 1. Auth + composable

- [x] 1.1 Adicionar `canManageMembers` em `frontend/app/composables/useAuth.ts` (super_admin ou role `admin`) e exportá-lo; verificar via typecheck/inspect que o composable expõe o flag.
- [x] 1.2 Estender `frontend/app/composables/useMembers.ts` com `create` / `update` / `remove` (e opcionalmente `list`) contra `/account/members`, mantendo `listDirectory`; verificar tipagem e que as rotas batem com `AccountMemberController`.

## 2. UI Equipe → Membros

- [x] 2.1 Criar `frontend/app/components/equipe/MembersList.vue` a partir do layout de `SettingsMembersList` (avatar/nome, badges de departamento ou “Sem departamento”, papel read-only vs `USelect` admin|operador|user, menu remover) usando `MemberDirectoryEntry` e `canManageMembers`; verificar typecheck.
- [x] 2.2 Reescrever `frontend/app/pages/equipe/index.vue` com layout Settings (`UPageCard` + Convidar + busca + filtro departamento + lista), dados de `listDirectory`, estados loading/empty/error pt-BR, sem A–Z e sem rodapé Configurações; verificar página carrega membros reais.
- [x] 2.3 Modal Convidar (nome, email, senha, papel) só se `canManageMembers`, chamando `create` e refresh; verificar admin cria e operador não vê o botão.

## 3. UI Equipe → Departamentos

- [x] 3.1 Redesign `frontend/app/pages/equipe/departamentos.vue`: `UPageCard` + busca + lista flat com AvatarGroup + DropdownMenu; reusar modais; sem grupos A–Z; verificar typecheck e lista densa.

## 4. Sidebar ícones

- [x] 4.1 Aplicar tabela de ícones em `layouts/default.vue` e alinhar `equipeNav.ts` / `workNav.ts` (`network`, `building`, `square-kanban`); verificar que não restam `building-2` / `kanban-square` nesses arquivos.

## 5. Remover Settings Members

- [x] 5.1 Remover item Members de `settings.vue` e do submenu Settings em `layouts/default.vue`; verificar nav Settings sem Members.
- [x] 5.2 Apagar `settings/members.vue`, `SettingsMembersList.vue`, `server/api/members.ts` e limpar tipo stub `Member` se órfão; verificar `rg` sem imports quebrados.

## 6. Verificação

- [x] 6.1 Rodar `pnpm lint` e `pnpm typecheck` em `frontend/` e corrigir falhas desta change.
- [x] 6.2 Smoke: Membros (read vs admin), Departamentos (lista + CRUD), Settings sem Members, ícones sidebar distintos.
