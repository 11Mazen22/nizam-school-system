<?php
/**
 * Nizam -- Settings -> Academic Structure / Terms / Grading.
 *
 * One view for the three configuration surfaces, selected by $section, so the
 * sub-navigation, page heading and error banner cannot drift between them.
 *
 * @var string $section  stages|terms|grading
 * @var ?string $error
 * @var array $stages
 * @var array $grades
 * @var array $terms
 * @var array $years
 * @var array $scales
 * @var ?array $selected
 * @var array $bands
 */
$pageTitle = __('structure.title');
$activeNav = 'settings';
$activeSettingsTab = 'structure';
$suppressPageTitle = true;
require dirname(__DIR__) . '/layout/start.php';
use App\Middleware\CsrfMiddleware;

$sections = [
    'stages'  => ['href' => '/settings/structure', 'label' => 'structure.section.stages', 'icon' => 'layers'],
    'terms'   => ['href' => '/settings/terms', 'label' => 'structure.section.terms', 'icon' => 'calendar'],
    'grading' => ['href' => '/settings/grading', 'label' => 'structure.section.grading', 'icon' => 'star'],
];

$termsByYear = [];
foreach ($terms as $term) {
    $termsByYear[(int) $term['academic_year_id']][] = $term;
}
$bandRows = $bands;
// Four blank rows so a school can add bands without any client-side script.
for ($i = 0; $i < 4; $i++) {
    $bandRows[] = ['band_label' => '', 'min_score' => '', 'max_score' => '', 'gpa_point' => '', 'is_passing' => 0, 'description_en' => '', 'description_ar' => ''];
}
?>

<div class="n-page-head">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <?= icon('layers', 'n-icon-lg text-body-secondary') ?>
      <?= e(__('structure.title')) ?>
    </h1>
    <p class="text-body-secondary mb-0"><?= e(__('structure.intro')) ?></p>
  </div>
</div>

<?php require __DIR__ . '/_nav.php'; ?>

