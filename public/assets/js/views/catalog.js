// =====================================================
// views/catalog.js — product catalog + cart drawer
// =====================================================
import { api } from '../api.js';

let state = {
  products: [],
  categories: [],
  query: '',
  catId: 0,        // 0 = all
  clinics: [],
  selectedClinic: 0,
};

export async function renderCatalog(el, ctx) {
  // Fetch categories + products + clinics in parallel
  const [cats, prods, clins] = await Promise.all([
    api.get('/categories'),
    api.get('/products'),
    api.get('/clinics'),
  ]);
  state.categories = cats.categories || [];
  state.products   = prods.products  || [];
  state.clinics    = clins.clinics   || [];

  el.innerHTML = `
    <div class="view-head">
      <div>
        <h1>Catalog</h1>
        <div class="muted">${state.products.length} active products across ${state.categories.length} categories</div>
      </div>
    </div>

    <div class="toolbar">
      <div class="toolbar-row">
        <div class="search" style="flex:1; min-width:240px">
          <input id="searchInput" placeholder="Search by product name or code…" autocomplete="off">
        </div>
        <select id="clinicSelect" style="max-width:280px">
          <option value="0">— Select clinic for this order —</option>
          ${state.clinics.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('')}
        </select>
      </div>
      <div class="toolbar-row" id="catRow">
        ${renderCatChips()}
      </div>
    </div>

    <div id="productGrid" class="product-grid"></div>
  `;

  el.querySelector('#searchInput').addEventListener('input', e => { state.query = e.target.value.trim().toLowerCase(); paint(); });
  el.querySelector('#catRow').addEventListener('click', e => {
    const c = e.target.closest('.chip'); if (!c) return;
    state.catId = Number(c.dataset.id);
    el.querySelectorAll('#catRow .chip').forEach(x => x.classList.toggle('on', x === c));
    paint();
  });
  el.querySelector('#clinicSelect').addEventListener('change', e => {
    state.selectedClinic = Number(e.target.value);
  });

  ctx.cart.on(() => paint());  // Re-paint when cart changes (badges)
  paint();

  function renderCatChips() {
    const all = `<button class="chip on" data-id="0">All</button>`;
    const rest = state.categories.map(c =>
      `<button class="chip" data-id="${c.id}">${escapeHtml(c.label)}</button>`
    ).join('');
    return all + rest;
  }

  function paint() {
    const grid = el.querySelector('#productGrid');
    const inCart = new Set(ctx.cart.items.map(i => i.productId));
    const filtered = state.products.filter(p => {
      if (state.catId > 0 && p.category_id !== state.catId) return false;
      if (state.query && !(p.name.toLowerCase().includes(state.query) || p.code.toLowerCase().includes(state.query))) return false;
      return true;
    });
    if (filtered.length === 0) {
      grid.innerHTML = `<div class="empty" style="grid-column:1/-1"><h3>No products match</h3><p>Try a different search or category.</p></div>`;
      return;
    }
    grid.innerHTML = filtered.map(p => {
      const qty = ctx.cart.items.find(i => i.productId === p.id)?.qty || 0;
      return `
        <div class="product-card ${qty ? 'in' : ''}" data-id="${p.id}">
          ${qty ? `<span class="qbadge">×${qty}</span>` : ''}
          <div class="product-code">${escapeHtml(p.code)}</div>
          <div class="product-name">${escapeHtml(p.name)}</div>
          <div class="product-foot">
            <div class="product-price">${ctx.fmt.rm(p.price)}</div>
            ${p.note ? `<div class="product-note">${escapeHtml(p.note)}</div>` : ''}
          </div>
        </div>`;
    }).join('');
    grid.querySelectorAll('.product-card').forEach(card => {
      card.addEventListener('click', () => {
        const id = Number(card.dataset.id);
        const p = state.products.find(x => x.id === id);
        if (p) { ctx.cart.add(p); ctx.toast(`Added ${p.name}`, 'success'); }
      });
    });
  }
}

