<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
auth_start_session();
auth_require_login_redirect();
db();

$timerEmbedPrefix = app_timer_embed_src_prefix();
$embedNeedsPublicBase = str_starts_with($timerEmbedPrefix, '/');
$embedIsHttpsAbsolute = app_embed_is_https_absolute();
$embedBlockedReason = '';
if ($embedNeedsPublicBase) {
    $embedBlockedReason = 'root-relative';
} elseif (!$embedIsHttpsAbsolute) {
    $embedBlockedReason = 'not-https';
}

$appNav = 'dashboard';
$appTitle = 'Dashboard';
$appSubtitle = 'Build countdown timers, pick styles from the gallery, then copy HTML for email.';
$appTopActions = '<a class="btn btn-primary btn-sm" href="timers.php?new=1">+ New timer</a>';
require __DIR__ . '/include/app_shell_start.php';
?>
    <ul class="steps steps-horizontal w-full max-w-2xl mb-1" aria-label="How to use">
      <li class="step step-primary">Pick a style</li>
      <li class="step step-primary">Build a timer</li>
      <li class="step">Copy for email</li>
    </ul>

    <?php if ($embedBlockedReason === 'root-relative'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail will not load these images yet.</strong> Add <code class="bg-base-100 px-1 rounded">public_base_url</code> as an absolute HTTPS origin in <code class="bg-base-100 px-1 rounded">data/secrets.php</code>.</span>
    </div>
    <?php elseif ($embedBlockedReason === 'not-https'): ?>
    <div role="alert" class="alert alert-error shadow-sm">
      <span><strong>Gmail requires HTTPS image URLs.</strong> Set <code class="bg-base-100 px-1 rounded">public_base_url</code> to <code class="bg-base-100 px-1 rounded">https://…</code> in secrets.</span>
    </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-sm border border-base-300" id="billing-box">
      <div class="card-body flex-row flex-wrap items-center justify-between gap-4 py-5">
        <div>
          <div id="billing-title" class="font-bold text-base">Full access</div>
          <div id="billing-kpis" class="billing-kpis"><span class="text-base-content/50">Loading…</span></div>
        </div>
        <a class="btn btn-outline btn-sm" href="timers.php">Open timers</a>
      </div>
    </div>

    <div class="hub-grid">
      <a class="hub-card" href="timers.php">
        <h2>Timers</h2>
        <p>Create, edit, and copy Gmail-safe countdown HTML. Select one timer at a time to keep the workspace clear.</p>
        <span class="btn btn-primary btn-sm w-fit">Manage timers</span>
      </a>
      <a class="hub-card" href="gallery.php">
        <h2>Template gallery</h2>
        <p>Browse 25+ premade countdown concepts across promo, retail, luxury, and product looks.</p>
        <span class="btn btn-outline btn-sm w-fit">Browse gallery</span>
      </a>
      <a class="hub-card" href="templates.php">
        <h2>My templates</h2>
        <p>Edit saved brand styles, upload backgrounds, and set a default for new timers.</p>
        <span class="btn btn-outline btn-sm w-fit">Open library</span>
      </a>
      <a class="hub-card" href="integrations.php">
        <h2>Integrations</h2>
        <p>Connect Braze to push Content Blocks or use Connected Content Liquid.</p>
        <span class="btn btn-ghost btn-sm w-fit">Open Braze</span>
      </a>
    </div>

    <div class="card bg-base-100 shadow-sm border border-base-300">
      <div class="card-body gap-3">
        <div>
          <h2 class="card-title text-lg">Recent activity</h2>
          <p class="text-sm text-base-content/60 mt-1">Latest changes in this workspace.</p>
        </div>
        <div id="audit" class="audit-lines"><span class="text-base-content/50">Loading…</span></div>
      </div>
    </div>

  <script>
    const BILLING_API = 'api/billing.php';
    const AUDIT_API = 'api/audit.php';

    async function loadBilling() {
      try {
        const r = await fetch(BILLING_API, { credentials: 'same-origin' });
        if (!r.ok) return;
        const j = await r.json();
        const ent = j.entitlements || {};
        document.getElementById('billing-title').textContent = 'Full access';
        document.getElementById('billing-kpis').innerHTML =
          '<span>Timers <strong>' + Number(ent.timer_count || 0) + '</strong></span>' +
          '<span>Layouts <strong>All</strong></span>' +
          '<span>Fonts <strong>All</strong></span>';
      } catch (e) {}
    }

    async function loadAudit() {
      const el = document.getElementById('audit');
      try {
        const r = await fetch(AUDIT_API + '?limit=30', { credentials: 'same-origin' });
        if (!r.ok) {
          el.innerHTML = '<span class="text-base-content/50">Audit unavailable.</span>';
          return;
        }
        const j = await r.json();
        const rows = j.entries || [];
        if (!rows.length) {
          el.innerHTML = '<span class="text-base-content/50">No activity yet.</span>';
          return;
        }
        el.innerHTML = rows.map(row => {
          const t = new Date((row.created_at || 0) * 1000);
          const ts = t.toISOString().replace('T', ' ').slice(0, 19) + 'Z';
          const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
          return '<div>' + esc(ts) + ' · ' + esc(row.action || '') + ' · ' + esc(row.entity_type || '') + ' ' + esc(String(row.entity_id || '').slice(0, 12)) + '...</div>';
        }).join('');
      } catch (e) {
        el.innerHTML = '<span class="text-base-content/50">Could not load audit log.</span>';
      }
    }

    loadBilling();
    loadAudit();
  </script>

<?php require __DIR__ . '/include/app_shell_end.php'; ?>
