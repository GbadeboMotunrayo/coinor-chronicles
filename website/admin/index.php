<?php
/**
 * Coinor Chronicles — Creator Control Panel
 * Password-protected admin dashboard.
 * Set ADMIN_PASSWORD in config.local.php before deploying.
 */
require_once __DIR__ . '/../includes/config.php';

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: (defined('ADMIN_PASS') ? ADMIN_PASS : 'changeme123'));

session_start();

// ── Auth ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['cc_admin'] = true;
        header('Location: /admin/');
        exit;
    }
    $auth_error = 'Wrong passphrase.';
}

if (empty($_SESSION['cc_admin'])) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Creator Control — Login</title>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .login-box { background:var(--mid); border:1px solid rgba(201,168,76,.18); border-radius:12px; padding:3rem 2.5rem; max-width:400px; width:100%; text-align:center; }
    .login-box h1 { font-size:1.4rem; color:var(--gold); margin-bottom:.5rem; letter-spacing:.1em; text-transform:uppercase; }
    .login-box p  { font-size:.8rem; color:var(--cream-dim); opacity:.6; margin-bottom:2rem; }
    .login-input { width:100%; background:var(--dark); border:1px solid rgba(201,168,76,.2); border-radius:6px; padding:.8rem 1rem; color:var(--cream); font-family:var(--font-serif); font-size:.95rem; margin-bottom:1rem; outline:none; transition:border-color .2s; }
    .login-input:focus { border-color:var(--gold); }
    .login-btn { width:100%; padding:.8rem; background:var(--gold); color:var(--dark); border:none; border-radius:6px; font-family:var(--font-serif); font-size:.9rem; letter-spacing:.12em; text-transform:uppercase; cursor:pointer; transition:background .2s; }
    .login-btn:hover { background:var(--gold-bright); }
    .error { color:var(--red-down); font-size:.8rem; margin-bottom:.75rem; }
  </style>
</head>
<body>
  <div class="login-box">
    <div style="font-size:2rem;margin-bottom:.75rem;">♚</div>
    <h1>Creator Control</h1>
    <p>The Obsidian Citadel admits only the author of the chronicle.</p>
    <?php if (!empty($auth_error)): ?><p class="error"><?= htmlspecialchars($auth_error) ?></p><?php endif; ?>
    <form method="POST">
      <input type="password" name="password" class="login-input" placeholder="Passphrase…" autofocus autocomplete="current-password"/>
      <button type="submit" class="login-btn">Enter the Citadel</button>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}

// ── Logout ───────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// ── Load DB ───────────────────────────────────────────────────────────────────
$db_ok = false;
$db_error = '';
try {
    require_once __DIR__ . '/../includes/db.php';
    $db_ok = true;
} catch (Throwable $e) {
    $db_error = $e->getMessage();
}

// ── Handle POST actions ───────────────────────────────────────────────────────
$action_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_override') {
        $text  = trim($_POST['override_text'] ?? '');
        $clan  = trim($_POST['target_clan'] ?? '') ?: null;
        $prio  = (int)($_POST['priority'] ?? 5);
        if ($text) {
            try {
                $pdo = get_db();
                $stmt = $pdo->prepare("INSERT INTO creator_control (target_clan, override_text, priority) VALUES (?, ?, ?)");
                $stmt->execute([$clan, $text, $prio]);
                $action_msg = 'success:Override saved. It will be used in the next pipeline run.';
            } catch (Throwable $e) {
                $action_msg = 'error:DB error: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete_override') {
        $id = (int)($_POST['override_id'] ?? 0);
        if ($id) {
            try {
                $pdo = get_db();
                $pdo->prepare("DELETE FROM creator_control WHERE id = ?")->execute([$id]);
                $action_msg = 'success:Override deleted.';
            } catch (Throwable $e) {
                $action_msg = 'error:' . $e->getMessage();
            }
        }
    }

    if ($action === 'publish_toggle') {
        $id  = (int)($_POST['story_id'] ?? 0);
        $val = (int)($_POST['published'] ?? 0);
        if ($id) {
            try {
                $pdo = get_db();
                $pdo->prepare("UPDATE chronicles SET published=?, published_at=IF(?=1 AND published_at IS NULL, NOW(), published_at) WHERE id=?")
                    ->execute([$val, $val, $id]);
                $action_msg = 'success:Chronicle ' . ($val ? 'published' : 'unpublished') . '.';
            } catch (Throwable $e) {
                $action_msg = 'error:' . $e->getMessage();
            }
        }
    }
}

