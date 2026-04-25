// =====================================================
// views/clinics.js — clinic management
// =====================================================
import { api } from '../api.js';

export async function renderClinics(el, ctx) {
  let clinics = (await api.get('/clinics')).clinics;
  let editing = null;  // null | clinic-id

  paint();

  function paint() {
    el.innerHTML = `
      <div class="view-head">
        <div>
          <h1>Clinics</h1>
          <div class="muted">${clinics.length} clinic${clinics.length === 1 ? '' : 's'}</div>
        </div>
        <div class="btn-row">
          <button class="btn-primary" id="addBtn">+ Add clinic</button>
        </div>
      </div>

      <div id="formBox"></div>

      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Address</th><th></th>
          </tr></thead>
          <tbody>
            ${clinics.length === 0
              ? `<tr><td colspan="6" class="empty">No clinics yet. Add your first one.</td></tr>`
              : clinics.map(c => `
                <tr data-id="${c.id}">
                  <td><b>${escapeHtml(c.name)}</b></td>
                  <td>${escapeHtml(c.contact_person)}</td>
                  <td>${escapeHtml(c.phone)}</td>
                  <td>${escapeHtml(c.email)}</td>
                  <td class="muted">${escapeHtml(c.address)}</td>
                  <td class="right">
                    <button class="btn-ghost edit">Edit</button>
                    <button class="btn-danger del">Delete</button>
                  </td>
                </tr>`).join('')}
          </tbody>
        </table>
      </div>
    `;

    el.querySelector('#addBtn').addEventListener('click', () => openForm(null));
    el.querySelectorAll('tr[data-id]').forEach(row => {
      const id = Number(row.dataset.id);
      row.querySelector('.edit').addEventListener('click', () => openForm(id));
      row.querySelector('.del').addEventListener('click',  () => removeClinic(id));
    });
  }

  function openForm(id) {
    editing = id;
    const c = id ? clinics.find(x => x.id === id) : { name:'', contact_person:'', phone:'', email:'', address:'', notes:'' };
    const box = el.querySelector('#formBox');
    box.innerHTML = `
      <div class="card" style="margin-bottom:16px">
        <h3 style="margin-bottom:14px">${id ? 'Edit clinic' : 'New clinic'}</h3>
        <div class="form-grid">
          <label class="wide"><span>Name</span><input name="name"           value="${escapeAttr(c.name)}"></label>
          <label><span>Contact person</span><input name="contact_person"    value="${escapeAttr(c.contact_person)}"></label>
          <label><span>Phone</span><input name="phone"                       value="${escapeAttr(c.phone)}"></label>
          <label><span>Email</span><input name="email" type="email"          value="${escapeAttr(c.email)}"></label>
          <label class="wide"><span>Address</span><input name="address"      value="${escapeAttr(c.address)}"></label>
          <label class="wide"><span>Notes</span><textarea name="notes" rows="2">${escapeHtml(c.notes || '')}</textarea></label>
        </div>
        <div class="btn-row" style="margin-top:14px">
          <button class="btn-primary" id="saveBtn">${id ? 'Save changes' : 'Create clinic'}</button>
          <button class="btn-ghost"   id="cancelBtn">Cancel</button>
        </div>
      </div>
    `;
    box.querySelector('#cancelBtn').addEventListener('click', () => { box.innerHTML = ''; });
    box.querySelector('#saveBtn').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      const data = Object.fromEntries(
        ['name', 'contact_person', 'phone', 'email', 'address', 'notes']
          .map(k => [k, box.querySelector(`[name="${k}"]`).value.trim()])
      );
      if (!data.name) { ctx.toast('Name is required', 'error'); btn.disabled = false; return; }
      try {
        if (editing) await api.put(`/clinics/${editing}`, data);
        else         await api.post('/clinics', data);
        ctx.toast('Saved', 'success');
        clinics = (await api.get('/clinics')).clinics;
        paint();
      } catch (err) {
        ctx.toast(err.message || 'Save failed', 'error');
      } finally {
        btn.disabled = false;
      }
    });
  }

  async function removeClinic(id) {
    if (!confirm('Delete this clinic? Past invoices will retain a snapshot of the clinic info.')) return;
    try {
      await api.delete(`/clinics/${id}`);
      clinics = clinics.filter(c => c.id !== id);
      paint();
      ctx.toast('Deleted', 'success');
    } catch (err) {
      ctx.toast(err.message || 'Delete failed', 'error');
    }
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}
function escapeAttr(s) { return escapeHtml(s).replaceAll('"', '&quot;'); }
