<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

/**
 * Academic configuration integration test.
 *
 * Connection-local temporary tables shadow the real school tables, so no
 * permanent row is created, changed or deleted by this test.
 */
$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This integration fixture requires MySQL/MariaDB');
}

$definitions = [
    'educational_stages' => 'id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30), name_en VARCHAR(60), name_ar VARCHAR(60), sort_order INT, is_active TINYINT',
    'grades' => 'id INT PRIMARY KEY, name_en VARCHAR(60), name_ar VARCHAR(60), sort_order INT, stage_id INT NULL, is_active TINYINT',
    'academic_years' => 'id INT PRIMARY KEY, label VARCHAR(20), start_date DATE, end_date DATE, is_active TINYINT, is_closed TINYINT',
    'academic_terms' => 'id INT AUTO_INCREMENT PRIMARY KEY, academic_year_id INT, name_en VARCHAR(60), name_ar VARCHAR(60), term_number INT, start_date DATE, end_date DATE, is_active TINYINT, created_at DATETIME NULL',
    'grading_scales' => 'id INT AUTO_INCREMENT PRIMARY KEY, name_en VARCHAR(100), name_ar VARCHAR(100), scale_type VARCHAR(20), is_default TINYINT, description VARCHAR(255), created_at DATETIME NULL',
    'grading_scale_bands' => 'id INT AUTO_INCREMENT PRIMARY KEY, grading_scale_id INT, band_label VARCHAR(20), min_score DECIMAL(5,2), max_score DECIMAL(5,2), gpa_point DECIMAL(3,2) NULL, is_passing TINYINT, description_en VARCHAR(100) NULL, description_ar VARCHAR(100) NULL',
    'activity_logs' => 'user_id INT, action TEXT, entity_type TEXT, entity_id INT, description TEXT, metadata TEXT, ip_address TEXT',
];
foreach ($definitions as $table => $columns) {
    $pdo->exec("CREATE TEMPORARY TABLE {$table} ({$columns}) ENGINE=InnoDB");
}

$pdo->exec("INSERT INTO grades VALUES (1,'Grade 1','الصف الأول',1,NULL,1),(2,'Grade 2','الصف الثاني',2,NULL,1)");
$pdo->exec("INSERT INTO academic_years VALUES (1,'2025/2026','2025-09-01','2026-06-30',1,0)");

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new LogicException($message);
    }
};

$service = new App\Services\AcademicConfigService();
$repo = new App\Repositories\AcademicConfigRepository();

// ---------------------------------------------------------------- Stages
$created = $service->saveStage(null, 'primary', 'Primary', 'المرحلة الابتدائية', 1, true);
$check($created['ok'] === true, 'A valid stage was rejected');
$stageId = (int) $created['id'];

$duplicate = $service->saveStage(null, 'PRIMARY', 'Duplicate', 'مكرر', 2, true);
$check($duplicate['ok'] === false && $duplicate['error'] === 'structure.error.code_taken', 'Duplicate stage code accepted');

$badCode = $service->saveStage(null, 'bad code!', 'Bad', 'سيئ', 3, true);
$check($badCode['ok'] === false && $badCode['error'] === 'structure.error.code', 'Malformed stage code accepted');

$empty = $service->saveStage(null, 'middle', '', 'متوسط', 2, true);
$check($empty['ok'] === false && $empty['error'] === 'validation.required', 'Stage without a name accepted');

// A stage with grades in it must not be deletable.
$repo->assignGradeStages([1 => $stageId]);
$stage = $repo->findStage($stageId);
$check((int) $stage['grade_count'] === 1, 'Grade assignment was not persisted');
$blocked = $service->deleteStage($stageId);
$check($blocked['ok'] === false && $blocked['error'] === 'structure.stage.delete_blocked', 'Stage with grades was deleted');

// Unassign, then deletion becomes possible.
$repo->assignGradeStages([1 => null]);
$check((int) $repo->findStage($stageId)['grade_count'] === 0, 'Grade was not unassigned');
$deleted = $service->deleteStage($stageId);
$check($deleted['ok'] === true, 'An empty stage could not be deleted');

// ----------------------------------------------------------------- Terms
$term = $service->saveTerm(null, 1, 'Term 1', 'الفصل الأول', 1, '2025-09-01', '2025-12-31', true, '2025-09-01', '2026-06-30');
$check($term['ok'] === true, 'A valid term was rejected');

$overlap = $service->saveTerm(null, 1, 'Term 2', 'الفصل الثاني', 2, '2025-12-01', '2026-03-31', true, '2025-09-01', '2026-06-30');
$check($overlap['ok'] === false && $overlap['error'] === 'structure.terms.overlap', 'Overlapping term accepted');

$outside = $service->saveTerm(null, 1, 'Term 3', 'الفصل الثالث', 3, '2026-06-01', '2026-09-01', true, '2025-09-01', '2026-06-30');
$check($outside['ok'] === false && $outside['error'] === 'structure.terms.outside_year', 'Term outside the academic year accepted');

$duplicateNumber = $service->saveTerm(null, 1, 'Term 1 again', 'فصل أول', 1, '2026-01-01', '2026-02-01', true, '2025-09-01', '2026-06-30');
$check($duplicateNumber['ok'] === false && $duplicateNumber['error'] === 'structure.terms.number_taken', 'Duplicate term number accepted');

