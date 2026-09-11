#!/bin/sh
# Nizam -- Render container entrypoint. Writes config/config.php from
# environment variables before Apache starts, the same "provisioning writes
# config.php so the Setup Wizard's own DB-connection screen is never
# reached" pattern the Windows installer already uses (packaging/
# installer.iss) -- applied here via Render's environment variables instead
# of an installer dialog. Fails loudly and immediately if a required
# variable is missing, rather than letting the app start and fail
# confusingly on its first database query.
set -e

required_vars="DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD SUPABASE_URL SUPABASE_SERVICE_ROLE_KEY"
missing=""
for var in $required_vars; do
    eval "value=\$$var"
    if [ -z "$value" ]; then
        missing="$missing $var"
    fi
done
if [ -n "$missing" ]; then
    echo "FATAL: missing required environment variable(s):$missing" >&2
    exit 1
fi

# Render assigns the container a $PORT at runtime and routes traffic to
# exactly that port (not always 80) -- Apache's own default "Listen 80" and
# the vhost's "*:80" both need to match it, substituted here rather than
# baked in at image build time since the real value isn't known until now.
PORT="${PORT:-80}"
sed -i "s/@@PORT@@/${PORT}/" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

# var_export()-safe: writing via a PHP script (not raw shell heredoc
# interpolation) means a password containing a quote or backslash can never
# produce broken or injectable PHP source -- var_export() escapes it
# correctly regardless of content.
php -r '
$config = [
    "driver"   => "pgsql",
    "host"     => getenv("DB_HOST"),
    "port"     => (int) getenv("DB_PORT"),
    "database" => getenv("DB_DATABASE"),
    "username" => getenv("DB_USERNAME"),
    "password" => getenv("DB_PASSWORD"),
    "charset"  => "utf8mb4",
];
$out = "<?php\n"
    . "/**\n"
    . " * Nizam -- written by packaging-online/entrypoint.sh from this container'\''s\n"
    . " * environment variables at startup. Never edit by hand; changes here are lost\n"
    . " * on the next deploy/restart. Real credentials -- never committed.\n"
    . " */\n"
    . "return " . var_export($config, true) . ";\n";
file_put_contents("/var/www/html/config/config.php", $out);
echo "config/config.php written.\n";
'

# Temporary runtime diagnostic for the "More than one MPM loaded" error --
# the build-time filesystem state (checked separately) showed only
# mpm_prefork enabled, yet apache2 still refuses to start with this error at
# container startup, so something differs between build time and here.
echo "=== runtime mods-enabled (mpm) ==="
ls -la /etc/apache2/mods-enabled/ | grep -i mpm || echo "(none matched)"
echo "=== apache2ctl -M ==="
apache2ctl -M 2>&1 || true
echo "=== end diagnostic ==="

exec "$@"
