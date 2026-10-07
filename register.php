<?php
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = [
    'name' => '', 'email' => '', 'college' => '', 'department' => '', 'year' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'       => trim($_POST['name'] ?? ''),
        'email'      => strtolower(trim($_POST['email'] ?? '')),
        'college'    => trim($_POST['college'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'year'       => trim($_POST['year'] ?? ''),
    ];
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Full name is required.';
    elseif (mb_strlen($old['name']) > 100) $errors[] = 'Name must be 100 characters or fewer.';

    if ($old['email'] === '') $errors[] = 'Email is required.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if ($password === '') $errors[] = 'Password is required.';
    elseif (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password, college, department, year) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $old['name'], $old['email'], password_hash($password, PASSWORD_BCRYPT),
            $old['college'], $old['department'], $old['year'],
        ]);
        $uid = (int) db()->lastInsertId();

        db()->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$uid]);

        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        unset($_SESSION['csrf_token']);

        flash('success', 'Welcome to StudentFlow, ' . explode(' ', $old['name'])[0] . '! Let\'s set up your dashboard.');
        header('Location: dashboard.php');
        exit;
    }
}

$page_title = 'Create account';
$dark = false;
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Postgraduate', 'Other'];
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
      <p class="text-sm uppercase tracking-[0.2em] text-indigo-200/80">Data → Analysis → Action</p>
      <h2 class="mt-4 text-4xl font-semibold leading-tight tracking-tight max-w-md">Stop juggling ten apps for your student life.</h2>
      <p class="mt-4 text-indigo-100/90 max-w-md leading-relaxed">Tasks, deadlines, study plans, expenses, habits and goals — combined into one prioritized daily action.</p>
      <div class="mt-8 grid grid-cols-3 gap-4 text-center">
        <div class="rounded-2xl bg-white/10 backdrop-blur p-4"><p class="text-2xl font-semibold">1</p><p class="text-xs text-indigo-100 mt-1">Dashboard</p></div>
        <div class="rounded-2xl bg-white/10 backdrop-blur p-4"><p class="text-2xl font-semibold">10</p><p class="text-xs text-indigo-100 mt-1">Features</p></div>
        <div class="rounded-2xl bg-white/10 backdrop-blur p-4"><p class="text-2xl font-semibold">0</p><p class="text-xs text-indigo-100 mt-1">Missed deadlines</p></div>
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

      <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Create your account</h1>
      <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">Free for students. Takes less than a minute.</p>

      <?php if ($errors): ?>
        <div class="mt-5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 px-4 py-3 text-sm text-rose-700 dark:text-rose-300 space-y-1">
          <?php foreach ($errors as $err): ?>
            <p class="flex items-start gap-2"><i data-lucide="circle-alert" class="w-4 h-4 mt-0.5 shrink-0"></i><span><?= e($err) ?></span></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="register.php" class="mt-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Full Name</label>
            <input id="name" name="name" required value="<?= e($old['name']) ?>" placeholder="Aditi Sharma"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="sm:col-span-2">
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
            <input id="email" name="email" type="email" required value="<?= e($old['email']) ?>" placeholder="you@college.edu"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Password</label>
            <input id="password" name="password" type="password" required minlength="8" placeholder="Min. 8 characters"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label for="confirm_password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" required minlength="8" placeholder="Repeat password"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label for="college" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">College</label>
            <input id="college" name="college" value="<?= e($old['college']) ?>" placeholder="College name"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label for="department" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Department</label>
            <input id="department" name="department" value="<?= e($old['department']) ?>" placeholder="Computer Science"
                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div class="sm:col-span-2">
            <label for="year" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Year</label>
            <select id="year" name="year" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Select your year</option>
              <?php foreach ($years as $y): ?>
                <option value="<?= e($y) ?>" <?= $old['year'] === $y ? 'selected' : '' ?>><?= e($y) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <button type="submit"
                class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold px-4 py-3 shadow-soft transition">
          Create account
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
        Already have an account?
        <a href="login.php" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Log in</a>
      </p>
    </div>
  </div>
</div>
<script>lucide && lucide.createIcons();</script>
</body>
</html>
