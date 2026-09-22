# syntax=docker/dockerfile:1
#
# Targets:
#   dev  (usado pelo docker-compose): `php artisan dev` com hot-reload
#   prod: assets compilados, sem devDependencies
# Build prod: docker build --target prod ./backend

FROM php:8.4-cli AS base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

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

# ---------------- dev ----------------
FROM base AS dev

# Manifestos primeiro para aproveitar o cache de layers: o install só
# roda de novo quando composer.json/lock ou package.json mudarem.
COPY composer.json composer.lock ./
# artisan ainda não existe neste ponto, então instala sem rodar scripts;
# o autoload final roda após o COPY completo, com o código presente.
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress

COPY package.json .npmrc ./
RUN npm install --no-audit --no-fund

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

EXPOSE 8000 5173

CMD ["php", "artisan", "dev", "--inline"]

# ---------------- prod ----------------
FROM base AS prod

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
