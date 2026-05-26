<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Support Tickets';

// Ensure table exists
db()->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('booking','payment','account','technical','partnership','other') NOT NULL DEFAULT 'other',
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `admin_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$errors = [];

// Handle POST: reply or status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)($_POST['ticket_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && $action === 'reply') {
        $reply  = trim($_POST['admin_reply'] ?? '');
        $status = in_array($_POST['status']??'', ['open','in_progress','resolved','closed']) ? $_POST['status'] : 'in_progress';
        if ($reply) {
            db()->prepare('UPDATE support_tickets SET admin_reply=?, status=?, replied_at=NOW() WHERE id=?')
               ->execute([$reply, $status, $id]);
            flashSet('success', '✅ Reply sent and ticket updated.');
        } else {
            flashSet('error', 'Reply cannot be empty.');
        }
    } elseif ($id && $action === 'status') {
        $status = in_array($_POST['status']??'', ['open','in_progress','resolved','closed']) ? $_POST['status'] : 'open';
        db()->prepare('UPDATE support_tickets SET status=? WHERE id=?')->execute([$status, $id]);
        flashSet('success', 'Ticket status updated.');
    } elseif ($id && $action === 'delete') {
        db()->prepare('DELETE FROM support_tickets WHERE id=?')->execute([$id]);
        flashSet('success', 'Ticket deleted.');
    }
    header('Location: support.php'.($_GET['filter']??''?'?filter='.$_GET['filter']:'')); exit;
}

// Filter
$filter = in_array($_GET['filter']??'', ['open','in_progress','resolved','closed']) ? $_GET['filter'] : 'all';
$where  = $filter !== 'all' ? 'WHERE status=?' : '';
$params = $filter !== 'all' ? [$filter] : [];

// View single ticket
$view   = (int)($_GET['view'] ?? 0);
$ticket = null;
if ($view) {
    $s = db()->prepare('SELECT * FROM support_tickets WHERE id=?');
    $s->execute([$view]);
    $ticket = $s->fetch();
}

$stmt = db()->prepare("SELECT * FROM support_tickets $where ORDER BY
  FIELD(status,'open','in_progress','resolved','closed'),
  FIELD(priority,'urgent','high','normal','low'),
  created_at DESC");
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Counts
$counts = db()->query("SELECT status, COUNT(*) c FROM support_tickets GROUP BY status")->fetchAll();
$cnt = ['open'=>0,'in_progress'=>0,'resolved'=>0,'closed'=>0,'all'=>0];
foreach ($counts as $r) { $cnt[$r['status']] = (int)$r['c']; $cnt['all'] += (int)$r['c']; }

// Priority + status styles
function priStyle(string $p): string {
    return match($p) {
        'urgent' => 'background:#fef2f2;color:#b91c1c;border-color:#fca5a5',
        'high'   => 'background:#fff7ed;color:#c2410c;border-color:#fdba74',
        'low'    => 'background:#f0fdf4;color:#15803d;border-color:#86efac',
        default  => 'background:#eff6ff;color:#1d4ed8;border-color:#93c5fd',
    };
}
function statusStyle(string $s): string {
    return match($s) {
        'open'        => 'badge-coral',
        'in_progress' => 'badge-navy',
        'resolved'    => 'badge-green',
        'closed'      => 'badge-grey',
        default       => 'badge-grey',
    };
}
function catIcon(string $c): string {
    return match($c) {
        'booking'     => '📋',
        'payment'     => '💳',
        'account'     => '👤',
        'technical'   => '🔧',
        'partnership' => '🏨',
        default       => '💬',
    };
}
function humanStatus(string $s): string {
    return match($s) {
        'in_progress' => 'In Progress',
        default       => ucfirst($s),
    };
}

include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">💬 Support Tickets</div>

  <!-- Filter tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['all'=>'All','open'=>'🔴 Open','in_progress'=>'🔵 In Progress','resolved'=>'✅ Resolved','closed'=>'⬛ Closed'] as $val=>$lbl): ?>
      <a href="?filter=<?= $val ?>"
         style="padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:500;text-decoration:none;border:1.5px solid;
                <?= $filter===$val ? 'background:var(--navy);color:#fff;border-color:var(--navy)' : 'background:#fff;color:var(--text2);border-color:var(--border)' ?>">
        <?= $lbl ?> <span style="opacity:.7">(<?= $cnt[$val] ?? 0 ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($ticket): ?>
  <!-- ── SINGLE TICKET VIEW ── -->
  <div style="margin-bottom:16px">
    <a href="support.php?filter=<?= $filter ?>" style="color:var(--navy);text-decoration:none;font-size:.85rem">← Back to tickets</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 280px;gap:24px;align-items:start">
    <div>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:26px;box-shadow:var(--shadow-sm);margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px">
          <span style="font-size:1.4rem"><?= catIcon($ticket['category']) ?></span>
          <h2 style="font-size:1.05rem;font-weight:700;color:var(--navy);margin:0"><?= e($ticket['subject']) ?></h2>
          <span class="badge <?= statusStyle($ticket['status']) ?>"><?= humanStatus($ticket['status']) ?></span>
          <span style="font-size:.75rem;font-weight:600;padding:3px 10px;border-radius:12px;border:1px solid;<?= priStyle($ticket['priority']) ?>">
            <?= ucfirst($ticket['priority']) ?> priority
          </span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:6px 20px;font-size:.82rem;color:var(--text2);margin-bottom:18px">
          <div>👤 <?= e($ticket['name']) ?></div>
          <div>✉️ <a href="mailto:<?= e($ticket['email']) ?>" style="color:var(--navy)"><?= e($ticket['email']) ?></a></div>
          <div>🏷 <?= ucfirst($ticket['category']) ?></div>
          <div>📅 <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
          <?php if ($ticket['user_id']): ?><div>🔗 User #<?= $ticket['user_id'] ?></div><?php endif; ?>
        </div>

        <div style="background:var(--off);border-radius:8px;padding:16px 18px;font-size:.88rem;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= e($ticket['message']) ?></div>
      </div>

      <?php if ($ticket['admin_reply']): ?>
      <div style="background:#fff;border:1.5px solid #86efac;border-radius:var(--r);padding:24px;box-shadow:var(--shadow-sm);margin-bottom:20px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
          <div style="width:30px;height:30px;background:var(--navy);border-radius:50%;display:grid;place-items:center;color:#fff;font-size:.75rem;font-weight:700">A</div>
          <div>
            <div style="font-weight:600;font-size:.85rem;color:var(--navy)">Admin reply</div>
            <div style="font-size:.75rem;color:var(--text3)"><?= $ticket['replied_at'] ? date('M j, Y g:i A', strtotime($ticket['replied_at'])) : '' ?></div>
          </div>
          <span class="badge badge-green" style="margin-left:auto">Sent</span>
        </div>
        <div style="font-size:.88rem;color:var(--text);line-height:1.8;white-space:pre-wrap"><?= e($ticket['admin_reply']) ?></div>
      </div>
      <?php endif; ?>

      <!-- Reply form -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;box-shadow:var(--shadow-sm)">
        <h3 style="font-size:.95rem;font-weight:700;color:var(--navy);margin-bottom:16px"><?= $ticket['admin_reply'] ? '✏️ Update reply' : '✍️ Send reply' ?></h3>
        <form method="POST">
          <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
          <input type="hidden" name="ticket_id"  value="<?= $ticket['id'] ?>">
          <input type="hidden" name="action"     value="reply">
          <div class="form-group">
            <label>Reply to <?= e($ticket['name']) ?></label>
            <textarea name="admin_reply" rows="6" placeholder="Write your reply here…" required><?= e($ticket['admin_reply']??'') ?></textarea>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="form-group" style="margin:0;flex:1;min-width:160px">
              <label>Set status</label>
              <select name="status">
                <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
                  <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= humanStatus($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:22px">Send Reply →</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ticket sidebar -->
    <div>
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:20px;box-shadow:var(--shadow-sm);margin-bottom:16px">
        <h4 style="font-size:.88rem;font-weight:700;color:var(--navy);margin-bottom:14px">Quick actions</h4>
        <form method="POST" style="display:flex;flex-direction:column;gap:8px">
          <input type="hidden" name="_csrf"     value="<?= csrf() ?>">
          <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
          <input type="hidden" name="action"    value="status">
          <select name="status" style="font-size:.82rem">
            <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= humanStatus($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline btn-sm">Update status</button>
        </form>
        <hr style="margin:14px 0;border-color:var(--border)">
        <form method="POST" onsubmit="return confirm('Delete this ticket permanently?')">
          <input type="hidden" name="_csrf"     value="<?= csrf() ?>">
          <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
          <input type="hidden" name="action"    value="delete">
          <button type="submit" class="btn btn-danger btn-sm btn-full">🗑 Delete ticket</button>
        </form>
      </div>

      <div style="background:var(--off);border-radius:var(--r);padding:16px;font-size:.8rem;color:var(--text2);line-height:1.7">
        <strong>Ticket #<?= $ticket['id'] ?></strong><br>
        Submitted: <?= date('M j, Y', strtotime($ticket['created_at'])) ?><br>
        Category: <?= ucfirst($ticket['category']) ?><br>
        Priority: <?= ucfirst($ticket['priority']) ?><br>
        <?php if ($ticket['replied_at']): ?>
        Last reply: <?= date('M j, Y', strtotime($ticket['replied_at'])) ?>
        <?php else: ?>
        <span style="color:var(--coral);font-weight:600">⚠ Awaiting reply</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php elseif (empty($tickets)): ?>
  <div class="empty"><div class="icon">📭</div><h3>No tickets</h3><p>No support tickets for this filter.</p></div>

  <?php else: ?>
  <!-- ── TICKET LIST ── -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($tickets as $t):
      $isUnreplied = !$t['admin_reply'] && $t['status'] === 'open';
    ?>
    <div style="background:#fff;border:1.5px solid <?= $isUnreplied ? 'var(--coral)' : 'var(--border)' ?>;border-radius:var(--r);padding:18px 22px;box-shadow:var(--shadow-sm);display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <!-- Icon + category -->
      <div style="width:42px;height:42px;background:var(--sky);border-radius:10px;display:grid;place-items:center;font-size:1.3rem;flex-shrink:0"><?= catIcon($t['category']) ?></div>

      <!-- Main content -->
      <div style="flex:1;min-width:220px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:5px">
          <a href="?view=<?= $t['id'] ?>&filter=<?= $filter ?>" style="font-weight:700;font-size:.95rem;color:var(--navy);text-decoration:none"><?= e($t['subject']) ?></a>
          <span class="badge <?= statusStyle($t['status']) ?>"><?= humanStatus($t['status']) ?></span>
          <span style="font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:10px;border:1px solid;<?= priStyle($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span>
          <?php if ($isUnreplied): ?><span class="badge badge-coral" style="animation:pulse 2s infinite">⚡ New</span><?php endif; ?>
        </div>
        <div style="font-size:.8rem;color:var(--text2);margin-bottom:6px">
          <?= catIcon($t['category']) ?> <?= ucfirst($t['category']) ?> &nbsp;·&nbsp;
          👤 <?= e($t['name']) ?> &nbsp;·&nbsp;
          ✉️ <?= e($t['email']) ?> &nbsp;·&nbsp;
          📅 <?= date('M j, Y g:i A', strtotime($t['created_at'])) ?>
        </div>
        <p style="font-size:.82rem;color:var(--text2);line-height:1.5;margin:0"><?= e(mb_substr($t['message'],0,140)) ?><?= mb_strlen($t['message'])>140?'…':'' ?></p>
        <?php if ($t['admin_reply']): ?>
        <div style="margin-top:8px;font-size:.78rem;color:var(--text3);font-style:italic">
          ✅ Replied <?= $t['replied_at'] ? date('M j', strtotime($t['replied_at'])) : '' ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
        <a href="?view=<?= $t['id'] ?>&filter=<?= $filter ?>" class="btn btn-navy btn-sm">View →</a>
        <form method="POST" onsubmit="return confirm('Delete this ticket?')">
          <input type="hidden" name="_csrf"     value="<?= csrf() ?>">
          <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="action"    value="delete">
          <button type="submit" class="btn btn-danger btn-sm btn-full">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div></div>
<style>
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
