<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head><meta charset="UTF-8"><title><?= e(__('error.csrf')) ?></title></head>
<body style="font-family:Tahoma,Arial,sans-serif;padding:60px;text-align:center;">
  <h1><?= e(__('error.csrf')) ?></h1>
  <p><a href="/login"><?= e(__('common.back')) ?></a></p>
</body>
</html>
