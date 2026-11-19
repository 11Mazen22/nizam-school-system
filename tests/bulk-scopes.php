<?php

declare(strict_types=1);

/**
 * Bulk-scope and archive contract for the document engine.
 *
 * Runs against the current database, but every fixture row it needs for the
 * empty scopes (a cover date, a second academic year) is written inside a
 * transaction and rolled back. No school record is created or changed.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Middleware\AcademicYearContext;
use App\Services\Documents\BulkExportJob;
use App\Services\Documents\DocumentEngine;
use App\Services\Documents\DocumentRegistry;
use App\Services\Documents\DocumentIdentity;

$pass = 0;
$fail = 0;
$check = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        echo "  ok    {$label}\n";
        return;
    }
    $fail++;
    echo "  FAIL  {$label}\n";
};

$pdo = Database::connection();
$year = $pdo->query('SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1')->fetch();
if ($year === false) {
    fwrite(STDERR, "BLOCKED: no active academic year\n");
    exit(2);
}
AcademicYearContext::set($year);
$yearId = (int) $year['id'];

$identity = new DocumentIdentity(
    nameAr: 'مدرسة اختبار', nameEn: 'Test School', logoDataUri: null,
    ministry: '', directorate: '', administration: '', address: '', phone: '', email: '',
    yearLabel: (string) $year['label'], issueDate: date('d/m/Y'),
    locale: 'ar', showSignatures: false, showWatermark: false,
);

$registry = new DocumentRegistry();
$engine = new DocumentEngine();

echo "Bulk scopes\n";
foreach ($registry->keys() as $key) {
    $scopes = $registry->make($key)->bulkScopes([]);
    foreach ($scopes as $group) {
        $check(!empty($group['items']), "Scope '{$key}' only advertises groups that contain records");
    }
}
$check($registry->make('ranking')->bulkScopes([]) !== [], 'Ranking offers real class scopes');
$check($registry->make('timetable-gaps')->bulkScopes([]) !== [], 'Gap lists offer real class scopes');
$check($registry->make('timetable-covers')->bulkScopes([]) === [], 'Cover ZIP is hidden when no cover date exists');
$check($registry->make('promotion')->bulkScopes([]) === [], 'Promotion ZIP is hidden with only one academic year');

$archiveFor = static function (string $key, array $items, string $title) use ($engine, $identity): array {
    $root = dirname(__DIR__) . '/storage/tmp/bulk-scope-tests';
    @mkdir($root, 0775, true);
    $jobs = new BulkExportJob($root);
    $job = $jobs->create($key, [], $items, 'pdf', ['signatures' => false], $title);
    $token = $job['token'];
    try {
        for ($i = 0; $i < $job['total']; $i++) {
            $jobs->step($token, $engine);
        }
        $archive = $jobs->archive($token);
        $zip = new ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 'nizam_scope_zip_');
        file_put_contents($path, $archive['bytes']);
        $opened = $zip->open($path);
        $files = [];
        if ($opened === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $files[] = (string) $zip->getNameIndex($i);
            }
            $zip->close();
        }
        @unlink($path);
        return ['bytes' => $archive['bytes'], 'files' => $files, 'name' => $archive['filename']];
    } finally {
        $jobs->purge($token);
    }
};

echo "Archives from real scopes\n";
foreach (['ranking' => 'classes', 'timetable-gaps' => 'classes'] as $key => $groupKey) {
    $items = array_slice($registry->make($key)->bulkScopes([])[$groupKey]['items'], 0, 2);
    if ($items === []) {
        $check(false, "{$key} has at least one real scope item");
        continue;
    }
    $archive = $archiveFor($key, $items, __('document.title.' . $key));
    $check(str_starts_with($archive['bytes'], 'PK'), "{$key} bulk download is a real ZIP");
    $check(count(array_filter($archive['files'], static fn (string $f): bool => str_ends_with($f, '.pdf'))) === count($items), "{$key} archive contains one PDF per selected item");
    $check(count(array_unique($archive['files'])) === count($archive['files']), "{$key} archive file names are unique");
    $check((bool) preg_match('/[\x{0600}-\x{06FF}]/u', implode(' ', $archive['files'])), "{$key} archive keeps Arabic record names");
}

echo "Fixtures for the empty scopes (rolled back)\n";
$pdo->beginTransaction();
try {
    $coverScopeBefore = $registry->make('timetable-covers')->bulkScopes([]);
    $check($coverScopeBefore === [], 'No cover scope before the fixture');
    $slot = $pdo->query(
        'SELECT id, class_id, teacher_id FROM timetables
         WHERE academic_year_id = ' . $yearId . ' AND teacher_id IS NOT NULL LIMIT 1'
    )->fetch();
    $other = $pdo->query('SELECT id FROM teachers WHERE id <> ' . (int) ($slot['teacher_id'] ?? 0) . ' AND status = \'active\' LIMIT 1')->fetchColumn();
    if ($slot !== false && (int) $other > 0) {
        $coverDate = date('Y-m-d');
        $stmt = $pdo->prepare(
            'INSERT INTO lesson_covers
                (academic_year_id, cover_date, timetable_id, class_id, period_number, original_teacher_id, cover_teacher_id)
             VALUES (:year, :date, :slot, :class, 1, :original, :cover)'
        );
        $stmt->execute([
            'year' => $yearId, 'date' => $coverDate, 'slot' => $slot['id'],
            'class' => $slot['class_id'], 'original' => $slot['teacher_id'], 'cover' => $other,
        ]);
        $scopes = $registry->make('timetable-covers')->bulkScopes([]);
        $check(isset($scopes['dates']) && count($scopes['dates']['items']) === 1, 'A real cover date creates one bulk scope');
        $archive = $archiveFor('timetable-covers', $scopes['dates']['items'], 'أغطية الحصص');
        $check(count($archive['files']) >= 2, 'Cover archive contains the PDF and the README');
    }

    $pairsBefore = $registry->make('promotion')->bulkScopes([]);
    $check($pairsBefore === [], 'No promotion scope before the fixture');
    $pdo->prepare(
        'INSERT INTO academic_years (label, start_date, end_date, is_active, is_closed)
         VALUES (:label, :start, :end, 0, 0)'
    )->execute([
        'label' => 'Fixture 2027/2028',
        'start' => '2027-09-01',
        'end' => '2028-06-30',
    ]);
    $targetId = (int) $pdo->lastInsertId();
    $pairs = $registry->make('promotion')->bulkScopes([]);
    $check(isset($pairs['year_pairs']) && $pairs['year_pairs']['items'] !== [], 'A second year creates real promotion pairs');
    $pair = null;
    foreach ($pairs['year_pairs']['items'] as $item) {
        if ((string) $item['params']['source'] === (string) $yearId && (string) $item['params']['target'] === (string) $targetId) {
            $pair = $item;
            break;
        }
    }
    $check($pair !== null, 'The promotion pair uses the real active and fixture years');
    if ($pair !== null) {
        $archive = $archiveFor('promotion', [$pair], 'كشف الترفيع');
        $check(str_starts_with($archive['bytes'], 'PK'), 'Promotion bulk download is a real ZIP');
    }
} finally {
    $pdo->rollBack();
}

echo "\nBulk scopes: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
