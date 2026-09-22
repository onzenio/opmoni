# AGENTS.md — opmoni

Monorepo sem scripts na raiz. Trabalhe dentro do pacote certo:

- `backend/` — Laravel 13, PHP `^8.3` (ver `backend/composer.json`). Regras autoritativas em `backend/AGENTS.md` — leia antes de mexer no backend, não duplique aqui.
- `frontend/` — Nuxt 4 + Vue 3 + Nuxt UI, gerenciador `pnpm@12.5.1` (`packageManager` pinado em `frontend/package.json`).
- Raiz — só `docker-compose.yml` (backend :8000, frontend :3000, postgres :5432, redis :6379, nats :4222/8222).
- `openspec/` — specs; skills em `.opencode/skills/openspec-*`.
- `.ref/` — referência somente-leitura (template dashboard + chatwoot). Não edite, não importe às cegas.

## Backend (`cd backend`)

- Setup: `composer install` + `cp .env.example .env` se faltar + `php artisan key:generate` + `php artisan migrate --force`. Atalho: `composer setup`.
- Dev: `composer dev` (= `php artisan dev`, multiplex server+queue+vite). Não use `php artisan serve` direto.
- Teste único: `php artisan test --compact --filter=NomeDoTeste` ou `vendor/bin/phpunit <path>`. Suite cheia: `composer test` (faz `config:clear` antes).
- Estilo PHP: `vendor/bin/pint --dirty --format agent` após editar PHP.
- Testes usam sqlite `:memory:` via `phpunit.xml` — não precisa de docker/postgres para testar.
- Quirk env: `.env.example` padrão é `sqlite/database`; `docker-compose.yml` sobrescreve para `pgsql` + `redis` + `nats`. Não copie valores do compose para `.env` local fora do docker.
- Criar arquivos via `php artisan make:* --no-interaction` (ex.: `php artisan make:test --phpunit Nome`).

## Frontend (`cd frontend`)

- Requer `corepack enable` uma vez; depois `pnpm install` (nunca `npm install` aqui — backend usa `npm`, frontend usa `pnpm`).
- Dev: `pnpm dev` (:3000). Build: `pnpm build`. Preview: `pnpm preview`.
- Lint: `pnpm lint` (`eslint .`). Types: `pnpm typecheck` (`nuxt typecheck`).
- Entradas reais: `app/app.vue`, `app/pages/`, `app/layouts/`, `server/api/`, `nuxt.config.ts`. Template original em `.ref/frontend/` — só consulte.

## Docker / git

- Subir tudo: `docker compose up --build` na raiz. Vite do backend espera `host 0.0.0.0` + `hmr host localhost` (ver `backend/vite.config.js`).
- Repo git ainda sem nenhum arquivo trackeado e **sem `.gitignore` na raiz** (só `backend/.gitignore` e `frontend/.gitignore`). Não commite `vendor/`, `node_modules/`, `.nuxt/`, `.output/`, `backend/.env` até o `.gitignore` raiz existir.
- Não crie docs (`*.md`) sem pedido explícito. Siga convenções dos arquivos irmãos.
