<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

// Connection-local temporary tables shadow school tables; no permanent data is changed.
$pdo = App\Database::connection();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('This integration fixture requires MySQL/MariaDB');
}
$definitions = [
    'grades' => 'id INT, name_en TEXT, name_ar TEXT',
    'classes' => 'id INT, grade_id INT, academic_year_id INT',
    'students' => 'id INT, full_name TEXT, student_code TEXT',
    'student_enrollments' => 'student_id INT, class_id INT, academic_year_id INT, status VARCHAR(20)',
    'subjects' => 'id INT, name_en TEXT, name_ar TEXT',
    'teacher_assignments' => 'class_id INT, academic_year_id INT, subject_id INT, status VARCHAR(20)',
    'exams' => 'id INT, academic_year_id INT, max_score DECIMAL(5,2)',
    'exam_scores' => 'id INT AUTO_INCREMENT PRIMARY KEY, exam_id INT, student_id INT, subject_id INT, score DECIMAL(5,2), notes VARCHAR(255), recorded_by INT, UNIQUE(exam_id,student_id,subject_id)',
    'activity_logs' => 'user_id INT, action TEXT, entity_type TEXT, entity_id INT, description TEXT, metadata TEXT, ip_address TEXT',
];
foreach ($definitions as $table => $columns) {
    $pdo->exec("CREATE TEMPORARY TABLE {$table} ({$columns}) ENGINE=InnoDB");
}
$pdo->exec("INSERT INTO grades VALUES (1,'Grade','صف')");
$pdo->exec('INSERT INTO classes VALUES (1,1,1)');
$pdo->exec("INSERT INTO students VALUES (1,'First','T1'),(2,'Second','T2')");
$pdo->exec("INSERT INTO student_enrollments VALUES (1,1,1,'active'),(2,1,1,'active')");
$pdo->exec("INSERT INTO subjects VALUES (1,'Math','رياضيات'),(2,'Science','علوم')");
$pdo->exec("INSERT INTO teacher_assignments VALUES (1,1,1,'active'),(1,1,2,'active')");
$pdo->exec('INSERT INTO exams VALUES (1,1,100)');
App\Middleware\AcademicYearContext::set(['id' => 1, 'is_closed' => false]);
$service = new App\Services\ExamService();
$repo = new App\Repositories\ExamRepository();
$check = static function (bool $ok, string $message): void {
    if (!$ok) throw new LogicException($message);
};
$service->saveScores(1,1,[1=>['subject_id'=>1,'score'=>75]],null);
$service->saveScores(1,1,[1=>['subject_id'=>2,'score'=>88]],null);
$math = $repo->scoresForExamClass(1,1,1);
$science = $repo->scoresForExamClass(1,1,2);
$check(count($math) === 2 && $math[2]['score'] === null, 'Missing-score student disappeared');
$check((float)$math[1]['score'] === 75.0 && (float)$science[1]['score'] === 88.0, 'Subject scores mixed');
foreach ([
    [2=>['subject_id'=>1,'score'=>101]],
    [2=>['subject_id'=>1,'score'=>-1]],
    [2=>['subject_id'=>1,'score'=>'invalid']],
    [2=>['subject_id'=>99,'score'=>5]],
    [99=>['subject_id'=>1,'score'=>5]],
    [2=>['subject_id'=>1,'score'=>[5]]],
] as $invalid) {
    try {
        $service->saveScores(1,1,[1=>['subject_id'=>1,'score'=>99]] + $invalid,null);
        throw new LogicException('Invalid batch accepted');
    } catch (RuntimeException $expected) {
        $check($expected->getMessage() === 'validation_error', 'Unexpected validation failure');
    }
    $check((float)$repo->scoresForExamClass(1,1,1)[1]['score'] === 75.0, 'Rejected batch changed a score');
}
// Force an actual database failure on row two and verify row one rolls back.
try {
    $service->saveScores(1,1,[1=>['subject_id'=>1,'score'=>99],2=>['subject_id'=>1,'score'=>50,'notes'=>str_repeat('x',300)]],null);
    throw new LogicException('Expected database failure');
} catch (PDOException $expected) {
    $check((float)$repo->scoresForExamClass(1,1,1)[1]['score'] === 75.0, 'Database failure left a partial save');
}
echo "PASS: subject isolation, full roster, six invalid batches, database rollback\n";
