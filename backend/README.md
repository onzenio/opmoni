# opmoni — backend

API Laravel 13 (PHP `^8.3`). Esqueleto inicial: model `User` + migrations padrão.

```bash
composer setup   # install + .env + key + migrate + build
composer dev     # server + queue + vite (:8000)
composer test    # suite (sqlite :memory:, sem docker)
vendor/bin/pint --dirty --format agent  # estilo após editar PHP
```

Regras do agente: ver `AGENTS.md` (raiz) e `backend/AGENTS.md`.
Env local usa `sqlite`; no Docker o compose sobrescreve para `pgsql`/`redis`/`nats`.
