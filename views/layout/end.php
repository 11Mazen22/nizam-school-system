    </main>
  </div>
</div>

<?php if (!empty($pageModals)): ?>
  <?= $pageModals ?>
<?php endif; ?>

<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(assetUrl('/assets/js/app.js')) ?>"></script>
<script src="<?= e(assetUrl('/assets/js/confirm.js')) ?>"></script>

<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $script): ?>
    <script src="<?= e(assetUrl($script)) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
