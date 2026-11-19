    </main>
  </div>
</div>

<?php if (!empty($pageModals)): ?>
  <?= $pageModals ?>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/document-modals.php'; ?>

<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
<script src="<?= e(assetUrl('/assets/js/confirm.js')) ?>"></script>
<script src="<?= e(assetUrl('/assets/js/offline-queue.js')) ?>"></script>
<script src="<?= e(assetUrl('/assets/js/document-export.js')) ?>"></script>

<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $script): ?>
    <script src="<?= e(assetUrl($script)) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
