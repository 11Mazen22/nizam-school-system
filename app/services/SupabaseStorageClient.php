<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Nizam -- thin wrapper around Supabase Storage's REST API, used by
 * UploadService (photos/logo) and BackupService (backup files) for the
 * online/Postgres deployment. The one thing both needed once the
 * production filesystem stopped being safe to rely on: Render's free-tier
 * container disk is ephemeral (a redeploy, or even a routine restart, can
 * wipe local files), which the offline/Windows deployment's local
 * storage/uploads and database/backups never had to consider at all.
 *
 * The service_role key used here is exactly as sensitive as the database
 * password -- server-side only, from an environment variable, NEVER
 * embedded in any page the browser loads. Every caller goes through this
 * one class specifically so that guarantee only has to be verified once.
 */
final class SupabaseStorageClient
{
    private readonly string $projectUrl;
    private readonly string $serviceRoleKey;

    /**
     * True when the environment actually carries Supabase Storage
     * credentials. This -- not "is the database Postgres?" -- is the real
     * switch for cloud storage: a Postgres deployment backed by a persistent
     * volume (e.g. the Railway-native target, whose database is Railway's own
     * Postgres) has no Supabase to upload to and keeps every file on its own
     * disk, while a genuine Supabase deployment sets both variables and gets
     * cloud storage. Deciding on the database driver alone conflated two
     * independent things.
     */
    public static function configured(): bool
    {
        $url = getenv('SUPABASE_URL');
        $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
        return is_string($url) && $url !== '' && is_string($key) && $key !== '';
    }

    public function __construct()
    {
        // configured() is the guard; these narrows exist only for the static
        // analyser -- getenv() alone also returns false for an absent var.
        $url = getenv('SUPABASE_URL');
        $key = getenv('SUPABASE_SERVICE_ROLE_KEY');
        if (!is_string($url) || !is_string($key) || !self::configured()) {
            throw new RuntimeException('supabase_storage_not_configured');
        }
        $this->projectUrl = rtrim($url, '/');
        $this->serviceRoleKey = $key;
    }

    public function upload(string $bucket, string $path, string $contents, string $contentType): void
    {
        $this->request('POST', "/storage/v1/object/{$bucket}/{$path}", $contents, [
            'Content-Type: ' . $contentType,
            'x-upsert: true',
        ]);
    }

    /** @throws RuntimeException 'not_found' if the object doesn't exist */
    public function download(string $bucket, string $path): string
    {
        return $this->request('GET', "/storage/v1/object/{$bucket}/{$path}");
    }

    public function delete(string $bucket, string $path): void
    {
        try {
            $this->request('DELETE', "/storage/v1/object/{$bucket}/{$path}");
        } catch (RuntimeException $e) {
            // Deleting something already gone is not an error for any
            // caller here -- matches UploadService::delete()'s own
            // already-established "safe no-op" contract for the local-disk
            // deployment.
            if ($e->getMessage() !== 'not_found') {
                throw $e;
            }
        }
    }

    /** @param string[] $extraHeaders */
    private function request(string $method, string $path, ?string $body = null, array $extraHeaders = []): string
    {
        $ch = curl_init($this->projectUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Authorization: Bearer ' . $this->serviceRoleKey,
                'apikey: ' . $this->serviceRoleKey,
            ], $extraHeaders),
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('storage_connection_failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 404) {
            throw new RuntimeException('not_found');
        }
        if ($status >= 400) {
            throw new RuntimeException('storage_request_failed: HTTP ' . $status);
        }
        return (string) $response;
    }
}
