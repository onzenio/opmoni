# syntax=docker/dockerfile:1
#
# Targets:
#   dev  (usado pelo docker-compose): toolchain + entrypoint.
#        Código, vendor e node_modules vêm dos volumes — o entrypoint
#        instala quando o volume está vazio ou o lock está mais novo.
#   prod: assets compilados, sem devDependencies.
# Build prod: docker build --target prod ./backend

FROM php:8.4-cli AS base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        curl \
        git \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath opcache pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Node só entra nos alvos que rodam Vite ou compilam assets.
FROM base AS with-node

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# ---------------- dev ----------------
FROM with-node AS dev

COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

EXPOSE 8000 5173

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "dev", "--inline"]

# ---------------- prod ----------------
FROM with-node AS prod

ENV APP_ENV=production \
    APP_DEBUG=false

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader

COPY package.json .npmrc ./
RUN npm install --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && npm prune --omit=dev

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
