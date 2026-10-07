<?php
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['email' => '', 'remember' => true];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['email'] = strtolower(trim($_POST['email'] ?? ''));
    $old['remember'] = isset($_POST['remember']);
    $password = $_POST['password'] ?? '';

    if ($old['email'] === '') $errors[] = 'Email is required.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($password === '') $errors[] = 'Password is required.';

    if (!$errors) {
        [$ok, $message] = attempt_login($old['email'], $password, $old['remember']);
        if ($ok) {
            $redirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? '');
            $redirect = basename($redirect);
            if ($redirect && preg_match('/^[a-z0-9\-]+\.php$/', $redirect) && file_exists(__DIR__ . '/' . $redirect)) {
                header('Location: ' . $redirect);
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
        $errors[] = $message;
    }
}

$page_title = 'Log in';
$dark = false;
require __DIR__ . '/includes/head.php';
?>
<div class="min-h-screen lg:grid lg:grid-cols-2">
  <!-- Brand panel -->
  <div class="hidden lg:flex flex-col justify-between p-12 bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900 text-white">
    <a href="index.php" class="flex items-center gap-2.5 w-fit">
      <span class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center"><i data-lucide="zap" class="w-5 h-5"></i></span>
      <span class="text-xl font-semibold tracking-tight">StudentFlow</span>
    </a>
    <div>
      <h2 class="text-4xl font-semibold leading-tight tracking-tight max-w-md">Your Student Life.<br>One Smart Dashboard.</h2>
      <p class="mt-4 text-indigo-100/90 max-w-md leading-relaxed">Plan your studies, manage tasks, track expenses, build habits and stay focused — all in one place.</p>
      <div class="mt-10 space-y-3 text-sm">
        <p class="flex items-center gap-3"><span class="w-6 h-6 rounded-lg bg-white/15 flex items-center justify-center"><i data-lucide="sparkles" class="w-3.5 h-3.5"></i></span> “What should I do now?” smart recommendations</p>
        <p class="flex items-center gap-3"><span class="w-6 h-6 rounded-lg bg-white/15 flex items-center justify-center"><i data-lucide="timer" class="w-3.5 h-3.5"></i></span> Focus mode with Pomodoro sessions</p>
        <p class="flex items-center gap-3"><span class="w-6 h-6 rounded-lg bg-white/15 flex items-center justify-center"><i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i></span> Real analytics from your real data</p>
      </div>
    </div>
    <p class="text-sm text-indigo-200/70">© <?= date('Y') ?> StudentFlow</p>
  </div>

  <!-- Form panel -->
  <div class="flex items-center justify-center px-5 py-12 sm:px-10">
    <div class="w-full max-w-md">
      <a href="index.php" class="lg:hidden flex items-center gap-2.5 mb-8 w-fit">
        <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center"><i data-lucide="zap" class="w-5 h-5"></i></span>
        <span class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">StudentFlow</span>
      </a>

      <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Welcome back</h1>
      <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Log in to see what you should do next.</p>

      <?php foreach ($errors as $err): ?>
        <div class="mt-5 flex items-start gap-2.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
          <i data-lucide="circle-alert" class="w-4 h-4 mt-0.5 shrink-0"></i><span><?= e($err) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . e(urlencode($_GET['redirect'])) : '' ?>" class="mt-6 space-y-4">
        <input type="hidden" name="redirect" value="<?= e($_POST['redirect'] ?? ($_GET['redirect'] ?? '')) ?>">

        <div>
          <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
          <input id="email" name="email" type="email" required value="<?= e($old['email']) ?>" placeholder="you@college.edu"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
          <input id="password" name="password" type="password" required placeholder="••••••••"
                 class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 select-none">
          <input type="checkbox" name="remember" <?= $old['remember'] ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
          Remember me
        </label>

        <button type="submit"
                class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-3 shadow-soft transition">
          Log in
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        New to StudentFlow?
        <a href="register.php" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Create an account</a>
      </p>

      <div class="mt-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/60 p-4 text-xs text-slate-500 dark:text-slate-400">
        <p class="font-medium text-slate-700 dark:text-slate-300 mb-1">Demo account</p>
        <p>Email: <code class="font-mono">demo@studentflow.app</code> · Password: <code class="font-mono">demo1234</code></p>
        <p class="mt-1">(after importing database/demo_data.sql)</p>
      </div>
    </div>
  </div>
</div>
<script>lucide && lucide.createIcons();</script>
</body>
</html>
