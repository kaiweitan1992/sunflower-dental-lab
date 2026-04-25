<?php
declare(strict_types=1);

use Sunflower\Auth;
use Sunflower\Bootstrap;
use Sunflower\Csrf;

$root = dirname(__DIR__);
require $root . '/src/Bootstrap.php';
Bootstrap::init($root);

if (Auth::check()) {
    header('Location: /');
    exit;
}
$csrf = Csrf::token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in — Sunflower Dental Lab</title>
<link rel="stylesheet" href="/assets/css/styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-mark">SF</div>
    <h1>Sunflower Dental Lab</h1>
    <p class="muted">Sign in to manage orders, clinics &amp; invoices.</p>

    <form id="loginForm" autocomplete="on" novalidate>
      <label>
        <span>Username</span>
        <input name="username" type="text" required autocomplete="username" autofocus>
      </label>
      <label>
        <span>Password</span>
        <input name="password" type="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn-primary">Sign in</button>
      <p id="loginErr" class="form-error" hidden></p>
    </form>
    <p class="login-foot muted">
      Open source · <a href="https://github.com/" target="_blank" rel="noopener">view on GitHub</a>
    </p>
  </main>

<script>
const form = document.getElementById('loginForm');
const err  = document.getElementById('loginErr');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  err.hidden = true;
  const fd = new FormData(form);
  const body = JSON.stringify({ username: fd.get('username'), password: fd.get('password') });
  try {
    const r = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body
    });
    if (r.ok) {
      window.location.href = '/';
      return;
    }
    const data = await r.json().catch(() => ({}));
    err.textContent = data.error === 'invalid_credentials'
      ? 'Wrong username or password.'
      : 'Sign-in failed. Please try again.';
    err.hidden = false;
  } catch (e) {
    err.textContent = 'Network error. Check your connection.';
    err.hidden = false;
  }
});
</script>
</body>
</html>
