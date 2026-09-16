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
    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-5">
        <div>
          <h2 class="card-title text-lg">Access</h2>
          <p class="text-sm text-base-content/60 mt-1">Every account currently has full access to timers, layouts, fonts, and integrations. Plan labels are kept for future billing only.</p>
        </div>
        <div id="billing" class="text-sm text-base-content/70">Loading…</div>
        <div class="flex flex-wrap gap-3 items-end">
          <fieldset class="fieldset p-0 min-w-[12rem] flex-1">
            <label class="label" for="plan_key"><span class="label-text font-semibold">Plan label</span></label>
            <select id="plan_key" class="select select-bordered w-full" aria-label="Plan"></select>
          </fieldset>
          <fieldset class="fieldset p-0 min-w-[10rem] flex-1">
            <label class="label" for="plan_status"><span class="label-text font-semibold">Status</span></label>
            <select id="plan_status" class="select select-bordered w-full" aria-label="Plan status">
              <option value="active">Active</option>
              <option value="past_due">Past due</option>
              <option value="paused">Paused</option>
              <option value="canceled">Canceled</option>
            </select>
          </fieldset>
          <button id="save-plan" type="button" class="btn btn-primary">Save</button>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-4">
        <div>
          <h2 class="card-title text-lg">Members &amp; roles</h2>
          <p class="text-sm text-base-content/60 mt-1">All signed-in members can use the full product. Roles are kept for labeling only right now.</p>
        </div>
        <div class="overflow-x-auto rounded-box border border-base-300">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Email</th>
                <th>Name</th>
                <th>Role</th>
                <th>Active</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="members"><tr><td colspan="5" class="text-base-content/50">Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-5">
        <div>
          <h2 class="card-title text-lg">Invite member</h2>
          <p class="text-sm text-base-content/60 mt-1">Creates or links a user to this workspace and emails a verification link when mail is configured.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <fieldset class="fieldset p-0">
            <label class="label" for="m_email"><span class="label-text font-semibold">Work email</span></label>
            <input id="m_email" type="email" placeholder="user@company.com" autocomplete="off" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="m_name"><span class="label-text font-semibold">Display name</span></label>
            <input id="m_name" type="text" placeholder="Alex Rivera" autocomplete="off" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="m_password"><span class="label-text font-semibold">Temp password</span></label>
            <input id="m_password" type="password" placeholder="Required for new users" autocomplete="new-password" class="input input-bordered w-full">
          </fieldset>
          <fieldset class="fieldset p-0">
            <label class="label" for="m_role"><span class="label-text font-semibold">Role</span></label>
            <select id="m_role" class="select select-bordered w-full">
              <option value="viewer">Viewer — read only</option>
              <option value="editor" selected>Editor — create &amp; edit</option>
              <option value="admin">Admin — members &amp; billing</option>
              <option value="owner">Owner — full control</option>
            </select>
          </fieldset>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button id="add-member" type="button" class="btn btn-primary">Invite member</button>
          <span id="msg" class="text-sm text-base-content/60"></span>
        </div>
      </div>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-4">
        <div>
          <h2 class="card-title text-lg">System health</h2>
          <p class="text-sm text-base-content/60 mt-1">Runtime checks and recent structured events (renders, mail, auth).</p>
        </div>
        <div id="health-summary" class="text-sm text-base-content/70">Loading health checks…</div>
        <div id="health-grid" class="health-grid"></div>
        <div class="flex flex-wrap items-center gap-3">
          <button id="refresh-observability" type="button" class="btn btn-outline btn-sm">Refresh events</button>
          <span class="text-sm text-base-content/50">From <code class="bg-base-200 px-1 rounded">data/events.jsonl</code></span>
        </div>
        <div id="events" class="events"><div class="text-base-content/50">Loading events…</div></div>
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
      billing.textContent = `Full access · ${ent.timer_count || 0} timer${Number(ent.timer_count || 0) === 1 ? '' : 's'} · Label ${ent.plan_name || ent.plan_key} · Status ${ent.status || 'active'}`;
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
        membersEl.innerHTML = '<tr><td colspan="5" class="text-base-content/50">No access.</td></tr>';
        return;
      }
      const j = await parseJsonSafe(r);
      const rows = j.members || [];
      if (!rows.length) {
        membersEl.innerHTML = '<tr><td colspan="5" class="text-base-content/50">No members.</td></tr>';
        return;
      }
      membersEl.innerHTML = rows.map(m => `
        <tr>
          <td>${esc(m.email)}</td>
          <td>${esc(m.display_name || '')}</td>
          <td>
            <select class="select select-bordered select-sm" data-role-id="${Number(m.id)}">
              ${[['viewer','Viewer'],['editor','Editor'],['admin','Admin'],['owner','Owner']].map(([r,lab]) => `<option value="${r}" ${m.role===r?'selected':''}>${lab}</option>`).join('')}
            </select>
          </td>
          <td><input type="checkbox" class="checkbox checkbox-sm checkbox-primary" data-active-id="${Number(m.id)}" ${Number(m.is_active) ? 'checked' : ''}></td>
          <td><button type="button" class="btn btn-outline btn-xs" data-save-id="${Number(m.id)}">Save</button></td>
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
          eventsEl.innerHTML = '<div class="text-base-content/50">No observability access.</div>';
          return;
        }
        const health = j.health || {};
        healthSummary.textContent = `${health.ok ? 'Healthy' : 'Needs attention'} · Request ${esc(health.request_id || '')}`;
        const checks = health.checks || {};
        healthGrid.innerHTML = Object.keys(checks).map(k => {
          const c = checks[k] || {};
          return `<div class="health-item ${c.ok ? 'ok' : 'bad'}"><strong>${esc(k)}</strong><span class="text-sm text-base-content/60 block mt-1">${esc(c.detail || '')}</span></div>`;
        }).join('');
        const events = j.events || [];
        if (!events.length) {
          eventsEl.innerHTML = '<div class="text-base-content/50">No events recorded yet.</div>';
          return;
        }
        eventsEl.innerHTML = events.map(ev => {
          const fields = ev.fields ? JSON.stringify(ev.fields) : '{}';
          const lvl = esc(ev.level || 'info');
          return `<div><span class="level-${lvl}">${lvl.toUpperCase()}</span> ${esc(ev.ts || '')} <strong>${esc(ev.event || '')}</strong><br><span class="text-base-content/50">${esc(ev.path || '')} · request ${esc(ev.request_id || '')} · ${esc(fields)}</span></div>`;
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
