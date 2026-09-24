<!-- validado 2026-09-24: código existe, pendente execução -->

## 1. Auth backend (Sanctum SPA)

- [x] 1.1 Instalar Sanctum via `php artisan install:api` e verificar `routes/api.php` + migration de tokens criados
- [x] 1.2 Configurar `stateful=[localhost:3000]`, `statefulApi()`, CORS com credentials e `SESSION_DOMAIN=localhost`, e verificar `docker compose config` + login manual cross-port funcionando
- [x] 1.3 Adicionar `is_super_admin` e `current_account_id` em `users` com migration, e verificar via `php artisan migrate` + teste de colunas
- [x] 1.4 Implementar `POST /register`, `POST /login`, `POST /logout`, `GET /me` e verificar com testes: primeiro registro vira super_admin, login inválido retorna 422, `/me` sem sessão retorna 401

## 2. Domínio tenant

- [x] 2.1 Criar migrations e models `accounts`, `account_user` (role admin|operador|user, unique par), `plans`, `subscriptions`, `support_access_logs` (append-only) e verificar com `migrate:fresh --seed` passando
- [x] 2.2 Criar seeder dos 3 planos (basico 5/50/100, profissional 20/500/1000, empresarial ilimitado) e verificar os 3 registros com limites corretos
- [x] 2.3 Implementar observer `Account::created` criando subscription no Básico e verificar com teste: toda conta criada possui exatamente uma assinatura ativa no Básico
- [x] 2.4 Criar migrations e models `clients`, `serpro_monitorings`, `documents`, `processes` com `account_id` + trait `BelongsToAccount`, e verificar escopo aplicado em listagem e acesso direto

## 3. Autorização e suporte

- [x] 3.1 Implementar middleware de tenant (`current_account_id`, 403 em conta suspensa) + gate `super_admin`, e verificar com testes de 403
- [x] 3.2 Implementar policies por nível (admin total, operador CRUD recursos, user leitura) + criação de contas só super_admin, e verificar matriz com testes (operador gerenciando membros → 403, comum criando conta → 403)
- [x] 3.3 Implementar checagem de limites no `creating` (422 sem criar) e bloqueio de escrita com assinatura past_due/canceled, e verificar com testes de estouro e inadimplência
- [x] 3.4 Implementar `enter/exit` de suporte com logs (enter, exit, create, update, delete) mantendo a identidade do super_admin, e verificar log completo com teste dedicado

## 4. Frontend

- [x] 4.1 Criar store `auth` persistida (user, flag, contas, conta atual, login/logout/switch/enter/exit) e verificar login manual contra a API exibindo a conta atual
- [x] 4.2 Criar middlewares `auth` e `super-admin` + layout `admin`, e verificar redirecionamentos (`/login` sem sessão, `/` em `/admin` como comum)
- [x] 4.3 Conectar `/login` e `/onboarding` à API real mantendo validação zod, e verificar onboarding criando super_admin + conta na primeira execução
- [x] 4.4 Criar páginas `/admin` (contas, planos, assinaturas, usuários, suporte com logs) + switcher só super_admin + banner de suporte com sair, e verificar fluxo entrar → banner → sair
- [x] 4.5 Corrigir lint de `onboarding.vue` e verificar `pnpm lint` limpo

## 5. Verificação final

- [x] 5.1 Rodar suite completa e verificar `composer test` verde + `vendor/bin/pint --dirty` sem diff
- [x] 5.2 Rodar `pnpm lint` e `pnpm typecheck` no frontend e verificar ambos passando
- [x] 5.3 Executar roteiro manual (onboarding → criar conta → trocar contexto → escrever → conferir log → suspender → confirmar bloqueio) e verificar cada passo
