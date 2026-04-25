<?php
declare(strict_types=1);

use Sunflower\Auth;
use Sunflower\Bootstrap;
use Sunflower\Config;
use Sunflower\Csrf;

$root = dirname(__DIR__);
require $root . '/src/Bootstrap.php';
Bootstrap::init($root);

if (!Auth::check()) {
    header('Location: /login.php');
    exit;
}
$user    = Auth::user();
$csrf    = Csrf::token();
$bizName = Config::get('BUSINESS_NAME', 'Sunflower Dental Lab');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<title><?= htmlspecialchars($bizName) ?></title>
<link rel="stylesheet" href="/assets/css/styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<header class="topbar">
  <div class="brand">
    <span class="brand-mark">SF</span>
    <div>
      <div class="brand-name"><?= htmlspecialchars($bizName) ?></div>
      <div class="brand-sub muted">Order &amp; Invoice Manager</div>
    </div>
  </div>
  <nav class="topnav" id="topnav">
    <button data-view="catalog"  class="navlink active">Catalog</button>
    <button data-view="clinics"  class="navlink">Clinics</button>
    <button data-view="records"  class="navlink">Records</button>
    <button data-view="settings" class="navlink">Settings</button>
  </nav>
  <div class="topright">
    <span class="user-chip" title="Signed in"><?= htmlspecialchars($user['display_name']) ?></span>
    <button id="logoutBtn" class="btn-ghost">Sign out</button>
  </div>
</header>

<main id="view" class="view"></main>

<!-- Floating cart button (visible on Catalog) -->
<button id="cartFab" class="cart-fab" hidden>
  <span>Cart</span>
  <span class="cart-count" id="cartCount">0</span>
</button>

<!-- Cart drawer -->
<aside id="cartDrawer" class="drawer" hidden>
  <div class="drawer-head">
    <h2>Cart</h2>
    <button class="btn-ghost" id="cartClose">×</button>
  </div>
  <div class="drawer-body" id="cartBody"></div>
</aside>

<!-- Toasts -->
<div id="toast" class="toast" hidden></div>

<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
