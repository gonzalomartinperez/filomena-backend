###############################################################################
# 0) Parámetros de versión
###############################################################################
ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

#########################################################################################
# 1) Base – Imagen de PHP con extensiones y herramientas comunes a todos los entornos
#########################################################################################
FROM dunglas/frankenphp:php${PHP_VERSION} AS base

# Re-exponer los ARG dentro de la etapa
ARG PHP_VERSION
ARG NODE_VERSION

# 1.1 Instalar extensiones de PHP
RUN install-php-extensions \
      pcntl opcache pdo_mysql pdo_pgsql pgsql intl zip bcmath soap exif redis imagick gd

# 1.2 Instalar utilidades de sistema y dependencias de Chromium (para Puppeteer)
RUN --mount=type=cache,target=/var/cache/apt \
    apt-get update && apt-get install -y --no-install-recommends \
      supervisor imagemagick ghostscript \
      chromium \
      libx11-xcb1 libxcomposite1 libasound2 libatk1.0-0 libatk-bridge2.0-0 \
      libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgbm1 \
      libglib2.0-0 libgtk-3-0 libnspr4 libpango-1.0-0 libpangocairo-1.0-0 \
      libx11-6 libxcb1 libxcursor1 libxdamage1 libxext6 libxfixes3 libxi6 \
      libxrandr2 libxrender1 libxss1 libxtst6 \
      fonts-liberation fonts-noto fonts-noto-cjk fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /etc/supervisor/conf.d /var/log/supervisor

# 1.3 Configuración de Supervisor para manejo de colas y jobs
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/octane.conf      /etc/supervisor/conf.d/octane.conf
COPY docker/supervisor/horizon.conf     /etc/supervisor/conf.d/horizon.conf
COPY docker/supervisor/scheduler.conf   /etc/supervisor/conf.d/scheduler.conf

# Directorio de trabajo por defecto
WORKDIR /app

###############################################################################
# 2) Deps – Instalación de dependencias Composer y Node
###############################################################################
FROM base AS deps

# 2.1 Descargar 'composer' e instalar dependencias de PHP
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer \
    && composer self-update --stable \
    && composer install \
       --prefer-dist \
       --optimize-autoloader \
       --classmap-authoritative \
       --apcu-autoloader \
       --no-interaction \
       --no-progress \
       --no-scripts

# 2.2 Instalar Node + npm para compilar assets
RUN --mount=type=cache,target=/var/cache/apt \
    apt-get update \
 && apt-get install -y --no-install-recommends \
      curl gnupg ca-certificates git unzip \
 && curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && npm install -g npm@latest \
 && ln -sf /usr/lib/node_modules/npm/bin/npm-cli.js /usr/bin/npm \
 && ln -sf /usr/lib/node_modules/npm/bin/npx-cli.js /usr/bin/npx \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# 2.2.1 Instalar dependencias de Node específicas para manejo de PDFs (Puppeteer)
RUN npm install -g puppeteer@latest \
 && npx puppeteer browsers install chrome

# 2.3 Copiar el resto del código fuente
COPY --link . .

# 2.4 Optimizar autoload
RUN composer dump-autoload --optimize --apcu --no-scripts

###############################################################################
# 3) Development – Contenedor con herramientas de desarrollo activas
###############################################################################
FROM deps AS development

# 3.1 Variables para entorno de desarrollo
ENV APP_ENV=local \
    APP_DEBUG=1 \
    NODE_PATH=/usr/lib/node_modules \
    PUPPETEER_CACHE_DIR=/root/.cache/puppeteer

# 3.2 Configurar php.ini optimizado para desarrollo
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini \
 && printf '\nopcache.validate_timestamps=1\nopcache.revalidate_freq=1\n' > /usr/local/etc/php/conf.d/opcache-dev.ini

# 3.3 Entrypoint general dinámico
COPY docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 3.4 Exponer puerto HTTP y sobrescribir entrypoint
EXPOSE 8000
ENTRYPOINT ["/entrypoint.sh"]

###############################################################################
# 4) Production – Imagen final ligera sin herramientas de desarrollo
###############################################################################
FROM base AS production

# 4.1 Variables para entorno de producción
ENV APP_ENV=production \
    APP_DEBUG=0 \
    NODE_PATH=/usr/lib/node_modules \
    PUPPETEER_CACHE_DIR=/root/.cache/puppeteer

# 4.2 php.ini optimizado para producción
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
 && printf '\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/opcache-prod.ini

# 4.3 Copiar la aplicación y runtime de Node
COPY --from=deps /app                    /app
COPY --from=deps /usr/bin/node           /usr/bin/
COPY --from=deps /usr/lib/node_modules   /usr/lib/node_modules

RUN ln -sf /usr/lib/node_modules/npm/bin/npm-cli.js  /usr/bin/npm  && \
    ln -sf /usr/lib/node_modules/npm/bin/npx-cli.js  /usr/bin/npx

COPY --from=deps /root/.cache/puppeteer  /root/.cache/puppeteer

# 4.4 Limpiar caches y archivos temporales innecesarios
RUN rm -rf /root/.composer

# 4.5 Entrypoint general dinámico
COPY docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 4.6 Exponer puerto HTTP y sobrescribir entrypoint
EXPOSE 8000
ENTRYPOINT ["/entrypoint.sh"]