// A school may define three terms, a semester model, or quarters.
$second = $service->saveTerm(null, 1, 'Term 2', 'الفصل الثاني', 2, '2026-01-01', '2026-03-31', true, '2025-09-01', '2026-06-30');
$check($second['ok'] === true, 'A second non-overlapping term was rejected');
$third = $service->saveTerm(null, 1, 'Term 3', 'الفصل الثالث', 3, '2026-04-01', '2026-06-30', true, '2025-09-01', '2026-06-30');
$check($third['ok'] === true, 'A third non-overlapping term was rejected');
$check(count($repo->terms(1)) === 3, 'Term list did not contain all three configured terms');

// Exactly one current term per year.
$service->activateTerm((int) $second['id']);
$activeTerms = array_values(array_filter($repo->terms(1), static fn (array $row): bool => (int) $row['is_active'] === 1));
$check(count($activeTerms) === 1 && (int) $activeTerms[0]['id'] === (int) $second['id'], 'Current term was not unique');

$deletedTerm = $service->deleteTerm((int) $third['id']);
$check($deletedTerm['ok'] === true && count($repo->terms(1)) === 2, 'Term deletion did not take effect');

// --------------------------------------------------------------- Grading
$bands = [
    ['label' => 'A', 'min' => '85', 'max' => '100', 'gpa' => '4.00', 'passing' => true],
    ['label' => 'B', 'min' => '70', 'max' => '84.99', 'gpa' => '3.00', 'passing' => true],
    ['label' => 'F', 'min' => '0', 'max' => '69.99', 'gpa' => '0.00', 'passing' => false],
];
$scale = $service->saveScale(null, 'Letter Scale', 'مقياس حرفي', 'letter', 'School letter grades', true, $bands);
$check($scale['ok'] === true, 'A valid grading scale was rejected');
$scaleId = (int) $scale['id'];

$stored = $repo->bands($scaleId);
$check(count($stored) === 3, 'Bands were not persisted');
$check($stored[0]['band_label'] === 'A' && (int) $stored[0]['is_passing'] === 1, 'Highest band or pass flag is wrong');
$check($stored[2]['band_label'] === 'F' && (int) $stored[2]['is_passing'] === 0, 'Failing band flag is wrong');
$check((int) $repo->defaultScale()['id'] === $scaleId, 'First scale was not recorded as default');

$overlapping = $service->saveScale(null, 'Overlapping', 'متداخل', 'percentage', '', false, [
    ['label' => 'X', 'min' => '0', 'max' => '60', 'gpa' => null, 'passing' => true],
    ['label' => 'Y', 'min' => '50', 'max' => '100', 'gpa' => null, 'passing' => true],
]);
$check($overlapping['ok'] === false && $overlapping['error'] === 'structure.grading.overlap', 'Overlapping bands accepted');

$inverted = $service->saveScale(null, 'Inverted', 'معكوس', 'percentage', '', false, [
    ['label' => 'X', 'min' => '80', 'max' => '20', 'gpa' => null, 'passing' => true],
]);
$check($inverted['ok'] === false && $inverted['error'] === 'structure.grading.invalid_band', 'Inverted band accepted');

$badType = $service->saveScale(null, 'Typed', 'نوع', 'curved', '', false, $bands);
$check($badType['ok'] === false && $badType['error'] === 'structure.error.scale_type', 'Unknown scale type accepted');

// A second scale can exist, but only one is default at a time.
$secondScale = $service->saveScale(null, 'GPA Scale', 'مقياس معدل', 'gpa', '', false, [
    ['label' => 'A', 'min' => '90', 'max' => '100', 'gpa' => '4.00', 'passing' => true],
    ['label' => 'B', 'min' => '0', 'max' => '89.99', 'gpa' => '3.00', 'passing' => true],
]);
$check($secondScale['ok'] === true, 'A second scale was rejected');
$service->makeDefault((int) $secondScale['id']);
$defaults = array_values(array_filter($repo->scales(), static fn (array $row): bool => (int) $row['is_default'] === 1));
$check(count($defaults) === 1 && (int) $defaults[0]['id'] === (int) $secondScale['id'], 'Default scale was not exclusive');

$blockedDelete = $service->deleteScale((int) $secondScale['id']);
$check($blockedDelete['ok'] === false && $blockedDelete['error'] === 'structure.grading.delete_blocked', 'Default scale was deleted');

// Replacing the band set must not leave the previous set behind.
$service->saveScale($scaleId, 'Letter Scale', 'مقياس حرفي', 'letter', '', false, [
    ['label' => 'P', 'min' => '50', 'max' => '100', 'gpa' => '2.00', 'passing' => true],
    ['label' => 'F', 'min' => '0', 'max' => '49.99', 'gpa' => '0.00', 'passing' => false],
]);
$check(count($repo->bands($scaleId)) === 2, 'Replacing bands left stale rows behind');

echo "PASS: stage lifecycle, grade linkage, multi-term calendars, overlap/range rules, exclusive default scale, band replacement\n";
