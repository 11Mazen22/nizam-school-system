# Nizam -- Docker production image for the online deployment target
# (Railway; also works unmodified on Render or any other Dockerfile-based
# host -- nothing here is platform-specific).
#
# Base: PHP 8.3 + Apache on Debian Bookworm. The multi-architecture image is
# digest-pinned so a rebuild cannot silently pick up a different runtime;
# updating it is an explicit, reviewable dependency change. The offline
# package keeps vendor/ committed so a school installer never needs network
# access; the online image independently rebuilds vendor/ from composer.lock
# in a build-only stage. That guarantees the deployed dependencies match the
# reviewed lock file even if a developer's local vendor directory is stale.
#
# What each extension is for (nothing installed speculatively):
#   pdo_pgsql -- the production database driver (App\Database's pgsql branch)
#   gd        -- UploadService's re-encode-on-upload step (photos/logo)
#   zip       -- PhpSpreadsheet's Xlsx writer (Excel report export)
#   intl      -- PhpSpreadsheet's number/date formatting
#   mbstring  -- mb_* string functions used throughout (UserService et al.)
#   curl      -- SupabaseStorageClient's REST calls to Supabase Storage
#   opcache   -- immutable production bytecode cache
#
# The -dev packages below are deliberately NOT purged after
# docker-php-ext-install: found live (a real failed deploy, not a guess)
# that apt's --auto-remove sweeps away the RUNTIME shared libraries
# (libpq.so.5, libpng16.so.16, etc.) right along with the -dev headers,
# since nothing else on the image depends on them once the -dev metapackage
# is gone -- every compiled extension then fails to load at PHP startup.
# Keeping the -dev packages costs a modest amount of image size; that's a
# better trade than a container that boots with half its extensions silently
# missing.
#
# mod_php requires mpm_prefork specifically (not thread-safe). Fixing this
# at build time here was tried and found NOT to stick -- the base image's
# own mpm_event.load/.conf reappear by container start regardless, most
# likely a BuildKit layer-caching quirk on this pipeline rather than
# anything this RUN step actually does wrong. The real, guaranteed fix lives
# in entrypoint.sh instead, applied fresh on every container start against
# whatever the live filesystem actually is at that moment -- see its own
# comment for the full story.

FROM composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer-bin

FROM php:8.3-apache-bookworm@sha256:d60f7be79a869deb1562b6ecb2f555bccb65c8dd4af930b50ae4d14d4bd4696c AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libcurl4-openssl-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        gd \
        zip \
        intl \
        mbstring \
        curl \
        opcache \
    && a2enmod rewrite headers expires deflate \
    && printf '%s\n' 'ServerName nizam.local' > /etc/apache2/conf-available/nizam-server-name.conf \
    && a2enconf nizam-server-name \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS vendor-builder

WORKDIR /build
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

FROM php-base AS runtime

COPY packaging-online/apache-nizam.conf /etc/apache2/sites-available/000-default.conf
COPY packaging-online/php-production.ini /usr/local/etc/php/conf.d/zz-nizam-production.ini

WORKDIR /var/www/html
COPY . .
COPY --from=vendor-builder /build/vendor ./vendor

# config/config.php is written by entrypoint.sh from environment variables
# at container start -- the same "provisioning writes config.php, the Setup
# Wizard's own DB-connection screen is never reached" pattern the Windows
# installer already uses (packaging/installer.iss), applied here instead of
# a version-controlled or image-baked file. Never commit real credentials
# into this image.
#
# vendor/mpdf/mpdf/tmp is chowned too, not just storage/: found live, PDF
# export 500'd with "Temporary files directory ... is not writable" --
# mpdf/mpdf writes its own cache/temp files under its own package directory,
# which COPY . left owned by root while Apache's worker runs as www-data.
RUN rm -f config/config.php config/config.supabase.php \
    && chown -R www-data:www-data /var/www/html/storage \
    && mkdir -p vendor/mpdf/mpdf/tmp \
    && chown -R www-data:www-data vendor/mpdf/mpdf/tmp \
    && chmod +x packaging-online/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["packaging-online/entrypoint.sh"]
CMD ["apache2-foreground"]
