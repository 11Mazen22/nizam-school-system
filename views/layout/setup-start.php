<?php
/**
 * Nizam -- Setup Wizard shell. Separate from views/layout/start.php (the
 * authenticated app shell): most wizard steps run before anyone is
 * authenticated and before school/settings data exists to show in a topbar,
 * so reusing the authenticated shell would mean special-casing it apart
 * rather than actually reusing it.
 *
 * @var string $pageTitle
 * @var int    $stepNumber
 */
$stepNumber = $stepNumber ?? 1;
$totalSteps = 5;
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= e(__('app.name')) ?></title>
  <?php if (currentDirection() === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <?php else: ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-light">
<div class="container" style="max-width: 560px; padding-top: 56px;">
  <div class="text-center mb-3">
    <div class="fs-4 fw-bold"><?= e(__('app.name')) ?></div>
    <div class="text-muted small"><?= e(__('setup.step_of', ['current' => $stepNumber, 'total' => $totalSteps])) ?></div>
  </div>
  <div class="progress mb-4" style="height: 6px;">
    <div class="progress-bar" role="progressbar"
         style="width: <?= (int) round($stepNumber / $totalSteps * 100) ?>%"
         aria-valuenow="<?= (int) $stepNumber ?>" aria-valuemin="0" aria-valuemax="<?= $totalSteps ?>"></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h5 mb-3"><?= e($pageTitle) ?></h1>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
