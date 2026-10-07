<?php
/**
 * Common <head> for every page. Expects optional:
 * $page_title (string), $dark (bool)
 */
$dark = !empty($dark);
$page_title = $page_title ?? 'StudentFlow';
?><!DOCTYPE html>
<html lang="en" class="<?= $dark ? 'dark' : '' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="description" content="StudentFlow - Smart Student Life Assistant. Plan studies, manage tasks, track expenses, build habits and stay focused in one dashboard.">
<title><?= e($page_title) ?> · StudentFlow</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'] },
      boxShadow: { soft: '0 2px 15px -3px rgba(15,23,42,.08), 0 4px 6px -4px rgba(15,23,42,.04)' }
    }
  }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-200 antialiased font-sans">
