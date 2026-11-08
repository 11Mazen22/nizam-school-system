<?php
/** @var array $books */
$pageTitle = __('library.title');
$activeNav = 'library';
require dirname(__DIR__) . '/layout/start.php';
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('book-open', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('library.title')) ?>
    </h1>
    <p class="text-muted small mb-0"><?= e(__('library.subtitle')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="/library/borrows" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('clipboard-list') ?>
      <span><?= e(__('library.borrows_desk')) ?></span>
    </a>
    <?php if (hasPermission('library.manage')): ?>
      <a href="/library/create" class="btn btn-primary d-inline-flex align-items-center gap-2">
        <?= icon('plus') ?>
        <span><?= e(__('library.add_book')) ?></span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if (empty($books)): ?>
      <div class="n-empty py-5">
        <div class="n-empty-icon"><?= icon('book-open') ?></div>
        <p class="mb-2 fw-semibold"><?= e(__('library.no_books_yet')) ?></p>
        <?php if (hasPermission('library.manage')): ?>
          <a href="/library/create" class="btn btn-sm btn-primary"><?= e(__('library.add_book')) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= e(__('library.book_title')) ?></th>
              <th><?= e(__('library.author')) ?></th>
              <th><?= e(__('library.category')) ?></th>
              <th><?= e(__('library.isbn')) ?></th>
              <th><?= e(__('library.copies')) ?></th>
              <th><?= e(__('library.shelf')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($books as $b): ?>
              <tr>
                <td class="fw-semibold"><?= e($b['title']) ?></td>
                <td><?= e($b['author']) ?></td>
                <td>
                  <span class="badge text-bg-light border"><?= e($b['category']) ?></span>
                </td>
                <td dir="ltr" class="font-monospace small"><?= e($b['isbn'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $b['available_copies'] > 0 ? 'text-bg-success' : 'text-bg-danger' ?>">
                    <?= (int) $b['available_copies'] ?> / <?= (int) $b['total_copies'] ?> <?= e(__('library.available')) ?>
                  </span>
                </td>
                <td class="small text-muted"><?= e($b['shelf_location'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
