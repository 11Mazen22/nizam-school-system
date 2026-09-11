# Nizam -- Render/Docker production image (online deployment target).
#
# Base: php:8.2-apache (Debian). vendor/ is committed to the repo (composer
# is a dev-time-only tool per composer.json's own docblock -- "the school
# never runs composer install"), so this image never runs composer at all;
# it just copies the already-complete application tree and installs the
# PHP/Apache extensions that tree actually uses.
#
# What each extension is for (nothing installed speculatively):
#   pdo_pgsql -- the production database driver (App\Database's pgsql branch)
#   gd        -- UploadService's re-encode-on-upload step (photos/logo)
#   zip       -- PhpSpreadsheet's Xlsx writer (Excel report export)
#   intl      -- PhpSpreadsheet's number/date formatting
#   mbstring  -- mb_* string functions used throughout (UserService et al.)
#   curl      -- SupabaseStorageClient's REST calls to Supabase Storage

FROM php:8.2-apache

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
    && a2enmod rewrite \
    && apt-get purge -y --auto-remove \
        libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev libcurl4-openssl-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY packaging-online/apache-nizam.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .

# config/config.php is written by entrypoint.sh from environment variables
# at container start -- the same "provisioning writes config.php, the Setup
# Wizard's own DB-connection screen is never reached" pattern the Windows
# installer already uses (packaging/installer.iss), applied here instead of
# a version-controlled or image-baked file. Never commit real credentials
# into this image.
RUN rm -f config/config.php config/config.supabase.php \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod +x packaging-online/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["packaging-online/entrypoint.sh"]
CMD ["apache2-foreground"]
