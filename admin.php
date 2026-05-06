<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

auth_start_session();
auth_require_admin_page_redirect();

$cu = auth_current_user();
$workspaceName = 'Workspace';
if ($cu !== null) {
    $stmt = db()->prepare('SELECT name FROM workspaces WHERE id = ?');
    $stmt->execute([(int) $cu['workspace_id']]);
    $name = $stmt->fetchColumn();
    if ($name !== false) {
        $workspaceName = (string) $name;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Email countdown</title>
  <?php require_once __DIR__ . '/include/google-fonts.php'; ?>
  <style>
    :root { --bg:#f3f5f4; --surface:#ffffff; --border:#d9e2dc; --text:#0f1720; --muted:#5c6b62; --accent:#004225; --accent-dim:#0a5a36; --ring:rgba(0,66,37,.18); }
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;background:linear-gradient(180deg,#f8faf9 0%,var(--bg) 100%);color:var(--text);font-family:var(--font-body)}
    .wrap{max-width:1080px;margin:0 auto;padding:2.4rem 1.35rem 3rem}
    .top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
    h1{margin:.1rem 0 .25rem;font-family:var(--font-display);font-size:1.95rem;letter-spacing:-.02em}
    .pill{font-size:.82rem;color:var(--muted);font-family:var(--font-mono)}
    .menu{display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 1.15rem}
    .menu a{color:var(--text);text-decoration:none;border:1px solid var(--border);padding:.4rem .78rem;border-radius:999px;font-size:.82rem;font-weight:600}
    .menu a.active{border-color:var(--accent);color:var(--accent);background:#f5fbf7}
    .panel{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:1.1rem 1.25rem;margin-bottom:1rem;box-shadow:0 6px 18px rgba(17,24,39,.06)}
    table{width:100%;border-collapse:collapse;font-size:.88rem}
    th{font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700}
    th,td{padding:.6rem .35rem;border-bottom:1px solid var(--border);text-align:left}
    input,select{width:100%;padding:.55rem .6rem;border:1px solid var(--border);border-radius:8px;background:#ffffff;color:var(--text)}
    input:focus,select:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--ring);outline:none}
    .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
    button{padding:.58rem .85rem;border:none;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-dim));color:#ffffff;font-weight:600;cursor:pointer;transition:transform .12s ease,box-shadow .12s ease}
    button:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(0,66,37,.2)}
    button.secondary{background:#ffffff;color:var(--text);border:1px solid var(--border)}
    .row{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}
    .muted{color:var(--muted);font-size:.82rem}
    @media (max-width: 860px){.grid{grid-template-columns:1fr 1fr}}
    @media (max-width: 620px){.wrap{padding:1.2rem .9rem 2rem}.grid{grid-template-columns:1fr}h1{font-size:1.55rem}.row > *{width:100%}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div>
        <h1>Admin</h1>
        <div class="pill">Workspace: <?= htmlspecialchars($workspaceName, ENT_QUOTES, 'UTF-8') ?> · Role: <?= htmlspecialchars((string)($cu['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <a href="logout.php" class="pill">Log out</a>
    </div>
    <div class="menu">
      <a href="index.php">Dashboard</a>
      <a href="admin.php" class="active">Admin</a>
    </div>

    <div class="panel">
      <h2 style="margin:.1rem 0 .7rem">Billing & Plan</h2>
      <div id="billing" class="muted">Loading…</div>
      <div class="row" style="margin-top:.7rem">
        <select id="plan_key"></select>
        <select id="plan_status">
          <option value="active">active</option>
          <option value="past_due">past_due</option>
          <option value="paused">paused</option>
          <option value="canceled">canceled</option>
        </select>
        <button id="save-plan" type="button">Save billing</button>
      </div>
    </div>

    <div class="panel">
      <h2 style="margin:.1rem 0 .7rem">Members & Roles</h2>
      <table>
        <thead><tr><th>Email</th><th>Name</th><th>Role</th><th>Active</th><th>Action</th></tr></thead>
        <tbody id="members"><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
      </table>
    </div>

    <div class="panel">
      <h2 style="margin:.1rem 0 .7rem">Add / Invite Member</h2>
      <div class="grid">
        <input id="m_email" type="email" placeholder="user@company.com">
        <input id="m_name" type="text" placeholder="Display name">
        <input id="m_password" type="password" placeholder="Temp password (required for new user)">
        <select id="m_role">
          <option value="viewer">viewer</option>
          <option value="editor">editor</option>
          <option value="admin">admin</option>
          <option value="owner">owner</option>
        </select>
      </div>
      <div class="row" style="margin-top:.8rem">
        <button id="add-member" type="button">Add member</button>
        <span id="msg" class="muted"></span>
      </div>
    </div>
  </div>

  <script>
    const MEMBERS_API = 'api/admin_members.php';
    const BILLING_API = 'api/billing.php';
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
              ${['viewer','editor','admin','owner'].map(r => `<option value="${r}" ${m.role===r?'selected':''}>${r}</option>`).join('')}
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

    loadBilling();
    loadMembers();
  </script>
</body>
</html>

