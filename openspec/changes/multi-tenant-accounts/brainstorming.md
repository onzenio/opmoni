# Brainstorming — Arquitetura multi-tenant

Classificação anunciada: **architectural** (subsistema novo, sem fluxo existente para alterar; reestrutura backend e frontend).

## Contexto explorado
- Backend: esqueleto Laravel 13, sem `api.php`, sem auth, `User` só com name/email/password.
- Frontend: template Nuxt sem store/guards; `/login` e `/onboarding` com UI pronta, sem API.
- Referência `.ref/chatwoot`: users + accounts + account_users (role), SuperAdmin separado, onboarding de instalação.
- Docs oficiais Sanctum lidas: SPA first-party usa sessão+CSRF, nunca bearer token.

## Decisões fechadas (perguntas)
1. Planos → padrão sugerido: Básico 5/50/100, Profissional 20/500/1000, Empresarial ilimitado.
2. Auth → forma recomendada pelos docs oficiais (Sanctum SPA por sessão).
3. Auditoria → enter/exit + escrita, append-only.
4. Escopo → plano completo em sequência.
5. Criação de contas → só super_admin.
6. Matriz de níveis → padrão (admin total, operador CRUD recursos, user leitura).
7. Estouro de limite → bloquear (422, nada criado).
8. Poder do suporte → entra como admin, pode tudo, auditoria simples.

## Abordagens consideradas
- **A. Single-DB + `account_id`** (escolhida): simples, barata, conceito Chatwoot.
- B. Schema Postgres por conta: descartada (overkill operacional).
- C. Banco por conta: descartada (contra a simplificação pedida).

## Design por seções (todas aprovadas)
1. Modelo de dados — tabelas, observer do Básico, primeiro usuário super_admin. ✓
2. Backend — Sanctum SPA, tenant via middleware, policies, limites, suporte. ✓ (+ detalhamento das rotas `/admin`: contas, financeiro, suporte)
3. Frontend — store, guards, layout/páginas `/admin`, switcher só super_admin, banner, wiring login/onboarding. ✓
4. Testes e verificação — isolamento, 403s, auditoria, roteiro manual. ✓

## Resultado
Spec travada em `openspec/changes/multi-tenant-accounts/` via `openspec propose` (`proposal.md`, 6 specs, `design.md`, `tasks.md` — change válida). Plano de implementação detalhado em `plan.md` (10 tasks, TDD, sem placeholders).
