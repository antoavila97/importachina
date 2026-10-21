# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Imagen de despliegue para Render (y cualquier host con Docker).
#
# Esta en deploy/ a proposito: Railway solo usa el Dockerfile de la RAIZ y
# sigue desplegando con Nixpacks (nixpacks.toml). Render, en cambio, permite
# indicar la ruta del Dockerfile, asi que apunta a deploy/Dockerfile.
# ---------------------------------------------------------------------------

# ---------- 1. Assets (Vite + Tailwind) ----------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js postcss.config.js tailwind.config.js ./
# Se copia resources completo: Tailwind escanea resources/views/**/*.blade.php
# ademas de resources/css y resources/js.
COPY resources ./resources

RUN npm run build

# ---------- 2. Runtime (PHP) ----------
FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libicu-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer oficial, pero corriendo sobre el mismo PHP 8.3 del runtime.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Primero solo las dependencias: si cambia el codigo, esta capa se reutiliza.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

COPY . .

# Ahora que existe el codigo, se dispara package:discover (post-autoload-dump).
RUN composer dump-autoload --no-dev --optimize --no-interaction

# Assets compilados en la etapa de Node.
COPY --from=assets /app/public/build ./public/build

RUN chmod +x deploy/entrypoint.sh

EXPOSE 8000

CMD ["sh", "deploy/entrypoint.sh"]
