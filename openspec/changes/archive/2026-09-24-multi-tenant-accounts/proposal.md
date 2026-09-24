## Why

O backend é um esqueleto Laravel sem noção de conta, auth ou isolamento; o frontend não tem sessão nem painel administrativo. Para operar escritórios com dados isolados, planos de assinatura e um painel global estilo Chatwoot, é preciso introduzir tenancy por `account_id` desde já, antes que código sem tenant se espalhe.

## What Changes

- Registro/onboarding cria usuário + conta + vínculo `admin` + assinatura no plano Básico; o primeiro usuário da base vira `super_admin`
- Login/logout/sessão via Sanctum modo SPA (cookie + CSRF, sem token no client); `GET /me` retorna usuário, flag, contas e conta atual
- Contas (escritórios) com vínculos `admin|operador|user`; criação de contas só por super_admin; suspensão em vez de hard delete
- Isolamento por `account_id` com escopo global + policies; conta `suspended` bloqueia o tenant; usuário comum tem conta fixa
- Planos Básico/Profissional/Empresarial com limites; estouro bloqueia criação (422); gestão financeira no painel global
- Acesso de suporte: super_admin entra na conta com poder de admin, mantendo a identidade, com auditoria append-only (enter/exit + escritas) e banner visível
- Painel `/admin` (layout próprio): contas, planos, assinaturas, usuários, suporte
- Recursos do escritório (`clients`, `serpro_monitorings`, `documents`, `processes`) nascem já com `account_id`

## Capabilities

### New Capabilities
- `tenant/auth`: registro, login, logout, sessão Sanctum SPA e endpoint `/me`
- `tenant/accounts`: contas, vínculos, matriz de níveis, criação restrita, suspensão
- `tenant/isolation`: escopo por conta, 403s, conta fixa do usuário comum
- `tenant/subscriptions`: planos, assinatura automática, bloqueio no estouro, gestão financeira
- `tenant/support-access`: enter/exit de suporte, poder de admin, auditoria, banner
- `tenant/admin-panel`: rotas e telas do painel global do super_admin

### Modified Capabilities
- (nenhuma — `openspec/specs/` está vazio; não há comportamento existente para alterar)

## Impact

- Backend: nova dependência `laravel/sanctum`; novo `routes/api.php`; migrations para `accounts`, `account_user`, `plans`, `subscriptions`, `support_access_logs`, recursos do escritório e colunas em `users`
- Frontend: nova store `auth`, middlewares `auth`/`super-admin`, layout `admin`, páginas `/admin/*`, switcher de conta, banner de suporte; `/login` e `/onboarding` passam a chamar a API real
- Infra: config Sanctum stateful (`localhost:3000`), CORS com credentials, `SESSION_DOMAIN=localhost`; sem mudança nos services do compose
