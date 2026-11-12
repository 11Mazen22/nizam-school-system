<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

/**
 * Shared academic calculation test.
 *
 * Connection-local temporary tables shadow the real school tables, so no
 * permanent row is created, changed or deleted by this test.
 */
$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This integration fixture requires MySQL/MariaDB');
}

$definitions = [
    'grading_scales' => 'id INT AUTO_INCREMENT PRIMARY KEY, name_en VARCHAR(100), name_ar VARCHAR(100), scale_type VARCHAR(20), is_default TINYINT, description VARCHAR(255), created_at DATETIME NULL',
    'grading_scale_bands' => 'id INT AUTO_INCREMENT PRIMARY KEY, grading_scale_id INT, band_label VARCHAR(20), min_score DECIMAL(5,2), max_score DECIMAL(5,2), gpa_point DECIMAL(3,2) NULL, is_passing TINYINT, description_en VARCHAR(100) NULL, description_ar VARCHAR(100) NULL',
    'exams' => 'id INT AUTO_INCREMENT PRIMARY KEY, academic_year_id INT, name_en TEXT, name_ar TEXT, term INT, max_score DECIMAL(6,2), weight DECIMAL(6,2)',
    'exam_scores' => 'id INT AUTO_INCREMENT PRIMARY KEY, exam_id INT, student_id INT, subject_id INT, score DECIMAL(6,2) NULL, notes VARCHAR(255) NULL, recorded_by INT NULL, UNIQUE(exam_id,student_id,subject_id)',
    'students' => 'id INT PRIMARY KEY, full_name TEXT, student_code TEXT',
    'student_enrollments' => 'student_id INT, class_id INT, academic_year_id INT, status VARCHAR(20)',
    'subjects' => 'id INT PRIMARY KEY, name_en TEXT, name_ar TEXT',
    'activity_logs' => 'user_id INT, action TEXT, entity_type TEXT, entity_id INT, description TEXT, metadata TEXT, ip_address TEXT',
];
foreach ($definitions as $table => $columns) {
    $pdo->exec("CREATE TEMPORARY TABLE {$table} ({$columns}) ENGINE=InnoDB");
}

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new LogicException($message);
    }
};

// A four-band letter scale, exactly what a school would configure in
// Settings -> Academic Structure -> Grading Scales.
$pdo->exec(
    "INSERT INTO grading_scales (id,name_en,name_ar,scale_type,is_default,description,created_at)
     VALUES (1,'Letter Scale','مقياس حرفي','letter',1,NULL,NOW())"
);
$pdo->exec(
    "INSERT INTO grading_scale_bands (grading_scale_id,band_label,min_score,max_score,gpa_point,is_passing) VALUES
      (1,'A',85,100,4.00,1),
      (1,'B',70,84.99,3.00,1),
      (1,'C',50,69.99,2.00,1),
      (1,'F',0,49.99,0.00,0)"
);

$calculator = new App\Services\AcademicCalculationService();

// ------------------------------------------------------- score -> percentage
$check($calculator->percentage(45, 50) === 90.0, 'Percentage conversion is wrong');
$check($calculator->percentage(84.995, 100) === 85.0, 'Percentage rounding is inconsistent');
$check($calculator->percentage(null, 100) === null, 'A missing mark was treated as a number');
$check($calculator->percentage('', 100) === null, 'An empty mark produced a percentage');
$check($calculator->percentage(5, 0) === null, 'A zero maximum produced a percentage');
$check($calculator->percentage(5, 'not-a-number') === null, 'A non-numeric maximum produced a percentage');

// ------------------------------------------------------------- weighting
$check(
    abs($calculator->weightedPercentage([
        ['score' => 100, 'max_score' => 100, 'weight' => 1],
        ['score' => 0, 'max_score' => 100, 'weight' => 3],
    ]) - 25.0) < 0.001,
    'Exam weights are not applied'
);
$check($calculator->weightedPercentage([]) === null, 'An empty result set produced an average');
$check(
    $calculator->weightedPercentage([['score' => null, 'max_score' => 100, 'weight' => 1]]) === null,
    'An unmarked exam was counted as zero'
);
$check(
    abs($calculator->weightedPercentage([
        ['score' => 40, 'max_score' => 50, 'weight' => 2],
        ['score' => 30, 'max_score' => 20, 'weight' => 0],
    ]) - 80.0) < 0.001,
    'A zero-weight assessment influenced the average'
);

