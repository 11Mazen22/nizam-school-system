<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

/**
 * Nizam -- decision #11 / §O-18 image upload handling (student/teacher
 * photos, school logo). Every layer §O-18 asked for: (1) strict MIME +
 * extension whitelist, re-encoded on upload so a disguised-executable never
 * survives as bytes worth executing; (2) a randomly generated filename,
 * never the name the browser sent; (3) storage outside any path a browser
 * can request directly -- a controller streams the file back, per §S-10 by
 * database id, never a client-supplied path (see StudentController::photo()
 * / TeacherController::photo() / SettingsController::logo()).
 *
 * Validation and re-encoding are identical for every deployment target --
 * only the final write/read/delete step branches on the active DB driver
 * (same branch already established in Database.php / MigrationService.php).
 * The mysql (Windows/offline) path writes to local disk under
 * storage/uploads/ exactly as originally built. The pgsql (online) path
 * writes to Supabase Storage instead, because Render's free-tier container
 * disk is ephemeral -- a redeploy or restart can wipe local files, which
 * the offline deployment never had to consider.
 */
final class UploadService
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB -- a photo/logo, not a document
    /** @var array<string,string> real (finfo-detected) MIME -> canonical extension */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** Supabase Storage bucket for the pgsql/online path -- private, mirrors the local storage/uploads/ directory name. */
    private const BUCKET = 'uploads';

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file one $_FILES[...] entry
     * @param 'students'|'teachers'|'school' $type subfolder under storage/uploads/
     * @return string relative path to store in the DB, e.g. "students/ab12....jpg"
     * @throws RuntimeException 'no_file'|'upload_error'|'too_large'|'invalid_type'|'not_an_image'|'write_failed'
     */
    public function store(array $file, string $type): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('no_file');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('upload_error');
        }
        // Trust filesize() over the client-declared 'size' field -- $_FILES
        // is otherwise trusted enough here because is_uploaded_file() below
        // confirms tmp_name genuinely came from this request's own upload,
        // not an attacker-supplied path.
        if (!is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > self::MAX_BYTES) {
            throw new RuntimeException('too_large');
        }

        // Real, sniffed content type -- never the client-declared 'type' field,
        // which is fully attacker-controlled and routinely wrong.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo !== false ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if ($realMime === false || !isset(self::ALLOWED_MIME[$realMime])) {
            throw new RuntimeException('invalid_type');
        }
        $extension = self::ALLOWED_MIME[$realMime];

        // getimagesize() additionally confirms the bytes actually decode as a
        // real raster image (not, say, a valid-looking header on garbage
        // data) -- belt-and-braces alongside the MIME sniff before anything
        // touches GD.
        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) {
            throw new RuntimeException('not_an_image');
        }

        // Re-encoding (decision #11's original control): decode then
        // re-save through GD, which only ever emits a clean image stream --
        // this is what neutralizes a polyglot file (valid image bytes with a
        // malicious payload appended/embedded) regardless of what container
        // tricks produced it, and strips EXIF/metadata as a side effect.
        $image = $realMime === 'image/png' ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromjpeg($file['tmp_name']);
        if ($image === false) {
            throw new RuntimeException('not_an_image');
        }

        // §O-18 layer 1: a randomly generated filename, never the name the
        // browser sent.
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = $type . '/' . $filename;

        if (self::isPgsql()) {
            // No filesystem to write to on this deployment target -- encode
            // into memory (GD writes to the output buffer when the filename
            // argument is omitted) and ship the bytes to Supabase Storage.
            ob_start();
            $written = $extension === 'png' ? imagepng($image) : imagejpeg($image, null, 90);
            $contents = ob_get_clean();
            imagedestroy($image);
            if (!$written || $contents === false || $contents === '') {
                throw new RuntimeException('write_failed');
            }
            try {
                self::storage()->upload(self::BUCKET, $relativePath, $contents, $extension === 'png' ? 'image/png' : 'image/jpeg');
            } catch (RuntimeException) {
                throw new RuntimeException('write_failed');
            }
            return $relativePath;
        }

        $dir = self::directory($type);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            imagedestroy($image);
            throw new RuntimeException('write_failed');
        }

        $absolute = $dir . '/' . $filename;
        $written = $extension === 'png'
            ? imagepng($image, $absolute)
            : imagejpeg($image, $absolute, 90);
        imagedestroy($image);

        if (!$written) {
            throw new RuntimeException('write_failed');
        }

        return $relativePath;
    }

    /** Removes a previously stored file. Only ever called with a path this class itself generated and the caller read back from its own database row -- never client input (§S-10). */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (self::isPgsql()) {
            try {
                self::storage()->delete(self::BUCKET, $relativePath);
            } catch (RuntimeException) {
                // Best-effort, matching the local-disk branch's @unlink --
                // a delete failure here must never block the caller's own
                // (already-committed) database update.
            }
            return;
        }

        $absolute = self::root() . '/' . $relativePath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * Streams a stored file back with an explicitly-set Content-Type
     * (§O-18 layer 2) -- never left for Apache to guess from the extension.
     * The caller resolves $relativePath from a database row it already
     * looked up by id; this method never accepts anything from the request
     * directly (§S-10).
     */
    public function stream(?string $relativePath): void
    {
        $contents = self::retrieve($relativePath);
        if ($contents === null) {
            \App\ErrorHandler::renderNotFound();
            return;
        }
        \App\Response::inlineFile($contents, self::contentType($relativePath));
    }

    /**
     * Raw bytes of a stored file, or null if there is none / it can't be
     * read -- for a caller that embeds the bytes itself rather than sending
     * them as the HTTP response (ExportService::logoDataUri(), which embeds
     * the school logo into an exported PDF as a data: URI). Same §S-10
     * contract as stream(): $relativePath always comes from a database row
     * the caller already looked up, never client input.
     */
    public static function retrieve(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        if (self::isPgsql()) {
            try {
                return self::storage()->download(self::BUCKET, $relativePath);
            } catch (RuntimeException) {
                return null;
            }
        }

        $absolute = self::root() . '/' . $relativePath;
        if (!is_file($absolute)) {
            return null;
        }
        return (string) file_get_contents($absolute);
    }

    private static function contentType(?string $relativePath): string
    {
        $extension = strtolower(pathinfo((string) $relativePath, PATHINFO_EXTENSION));
        return $extension === 'png' ? 'image/png' : 'image/jpeg';
    }

    private static function isPgsql(): bool
    {
        return Database::connection()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    private static function storage(): SupabaseStorageClient
    {
        return new SupabaseStorageClient();
    }

    private static function directory(string $type): string
    {
        return self::root() . '/' . $type;
    }

    /** storage/uploads -- outside public/, already unreachable by direct URL under the root deny-all .htaccess (§S-4); storage/uploads/.htaccess adds the O-18 execution-lockout as defense in depth. Local-disk (mysql) deployment only. */
    private static function root(): string
    {
        return dataPath() . '/storage/uploads';
    }
}
