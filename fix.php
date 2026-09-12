<?php
\ = glob('database/migrations/01[3456]*.sql');
foreach(\ as \){
    \=file_get_contents(\);
    \=preg_replace('/INSERT INTO \ole_permissions\ \(\ole_code\, \permission_code\\) VALUES[^;]+;/s', 'INSERT INTO \ole_permissions\ (\ole_id\, \permission_id\) SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = \'admin\' AND p.code IN (\'attendance.view\', \'attendance.manage\', \'exams.view\', \'exams.manage\', \'welfare.view\', \'welfare.manage\', \'timetables.view\', \'timetables.manage\');', \);
    file_put_contents(\, \);
}
