// =====================================================
// views/records.js — invoice & receipt records
// Date range filters + summary tiles + table with print.
// =====================================================
import { api } from '../api.js';

const today = () => new Date().toISOString().slice(0, 10);

function rangeFor(mode) {
  const now = new Date();
  const ymd = (d) => d.toISOString().slice(0, 10);
  switch (mode) {
    case 'thisMonth':
      return [ymd(new Date(now.getFullYear(), now.getMonth(), 1)), ymd(now)];
    case 'lastMonth': {
      const f = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      const t = new Date(now.getFullYear(), now.getMonth(), 0);
      return [ymd(f), ymd(t)];
    }
    case 'thisYear':
      return [ymd(new Date(now.getFullYear(), 0, 1)), ymd(now)];
    default:
      return ['', ''];
  }
}

export async function renderRecords(el, ctx) {
  let filters = { from: '', to: '', type: '', q: '' };
  let data = { invoices: [], summary: { count: 0, subtotal: 0, discount: 0, total: 0 } };
  let stats = null;

  await fetch1();
  paint();

  async function fetch1() {
    const qs = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => { if (v) qs.set(k, v); });
    const [recs, st] = await Promise.all([
      api.get('/invoices?' + qs.toString()),
      api.get('/stats'),
    ]);
    data  = recs;
    stats = st;
  }

  async function refilter() {
    el.querySelector('#tableWrap').innerHTML = '<div class="empty"><span class="spinner"></span></div>';
    await fetch1();
    paint();
  }

  function paint() {
    el.innerHTML = `
      <div class="view-head">
        <div>
          <h1>Records</h1>
          <div class="muted">Invoices &amp; receipts</div>
        </div>
      </div>

      <div class="stats">
        <div class="stat"><div class="stat-label">Documents</div><div class="stat-value">${data.summary.count}</div></div>
        <div class="stat"><div class="stat-label">Subtotal</div><div class="stat-value">${ctx.fmt.rm(data.summary.subtotal)}</div></div>
        <div class="stat"><div class="stat-label">Discount</div><div class="stat-value">${ctx.fmt.rm(data.summary.discount)}</div></div>
        <div class="stat"><div class="stat-label">Total</div><div class="stat-value" style="color:var(--gold)">${ctx.fmt.rm(data.summary.total)}</div></div>
      </div>

      <div class="toolbar">
        <div class="toolbar-row">
          <button class="chip" data-q="all">All time</button>
          <button class="chip" data-q="thisMonth">This month</button>
          <button class="chip" data-q="lastMonth">Last month</button>
          <button class="chip" data-q="thisYear">This year</button>
        </div>
        <div class="toolbar-row">
          <input type="date" id="fromDate" value="${filters.from}" style="max-width:160px">
          <span class="muted">to</span>
          <input type="date" id="toDate" value="${filters.to}" style="max-width:160px">
          <select id="typeSel" style="max-width:140px">
            <option value="">All types</option>
            <option value="invoice"${filters.type === 'invoice' ? ' selected' : ''}>Invoices</option>
            <option value="receipt"${filters.type === 'receipt' ? ' selected' : ''}>Receipts</option>
          </select>
          <button class="btn-primary" id="applyBtn">Apply</button>
          <button class="btn-ghost"   id="resetBtn">Reset</button>
        </div>
      </div>

      <div id="tableWrap" class="table-wrap">${tableHtml()}</div>
    `;

    el.querySelectorAll('.chip').forEach(c => c.addEventListener('click', async () => {
      const [from, to] = rangeFor(c.dataset.q);
      filters.from = from; filters.to = to;
      el.querySelector('#fromDate').value = from;
      el.querySelector('#toDate').value   = to;
      await refilter();
    }));
    el.querySelector('#applyBtn').addEventListener('click', async () => {
      filters.from = el.querySelector('#fromDate').value;
      filters.to   = el.querySelector('#toDate').value;
      filters.type = el.querySelector('#typeSel').value;
      await refilter();
    });
    el.querySelector('#resetBtn').addEventListener('click', async () => {
      filters = { from: '', to: '', type: '', q: '' };
      await refilter();
    });

    bindRowActions();
  }

  function tableHtml() {
    if (data.invoices.length === 0) {
      return `<div class="empty"><h3>No records</h3><p>Create an invoice or receipt from the Catalog.</p></div>`;
    }
    return `
      <table>
        <thead><tr>
          <th>Doc No</th><th>Type</th><th>Date</th><th>Clinic</th>
          <th class="right">Subtotal</th><th class="right">Discount</th><th class="right">Total</th><th></th>
        </tr></thead>
        <tbody>
          ${data.invoices.map(r => `
            <tr data-id="${r.id}">
              <td><b>${escapeHtml(r.doc_no)}</b></td>
              <td><span class="chip" style="background:${r.doc_type === 'receipt' ? 'rgba(26,122,64,.12)' : 'var(--gold-bg)'}; color:${r.doc_type === 'receipt' ? 'var(--green)' : 'var(--mid)'}">${r.doc_type}</span></td>
              <td>${ctx.fmt.date(r.doc_date)}</td>
              <td>${escapeHtml(r.clinic_name)}</td>
              <td class="right num">${ctx.fmt.rm(r.subtotal)}</td>
              <td class="right num">${ctx.fmt.rm(r.discount)}</td>
              <td class="right num"><b>${ctx.fmt.rm(r.total)}</b></td>
              <td class="right"><button class="btn-ghost open">View / Print</button></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }

  function bindRowActions() {
    el.querySelectorAll('tr[data-id]').forEach(row => {
      row.querySelector('.open').addEventListener('click', () => {
        window.open(`/print.php?id=${row.dataset.id}`, '_blank', 'noopener');
      });
    });
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
