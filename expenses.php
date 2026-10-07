<?php
$page_title = 'Expenses';
require_once __DIR__ . '/includes/auth_check.php';

$uid = user_id();
sync_notifications($uid);

$summary  = expense_summary($uid);
$insights = expense_insights($uid);
$charts   = expense_chart_data($uid);

$stmt = db()->prepare(
    'SELECT id, amount, category, description, expense_date FROM expenses
     WHERE user_id = ? ORDER BY expense_date DESC, id DESC LIMIT 100'
);
$stmt->execute([$uid]);
$expenses = $stmt->fetchAll();

$cats = ['Food', 'Travel', 'Education', 'Shopping', 'Entertainment', 'Bills', 'Other'];
$catColors = ['Food' => '#f43f5e', 'Travel' => '#0ea5e9', 'Education' => '#6366f1', 'Shopping' => '#a855f7', 'Entertainment' => '#f59e0b', 'Bills' => '#14b8a6', 'Other' => '#94a3b8'];

$page_data = [
    'summary'  => $summary,
    'insights' => $insights,
    'charts'   => $charts,
    'expenses' => $expenses,
    'colors'   => $catColors,
];
$page_scripts = ['charts.js', 'expenses.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="flex items-center justify-between gap-4 mb-5">
  <p class="text-sm text-slate-500 dark:text-slate-400">Track every rupee — insights update instantly.</p>
  <button type="button" id="addExpenseBtn"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-2.5 shadow-soft transition">
    <i data-lucide="plus" class="w-4 h-4"></i> Add Expense
  </button>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
  <?php
  $cards = [
      ['today', 'Today', format_money($summary['today']), 'sun', 'text-sky-500', 'bg-sky-50 dark:bg-sky-500/15', 'spent today'],
      ['week', 'This Week', format_money($summary['week']), 'calendar-range', 'text-indigo-500', 'bg-indigo-50 dark:bg-indigo-500/15', 'last 7 days'],
      ['month', 'This Month', format_money($summary['month']), 'wallet', 'text-violet-500', 'bg-violet-50 dark:bg-violet-500/15', date('F Y')],
      ['budget',
          $summary['budget'] > 0
              ? ['Budget Left', format_money($summary['remaining']), 'piggy-bank', $summary['over_budget'] ? 'text-rose-500' : 'text-emerald-500', $summary['over_budget'] ? 'bg-rose-50 dark:bg-rose-500/15' : 'bg-emerald-50 dark:bg-emerald-500/15', 'of ' . format_money($summary['budget']) . ' budget']
              : ['Monthly Budget', 'Not set', 'piggy-bank', 'text-slate-400', 'bg-slate-100 dark:bg-slate-800', 'set it in settings'],
      ],
  ];
  foreach ($cards as [$key, $card]): ?>
    <?php if ($key === 'budget') { [$label, $value, $icon, $color, $bg, $footer] = $card; } else { [, $label, $value, $icon, $color, $bg, $footer] = $card; } ?>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-soft">
      <div class="flex items-center justify-between gap-2">
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400" data-exp-label="<?= $key ?>"><?= e($label) ?></p>
        <span class="w-8 h-8 rounded-lg <?= $bg ?> <?= $color ?> flex items-center justify-center"><i data-lucide="<?= $icon ?>" class="w-4 h-4"></i></span>
      </div>
      <p class="mt-2 text-xl sm:text-2xl font-semibold text-slate-900 dark:text-white" data-exp-stat="<?= $key ?>"><?= e($value) ?></p>
      <p class="mt-1 text-[11px] text-slate-400" data-exp-foot="<?= $key ?>"><?= e($footer) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<!-- Insights -->
<div class="mt-6 rounded-3xl border border-indigo-100 dark:border-indigo-500/20 bg-indigo-50/60 dark:bg-indigo-500/10 p-5 sm:p-6">
  <div class="flex items-center gap-2.5">
    <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center"><i data-lucide="lightbulb" class="w-4.5 h-4.5"></i></span>
    <h3 class="font-semibold text-slate-900 dark:text-white">Smart expense insights</h3>
  </div>
  <ul class="mt-4 space-y-2" id="insightList">
    <?php foreach ($insights as $ins): ?>
      <li class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300">
        <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span><span><?= e($ins) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- Charts -->
<div class="mt-6 grid lg:grid-cols-3 gap-6">
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Expense by category <span class="text-xs font-normal text-slate-400">(this month)</span></h3>
    <div class="mt-3 h-56"><canvas id="catChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Weekly expense trend</h3>
    <div class="mt-3 h-56"><canvas id="weekChart"></canvas></div>
  </div>
  <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft">
    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Monthly expense trend</h3>
    <div class="mt-3 h-56"><canvas id="monthChart"></canvas></div>
  </div>
</div>

<!-- Expense list -->
<div class="mt-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-soft overflow-hidden">
  <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
    <h3 class="font-semibold text-slate-900 dark:text-white">Recent expenses</h3>
    <span class="text-xs text-slate-400" id="expenseCount"><?= count($expenses) ?> entries</span>
  </div>
  <div class="hidden sm:grid grid-cols-12 gap-4 px-5 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40">
    <span class="col-span-3">Date</span><span class="col-span-4">Description</span><span class="col-span-3">Category</span><span class="col-span-2 text-right">Amount</span>
  </div>
  <ul id="expenseList" class="divide-y divide-slate-100 dark:divide-slate-800">
    <?php foreach ($expenses as $ex): ?>
      <li class="grid grid-cols-2 sm:grid-cols-12 gap-2 sm:gap-4 px-5 py-3.5 items-center hover:bg-slate-50 dark:hover:bg-slate-800/40 transition"
          data-id="<?= (int) $ex['id'] ?>"
          data-amount="<?= e($ex['amount']) ?>"
          data-category="<?= e($ex['category']) ?>"
          data-description="<?= e($ex['description']) ?>"
          data-expense-date="<?= e($ex['expense_date']) ?>">
        <span class="sm:col-span-3 text-xs sm:text-sm text-slate-500 dark:text-slate-400 order-1"><?= e(date('d M Y', strtotime($ex['expense_date']))) ?></span>
        <span class="sm:col-span-4 text-sm font-medium text-slate-800 dark:text-slate-200 order-3 sm:order-2 col-span-2 truncate"><?= e($ex['description'] ?: '—') ?></span>
        <span class="sm:col-span-3 order-2 sm:order-3">
          <span class="inline-block rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2.5 py-0.5 text-[11px] font-medium"><?= e($ex['category']) ?></span>
        </span>
        <span class="sm:col-span-2 text-right flex sm:block items-center justify-end gap-2 order-4">
          <span class="text-sm font-semibold text-slate-900 dark:text-white"><?= format_money($ex['amount']) ?></span>
          <span class="flex gap-1">
            <button type="button" data-edit-expense class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-500/15"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
            <button type="button" data-delete-expense class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/15"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
          </span>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <div id="expenseEmpty" class="hidden px-5 py-10 text-center">
    <span class="inline-flex w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 items-center justify-center"><i data-lucide="wallet" class="w-6 h-6 text-slate-400"></i></span>
    <p class="mt-3 font-medium text-slate-700 dark:text-slate-300">No expenses yet</p>
    <p class="text-sm text-slate-400 mt-1">Add your first expense to unlock charts and insights.</p>
  </div>
</div>

<!-- Expense modal -->
<div id="expenseModal" class="hidden fixed inset-0 z-[60] p-4">
  <div class="min-h-full flex items-center justify-center">
    <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" data-close-modal></div>
    <div class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-soft border border-slate-200 dark:border-slate-800 p-6">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="expenseModalTitle">Add Expense</h3>
        <button type="button" data-close-modal class="w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <form id="expenseForm" class="mt-5 space-y-4">
        <p id="expenseModalError" class="hidden rounded-xl bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400 text-sm px-3.5 py-2.5"></p>
        <input type="hidden" name="id" id="exf_id">
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Amount (₹) *</label>
          <input type="number" name="amount" id="exf_amount" required min="0.01" step="0.01" placeholder="250"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
            <select name="category" id="exf_category"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach ($cats as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Date *</label>
            <input type="date" name="expense_date" id="exf_date" required value="<?= date('Y-m-d') ?>"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
          <input name="description" id="exf_description" placeholder="Lunch at canteen"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" data-close-modal class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
          <button type="submit" class="rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-5 py-2.5">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
