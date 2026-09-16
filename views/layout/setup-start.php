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
$steps = [
    1 => 'setup.database.title',
    2 => 'setup.schema.title',
    3 => 'setup.school.title',
    4 => 'setup.year.title',
    5 => 'setup.admin.title',
];
$totalSteps = count($steps);
?>
<!DOCTYPE html>
<html lang="<?= e(currentLocale()) ?>" dir="<?= e(currentDirection()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="/assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="/assets/img/favicon-180.png">
  <title><?= e($pageTitle) ?> — <?= e(__('app.name')) ?></title>
  <?php if (currentDirection() === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
  <?php else: ?>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="n-setup-body">
<div class="n-setup-wrap">
  <div class="n-setup-brand">
    <div class="school-brand-mark school-brand-mark-logo setup-school-mark"><img src="/assets/img/hadaba-logo.png" alt=""></div>
    <div class="n-setup-app-name"><?= e(__('app.name')) ?></div>
  </div>

  <?php if ($stepNumber <= $totalSteps): ?>
    <div class="n-setup-stepper">
      <?php foreach ($steps as $n => $labelKey): ?>
        <div class="n-setup-step <?= $n < $stepNumber ? 'done' : ($n === $stepNumber ? 'active' : '') ?>">
          <div class="n-setup-step-circle">
            <?= $n < $stepNumber ? icon('check') : $n ?>
          </div>
          <div class="n-setup-step-label"><?= e(__($labelKey)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="n-setup-card n-reveal">
    <h1><?= e($pageTitle) ?></h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2">
        <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
        <span><?= e($error) ?></span>
      </div>
    <?php endif; ?>
