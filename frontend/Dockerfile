# syntax=docker/dockerfile:1
#
# Targets:
#   dev  (usado pelo docker-compose): `pnpm dev` com hot-reload
#   prod: build Nuxt + `pnpm preview`
# Build prod: docker build --target prod ./frontend

FROM node:22-bookworm-slim AS base

WORKDIR /app

# Evita prompt interativo do corepack no download do pnpm pinado
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0

RUN corepack enable

# ---------------- dev ----------------
FROM base AS dev

# Manifestos primeiro para aproveitar o cache de layers: o install só
# roda de novo quando package.json/lock ou workspace mudarem.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
# postinstall (nuxt prepare) precisa do código-fonte, então é adiado
# para depois do COPY completo.
RUN pnpm install --frozen-lockfile --ignore-scripts

COPY . .
RUN pnpm run postinstall

EXPOSE 3000

CMD ["pnpm", "dev", "--host", "0.0.0.0", "--port", "3000"]

# ---------------- prod ----------------
FROM base AS prod

ENV NODE_ENV=production \
    NUXT_TELEMETRY_DISABLED=1

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile --ignore-scripts

COPY . .
RUN pnpm run postinstall \
    && pnpm build

EXPOSE 3000

CMD ["pnpm", "preview", "--host", "0.0.0.0", "--port", "3000"]
