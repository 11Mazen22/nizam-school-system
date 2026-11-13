<?php
$pageTitle = __('library.add_book');
$activeNav = 'library';
ob_start(); ?>
<a href="/library" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
      <?= icon('chevron-left', 'n-flip-rtl') ?>
      <span><?= e(__('common.back')) ?></span>
    </a>
<?php $pageActions = (string) ob_get_clean();
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;
?>



<div class="card col-lg-8 mx-auto">
  <div class="card-body p-4">
    <form method="post" action="/library">
      <?= CsrfMiddleware::field() ?>
      <div class="mb-3">
        <label class="form-label" for="title"><?= e(__('library.book_title')) ?></label>
        <input type="text" class="form-control" id="title" name="title" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="author"><?= e(__('library.author')) ?></label>
          <input type="text" class="form-control" id="author" name="author" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="category"><?= e(__('library.category')) ?></label>
          <input type="text" class="form-control" id="category" name="category" placeholder="Science, Literature, History...">
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="isbn"><?= e(__('library.isbn')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="isbn" name="isbn" dir="ltr">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="total_copies"><?= e(__('library.copies_count')) ?></label>
          <input type="number" min="1" max="500" class="form-control" id="total_copies" name="total_copies" value="1" required dir="ltr">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="shelf_location"><?= e(__('library.shelf')) ?> (<?= e(__('common.optional')) ?>)</label>
          <input type="text" class="form-control" id="shelf_location" name="shelf_location" placeholder="Section A-3">
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <a href="/library" class="btn btn-light"><?= e(__('common.cancel')) ?></a>
        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
          <?= icon('check') ?>
          <span><?= e(__('common.save')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<?php require dirname(__DIR__) . '/layout/end.php'; ?>
