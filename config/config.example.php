<?php
/**
 * Nizam -- database connection template.
 *
 * Copy this file to config/config.php and fill in real values for this
 * installation. config.php is gitignored and never committed (§C) -- it holds
 * real credentials, this file holds none.
 *
 * Phase 3 scope note: this is the minimal array the migration/seed runners need
 * to open a PDO connection. The full Config-loading class described in §C's
 * app/ file structure ("app/helpers", core infrastructure) is Phase 4 scope --
 * building it now would be scope creep beyond Phase 3's database layer.
 *
 * 'driver' is optional and defaults to 'mysql' when omitted -- every
 * existing local/offline/Windows-packaged deployment's config.php has no
 * such key and is completely unaffected. Set it to 'pgsql' for a Postgres/
 * Supabase deployment; 'charset' is ignored in that case (Postgres is UTF-8
 * natively, no per-connection charset needed).
 */
return [
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'database'  => 'nizam',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8mb4',
    
    // Email notification settings (for automations)
    'email' => [
        'enabled'       => false,  // Set to true to enable email notifications
        'from_email'    => 'noreply@school.local',
        'from_name'     => 'Nizam School System',
        'smtp_host'     => null,   // Optional: SMTP server (e.g., smtp.gmail.com)
        'smtp_port'     => null,   // Optional: SMTP port (587 for TLS, 465 for SSL)
        'smtp_user'     => null,   // Optional: SMTP username
        'smtp_password' => null,   // Optional: SMTP password
    ],
    
    // Bearer token for scheduled tasks (GitHub Actions automation)
    'scheduled_backup_token' => 'CHANGE_THIS_TO_A_RANDOM_SECRET',
];

// Postgres/Supabase example (uncomment and remove the array above instead):
// return [
//     'driver'    => 'pgsql',
//     'host'      => 'aws-<index>-<region>.pooler.supabase.com',  // Transaction pooler host, from the Supabase dashboard -- never the direct db.<ref>.supabase.co host, which is IPv6-only on most plans
//     'port'      => 6543,
//     'database'  => 'postgres',
//     'username'  => 'postgres.<project-ref>',
//     'password'  => '',
//     'charset'   => 'utf8mb4',
// ];
