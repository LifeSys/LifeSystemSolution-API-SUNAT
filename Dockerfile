FROM php:8.3-fpm-alpine AS base
# Activa el proveedor "legacy" de OpenSSL 3.x (Alpine lo trae desactivado por
# defecto). Sin esto, openssl_pkcs12_read() no puede leer certificados .p12
# antiguos cifrados con algoritmos como RC2-40-CBC, muy comunes en
# certificados digitales peruanos - falla con "digital envelope routines::unsupported".
RUN sed -i 's/^providers = provider_sect$/providers = provider_sect\n\n[provider_sect]\ndefault = default_sect\nlegacy = legacy_sect\n\n[default_sect]\nactivate = 1\n\n[legacy_sect]\nactivate = 1/' /etc/ssl/openssl.cnf \
    || printf '\n[openssl_init]\nproviders = provider_sect\n\n[provider_sect]\ndefault = default_sect\nlegacy = legacy_sect\n\n[default_sect]\nactivate = 1\n\n[legacy_sect]\nactivate = 1\n' >> /etc/ssl/openssl.cnf
# Dependencias del sistema (agregamos nodejs y npm)
RUN apk add --no-cache \
    postgresql-client \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    zip \
    unzip \
    git \
    curl \
    bash \
    nodejs \
    npm
# Extensiones PHP necesarias
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        soap \
        dom \
        gd \
        bcmath \
        intl \
        mbstring \
        opcache
# Extensión Redis (phpredis) via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
# Instalar dependencias PHP (sin scripts para que no falle sin APP_KEY)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --ignore-platform-reqs
# Copiar código fuente
COPY . .
# Autoload optimizado (necesario ANTES del build de frontend, porque wayfinder usa el autoload)
RUN composer dump-autoload --optimize
# Compilar assets del frontend (Vite + Wayfinder, que ahora sí tiene PHP disponible)
RUN npm ci && npm run build
# Permisos de storage y cache
RUN mkdir -p storage/app/public \
              storage/framework/sessions \
              storage/framework/views \
              storage/framework/cache/data \
              storage/logs \
              storage/certificates \
              bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
# Script de arranque
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh
EXPOSE 8000
CMD ["/start.sh"]