// ---------------------------------------------------- percentage -> band
$scale = $calculator->scale();
$check($scale !== null && $scale['id'] === 1, 'The configured scale was not loaded');
$check($calculator->bandFor(85.0)['band_label'] === 'A', '85 did not resolve to A');
$check($calculator->bandFor(84.99)['band_label'] === 'B', '84.99 did not resolve to B');
$check($calculator->bandFor(0.0)['band_label'] === 'F', '0 did not resolve to F');
$check($calculator->bandFor(100.1) === null, 'An out-of-range percentage resolved to a band');
$check($calculator->bandFor(-1.0) === null, 'A negative percentage resolved to a band');
$check($calculator->bandFor(null) === null, 'A missing percentage resolved to a band');

$fail = $calculator->describe(30, 100);
$check($fail['band_label'] === 'F' && $fail['is_passing'] === false, 'A failing mark resolved as passing');
$check($fail['gpa_point'] === 0.0, 'GPA point for a failing mark is wrong');

$pass = $calculator->describe(45, 50);
$check($pass['percentage'] === 90.0 && $pass['band_label'] === 'A', 'A passing mark resolved incorrectly');
$check($pass['gpa_point'] === 4.0 && $pass['is_passing'] === true, 'GPA/pass flag for a passing mark is wrong');

// ------------------------------- the same formula drives ranking and reports
$pdo->exec("INSERT INTO students VALUES (1,'Student One','S1'),(2,'Student Two','S2')");
$pdo->exec("INSERT INTO student_enrollments VALUES (1,1,1,'active'),(2,1,1,'active')");
$pdo->exec("INSERT INTO subjects (id,name_en,name_ar) VALUES (1,'Math','رياضيات')");
$pdo->exec(
    "INSERT INTO exams (id,academic_year_id,name_en,name_ar,term,max_score,weight) VALUES
      (1,1,'Quiz','اختبار',1,100,1),
      (2,1,'Midterm','نصفي',1,50,1),
      (3,1,'Final','نهائي',1,20,1)"
);

$insertScore = $pdo->prepare(
    'INSERT INTO exam_scores (exam_id,student_id,subject_id,score) VALUES (:exam,:student,:subject,:score)'
);
foreach ([
    [1, 1, 90], [2, 1, 45], [3, 1, 20],
    [1, 2, 60], [2, 2, 30], [3, 2, 10],
] as [$examId, $studentId, $score]) {
    $insertScore->execute(['exam' => $examId, 'student' => $studentId, 'subject' => 1, 'score' => $score]);
}

$service = new App\Services\ExamService();

$ranking = $service->getClassRanking(1, 1);
$check(count($ranking) === 2, 'Ranking did not include both students');
$check($ranking[0]['student_id'] === 1 && $ranking[0]['rank'] === 1, 'Ranking order is wrong');
$check(abs($ranking[0]['weighted_avg'] - 93.33) < 0.01, 'Ranking weighted average is wrong');
$check($ranking[0]['band_label'] === 'A', 'Ranking grade does not use the configured scale');
$check($ranking[1]['band_label'] === 'C', 'Second student grade does not use the configured scale');
$check($ranking[1]['is_passing'] === true, 'A passing average was marked as failing');

$sheet = $service->getScoreSheet(1, 1, 1);
$check($sheet[1]['band_label'] === 'A', 'Score sheet grade is not resolved');
$check($sheet[2]['band_label'] === 'C', 'Score sheet grade is not resolved for the second student');
$check($sheet[1]['percentage'] === 90.0, 'Score sheet percentage is wrong');

$report = $service->getStudentReport(1, 1);
$check(count($report['subjects']) === 1, 'Report did not summarise the subject');
$check(abs($report['overall']['percentage'] - 93.33) < 0.01, 'Report overall average differs from ranking');
$check($report['overall']['band_label'] === 'A', 'Report overall grade is wrong');
$check($report['subjects'][0]['band_label'] === 'A', 'Report subject grade is wrong');
$check($report['grid'][1][1]['band_label'] === 'A', 'Per-exam band was not attached');
$check($report['grid'][1][3]['percentage'] === 100.0, 'A full-mark exam was not resolved to 100%');

// A school that configures no grading scale gets "no grade", never a made-up one.
$pdo->exec('UPDATE grading_scales SET is_default = 0');
$ungraded = new App\Services\AcademicCalculationService();
$check($ungraded->scale() === null, 'A default scale still resolved after being unset');
$check($ungraded->describe(90, 100)['band_label'] === null, 'A band was invented without a configured scale');
$check($ungraded->describe(90, 100)['percentage'] === 90.0, 'The percentage was lost without a scale');

echo "PASS: percentage, weighting, band boundaries, ranking/report parity, score-sheet resolution, no-scale handling\n";
