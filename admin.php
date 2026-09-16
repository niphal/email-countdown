<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_admin_page_redirect();

$appNav = 'admin';
$appTitle = 'Settings';
$appSubtitle = 'Billing, members, and system health for this workspace.';
require __DIR__ . '/include/app_shell_start.php';
?>
<style>
  .grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .8rem; }
  @media (max-width: 860px) { .grid { grid-template-columns: 1fr 1fr; } }
  @media (max-width: 620px) { .grid { grid-template-columns: 1fr; } .row > * { width: 100%; } }
</style>
<div class="card">
      <h2 style="margin:.1rem 0 .35rem">Billing &amp; plan</h2>
      <p class="muted" style="margin:0 0 .7rem">Set the workspace plan and status. Timer limits and premium layouts/fonts follow this plan.</p>
      <div id="billing" class="muted">Loading...</div>
      <div class="row" style="margin-top:.7rem">
        <select id="plan_key" aria-label="Plan"></select>
        <select id="plan_status" aria-label="Plan status">
          <option value="active">Active</option>
          <option value="past_due">Past due</option>
          <option value="paused">Paused</option>
          <option value="canceled">Canceled</option>
        </select>
        <button id="save-plan" type="button">Save billing</button>
      </div>
    </div>

    <div class="card">
      <h2 style="margin:.1rem 0 .35rem">Members &amp; roles</h2>
      <p class="muted" style="margin:0 0 .7rem">Owners and admins can change roles. Viewers can only look; editors can create and edit timers.</p>
      <table>
        <thead><tr><th>Email</th><th>Name</th><th>Role</th><th>Active</th><th>Action</th></tr></thead>
        <tbody id="members"><tr><td colspan="5" class="muted">Loading...</td></tr></tbody>
      </table>
    </div>

    <div class="card">
      <h2 style="margin:.1rem 0 .35rem">Invite member</h2>
      <p class="muted" style="margin:0 0 .7rem">Creates or links a user to this workspace and emails a verification link when mail is configured.</p>
      <div class="grid">
        <div>
          <label for="m_email" class="muted" style="display:block;margin-bottom:.35rem;font-size:.78rem;font-weight:600">Work email</label>
          <input id="m_email" type="email" placeholder="user@company.com" autocomplete="off">
        </div>
        <div>
          <label for="m_name" class="muted" style="display:block;margin-bottom:.35rem;font-size:.78rem;font-weight:600">Display name</label>
          <input id="m_name" type="text" placeholder="Alex Rivera" autocomplete="off">
        </div>
        <div>
          <label for="m_password" class="muted" style="display:block;margin-bottom:.35rem;font-size:.78rem;font-weight:600">Temp password</label>
          <input id="m_password" type="password" placeholder="Required for new users" autocomplete="new-password">
        </div>
        <div>
          <label for="m_role" class="muted" style="display:block;margin-bottom:.35rem;font-size:.78rem;font-weight:600">Role</label>
          <select id="m_role">
            <option value="viewer">Viewer — read only</option>
            <option value="editor" selected>Editor — create &amp; edit</option>
            <option value="admin">Admin — members &amp; billing</option>
            <option value="owner">Owner — full control</option>
          </select>
        </div>
      </div>
      <div class="row" style="margin-top:.8rem">
        <button id="add-member" type="button">Invite member</button>
        <span id="msg" class="muted"></span>
      </div>
    </div>

    <div class="card">
      <h2 style="margin:.1rem 0 .35rem">System health</h2>
      <p class="muted" style="margin:0 0 .7rem">Runtime checks and recent structured events (renders, mail, auth).</p>
      <div id="health-summary" class="muted">Loading health checks...</div>
      <div id="health-grid" class="health-grid"></div>
      <div class="row" style="margin:.8rem 0">
        <button id="refresh-observability" type="button" class="secondary">Refresh events</button>
        <span class="muted">From <code>data/events.jsonl</code></span>
      </div>
      <div id="events" class="events"><div class="muted">Loading events...</div></div>
    </div>
  </div>

  <script>
    const MEMBERS_API = 'api/admin_members.php';
    const BILLING_API = 'api/billing.php';
    const OBS_API = 'api/observability.php';
    const membersEl = document.getElementById('members');
    const msgEl = document.getElementById('msg');

    function esc(s){ return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function setMsg(text) { msgEl.textContent = text || ''; }
    async function parseJsonSafe(resp) {
      const txt = await resp.text();
      try { return JSON.parse(txt); } catch { return { error: txt ? txt.slice(0, 180) : 'Unexpected response' }; }
    }

    async function loadBilling() {
      const r = await fetch(BILLING_API, { credentials: 'same-origin' });
      const j = await parseJsonSafe(r);
      if (!r.ok) {
        setMsg(j.error || 'Could not load billing');
        return;
      }
      const ent = j.entitlements || {};
      const plans = j.plans || {};
      const billing = document.getElementById('billing');
      billing.textContent = `Plan ${ent.plan_name || ent.plan_key} ($${ent.monthly_usd || 0}/mo) · Timers ${ent.timer_count || 0}/${ent.max_timers || 0} · Status ${ent.status || 'active'}`;
      const planSel = document.getElementById('plan_key');
      planSel.innerHTML = '';
      Object.keys(plans).forEach(k => {
        const opt = document.createElement('option');
        opt.value = k;
        opt.textContent = `${plans[k].name} ($${plans[k].monthly_usd}/mo)`;
        if (k === ent.plan_key) opt.selected = true;
        planSel.appendChild(opt);
      });
      document.getElementById('plan_status').value = ent.status || 'active';
    }

    async function loadMembers() {
      const r = await fetch(MEMBERS_API, { credentials: 'same-origin' });
      if (!r.ok) {
        const j = await parseJsonSafe(r);
        setMsg(j.error || 'No access to members API');
        membersEl.innerHTML = '<tr><td colspan="5" class="muted">No access.</td></tr>';
        return;
      }
      const j = await parseJsonSafe(r);
      const rows = j.members || [];
      if (!rows.length) {
        membersEl.innerHTML = '<tr><td colspan="5" class="muted">No members.</td></tr>';
        return;
      }
      membersEl.innerHTML = rows.map(m => `
        <tr>
          <td>${esc(m.email)}</td>
          <td>${esc(m.display_name || '')}</td>
          <td>
            <select data-role-id="${Number(m.id)}">
              ${[['viewer','Viewer'],['editor','Editor'],['admin','Admin'],['owner','Owner']].map(([r,lab]) => `<option value="${r}" ${m.role===r?'selected':''}>${lab}</option>`).join('')}
            </select>
          </td>
          <td><input type="checkbox" data-active-id="${Number(m.id)}" ${Number(m.is_active) ? 'checked' : ''}></td>
          <td><button type="button" class="secondary" data-save-id="${Number(m.id)}">Save</button></td>
        </tr>
      `).join('');
      document.querySelectorAll('[data-save-id]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = Number(btn.getAttribute('data-save-id'));
          const role = document.querySelector(`[data-role-id="${id}"]`).value;
          const isActive = document.querySelector(`[data-active-id="${id}"]`).checked;
          try {
            const rr = await fetch(MEMBERS_API, { method: 'PATCH', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: id, role, is_active: isActive }) });
            const jj = await parseJsonSafe(rr);
            setMsg(rr.ok ? 'Member updated' : (jj.error || 'Update failed'));
            if (rr.ok) loadMembers();
          } catch (e) {
            setMsg('Network error while updating member');
          }
        });
      });
    }

    async function loadObservability() {
      const healthSummary = document.getElementById('health-summary');
      const healthGrid = document.getElementById('health-grid');
      const eventsEl = document.getElementById('events');
      try {
        const r = await fetch(`${OBS_API}?limit=60`, { credentials: 'same-origin' });
        const j = await parseJsonSafe(r);
        if (!r.ok) {
          healthSummary.textContent = j.error || 'Could not load observability data';
          eventsEl.innerHTML = '<div class="muted">No observability access.</div>';
          return;
        }
        const health = j.health || {};
        healthSummary.textContent = `${health.ok ? 'Healthy' : 'Needs attention'} · Request ${esc(health.request_id || '')}`;
        const checks = health.checks || {};
        healthGrid.innerHTML = Object.keys(checks).map(k => {
          const c = checks[k] || {};
          return `<div class="health-item ${c.ok ? 'ok' : 'bad'}"><strong>${esc(k)}</strong><span class="muted">${esc(c.detail || '')}</span></div>`;
        }).join('');
        const events = j.events || [];
        if (!events.length) {
          eventsEl.innerHTML = '<div class="muted">No events recorded yet.</div>';
          return;
        }
        eventsEl.innerHTML = events.map(ev => {
          const fields = ev.fields ? JSON.stringify(ev.fields) : '{}';
          const lvl = esc(ev.level || 'info');
          return `<div><span class="level-${lvl}">${lvl.toUpperCase()}</span> ${esc(ev.ts || '')} <strong>${esc(ev.event || '')}</strong><br><span class="muted">${esc(ev.path || '')} · request ${esc(ev.request_id || '')} · ${esc(fields)}</span></div>`;
        }).join('');
      } catch (e) {
        healthSummary.textContent = 'Network error while loading observability';
      }
    }

    document.getElementById('add-member').addEventListener('click', async () => {
      const payload = {
        email: document.getElementById('m_email').value.trim(),
        display_name: document.getElementById('m_name').value.trim(),
        password: document.getElementById('m_password').value,
        role: document.getElementById('m_role').value,
      };
      if (!payload.email) {
        setMsg('Valid email required');
        return;
      }
      try {
        const r = await fetch(MEMBERS_API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const j = await parseJsonSafe(r);
        setMsg(r.ok ? 'Member added' : (j.error || 'Could not add member'));
        if (r.ok) {
          document.getElementById('m_email').value = '';
          document.getElementById('m_name').value = '';
          document.getElementById('m_password').value = '';
          loadMembers();
        }
      } catch (e) {
        setMsg('Network error while adding member');
      }
    });

    document.getElementById('save-plan').addEventListener('click', async () => {
      const payload = {
        plan_key: document.getElementById('plan_key').value,
        status: document.getElementById('plan_status').value,
      };
      try {
        const r = await fetch(BILLING_API, { method: 'PUT', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const j = await parseJsonSafe(r);
        setMsg(r.ok ? 'Billing updated' : (j.error || 'Billing update failed'));
        if (r.ok) loadBilling();
      } catch (e) {
        setMsg('Network error while updating billing');
      }
    });

    document.getElementById('refresh-observability').addEventListener('click', loadObservability);

    loadBilling();
    loadMembers();
    loadObservability();
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
