<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Partner Applications';

// Ensure table exists
db()->exec("CREATE TABLE IF NOT EXISTS `hotel_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `business_name` varchar(160) NOT NULL,
  `contact_name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(220) NOT NULL,
  `property_type` varchar(60) NOT NULL,
  `rooms_count` smallint unsigned DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `message` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle status update
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $id     = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $notes  = trim($_POST['admin_notes'] ?? '');

    if ($id && in_array($action, ['approve','reject','pending'])) {
        $statusMap = ['approve'=>'approved','reject'=>'rejected','pending'=>'pending'];
        db()->prepare('UPDATE hotel_applications SET status=?, admin_notes=? WHERE id=?')
           ->execute([$statusMap[$action], $notes ?: null, $id]);
        flashSet('success', 'Application ' . $statusMap[$action] . '.');
    }
    header('Location: applications.php'); exit;
}

$filter = in_array($_GET['filter']??'', ['pending','approved','rejected']) ? $_GET['filter'] : 'all';
$where  = $filter !== 'all' ? 'WHERE status=?' : '';
$params = $filter !== 'all' ? [$filter] : [];
$stmt   = db()->prepare("SELECT * FROM hotel_applications $where ORDER BY created_at DESC");
$stmt->execute($params);
$apps   = $stmt->fetchAll();

$counts = db()->query("SELECT status, COUNT(*) c FROM hotel_applications GROUP BY status")->fetchAll();
$cnt = ['pending'=>0,'approved'=>0,'rejected'=>0,'all'=>0];
foreach ($counts as $row) { $cnt[$row['status']] = (int)$row['c']; $cnt['all'] += (int)$row['c']; }

include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">🏢 Partner Applications</div>

  <!-- Filter tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val=>$lbl): ?>
      <a href="?filter=<?= $val ?>"
         style="padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:500;text-decoration:none;border:1.5px solid;
                <?= $filter===$val ? 'background:var(--navy);color:#fff;border-color:var(--navy)' : 'background:#fff;color:var(--text2);border-color:var(--border)' ?>">
        <?= $lbl ?> <span style="opacity:.7">(<?= $cnt[$val] ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($apps)): ?>
    <div class="empty"><div class="icon">📭</div><h3>No applications</h3><p>Nothing here yet for this filter.</p></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:16px">
    <?php foreach ($apps as $a):
      $badgeClass = match($a['status']) { 'approved'=>'badge-green','rejected'=>'badge-coral', default=>'badge-grey' };
    ?>
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px 24px;box-shadow:var(--shadow-sm)">
      <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:220px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <span style="font-weight:700;font-size:1rem;color:var(--navy)"><?= e($a['business_name']) ?></span>
            <span class="badge <?= $badgeClass ?>"><?= ucfirst($a['status']) ?></span>
            <span class="badge badge-grey" style="text-transform:capitalize"><?= e($a['property_type']) ?></span>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:4px 20px;font-size:.83rem;color:var(--text2)">
            <div>👤 <?= e($a['contact_name']) ?></div>
            <div>✉️ <a href="mailto:<?= e($a['email']) ?>" style="color:var(--navy)"><?= e($a['email']) ?></a></div>
            <?php if ($a['phone']): ?><div>📞 <?= e($a['phone']) ?></div><?php endif; ?>
            <div>📍 <?= e($a['location']) ?></div>
            <div>🛏 <?= $a['rooms_count'] ?> room<?= $a['rooms_count']!=1?'s':'' ?></div>
            <?php if ($a['website']): ?><div>🌐 <a href="<?= e($a['website']) ?>" target="_blank" rel="noopener" style="color:var(--navy)"><?= e($a['website']) ?></a></div><?php endif; ?>
            <div style="color:var(--text3)">📅 <?= date('M j, Y', strtotime($a['created_at'])) ?></div>
          </div>
          <?php if ($a['message']): ?>
            <div style="margin-top:10px;font-size:.83rem;color:var(--text2);background:var(--off);padding:10px 13px;border-radius:6px;line-height:1.6">
              <?= nl2br(e($a['message'])) ?>
            </div>
          <?php endif; ?>
          <?php if ($a['admin_notes']): ?>
            <div style="margin-top:8px;font-size:.8rem;color:var(--text3)">Admin notes: <?= e($a['admin_notes']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <form method="POST" style="display:flex;flex-direction:column;gap:8px;min-width:200px">
          <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
          <input type="hidden" name="app_id"  value="<?= $a['id'] ?>">
          <textarea name="admin_notes" placeholder="Internal notes (optional)…" rows="2"
                    style="font-size:.8rem;padding:7px 9px;border:1px solid var(--border);border-radius:6px;resize:vertical"><?= e($a['admin_notes']??'') ?></textarea>
          <div style="display:flex;gap:6px">
            <?php if ($a['status'] !== 'approved'): ?>
              <button type="submit" name="action" value="approve" class="btn btn-navy btn-sm">✓ Approve</button>
            <?php endif; ?>
            <?php if ($a['status'] !== 'rejected'): ?>
              <button type="submit" name="action" value="reject"  class="btn btn-danger btn-sm"
                      data-confirm="Reject application from <?= e($a['business_name']) ?>?">✕ Reject</button>
            <?php endif; ?>
            <?php if ($a['status'] !== 'pending'): ?>
              <button type="submit" name="action" value="pending" class="btn btn-outline btn-sm">↩ Reset</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
