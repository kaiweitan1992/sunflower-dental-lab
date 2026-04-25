// =====================================================
// api.js — thin fetch wrapper for the SPA
// Adds CSRF token automatically; surfaces server errors
// as Error instances with .status and .details.
// =====================================================

const meta = document.querySelector('meta[name="csrf-token"]');
let csrf = meta ? meta.content : '';

export function setCsrf(token) {
  csrf = token || csrf;
  if (meta) meta.content = csrf;
}

async function request(method, path, body) {
  const headers = { 'Accept': 'application/json' };
  const opts = { method, headers, credentials: 'same-origin' };

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers['X-CSRF-Token'] = csrf;
  }

  let res;
  try {
    res = await fetch('/api' + path, opts);
  } catch (e) {
    const err = new Error('Network unreachable');
    err.status = 0;
    throw err;
  }

  // 401 → bounce to login (unless we're already on login)
  if (res.status === 401 && !location.pathname.endsWith('/login.php')) {
    window.location.href = '/login.php';
    throw new Error('redirecting');
  }

  let data = null;
  try { data = await res.json(); } catch { /* empty body is fine */ }

  if (!res.ok) {
    const err = new Error(data?.error || `HTTP ${res.status}`);
    err.status  = res.status;
    err.details = data?.details || null;
    throw err;
  }
  return data ?? {};
}

export const api = {
  get:    (p)        => request('GET',    p),
  post:   (p, body)  => request('POST',   p, body ?? {}),
  put:    (p, body)  => request('PUT',    p, body ?? {}),
  delete: (p)        => request('DELETE', p),
};
