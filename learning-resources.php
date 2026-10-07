<?php
$page_title = 'Learning Resources';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();

/**
 * Topics commonly searched by students. Clicking a chip fills the search box.
 */
$topicChips = ['Java', 'DBMS', 'Web Development', 'Data Structures', 'Algorithms', 'Operating Systems', 'Computer Networks', 'Machine Learning', 'Python'];

$page_scripts = ['learning-resources.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl">
  <div class="flex items-center gap-2.5 mb-1">
    <span class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center"><i data-lucide="library" class="w-5 h-5"></i></span>
    <div>
      <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Learning Resources</h2>
      <p class="text-sm text-slate-500 dark:text-slate-400">Find books and study material for any subject.</p>
    </div>
  </div>

  <!-- Search -->
  <div class="mt-5 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 shadow-soft">
    <form id="resourceForm" class="flex gap-2.5">
      <div class="relative flex-1">
        <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="search" id="resourceQuery" name="q" autocomplete="off" maxlength="120"
               placeholder="Try &quot;Java programming&quot;, &quot;DBMS notes&quot;, &quot;Web development books&quot;…"
               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-3 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500">
      </div>
      <button type="submit" id="resourceSearchBtn"
              class="inline-flex items-center gap-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-sm font-semibold px-5 py-3 transition shrink-0">
        <i data-lucide="search" class="w-4 h-4"></i> Search
      </button>
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <span class="text-xs font-medium text-slate-400 mr-1">Popular:</span>
      <?php foreach ($topicChips as $tc): ?>
        <button type="button" data-resource-topic="<?= $tc ?>"
                class="rounded-full border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:border-sky-400 hover:text-sky-600 dark:hover:text-sky-400 transition">
          <?= e($tc) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Status line -->
  <div id="resourceStatus" class="mt-4"></div>

  <!-- Loading skeleton -->
  <div id="resourceLoading" class="hidden mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-soft">
        <div class="flex gap-3">
          <div class="w-14 h-20 rounded-lg bg-slate-100 dark:bg-slate-800 animate-pulse shrink-0"></div>
          <div class="flex-1 space-y-2 py-1">
            <div class="h-3.5 w-3/4 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
            <div class="h-3 w-1/2 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
            <div class="h-3 w-2/3 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
          </div>
        </div>
        <div class="mt-4 h-3 w-full rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
        <div class="mt-2 h-3 w-5/6 rounded bg-slate-100 dark:bg-slate-800 animate-pulse"></div>
      </div>
    <?php endfor; ?>
  </div>

  <!-- Results -->
  <div id="resourceGrid" class="mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>

  <!-- Empty state -->
  <div id="resourceEmpty" class="hidden mt-8 rounded-3xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-10 text-center">
    <span class="inline-flex w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 items-center justify-center"><i data-lucide="book-open" class="w-6 h-6"></i></span>
    <p class="mt-3 font-medium text-slate-700 dark:text-slate-300">Search for a book or topic to get started</p>
    <p class="text-sm text-slate-400 mt-1">e.g. Java, DBMS normalization, data structures, web development…</p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>