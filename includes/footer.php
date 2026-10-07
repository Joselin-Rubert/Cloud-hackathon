<?php
/**
 * Application shell (bottom): mobile navigation, toast/modal hosts, scripts.
 * Optional: $page_scripts (array of file names inside assets/js/)
 *           $page_data   (array exposed as window.SF_DATA)
 */
?>
</main>

<footer class="hidden md:block px-6 py-4 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-400 dark:text-slate-500">
  StudentFlow — Smart Student Life Assistant · Your data, one prioritized daily action.
</footer>
</div><!-- /main column -->
</div><!-- /app -->

<!-- Mobile bottom navigation -->
<nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-200 dark:border-slate-800">
  <div class="grid grid-cols-5">
    <?php
    $bottom_items = [
        ['dashboard.php', 'home',       'Home'],
        ['tasks.php',     'list-checks', 'Tasks'],
        ['focus.php',     'timer',      'Focus'],
        ['analytics.php', 'bar-chart-3', 'Charts'],
        ['profile.php',   'user',       'Profile'],
    ];
    $bcurrent = basename($_SERVER['PHP_SELF'] ?? '');
    foreach ($bottom_items as [$href, $icon, $label]):
      $bactive = ($bcurrent === $href);
    ?>
      <a href="<?= e($href) ?>" class="flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-medium transition
             <?= $bactive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400' ?>">
        <i data-lucide="<?= e($icon) ?>" class="w-5 h-5"></i>
        <span><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- Toasts -->
<div id="toastRoot" class="fixed top-4 right-4 z-[70] w-[min(92vw,360px)] space-y-2"></div>

<!-- Modal host -->
<div id="modalRoot" class="hidden fixed inset-0 z-[60] p-4 sm:p-6 overflow-y-auto">
  <div class="min-h-full flex items-center justify-center">
    <div id="modalBackdrop" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
    <div id="modalPanel" class="relative w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6"></div>
  </div>
</div>

<input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">

<?php $flashItems = get_flashes(); ?>
<?php if ($flashItems): ?>
<script>window.SF_FLASH = <?= json_encode($flashItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>

<script src="assets/js/main.js"></script>
<?php if (!empty($page_data)): ?>
<script>window.SF_DATA = <?= json_encode($page_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>
<?php foreach (($page_scripts ?? []) as $script): ?>
<script src="assets/js/<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
