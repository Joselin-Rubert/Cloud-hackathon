<?php
/**
 * StudentFlow - GitHub Explorer
 * Public profile search, repositories, language breakdown, recent public
 * activity, profile comparison and a sync action. All GitHub API traffic
 * runs server-side through services/GitHubService.php (cURL, no tokens).
 */

$page_title = 'GitHub Explorer';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/services/GitHubService.php';

$uid = user_id();

$githubPayload = GitHubService::loadPayload($uid);

$page_data = ['github' => $githubPayload];
$page_scripts = ['charts.js', 'github.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="mb-5">
  <h2 class="text-2xl sm:text-[28px] font-semibold text-slate-900 dark:text-white">GitHub Explorer</h2>
  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Search any public GitHub profile and explore real developer activity.</p>
</div>

<!-- Search -->
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
  <div class="flex items-center gap-3">
    <span class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center"><i data-lucide="github" class="w-5 h-5"></i></span>
    <div>
      <h3 class="font-semibold text-slate-900 dark:text-white">Look up a public profile</h3>
      <p class="text-xs text-slate-400">Username only — no password, no token, no OAuth.</p>
    </div>
  </div>

  <form id="githubSearchForm" class="mt-5 flex flex-col sm:flex-row gap-3">
    <input id="ghUsername" name="username" autocomplete="off" required
           placeholder="e.g. octocat"
           class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
    <button id="btnSearchGitHub" type="submit"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 shadow-soft transition disabled:opacity-60 disabled:cursor-not-allowed">
      <i data-lucide="search" class="w-4 h-4"></i> <span id="btnSearchLabel">View Profile</span>
    </button>
  </form>
  <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Your saved profile is stored privately in your account only.</p>
</div>

<!-- Empty state (before any profile is loaded) -->
<div id="githubEmptyState" class="mt-6 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-white/60 dark:bg-slate-900/40 p-10 text-center">
  <span class="mx-auto w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center"><i data-lucide="github" class="w-6 h-6"></i></span>
  <p class="mt-4 text-sm font-medium text-slate-600 dark:text-slate-300">Search a public GitHub username above</p>
  <p class="mt-1 text-xs text-slate-400">Profile card, repositories, language breakdown, recent public activity and a dashboard snapshot will show up right here.</p>
</div>

<!-- Results -->
<div id="githubResults" class="mt-6 space-y-6 hidden">

  <!-- Profile + Sync -->
  <div class="grid lg:grid-cols-3 gap-6">
    <section class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Github Profile</p>
      <div class="mt-4 flex flex-col sm:flex-row sm:items-start gap-4">
        <img id="ghAvatar" src="" alt="" class="w-20 h-20 rounded-2xl object-cover border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 shrink-0">
        <div class="min-w-0 flex-1">
          <h3 id="ghName" class="text-lg font-semibold text-slate-900 dark:text-white truncate"></h3>
          <a id="ghUsernameLink" href="#" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"></a>
          <div id="ghMeta" class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500 dark:text-slate-400"></div>
        </div>
        <a id="btnGithubProfile" href="#" target="_blank" rel="noopener"
           class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-indigo-200 dark:border-indigo-500/40 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-500/10 text-sm font-semibold px-4 py-2.5 transition">
          Open GitHub Profile <i data-lucide="external-link" class="w-4 h-4"></i>
        </a>
      </div>
      <p id="ghBio" class="mt-4 text-sm text-slate-600 dark:text-slate-300"></p>
    </section>

    <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
      <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Github Sync</p>
      <h3 class="mt-2 font-semibold text-slate-900 dark:text-white">Refresh stored data</h3>
      <p id="lastSyncedText" class="mt-2 text-xs text-slate-400"></p>
      <button id="btnGithubSync" type="button"
              class="mt-4 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-semibold px-4 py-2.5 transition disabled:opacity-60 disabled:cursor-not-allowed">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i> <span id="btnSyncLabel">Sync GitHub</span>
      </button>
      <p class="mt-3 text-xs text-slate-400">Pulls the latest profile, repositories and public activity from GitHub.</p>
    </section>
  </div>

  <!-- Stat cards -->
  <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
    <?php
    $ghStats = [
        ['Public Repositories', 'ghStatRepos', 'folder-git', 'text-indigo-500', 'bg-indigo-50 dark:bg-indigo-500/15'],
        ['Followers', 'ghStatFollowers', 'users', 'text-sky-500', 'bg-sky-50 dark:bg-sky-500/15'],
        ['Following', 'ghStatFollowing', 'user-plus', 'text-violet-500', 'bg-violet-50 dark:bg-violet-500/15'],
        ['Total Stars', 'ghStatStars', 'star', 'text-amber-500', 'bg-amber-50 dark:bg-amber-500/15'],
        ['Total Forks', 'ghStatForks', 'git-fork', 'text-emerald-500', 'bg-emerald-50 dark:bg-emerald-500/15'],
    ];
    foreach ($ghStats as [$label, $id, $icon, $color, $bg]): ?>
      <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-soft">
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400"><?= e($label) ?></p>
          <span class="w-8 h-8 rounded-lg <?= $bg ?> <?= $color ?> flex items-center justify-center"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></span>
        </div>
        <p id="<?= e($id) ?>" class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">–</p>
      </div>
    <?php endforeach; ?>
  </dl>

  <!-- Repositories -->
  <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">Public Repositories</h3>
        <p id="repoCount" class="mt-0.5 text-xs text-slate-400"></p>
      </div>
    </div>

    <div class="mt-4 flex flex-col md:flex-row gap-3">
      <div class="relative flex-1">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
        <input id="repoSearch" placeholder="Search repositories..."
               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
      </div>
      <select id="repoSort"
              class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="updated">Sort: Recently Updated</option>
        <option value="stars">Sort: Most Stars</option>
        <option value="forks">Sort: Most Forks</option>
        <option value="name">Sort: Name A–Z</option>
      </select>
    </div>

    <div id="repoFilters" class="mt-3 flex flex-wrap gap-2"></div>

    <div id="repoGrid" class="mt-5 grid md:grid-cols-2 xl:grid-cols-3 gap-4"></div>

    <div id="repoEmpty" class="hidden mt-5 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center">
      <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No repositories match your filters.</p>
      <p class="text-xs text-slate-400">Try a different search term or language.</p>
    </div>
  </section>

  <!-- Language distribution -->
  <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center"><i data-lucide="code2" class="w-5 h-5"></i></span>
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">Programming Language Distribution</h3>
        <p class="text-xs text-slate-400">Share of public repositories by primary language.</p>
      </div>
    </div>
    <div class="relative h-72 mt-6">
      <canvas id="languageChart"></canvas>
    </div>
    <p id="langNote" class="mt-3 text-xs text-slate-400"></p>
  </section>

  <!-- Recent public activity -->
  <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center"><i data-lucide="activity" class="w-5 h-5"></i></span>
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">Recent Public Activity</h3>
        <p class="text-xs text-slate-400">Latest public events from the GitHub timeline — real events only, no estimated streaks.</p>
      </div>
    </div>
    <ul id="activityList" class="mt-5 space-y-3"></ul>
    <p id="activityEmpty" class="mt-4 text-center text-sm text-slate-400">No recent public activity to show.</p>
  </section>

  <!-- Comparison -->
  <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-soft">
    <div class="flex items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center"><i data-lucide="arrows-left-right" class="w-5 h-5"></i></span>
      <div>
        <h3 class="font-semibold text-slate-900 dark:text-white">GitHub Profile Comparison</h3>
        <p class="text-xs text-slate-400">Compare two public usernames side by side — the numbers, not opinions.</p>
      </div>
    </div>

    <form id="compareForm" class="mt-5 grid sm:grid-cols-2 gap-3">
      <input id="cmpUser1" autocomplete="off" placeholder="First username (e.g. octocat)"
             class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <input id="cmpUser2" autocomplete="off" placeholder="Second username (e.g. torvalds)"
             class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <button type="submit" class="sm:col-span-2 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5 shadow-soft transition disabled:opacity-60 disabled:cursor-not-allowed">
        <i data-lucide="scale" class="w-4 h-4"></i> <span id="btnCompareLabel">Compare Profiles</span>
      </button>
    </form>

    <div id="compareWrap" class="hidden mt-6 overflow-x-auto -mx-6 px-6">
      <table class="w-full min-w-[560px] text-sm border-collapse">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 text-left text-xs font-semibold tracking-wider text-slate-400 uppercase">
            <th class="py-3 pr-4 font-semibold">Metric</th>
            <th id="cmpHead1" class="py-3 px-4 font-semibold"></th>
            <th id="cmpHead2" class="py-3 px-4 font-semibold"></th>
          </tr>
        </thead>
        <tbody id="compareTable"></tbody>
      </table>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>