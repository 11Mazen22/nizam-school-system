    </main>
  </div>
</div>

<!-- Bootstrap & Core Scripts -->
<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/confirm.js"></script>

<!-- Page-specific scripts -->
<?php if (!empty($pageScripts)): ?>
  <?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
