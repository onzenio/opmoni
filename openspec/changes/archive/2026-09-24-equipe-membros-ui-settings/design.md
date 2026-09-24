## Context

See proposal.md — Why. Equipe já tem diretório + departamentos e CRUD de departamento. Settings → Members é stub. Backend de membros (`AccountMemberController` + `manageMembers`) já existe.

## Goals / Non-Goals

**Goals:**

- Layout Settings-like em Equipe → Membros e Departamentos com dados reais.
- Controles admin de membros na Equipe; remover Settings Members.
- Ícones de sidebar semânticos e resolvíveis no Lucide pinado.

**Non-Goals:**

- Novas rotas ou mudanças de contrato no backend.
- Traduzir o restante de Settings.
- Convite por e-mail assíncrono.

## Decisions

### D1: Layout Settings, fonte directory (Membros)
- **Escolha:** `UPageCard` + lista; `listDirectory`; filtro departamento.
- **Alternativa recusada:** mock Settings.

### D2: Gestão admin na mesma página
- **Escolha:** `canManageMembers`; modal convidar; update/remove via API.
- **Alternativa recusada:** botões inertes.

### D3: Remover Settings Members
- **Escolha:** apagar página/nav/mock; gestão só na Equipe.

### D4: Sem mudança de backend
- Papéis UI = `admin|operador|user`.

### D5: Departamentos Settings-like
- **Escolha:** `UPageCard` + busca + lista flat (sem A–Z) + `UAvatarGroup` + `UDropdownMenu`; reusar modais existentes.
- **Alternativa recusada:** manter lista A–Z esparsa.

### D6: Ícones sidebar
- Clientes `building`; Equipe `users-round`; Monitoramento `activity`; Work `clipboard-list`; Help `life-buoy`; Departamentos filho `network`; Work/Clientes `building`; Tarefas `square-kanban`.
- **Razão:** um significado por seção; ícones presentes em `@iconify-json/lucide`.

## Risks / Trade-offs

- [Risk] Lista members sem email → Mitigation: aceito (directory).
- [Risk] Bookmarks `/settings/members` → Mitigation: rota some.
- [Trade-off] Convite exige password no create → aceito.

## Migration Plan

1. Deploy só frontend.
2. Rollback via git revert.
