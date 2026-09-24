# opmoni

Monorepo: API Laravel + dashboard Nuxt + infra Docker.

## Stack

- `backend/` — Laravel 13, PHP `^8.3` (esqueleto: só `User` + migrations padrão)
- `frontend/` — Nuxt 4, Vue 3, Nuxt UI, `pnpm@12.5.1` (dashboard em adaptação)
- Infra — `docker-compose.yml`: postgres 15, redis 7, nats 2.11

## Portas

| Serviço  | Porta |
|----------|-------|
| backend  | 8000  |
| frontend | 3000  |
| postgres | 5432  |
| redis    | 6379  |
| nats     | 4222 / 8222 (monitor) |

## Quickstart

Via Docker (na raiz):

```bash
docker compose up --build
```

Local (sem Docker):

```bash
cd backend && composer setup && composer dev   # :8000
cd frontend && corepack enable && pnpm install && pnpm dev  # :3000
```

## Env

- `backend/.env.example` padrão é `sqlite`/`database`; `docker-compose.yml` sobrescreve para `pgsql` + `redis` + `nats`. Não copie valores do compose para o `.env` local fora do docker. Testes usam sqlite `:memory:` (só para testar).
- `docker-compose.yml` fornece os serviços (`pgsql` + `redis` + `nats`). Não copie segredos de prod para o `.env` local.

## Comandos

```bash
cd backend && composer test        # suite (sqlite :memory:, sem docker)
cd backend && vendor/bin/pint --dirty --format agent  # estilo PHP
cd frontend && pnpm lint && pnpm typecheck
```

## Docs

- `AGENTS.md` (raiz) — guia do agente, comandos e quirks
- `backend/AGENTS.md` — regras Laravel (autoritativo no backend)
- `openspec/` — specs; `.ref/` — referência somente-leitura (fora do git)
