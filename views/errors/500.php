<?php
/** @var string|null $ref */
$errorCode = '500';
$errorIcon = 'alert-triangle';
$errorTone = 'danger';
$errorTitle = __('error.500.title');
$errorBody = __('error.500.body', ['ref' => $ref ?? '-']);
require __DIR__ . '/_document.php';