// ---------- Cart drawer (mounted once at boot) ----------
export function mountCart(ctx) {
  const drawer = document.getElementById('cartDrawer');
  const body   = document.getElementById('cartBody');
  const fab    = document.getElementById('cartFab');
  const close  = document.getElementById('cartClose');

  if (!drawer || !body || !fab || !close) {
    console.error('mountCart: required elements missing', { drawer, body, fab, close });
    return;
  }

  fab.addEventListener('click', () => {
    drawer.hidden = false;
    paint();
  });
  close.addEventListener('click', () => {
    drawer.hidden = true;
  });

  // Repaint on every cart change (cheap; ~1ms)
  ctx.cart.on(() => paint());

  // Initial paint
  paint();

  function paint() {
    if (!ctx.cart.items || ctx.cart.items.length === 0) {
      body.innerHTML = '<div class="empty"><h3>Cart is empty</h3><p>Tap a product to add it.</p></div>';
      return;
    }

    const itemsHtml = ctx.cart.items.map(it => `
      <div class="cart-item" data-id="${it.productId}">
        <div class="cart-item-head">
          <div>
            <div class="cart-item-name">${escapeHtml(it.name)}</div>
            <div class="muted" style="font-size:.78rem">${escapeHtml(it.code)} · ${ctx.fmt.rm(it.price)} ea</div>
          </div>
          <button class="btn-ghost remove" title="Remove">×</button>
        </div>
        <div class="cart-item-qty">
          <button class="qty-btn" data-act="dec">−</button>
          <span style="min-width:28px; text-align:center; font-weight:600">${it.qty}</span>
          <button class="qty-btn" data-act="inc">+</button>
          <span style="margin-left:auto; font-weight:600">${ctx.fmt.rm(it.qty * it.price)}</span>
        </div>
        <div class="cart-item-patient">
          <input placeholder="Patient name (optional)" value="${escapeAttr(it.patient)}">
        </div>
      </div>
    `).join('');

    body.innerHTML = `
      ${itemsHtml}
      <div class="cart-totals">
        <div class="cart-row"><span>Subtotal</span><span>${ctx.fmt.rm(ctx.cart.subtotal())}</span></div>
        <div class="cart-row"><span>Discount</span>
          <span><input id="discountInput" type="number" min="0" step="0.01" value="0" style="width:90px; padding:4px 8px; text-align:right"></span>
        </div>
        <div class="cart-row total"><span>Total</span><span id="totalOut">${ctx.fmt.rm(ctx.cart.subtotal())}</span></div>

        <div style="margin-top:14px; display:grid; gap:10px">
          <label><span>Document type</span>
            <select id="docType">
              <option value="invoice">Invoice</option>
              <option value="receipt">Receipt</option>
            </select>
          </label>
          <div id="paymentBox" hidden>
            <label><span>Payment method</span>
              <input id="payMethod" placeholder="Cash / Bank transfer / etc.">
            </label>
          </div>
        </div>

        <div class="btn-row" style="margin-top:14px">
          <button class="btn-primary" id="submitDoc" style="flex:1">Create document</button>
          <button class="btn-ghost" id="clearCart">Clear</button>
        </div>
      </div>
    `;

    // Wire up controls
    body.querySelectorAll('.cart-item').forEach(node => {
      const id = Number(node.dataset.id);
      node.querySelector('.remove').addEventListener('click', () => ctx.cart.remove(id));
      node.querySelectorAll('.qty-btn').forEach(b => b.addEventListener('click', () => {
        const it = ctx.cart.items.find(i => i.productId === id);
        if (!it) return;
        ctx.cart.setQty(id, b.dataset.act === 'inc' ? it.qty + 1 : it.qty - 1);
      }));
      node.querySelector('.cart-item-patient input').addEventListener('input',
        e => ctx.cart.setPatient(id, e.target.value));
    });

    const discIn  = body.querySelector('#discountInput');
    const totalEl = body.querySelector('#totalOut');
    if (discIn && totalEl) {
      discIn.addEventListener('input', () => {
        const sub  = ctx.cart.subtotal();
        const disc = Math.max(0, Number(discIn.value) || 0);
        totalEl.textContent = ctx.fmt.rm(Math.max(0, sub - disc));
      });
    }

    const docType = body.querySelector('#docType');
    if (docType) {
      docType.addEventListener('change', e => {
        body.querySelector('#paymentBox').hidden = e.target.value !== 'receipt';
      });
    }

    const clearBtn = body.querySelector('#clearCart');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        if (confirm('Clear all items?')) ctx.cart.clear();
      });
    }

    const submitBtn = body.querySelector('#submitDoc');
    if (submitBtn) {
      submitBtn.addEventListener('click', async () => {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        try {
          const dt = body.querySelector('#docType').value;
          const payment = body.querySelector('#payMethod')?.value || '';
          const today = new Date().toISOString().slice(0, 10);
          const sel = document.getElementById('clinicSelect');
          const clinicId = sel ? Number(sel.value) || 0 : 0;
          if (!clinicId) {
            ctx.toast('Pick a clinic first (top of catalog).', 'error');
            return;
          }
          const payload = {
            doc_type:  dt,
            clinic_id: clinicId,
            doc_date:  today,
            discount:  Math.max(0, Number(discIn.value) || 0),
            payment_method: dt === 'receipt' ? payment : '',
            paid_at:   dt === 'receipt' ? today : null,
            notes:     '',
            items: ctx.cart.items.map(i => ({
              product_id: i.productId, qty: i.qty, patient_name: i.patient,
            })),
          };
          const { api } = await import('../api.js');
          const res = await api.post('/invoices', payload);
          ctx.toast(`${dt === 'invoice' ? 'Invoice' : 'Receipt'} created`, 'success');
          ctx.cart.clear();
          drawer.hidden = true;
          window.open(`/print.php?id=${res.id}`, '_blank', 'noopener');
        } catch (err) {
          ctx.toast(err.message || 'Failed to save', 'error');
        } finally {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Create document';
        }
      });
    }
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '&quot;'); }