<ul class="nav nav-pills mb-4" role="tablist">
  <?php foreach ($sections as $key => $meta): ?>
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center gap-2<?= $section === $key ? ' active' : '' ?>" href="<?= e($meta['href']) ?>">
        <?= icon($meta['icon']) ?>
        <span><?= e(__($meta['label'])) ?></span>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <?= icon('alert-circle', 'n-icon-lg flex-shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<?php if ($section === 'stages'): ?>

  <div class="n-settings-card">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.section.stages')) ?></h2>
      <p><?= e(__('structure.grades.help')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <?php if ($stages === []): ?>
        <div class="n-empty-state">
          <div class="n-empty-icon"><?= icon('layers') ?></div>
          <h2 class="n-empty-title"><?= e(__('structure.stage.empty')) ?></h2>
          <p class="n-empty-description"><?= e(__('structure.grades.help')) ?></p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle n-table-stack">
            <thead>
              <tr>
                <th><?= e(__('structure.stage.code')) ?></th>
                <th><?= e(__('structure.stage.name_en')) ?></th>
                <th><?= e(__('structure.stage.name_ar')) ?></th>
                <th><?= e(__('structure.stage.order')) ?></th>
                <th><?= e(__('structure.stage.grades')) ?></th>
                <th><?= e(__('structure.stage.active')) ?></th>
                <th class="text-end"><?= e(__('common.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stages as $stage): ?>
                <tr>
                  <td data-label="<?= e(__('structure.stage.code')) ?>"><code><?= e($stage['code']) ?></code></td>
                  <td data-label="<?= e(__('structure.stage.name_en')) ?>" lang="en" dir="ltr"><strong><?= e($stage['name_en']) ?></strong></td>
                  <td data-label="<?= e(__('structure.stage.name_ar')) ?>" lang="ar" dir="rtl"><?= e($stage['name_ar']) ?></td>
                  <td data-label="<?= e(__('structure.stage.order')) ?>"><span class="n-order-chip"><?= (int) $stage['sort_order'] ?></span></td>
                  <td data-label="<?= e(__('structure.stage.grades')) ?>"><span class="badge text-bg-light"><?= (int) $stage['grade_count'] ?></span></td>
                  <td data-label="<?= e(__('structure.stage.active')) ?>">
                    <span class="badge <?= (int) $stage['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                      <?= e((int) $stage['is_active'] === 1 ? __('common.status_active') : __('common.status_archived')) ?>
                    </span>
                  </td>
                  <td data-label="<?= e(__('common.actions')) ?>">
                    <div class="n-table-actions">
                      <details>
                        <summary class="btn btn-sm btn-outline-secondary"><?= icon('edit') ?><?= e(__('common.edit')) ?></summary>
                        <form method="post" action="/settings/structure/stages/<?= (int) $stage['id'] ?>" class="mt-3" data-loading-submit>
                          <?= CsrfMiddleware::field() ?>
                          <div class="row g-2">
                            <div class="col-md-4"><label class="form-label"><?= e(__('structure.stage.code')) ?></label><input class="form-control" name="code" value="<?= e($stage['code']) ?>" required></div>
                            <div class="col-md-4"><label class="form-label"><?= e(__('structure.stage.name_en')) ?></label><input class="form-control" name="name_en" dir="ltr" lang="en" value="<?= e($stage['name_en']) ?>" required></div>
                            <div class="col-md-4"><label class="form-label"><?= e(__('structure.stage.name_ar')) ?></label><input class="form-control" name="name_ar" dir="rtl" lang="ar" value="<?= e($stage['name_ar']) ?>" required></div>
                            <div class="col-md-4"><label class="form-label"><?= e(__('structure.stage.order')) ?></label><input type="number" min="1" max="99" class="form-control" name="sort_order" value="<?= (int) $stage['sort_order'] ?>" required></div>
                            <div class="col-md-4 d-flex align-items-end">
                              <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="stage-active-<?= (int) $stage['id'] ?>" <?= (int) $stage['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="stage-active-<?= (int) $stage['id'] ?>"><?= e(__('structure.stage.active')) ?></label>
                              </div>
                            </div>
                          </div>
                          <button class="btn btn-primary btn-sm mt-3" type="submit"><?= icon('check') ?><?= e(__('common.save')) ?></button>
                        </form>
                      </details>
                      <form method="post" action="/settings/structure/stages/<?= (int) $stage['id'] ?>/delete" data-confirm="<?= e(__('structure.stage.delete_confirm')) ?>">
                        <?= CsrfMiddleware::field() ?>
                        <button class="btn btn-sm btn-ghost text-danger" type="submit" aria-label="<?= e(__('structure.delete')) ?>"><?= icon('trash') ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="n-settings-card mt-4">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.stage.add')) ?></h2>
      <p><?= e(__('structure.stage.code_help')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <form method="post" action="/settings/structure/stages" data-loading-submit>
        <?= CsrfMiddleware::field() ?>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label" for="new_stage_code"><?= e(__('structure.stage.code')) ?></label>
            <input class="form-control" id="new_stage_code" name="code" placeholder="PRIMARY" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="new_stage_en"><?= e(__('structure.stage.name_en')) ?></label>
            <input class="form-control" id="new_stage_en" name="name_en" dir="ltr" lang="en" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="new_stage_ar"><?= e(__('structure.stage.name_ar')) ?></label>
            <input class="form-control" id="new_stage_ar" name="name_ar" dir="rtl" lang="ar" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="new_stage_sort"><?= e(__('structure.stage.order')) ?></label>
            <input type="number" min="1" max="99" class="form-control" id="new_stage_sort" name="sort_order" value="<?= count($stages) + 1 ?>" required>
          </div>
        </div>
        <div class="n-settings-save">
          <button class="btn btn-primary" type="submit"><?= icon('plus') ?><?= e(__('common.save')) ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="n-settings-card mt-4">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.grades.title')) ?></h2>
      <p><?= e(__('structure.grades.help')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <?php if ($grades === []): ?>
        <p class="text-body-secondary mb-0"><?= e(__('structure.grades.none')) ?></p>
      <?php else: ?>
        <form method="post" action="/settings/structure/grade-stages" data-loading-submit>
          <?= CsrfMiddleware::field() ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th><?= e(__('grades.name_en')) ?></th>
                  <th><?= e(__('structure.section.stages')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($grades as $grade): ?>
                  <tr>
                    <td><strong><?= e($grade['name_en']) ?></strong> <span class="text-body-secondary"><?= e($grade['name_ar']) ?></span></td>
                    <td>
                      <select class="form-select" name="grade_stage[<?= (int) $grade['id'] ?>]" aria-label="<?= e(__('structure.section.stages')) ?>">
                        <option value=""><?= e(__('structure.grades.none')) ?></option>
                        <?php foreach ($stages as $stage): ?>
                          <option value="<?= (int) $stage['id'] ?>" <?= (int) ($grade['stage_id'] ?? 0) === (int) $stage['id'] ? 'selected' : '' ?>>
                            <?= e($stage['name_en']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="n-settings-save">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><?= e(__('structure.grades.save')) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($section === 'terms'): ?>

  <div class="n-settings-card">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.section.terms')) ?></h2>
      <p><?= e(__('structure.intro')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <?php if ($years === []): ?>
        <div class="n-empty-state">
          <div class="n-empty-icon"><?= icon('calendar') ?></div>
          <h2 class="n-empty-title"><?= e(__('structure.terms.no_years')) ?></h2>
          <a class="btn btn-primary" href="/academic-years/create"><?= icon('plus') ?><?= e(__('structure.terms.go_years')) ?></a>
        </div>
      <?php else: ?>
        <?php foreach ($years as $year): ?>
          <?php $yearTerms = $termsByYear[(int) $year['id']] ?? []; ?>
          <div class="mb-4">
            <h3 class="h5 d-flex align-items-center gap-2">
              <?= icon('calendar', 'text-body-secondary') ?>
              <?= e($year['label']) ?>
              <span class="text-body-secondary fs-6"><?= e($year['start_date']) ?> — <?= e($year['end_date']) ?></span>
            </h3>
            <?php if ($yearTerms === []): ?>
              <p class="text-body-secondary"><?= e(__('structure.terms.empty')) ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle n-table-stack">
                  <thead>
                    <tr>
                      <th><?= e(__('structure.terms.number')) ?></th>
                      <th><?= e(__('structure.terms.name_en')) ?></th>
                      <th><?= e(__('structure.terms.name_ar')) ?></th>
                      <th><?= e(__('structure.terms.start')) ?></th>
                      <th><?= e(__('structure.terms.end')) ?></th>
                      <th><?= e(__('structure.terms.current')) ?></th>
                      <th class="text-end"><?= e(__('common.actions')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($yearTerms as $term): ?>
                      <tr>
                        <td data-label="<?= e(__('structure.terms.number')) ?>"><span class="n-order-chip"><?= (int) $term['term_number'] ?></span></td>
                        <td data-label="<?= e(__('structure.terms.name_en')) ?>" lang="en" dir="ltr"><strong><?= e($term['name_en']) ?></strong></td>
                        <td data-label="<?= e(__('structure.terms.name_ar')) ?>" lang="ar" dir="rtl"><?= e($term['name_ar']) ?></td>
                        <td data-label="<?= e(__('structure.terms.start')) ?>"><?= e($term['start_date']) ?></td>
                        <td data-label="<?= e(__('structure.terms.end')) ?>"><?= e($term['end_date']) ?></td>
                        <td data-label="<?= e(__('structure.terms.current')) ?>">
                          <?php if ((int) $term['is_active'] === 1): ?>
                            <span class="badge text-bg-success"><?= e(__('structure.terms.current')) ?></span>
                          <?php else: ?>
                            <form method="post" action="/settings/terms/<?= (int) $term['id'] ?>/activate">
                              <?= CsrfMiddleware::field() ?>
                              <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(__('structure.terms.activate')) ?></button>
                            </form>
                          <?php endif; ?>
                        </td>
                        <td data-label="<?= e(__('common.actions')) ?>">
                          <div class="n-table-actions">
                            <details>
                              <summary class="btn btn-sm btn-outline-secondary"><?= icon('edit') ?><?= e(__('common.edit')) ?></summary>
                              <form method="post" action="/settings/terms/<?= (int) $term['id'] ?>" class="mt-3" data-loading-submit>
                                <?= CsrfMiddleware::field() ?>
                                <div class="row g-2">
                                  <div class="col-md-3"><label class="form-label"><?= e(__('structure.terms.number')) ?></label><input type="number" min="1" max="12" class="form-control" name="term_number" value="<?= (int) $term['term_number'] ?>" required></div>
                                  <div class="col-md-3"><label class="form-label"><?= e(__('structure.terms.name_en')) ?></label><input class="form-control" name="name_en" dir="ltr" lang="en" value="<?= e($term['name_en']) ?>" required></div>
                                  <div class="col-md-3"><label class="form-label"><?= e(__('structure.terms.name_ar')) ?></label><input class="form-control" name="name_ar" dir="rtl" lang="ar" value="<?= e($term['name_ar']) ?>" required></div>
                                  <div class="col-md-3"><label class="form-label"><?= e(__('structure.terms.start')) ?></label><input type="date" class="form-control" name="start_date" value="<?= e($term['start_date']) ?>" required></div>
                                  <div class="col-md-3"><label class="form-label"><?= e(__('structure.terms.end')) ?></label><input type="date" class="form-control" name="end_date" value="<?= e($term['end_date']) ?>" required></div>
                                  <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="term-active-<?= (int) $term['id'] ?>" <?= (int) $term['is_active'] === 1 ? 'checked' : '' ?>>
                                      <label class="form-check-label" for="term-active-<?= (int) $term['id'] ?>"><?= e(__('structure.terms.current')) ?></label>
                                    </div>
                                  </div>
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" type="submit"><?= icon('check') ?><?= e(__('common.save')) ?></button>
                              </form>
                            </details>
                            <form method="post" action="/settings/terms/<?= (int) $term['id'] ?>/delete" data-confirm="<?= e(__('structure.terms.delete_confirm')) ?>">
                              <?= CsrfMiddleware::field() ?>
                              <button class="btn btn-sm btn-ghost text-danger" type="submit" aria-label="<?= e(__('structure.delete')) ?>"><?= icon('trash') ?></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($years !== []): ?>
    <div class="n-settings-card mt-4">
      <div class="n-settings-card-head">
        <h2><?= e(__('structure.terms.add')) ?></h2>
        <p><?= e(__('structure.terms.outside_year')) ?></p>
      </div>
      <div class="n-settings-card-body">
        <form method="post" action="/settings/terms" data-loading-submit>
          <?= CsrfMiddleware::field() ?>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label" for="term_year"><?= e(__('structure.terms.year')) ?></label>
              <select class="form-select" id="term_year" name="academic_year_id" required>
                <?php foreach ($years as $year): ?>
                  <option value="<?= (int) $year['id'] ?>" <?= (int) $year['is_active'] === 1 ? 'selected' : '' ?>><?= e($year['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label" for="term_number"><?= e(__('structure.terms.number')) ?></label>
              <input type="number" min="1" max="12" class="form-control" id="term_number" name="term_number" value="1" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="term_en"><?= e(__('structure.terms.name_en')) ?></label>
              <input class="form-control" id="term_en" name="name_en" dir="ltr" lang="en" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="term_ar"><?= e(__('structure.terms.name_ar')) ?></label>
              <input class="form-control" id="term_ar" name="name_ar" dir="rtl" lang="ar" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="term_start"><?= e(__('structure.terms.start')) ?></label>
              <input type="date" class="form-control" id="term_start" name="start_date" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="term_end"><?= e(__('structure.terms.end')) ?></label>
              <input type="date" class="form-control" id="term_end" name="end_date" required>
            </div>
          </div>
          <div class="n-settings-save">
            <button class="btn btn-primary" type="submit"><?= icon('plus') ?><?= e(__('structure.terms.add')) ?></button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

<?php else: ?>

  <div class="n-settings-card">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.section.grading')) ?></h2>
      <p><?= e(__('structure.grading.type')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <?php if ($scales === []): ?>
        <div class="n-empty-state">
          <div class="n-empty-icon"><?= icon('star') ?></div>
          <h2 class="n-empty-title"><?= e(__('structure.grading.empty')) ?></h2>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle n-table-stack">
            <thead>
              <tr>
                <th><?= e(__('structure.grading.name_en')) ?></th>
                <th><?= e(__('structure.grading.type')) ?></th>
                <th><?= e(__('structure.grading.bands_count')) ?></th>
                <th><?= e(__('structure.grading.default')) ?></th>
                <th class="text-end"><?= e(__('common.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($scales as $scale): ?>
                <tr>
                  <td data-label="<?= e(__('structure.grading.name_en')) ?>">
                    <strong><?= e($scale['name_en']) ?></strong>
                    <div class="text-body-secondary" lang="ar" dir="rtl"><?= e($scale['name_ar']) ?></div>
                  </td>
                  <td data-label="<?= e(__('structure.grading.type')) ?>"><span class="badge text-bg-light"><?= e(__('structure.grading.type.' . $scale['scale_type'])) ?></span></td>
                  <td data-label="<?= e(__('structure.grading.bands_count')) ?>"><?= (int) $scale['band_count'] ?></td>
                  <td data-label="<?= e(__('structure.grading.default')) ?>">
                    <?php if ((int) $scale['is_default'] === 1): ?>
                      <span class="badge text-bg-success"><?= e(__('structure.grading.default')) ?></span>
                    <?php else: ?>
                      <form method="post" action="/settings/grading/<?= (int) $scale['id'] ?>/default">
                        <?= CsrfMiddleware::field() ?>
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(__('structure.grading.set_default')) ?></button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td data-label="<?= e(__('common.actions')) ?>">
                    <div class="n-table-actions">
                      <a class="btn btn-sm btn-outline-secondary" href="/settings/grading?scale=<?= (int) $scale['id'] ?>"><?= icon('edit') ?><?= e(__('common.edit')) ?></a>
                      <form method="post" action="/settings/grading/<?= (int) $scale['id'] ?>/delete" data-confirm="<?= e(__('structure.grading.delete_confirm')) ?>">
                        <?= CsrfMiddleware::field() ?>
                        <button class="btn btn-sm btn-ghost text-danger" type="submit" aria-label="<?= e(__('structure.delete')) ?>"><?= icon('trash') ?></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($selected !== null): ?>
    <div class="n-settings-card mt-4">
      <div class="n-settings-card-head">
        <h2><?= e($selected['name_en']) ?> — <?= e(__('structure.grading.bands_count')) ?></h2>
        <p><?= e(__('structure.grading.type')) ?></p>
      </div>
      <div class="n-settings-card-body">
        <form method="post" action="/settings/grading/<?= (int) $selected['id'] ?>" data-loading-submit>
          <?= CsrfMiddleware::field() ?>
          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <label class="form-label" for="scale_en"><?= e(__('structure.grading.name_en')) ?></label>
              <input class="form-control" id="scale_en" name="name_en" dir="ltr" lang="en" value="<?= e($selected['name_en']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="scale_ar"><?= e(__('structure.grading.name_ar')) ?></label>
              <input class="form-control" id="scale_ar" name="name_ar" dir="rtl" lang="ar" value="<?= e($selected['name_ar']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="scale_type"><?= e(__('structure.grading.type')) ?></label>
              <select class="form-select" id="scale_type" name="scale_type">
                <?php foreach (['percentage', 'letter', 'gpa', 'points'] as $type): ?>
                  <option value="<?= e($type) ?>" <?= $selected['scale_type'] === $type ? 'selected' : '' ?>><?= e(__('structure.grading.type.' . $type)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="scale_desc"><?= e(__('structure.grading.description')) ?></label>
              <input class="form-control" id="scale_desc" name="description" value="<?= e($selected['description'] ?? '') ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="scale_default" <?= (int) $selected['is_default'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="scale_default"><?= e(__('structure.grading.default')) ?></label>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th><?= e(__('structure.grading.band_label')) ?></th>
                  <th><?= e(__('structure.grading.band_min')) ?></th>
                  <th><?= e(__('structure.grading.band_max')) ?></th>
                  <th><?= e(__('structure.grading.band_gpa')) ?></th>
                  <th><?= e(__('structure.grading.band_passing')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bandRows as $index => $band): ?>
                  <tr>
                    <td><input class="form-control" name="band_label[]" value="<?= e($band['band_label']) ?>" aria-label="<?= e(__('structure.grading.band_label')) ?>"></td>
                    <td><input type="number" step="0.01" min="0" max="100" class="form-control" name="band_min[]" value="<?= e((string) $band['min_score']) ?>" aria-label="<?= e(__('structure.grading.band_min')) ?>"></td>
                    <td><input type="number" step="0.01" min="0" max="100" class="form-control" name="band_max[]" value="<?= e((string) $band['max_score']) ?>" aria-label="<?= e(__('structure.grading.band_max')) ?>"></td>
                    <td><input type="number" step="0.01" min="0" max="5" class="form-control" name="band_gpa[]" value="<?= e((string) ($band['gpa_point'] ?? '')) ?>" aria-label="<?= e(__('structure.grading.band_gpa')) ?>"></td>
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input" name="band_passing[<?= (int) $index ?>]" value="1" <?= (int) ($band['is_passing'] ?? 0) === 1 ? 'checked' : '' ?> aria-label="<?= e(__('structure.grading.band_passing')) ?>">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="n-settings-save">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><?= e(__('common.save')) ?></button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <div class="n-settings-card mt-4">
    <div class="n-settings-card-head">
      <h2><?= e(__('structure.grading.add')) ?></h2>
      <p><?= e(__('structure.grading.type')) ?></p>
    </div>
    <div class="n-settings-card-body">
      <form method="post" action="/settings/grading" data-loading-submit>
        <?= CsrfMiddleware::field() ?>
        <input type="hidden" name="band_label[]" value="F">
        <input type="hidden" name="band_min[]" value="0">
        <input type="hidden" name="band_max[]" value="100">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="new_scale_en"><?= e(__('structure.grading.name_en')) ?></label>
            <input class="form-control" id="new_scale_en" name="name_en" dir="ltr" lang="en" required>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="new_scale_ar"><?= e(__('structure.grading.name_ar')) ?></label>
            <input class="form-control" id="new_scale_ar" name="name_ar" dir="rtl" lang="ar" required>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="new_scale_type"><?= e(__('structure.grading.type')) ?></label>
            <select class="form-select" id="new_scale_type" name="scale_type">
              <?php foreach (['percentage', 'letter', 'gpa', 'points'] as $type): ?>
                <option value="<?= e($type) ?>"><?= e(__('structure.grading.type.' . $type)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="form-text mt-2"><?= e(__('structure.grading.invalid_band')) ?></p>
        <div class="n-settings-save">
          <button class="btn btn-primary" type="submit"><?= icon('plus') ?><?= e(__('structure.grading.add')) ?></button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/_end.php'; ?>
<?php require dirname(__DIR__) . '/layout/end.php'; ?>
