## Context

Backend é esqueleto Laravel 13 sem `routes/api.php`, sem auth e sem tenant (`User` só tem name/email/password). Frontend Nuxt 4 sem store/guards; `/login` e `/onboarding` com UI pronta sem API. Ver `proposal.md` (Why) e `specs/tenant/*/spec.md` (requisitos). Infra Docker já entrega postgres/redis/nats; sessão e CORS precisam de config nova.

## Goals / Non-Goals

**Goals:**
- Tenancy lógica por `account_id` com isolamento testado
- Auth SPA por sessão seguindo os docs oficiais do Sanctum
- Painel global separado do operacional, com auditoria de suporte

**Non-Goals:**
- Cobrança recorrente real (gateway de pagamento); gestão financeira é troca de plano/status manual
- Banco/schema por conta; realtime/broadcast; 2FA; convite por email (vínculos criados direto pelo admin)

## Decisions

- **Sanctum SPA por sessão (não bearer)** — docs oficiais proíbem token para SPA first-party; ganha CSRF + sessão + `tokenCan` sempre true no first-party. Alternativa bearer descartada por contrariar os docs.
- **`is_super_admin` boolean em `users`** em vez de STI do Chatwoot — uma coluna, gate trivial, sem tabela extra.
- **`current_account_id` no user + middleware de tenant** — comum resolve da própria membership; super_admin seta via suporte (com log). Alternativa (só session) perderia persistência entre sessões.
- **Observer `Account::created` → subscription Básico** — garante a invariante "toda conta tem assinatura" independente do caminho de criação.
- **Limites em `plans.limits` json + checagem no `creating`** — limites viram dado editável no painel, não código; 422 sem efeito colateral.
- **Auditoria append-only sem update/delete** — model + ausência de rotas; simplicidade sobre rastreio de leitura (decisão fechada: enter/exit + escrita).
- **Frontend: store persistida + guards finos, backend autoridade** — guards só navegam; todo 403 real vem da API.
- **Sem hard delete de conta** — suspensão preserva dados e auditoria.

## Risks / Trade-offs

- [Sessão cross-port `:3000`→`:8000`] → Mitigação: `stateful` com porta, CORS credentials, `SESSION_DOMAIN=localhost` (same-site), smoke manual no roteiro
- [Global scope esquecido em novo model] → Mitigação: teste de isolamento por recurso + convenção documentada em `backend/AGENTS.md`
- [Super_admin com poder total é alvo sensível] → Mitigação: auditoria + sem impersonação (identidade preservada) + banner
- [`limits json` sem schema] → Mitigação: chaves conhecidas (`users,clients,monitorings`), ausente = ilimitado, validado no seed e no teste

## Migration Plan

Base sem dados de produção: migrations puras, sem backfill. Seed de `plans` via seeder dedicado. Rollback = `migrate:rollback` por batch (fase inicial, sem dados reais).

## Open Questions

- Nenhuma que altere specs, abordagem ou tasks. (Gateway de pagamento futuro e convite por email ficam para changes próprias.)
