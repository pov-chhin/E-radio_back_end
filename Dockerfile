FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY src ./src
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY src ./src
# --no-scripts: post-autoload-dump runs `artisan package:discover`, which boots
# the app; SettingServiceProvider queries the DB on boot and there is none here.
# The package manifest is rebuilt on first boot in the final image instead.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.5.12-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ffmpeg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        python3 \
        python3-pip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pcntl pdo_mysql zip \
    && php -m | grep -qi 'zend opcache' \
    && a2enmod rewrite headers expires \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

# Apache's default mod_deflate list leaves JSON out, so every API response went out uncompressed
# (/api/home alone is ~48 KB, about 5.5 KB gzipped) — slow on the long, lossy mobile path to this server.
RUN a2enmod deflate \
    && printf '<IfModule mod_deflate.c>\n    AddOutputFilterByType DEFLATE application/json application/xml\n</IfModule>\n' \
        > /etc/apache2/conf-available/compress-json.conf \
    && a2enconf compress-json

# YouTube episodes (Src\Catalog\Jobs\FetchYoutubeAudio): yt-dlp fetches the audio, FFmpeg (above) shrinks it.
# yt-dlp needs a JavaScript runtime to get past YouTube's challenge — without one YouTube answers 403. Deno is the one
# it prefers and Node (tested working) is the fallback; both are copied in as bare binaries from their own images
# (no npm). The challenge solver scripts (yt-dlp-ejs) only come with the "default" extra, so install that: a plain
# `pip install yt-dlp` leaves them out. Bookworm's pip refuses system-wide installs unless told otherwise. YouTube
# changes often, and an old yt-dlp fails with "An extractor error has occurred (KeyError('INNERTUBE_CONTEXT'))" and the
# like. The ADD below is the cache-buster: PyPI's description of yt-dlp changes with every release, so Docker re-runs
# the install below on the first rebuild after a new release instead of reusing a layer with the old version.
# bgutil-ytdlp-pot-provider is yt-dlp's plugin that asks a separate "PO token" server (YT_DLP_POT_BASE_URL, the
# brainicism/bgutil-ytdlp-pot-provider container) for the proof-of-browser token YouTube wants for some downloads;
# keep its version equal to that container's tag.
COPY --from=denoland/deno:bin /deno /usr/local/bin/deno
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
ADD https://pypi.org/pypi/yt-dlp/json /tmp/yt-dlp-latest.json
RUN pip3 install --no-cache-dir --break-system-packages --upgrade "yt-dlp[default]" "bgutil-ytdlp-pot-provider==2.0.2" \
    && rm -f /tmp/yt-dlp-latest.json \
    && deno --version | head -1 \
    && node --version \
    && pip3 show yt-dlp-ejs | head -2 \
    && yt-dlp --version
# yt-dlp keeps what it learns about YouTube's player here (www-data has no home folder to write to).
ENV XDG_CACHE_HOME=/var/www/html/storage/framework/cache

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/99-eradio.ini
COPY docker/entrypoint.sh /usr/local/bin/eradio-entrypoint

RUN chmod +x /usr/local/bin/eradio-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80

ENTRYPOINT ["eradio-entrypoint"]
CMD ["apache2-foreground"]
