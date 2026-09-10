<?php /** @var string|null $ref */ ?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head><meta charset="UTF-8"><title><?= e(__('error.500.title')) ?></title></head>
<body style="font-family:Tahoma,Arial,sans-serif;padding:60px;text-align:center;">
  <h1><?= e(__('error.500.title')) ?></h1>
  <p><?= e(__('error.500.body', ['ref' => $ref ?? '-'])) ?></p>
</body>
</html>
