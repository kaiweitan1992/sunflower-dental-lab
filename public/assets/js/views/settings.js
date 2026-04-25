// =====================================================
// views/settings.js — invoice numbering + password change
// =====================================================
import { api } from '../api.js';

export async function renderSettings(el, ctx) {
  const [me, settings, stats] = await Promise.all([
    api.get('/auth/me'),
    api.get('/settings'),
    api.get('/stats'),
  ]);

  el.innerHTML = `
    <div class="view-head">
      <div>
        <h1>Settings</h1>
        <div class="muted">Signed in as <b>${escapeHtml(me.user.display_name)}</b> (${escapeHtml(me.user.role)})</div>
      </div>
    </div>

    <div class="stats">
      <div class="stat"><div class="stat-label">Active products</div><div class="stat-value">${stats.active_products}</div></div>
      <div class="stat"><div class="stat-label">Clinics</div><div class="stat-value">${stats.clinics}</div></div>
      <div class="stat"><div class="stat-label">Docs today</div><div class="stat-value">${stats.docs_today}</div></div>
      <div class="stat"><div class="stat-label">Revenue (MTD)</div><div class="stat-value">${ctx.fmt.rm(stats.revenue_mtd)}</div></div>
    </div>

    <div class="card">
      <h3>Invoice numbering</h3>
      <p class="muted" style="margin-bottom:14px">Documents are numbered in the format <b>PREFIX-000123</b>. The next number is reserved automatically every time you create a document.</p>
      <div class="form-grid">
        <label><span>Prefix</span><input id="prefix" maxlength="8" value="${escapeAttr(settings.settings.invoice_prefix || 'SF')}"></label>
        <label><span>Next number</span><input id="next" type="number" min="1" value="${escapeAttr(settings.settings.invoice_next || '1')}"></label>
      </div>
      <div class="btn-row" style="margin-top:14px">
        <button class="btn-primary" id="saveSettings" ${me.user.role !== 'admin' ? 'disabled title="Admin only"' : ''}>Save</button>
      </div>
    </div>

    <div class="card">
      <h3>Change password</h3>
      <div class="form-grid">
        <label class="wide"><span>Current password</span><input id="curPw" type="password" autocomplete="current-password"></label>
        <label class="wide"><span>New password (min 8 chars)</span><input id="newPw" type="password" autocomplete="new-password"></label>
      </div>
      <div class="btn-row" style="margin-top:14px">
        <button class="btn-primary" id="changePwBtn">Change password</button>
      </div>
    </div>
  `;

  el.querySelector('#saveSettings').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      await api.put('/settings', {
        invoice_prefix: el.querySelector('#prefix').value.trim() || 'SF',
        invoice_next:   Number(el.querySelector('#next').value) || 1,
      });
      ctx.toast('Settings saved', 'success');
    } catch (err) {
      ctx.toast(err.message || 'Save failed', 'error');
    } finally {
      btn.disabled = false;
    }
  });

  el.querySelector('#changePwBtn').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const cur = el.querySelector('#curPw').value;
    const nw  = el.querySelector('#newPw').value;
    if (!cur || !nw)         { ctx.toast('Fill in both fields', 'error'); return; }
    if (nw.length < 8)       { ctx.toast('New password must be 8+ chars', 'error'); return; }
    btn.disabled = true;
    try {
      await api.post('/auth/change-password', { current_password: cur, new_password: nw });
      el.querySelector('#curPw').value = '';
      el.querySelector('#newPw').value = '';
      ctx.toast('Password changed', 'success');
    } catch (err) {
      ctx.toast(err.message === 'invalid_current_password' ? 'Current password is wrong' : (err.message || 'Failed'), 'error');
    } finally {
      btn.disabled = false;
    }
  });
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '&quot;'); }
