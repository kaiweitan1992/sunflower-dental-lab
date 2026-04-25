// =====================================================
// app.js — top-level SPA controller
// Wires up: nav, logout, toast, cart state, view loading.
// Each view is a separate module in /assets/js/views/.
// =====================================================

import { api } from './api.js';
import { renderCatalog, mountCart } from './views/catalog.js';
import { renderClinics }  from './views/clinics.js';
import { renderRecords }  from './views/records.js';
import { renderSettings } from './views/settings.js';

const viewEl = document.getElementById('view');
const navEl  = document.getElementById('topnav');

// ---- Toast ----
const toastEl = document.getElementById('toast');
let toastTimer;
export function toast(msg, kind = '') {
  toastEl.textContent = msg;
  toastEl.className = 'toast' + (kind ? ' ' + kind : '');
  toastEl.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastEl.hidden = true; }, 2600);
}

// ---- Money formatter (Malaysian Ringgit) ----
export const fmt = {
  rm: (n) => 'RM ' + Number(n || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
  date: (s) => {
    if (!s) return '';
    const [y, m, d] = String(s).split('-');
    return `${d}/${m}/${y}`;
  },
};

// ---- Cart store (singleton, in-memory) ----
export const cart = {
  items: [],   // {productId, code, name, price, qty, patient}
  add(p) {
    const ex = this.items.find(i => i.productId === p.id);
    if (ex) ex.qty += 1;
    else this.items.push({
      productId: p.id, code: p.code, name: p.name,
      price: p.price, qty: 1, patient: ''
    });
    this._notify();
  },
  setQty(productId, qty) {
    const it = this.items.find(i => i.productId === productId);
    if (!it) return;
    it.qty = Math.max(1, qty | 0);
    this._notify();
  },
  setPatient(productId, name) {
    const it = this.items.find(i => i.productId === productId);
    if (it) it.patient = name;
    this._notify();
  },
  remove(productId) {
    this.items = this.items.filter(i => i.productId !== productId);
    this._notify();
  },
  clear() {
    this.items = [];
    this._notify();
  },
  count() { return this.items.reduce((a, i) => a + i.qty, 0); },
  subtotal() { return this.items.reduce((a, i) => a + i.qty * i.price, 0); },
  _listeners: new Set(),
  on(fn) { this._listeners.add(fn); return () => this._listeners.delete(fn); },
  _notify() { this._listeners.forEach(fn => fn(this)); },
};

// Wire up the floating cart button
const fab   = document.getElementById('cartFab');
const count = document.getElementById('cartCount');
cart.on(c => {
  count.textContent = c.count();
  fab.hidden = c.count() === 0 || currentView !== 'catalog';
});

// ---- View router ----
const views = {
  catalog:  renderCatalog,
  clinics:  renderClinics,
  records:  renderRecords,
  settings: renderSettings,
};
let currentView = 'catalog';

async function load(view) {
  currentView = view;
  navEl.querySelectorAll('.navlink').forEach(b => b.classList.toggle('active', b.dataset.view === view));
  viewEl.innerHTML = '<div class="empty"><span class="spinner"></span> Loading…</div>';
  try {
    await views[view](viewEl, { toast, fmt, cart });
    fab.hidden = !(view === 'catalog' && cart.count() > 0);
  } catch (e) {
    console.error(e);
    viewEl.innerHTML = `<div class="empty"><h3>Failed to load</h3><p>${e.message}</p></div>`;
  }
}

navEl.addEventListener('click', (e) => {
  const btn = e.target.closest('.navlink');
  if (!btn) return;
  load(btn.dataset.view);
});

// Mount the cart drawer (independent of view switches so cart survives navigation)
mountCart({ toast, fmt, cart });

// ---- Logout ----
document.getElementById('logoutBtn').addEventListener('click', async () => {
  try { await api.post('/auth/logout'); } catch {}
  window.location.href = '/login.php';
});

// ---- Boot ----
load('catalog');
