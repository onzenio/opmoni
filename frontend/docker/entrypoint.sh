#!/bin/sh
set -eu

cd /app

if [ ! -d node_modules/nuxt ] \
    || [ package.json -nt node_modules/.modules.yaml ] \
    || [ pnpm-lock.yaml -nt node_modules/.modules.yaml ] \
    || [ pnpm-workspace.yaml -nt node_modules/.modules.yaml ]; then
    pnpm install --frozen-lockfile
fi

exec "$@"
