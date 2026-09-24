## Why

A aba Equipe → Membros usa um diretório A–Z funcional, mas o layout preferido está em Settings → Members — stub do template (dados fake, inglês, ações mortas). Departamentos na Equipe está esparso (A–Z) e pouco alinhado ao Nuxt UI. Ícones da sidebar misturam metáforas (Clientes e Equipe ambos “pessoas”) e alguns nomes Lucide nem existem no pacote pinado.

## What Changes

- Reescrever Equipe → Membros com o **layout** de Settings/Members (`UPageCard`, busca, lista, convidar), textos pt-BR, dados do diretório real e filtro por departamento; ações admin na Equipe.
- Redesign Equipe → Departamentos com o mesmo padrão Settings-like (busca, lista densa, AvatarGroup, DropdownMenu); API/modais existentes.
- Remover Settings → Members (página, nav, mock `/api/members`, componentes órfãos).
- Ajustar ícones da sidebar (e filhos Equipe/Work) para um significado por seção, usando Lucide presente no pacote local.
- Sem mudança de contrato no backend.

## Capabilities

### New Capabilities

- `tenant/equipe-members-ui`: superfície Equipe (Membros + Departamentos UI) e ausência de Members em Settings; ícones de navegação coerentes.

### Modified Capabilities

- `tenant/member-directory`: diretório alimenta Equipe; gestão admin via endpoints `manageMembers`, acionada na Equipe.

## Impact

- Frontend: `equipe/index.vue`, `equipe/departamentos.vue`, `components/equipe/MembersList.vue`, `useMembers.ts`, `useAuth.ts`, `settings.vue`, `layouts/default.vue`, `equipeNav.ts`, `workNav.ts`; remoção do stub Settings Members.
- Backend: nenhum contrato novo.