// ── Fetch dashboard data ──────────────────────────────────────────────────────
$stats        = ['chronicles'=>0,'subscribers'=>0,'last_run'=>'—','total_today'=>0];
$overrides    = [];
$recent_logs  = [];
$recent_chron = [];
$char_status  = [];

if ($db_ok) {
    try {
        $pdo = get_db();

        // Stats
        $stats['chronicles']  = (int)$pdo->query("SELECT COUNT(*) FROM chronicles WHERE published=1")->fetchColumn();
        $stats['subscribers'] = (int)$pdo->query("SELECT COUNT(*) FROM subscribers WHERE active=1")->fetchColumn();
        $stats['last_run']    = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='last_run'")->fetchColumn() ?: '—';
        $stats['total_today'] = (int)$pdo->query("SELECT COUNT(*) FROM chronicles WHERE DATE(created_at)=CURDATE()")->fetchColumn();

        // Active overrides
        $overrides = $pdo->query("SELECT * FROM creator_control WHERE is_active=1 ORDER BY priority DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        // Recent pipeline logs
        $recent_logs = $pdo->query("SELECT * FROM pipeline_logs ORDER BY ran_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

        // Recent chronicles
        $recent_chron = $pdo->query("SELECT id,episode_number,title,season,clan,published,published_at,created_at FROM chronicles ORDER BY created_at DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);

        // Character status
        $char_status = $pdo->query("SELECT * FROM character_status ORDER BY clan, character_name")->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        $db_error = $e->getMessage();
    }
}

// ── Parse action message ──────────────────────────────────────────────────────
$msg_type = '';
$msg_text = '';
if ($action_msg) {
    [$msg_type, $msg_text] = explode(':', $action_msg, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Creator Control — Coinor Chronicles</title>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <style>
    /* ── Admin layout ── */
    body { min-height:100vh; }
    #admin-nav {
      position:fixed; top:0; left:0; right:0; height:54px; z-index:1000;
      background:rgba(13,12,10,.97); border-bottom:1px solid rgba(201,168,76,.12);
      display:flex; align-items:center; justify-content:space-between;
      padding:0 1.5rem;
    }
    .admin-brand { color:var(--gold); font-size:.9rem; letter-spacing:.18em; text-transform:uppercase; }
    .admin-nav-links { display:flex; gap:1.5rem; }
    .admin-nav-links a { font-size:.75rem; color:var(--cream-dim); letter-spacing:.1em; text-transform:uppercase; opacity:.6; transition:opacity .2s; }
    .admin-nav-links a:hover { opacity:1; }
    .admin-logout { font-size:.72rem; color:var(--red-down); opacity:.7; letter-spacing:.1em; text-transform:uppercase; cursor:pointer; background:none; border:none; font-family:var(--font-serif); }
    .admin-logout:hover { opacity:1; }

    #admin-content { padding: 80px 1.5rem 4rem; max-width: 1200px; margin:0 auto; }

    /* ── Stats row ── */
    .stat-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:2rem; }
    .admin-stat { background:var(--mid); border:1px solid rgba(201,168,76,.1); border-radius:10px; padding:1.25rem 1.5rem; }
    .admin-stat-num { font-size:2rem; color:var(--gold); display:block; }
    .admin-stat-label { font-size:.62rem; letter-spacing:.22em; text-transform:uppercase; color:var(--cream-dim); opacity:.5; margin-top:.2rem; display:block; }

    /* ── Sections ── */
    .admin-section { background:var(--mid); border:1px solid rgba(201,168,76,.1); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
    .admin-section-title { font-size:.7rem; letter-spacing:.28em; text-transform:uppercase; color:var(--gold); opacity:.7; margin-bottom:1.25rem; display:flex; align-items:center; gap:.6rem; }

    /* ── Tables ── */
    .admin-table { width:100%; border-collapse:collapse; font-size:.82rem; }
    .admin-table th { font-size:.6rem; letter-spacing:.2em; text-transform:uppercase; color:var(--cream-dim); opacity:.5; text-align:left; padding:.6rem .8rem; border-bottom:1px solid rgba(201,168,76,.08); white-space:nowrap; }
    .admin-table td { padding:.65rem .8rem; border-bottom:1px solid rgba(255,255,255,.04); color:var(--cream-dim); vertical-align:middle; }
    .admin-table tr:last-child td { border-bottom:none; }
    .admin-table tr:hover td { background:rgba(255,255,255,.02); }

    /* ── Badges ── */
    .badge { display:inline-block; font-size:.58rem; letter-spacing:.1em; text-transform:uppercase; padding:.18rem .55rem; border-radius:100px; }
    .badge-gold   { background:rgba(201,168,76,.1);  border:1px solid rgba(201,168,76,.25);  color:var(--gold); }
    .badge-green  { background:rgba(93,222,138,.08); border:1px solid rgba(93,222,138,.22);  color:var(--green-up); }
    .badge-red    { background:rgba(224,93,93,.08);  border:1px solid rgba(224,93,93,.22);   color:var(--red-down); }
    .badge-gray   { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); color:var(--cream-dim); opacity:.6; }
    .badge-blue   { background:rgba(91,140,255,.08); border:1px solid rgba(91,140,255,.22);  color:#8aabff; }

    /* ── Override form ── */
    .override-form { display:grid; gap:.75rem; }
    .form-row { display:grid; grid-template-columns:1fr 1fr auto; gap:.75rem; align-items:end; }
    @media(max-width:600px) { .form-row { grid-template-columns:1fr; } }
    .form-group { display:flex; flex-direction:column; gap:.35rem; }
    .form-label { font-size:.6rem; letter-spacing:.2em; text-transform:uppercase; color:var(--cream-dim); opacity:.55; }
    .form-input, .form-select, .form-textarea {
      background:var(--dark); border:1px solid rgba(201,168,76,.18); border-radius:6px;
      padding:.6rem .85rem; color:var(--cream); font-family:var(--font-serif); font-size:.88rem;
      outline:none; transition:border-color .2s; width:100%;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color:var(--gold); }
    .form-select option { background:var(--dark); }
    .form-textarea { resize:vertical; min-height:90px; }
    .btn-admin { padding:.6rem 1.2rem; background:var(--gold); color:var(--dark); border:none; border-radius:6px; font-family:var(--font-serif); font-size:.8rem; letter-spacing:.1em; text-transform:uppercase; cursor:pointer; transition:background .2s; white-space:nowrap; }
    .btn-admin:hover { background:var(--gold-bright); }
    .btn-danger { background:transparent; border:1px solid rgba(224,93,93,.35); color:var(--red-down); font-size:.72rem; padding:.35rem .75rem; border-radius:6px; cursor:pointer; transition:all .2s; font-family:var(--font-serif); letter-spacing:.1em; }
    .btn-danger:hover { background:rgba(224,93,93,.12); border-color:var(--red-down); }
    .btn-sm { padding:.35rem .75rem; font-size:.72rem; }

    /* ── Alert ── */
    .alert { padding:.8rem 1.1rem; border-radius:6px; font-size:.82rem; margin-bottom:1.25rem; }
    .alert-success { background:rgba(93,222,138,.08); border:1px solid rgba(93,222,138,.25); color:var(--green-up); }
    .alert-error   { background:rgba(224,93,93,.08);  border:1px solid rgba(224,93,93,.25);  color:var(--red-down); }

    /* ── Heaven progress mini ── */
    .h-bar { height:3px; background:rgba(255,255,255,.07); border-radius:3px; overflow:hidden; width:80px; }
    .h-fill { height:100%; background:linear-gradient(90deg,var(--accent),var(--gold)); border-radius:3px; }

    /* ── Log status ── */
    .log-success { color:var(--green-up); }
    .log-failed  { color:var(--red-down); }
    .log-skipped { color:var(--cream-dim); opacity:.5; }

    /* ── Pipeline trigger ── */
    .trigger-box { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
    .trigger-url { font-size:.72rem; color:var(--cream-dim); opacity:.5; font-family:monospace; background:var(--dark); padding:.4rem .75rem; border-radius:4px; border:1px solid rgba(255,255,255,.06); flex:1; word-break:break-all; }
  </style>
</head>
<body>

<!-- Admin nav -->
<nav id="admin-nav">
  <span class="admin-brand">♚ Creator Control</span>
  <div class="admin-nav-links">
    <a href="#overrides">Overrides</a>
    <a href="#chronicles">Chronicles</a>
    <a href="#fellowship">Fellowship</a>
    <a href="#logs">Logs</a>
  </div>
  <a href="?logout=1" class="admin-logout">Logout</a>
</nav>

<div id="admin-content">

  <?php if ($db_error): ?>
  <div class="alert alert-error">⚠ Database not connected — <?= htmlspecialchars($db_error) ?>. Set up the DB first.</div>
  <?php endif; ?>

  <?php if ($msg_text): ?>
  <div class="alert alert-<?= $msg_type === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($msg_text) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stat-row">
    <div class="admin-stat">
      <span class="admin-stat-num"><?= $stats['chronicles'] ?></span>
      <span class="admin-stat-label">Published Chronicles</span>
    </div>
    <div class="admin-stat">
      <span class="admin-stat-num"><?= $stats['subscribers'] ?></span>
      <span class="admin-stat-label">Active Subscribers</span>
    </div>
    <div class="admin-stat">
      <span class="admin-stat-num"><?= $stats['total_today'] ?></span>
      <span class="admin-stat-label">Episodes Today</span>
    </div>
    <div class="admin-stat">
      <span class="admin-stat-num" style="font-size:1rem;padding-top:.5rem;"><?= htmlspecialchars(substr($stats['last_run'],0,16)) ?></span>
      <span class="admin-stat-label">Last Pipeline Run</span>
    </div>
  </div>


  <!-- Pipeline trigger -->
  <div class="admin-section">
    <div class="admin-section-title">⚡ Pipeline Trigger</div>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.65;margin-bottom:1rem;">
      The pipeline runs automatically every 6 hours via cron. Use this URL in Hostinger hPanel → Cron Jobs,
      or visit it directly to force a run. Keep it secret.
    </p>
    <div class="trigger-box">
      <span class="trigger-url"><?= htmlspecialchars(SITE_URL) ?>/admin/run-pipeline.php?key=<?= htmlspecialchars(substr(hash('sha256', ADMIN_PASSWORD), 0, 16)) ?></span>
      <a href="/admin/run-pipeline.php?key=<?= htmlspecialchars(substr(hash('sha256', ADMIN_PASSWORD), 0, 16)) ?>" class="btn-admin btn-sm" target="_blank">Run Now ↗</a>
    </div>
  </div>


  <!-- Creator Override -->
  <div class="admin-section" id="overrides">
    <div class="admin-section-title">✍ Story Direction Overrides</div>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.65;margin-bottom:1.5rem;">
      Tell the AI where to take the story next — without touching the code.
      The override is consumed on the next pipeline run.
    </p>

    <form method="POST" class="override-form" style="margin-bottom:2rem;">
      <input type="hidden" name="action" value="add_override"/>
      <div class="form-group">
        <label class="form-label">Story direction</label>
        <textarea name="override_text" class="form-textarea" placeholder="e.g. Aragorn reaches Gate II today. Make it feel earned — the siege has been long. Reference the weeks of battle on the Electric Plains. Tone: triumphant but solemn." required></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Target clan (optional — leave blank for any)</label>
          <select name="target_clan" class="form-select">
            <option value="">Any clan</option>
            <option value="ancients">Clan of the Ancients</option>
            <option value="swift">Clan of the Swift</option>
            <option value="meme_lords">Clan of Meme Lords</option>
            <option value="builders">Clan of the Builders</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priority (1 = low, 10 = highest)</label>
          <input type="number" name="priority" class="form-input" value="5" min="1" max="10"/>
        </div>
        <button type="submit" class="btn-admin">Save Override</button>
      </div>
    </form>

    <?php if ($overrides): ?>
    <table class="admin-table">
      <thead><tr><th>Direction</th><th>Clan</th><th>Priority</th><th>Created</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($overrides as $ov): ?>
      <tr>
        <td style="max-width:400px;"><?= htmlspecialchars(mb_strimwidth($ov['override_text'], 0, 120, '…')) ?></td>
        <td><?= $ov['target_clan'] ? '<span class="badge badge-gold">' . htmlspecialchars($ov['target_clan']) . '</span>' : '<span class="badge badge-gray">Any</span>' ?></td>
        <td><?= (int)$ov['priority'] ?></td>
        <td style="white-space:nowrap;font-size:.75rem;"><?= substr($ov['created_at'],0,16) ?></td>
        <td>
          <form method="POST" onsubmit="return confirm('Delete this override?')">
            <input type="hidden" name="action" value="delete_override"/>
            <input type="hidden" name="override_id" value="<?= (int)$ov['id'] ?>"/>
            <button type="submit" class="btn-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.45;font-style:italic;">No active overrides. The AI chooses the road freely.</p>
    <?php endif; ?>
  </div>


  <!-- Recent Chronicles -->
  <div class="admin-section" id="chronicles">
    <div class="admin-section-title">📜 Recent Chronicles</div>
    <?php if ($recent_chron): ?>
    <table class="admin-table">
      <thead><tr><th>#</th><th>Title</th><th>Season</th><th>Clan</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($recent_chron as $ch): ?>
      <tr>
        <td style="color:var(--gold);opacity:.7;"><?= str_pad((int)$ch['episode_number'],3,'0',STR_PAD_LEFT) ?></td>
        <td style="max-width:320px;font-size:.8rem;"><?= htmlspecialchars(mb_strimwidth(strip_tags($ch['title']),0,80,'…')) ?></td>
        <td>
          <?php if ($ch['season']==='golden'): ?><span class="badge badge-green">Golden</span>
          <?php elseif ($ch['season']==='dark'): ?><span class="badge badge-red">Dark Siege</span>
          <?php else: ?><span class="badge badge-gray">Flat</span><?php endif; ?>
        </td>
        <td style="font-size:.75rem;"><?= htmlspecialchars($ch['clan']) ?></td>
        <td>
          <?php if ($ch['published']): ?><span class="badge badge-green">Published</span>
          <?php else: ?><span class="badge badge-gray">Draft</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap;font-size:.72rem;"><?= substr($ch['created_at'],0,16) ?></td>
        <td>
          <div style="display:flex;gap:.5rem;align-items:center;">
            <a href="/story.php?id=<?= (int)$ch['id'] ?>" class="badge badge-gold" target="_blank" style="text-decoration:none;">View</a>
            <form method="POST">
              <input type="hidden" name="action" value="publish_toggle"/>
              <input type="hidden" name="story_id" value="<?= (int)$ch['id'] ?>"/>
              <input type="hidden" name="published" value="<?= $ch['published'] ? 0 : 1 ?>"/>
              <button type="submit" class="btn-danger" style="font-size:.65rem;padding:.25rem .6rem;">
                <?= $ch['published'] ? 'Unpublish' : 'Publish' ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.45;font-style:italic;">No chronicles yet. Run the pipeline to generate the first episode.</p>
    <?php endif; ?>
  </div>


  <!-- Fellowship Status -->
  <div class="admin-section" id="fellowship">
    <div class="admin-section-title">⚔ Fellowship — Current Positions</div>
    <?php if ($char_status): ?>
    <table class="admin-table">
      <thead><tr><th>Character</th><th>Coin</th><th>Clan</th><th>Gate</th><th>Heaven</th><th>Progress</th><th>Price</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ($char_status as $cs): ?>
      <tr>
        <td><?= htmlspecialchars($cs['character_name']) ?></td>
        <td><span class="badge badge-gray"><?= htmlspecialchars(strtoupper($cs['coin_ticker'])) ?></span></td>
        <td style="font-size:.72rem;opacity:.6;"><?= htmlspecialchars($cs['clan']) ?></td>
        <td><span class="badge badge-gold">Gate <?= (int)$cs['gate_number'] ?></span></td>
        <td>Heaven <?= (int)$cs['heaven_number'] ?></td>
        <td>
          <div class="h-bar"><div class="h-fill" style="width:<?= min(100, (int)$cs['heaven_number']*10) ?>%"></div></div>
        </td>
        <td style="font-size:.75rem;font-family:monospace;">$<?= number_format((float)$cs['current_price'], 2) ?></td>
        <td style="font-size:.7rem;opacity:.5;white-space:nowrap;"><?= substr($cs['last_updated'],0,16) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.45;font-style:italic;">Run the pipeline once to populate character positions.</p>
    <?php endif; ?>
  </div>


  <!-- Pipeline Logs -->
  <div class="admin-section" id="logs">
    <div class="admin-section-title">🗒 Pipeline Logs</div>
    <?php if ($recent_logs): ?>
    <table class="admin-table">
      <thead><tr><th>Time</th><th>Clan</th><th>Status</th><th>Duration</th><th>Story</th><th>Error</th></tr></thead>
      <tbody>
      <?php foreach ($recent_logs as $log): ?>
      <tr>
        <td style="white-space:nowrap;font-size:.72rem;"><?= substr($log['ran_at'],0,16) ?></td>
        <td style="font-size:.75rem;"><?= htmlspecialchars($log['clan'] ?? '—') ?></td>
        <td><span class="badge <?= $log['status']==='success'?'badge-green':($log['status']==='failed'?'badge-red':'badge-gray') ?>"><?= htmlspecialchars($log['status']) ?></span></td>
        <td style="font-size:.75rem;"><?= $log['duration_ms'] ? number_format($log['duration_ms']).'ms' : '—' ?></td>
        <td><?= $log['story_id'] ? '<a href="/story.php?id='.(int)$log['story_id'].'" style="color:var(--gold);font-size:.72rem;" target="_blank">Episode #'.(int)$log['story_id'].'</a>' : '—' ?></td>
        <td style="font-size:.72rem;color:var(--red-down);max-width:280px;"><?= htmlspecialchars(mb_strimwidth($log['error_msg']??'',0,80,'…')) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="font-size:.82rem;color:var(--cream-dim);opacity:.45;font-style:italic;">No logs yet.</p>
    <?php endif; ?>
  </div>

</div><!-- /admin-content -->

<script>
// Smooth anchor scroll
document.querySelectorAll('a[href^="#"]').forEach(function(a){
  a.addEventListener('click',function(e){
    const el=document.querySelector(a.getAttribute('href'));
    if(el){e.preventDefault();el.scrollIntoView({behavior:'smooth'});}
  });
});
</script>
</body>
</html>
