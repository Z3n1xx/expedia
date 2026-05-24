<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Partner Applications';

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
  `staff_user_id` int DEFAULT NULL,
  `hotel_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Safely add new columns if upgrading from old schema
foreach (['staff_user_id INT DEFAULT NULL', 'hotel_id INT DEFAULT NULL'] as $col) {
    $colName = explode(' ', $col)[0];
    try { db()->exec("ALTER TABLE hotel_applications ADD COLUMN $col"); } catch(Exception $e) {}
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id     = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $notes  = trim($_POST['admin_notes'] ?? '');

    if ($id && in_array($action, ['approve','reject','pending'])) {
        $app = db()->prepare('SELECT * FROM hotel_applications WHERE id=?');
        $app->execute([$id]);
        $app = $app->fetch();

        if ($action === 'approve' && $app && $app['status'] !== 'approved') {

            // ── 1. Find matching location or create one ──────────────
            $city = trim(explode(',', $app['location'])[0]);
            $loc  = db()->prepare('SELECT id FROM locations WHERE city LIKE ?');
            $loc->execute(['%'.$city.'%']);
            $locRow = $loc->fetch();
            if ($locRow) {
                $locId = (int)$locRow['id'];
            } else {
                db()->prepare('INSERT INTO locations (city, country) VALUES (?, ?)')->execute([$city, 'Philippines']);
                $locId = (int)db()->lastInsertId();
            }

            // ── 2. Create hotel listing ──────────────────────────────
            $existingHotelId = $app['hotel_id'] ?? null;
            if (!$existingHotelId) {
                $typeThumb = [
                    'resort'     => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
                    'villa'      => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&q=80',
                    'hostel'     => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=800&q=80',
                    'guesthouse' => 'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=800&q=80',
                    'apartment'  => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=800&q=80',
                    'lodge'      => 'https://images.unsplash.com/photo-1455587734955-081b22074882?w=800&q=80',
                    'hotel'      => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
                    'other'      => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
                ];
                $thumb = $typeThumb[$app['property_type']] ?? $typeThumb['hotel'];
                db()->prepare(
                    'INSERT INTO hotels (location_id,name,description,address,stars,rating,review_count,thumbnail,amenities,is_active)
                     VALUES (?,?,?,?,3,0.00,0,?,\'[]\',1)'
                )->execute([$locId, $app['business_name'], $app['message'] ?: 'A quality property in '.$city.'.', $app['location'], $thumb]);
                $newHotelId = (int)db()->lastInsertId();
            } else {
                $newHotelId = (int)$existingHotelId;
            }

            // ── 3. Create or find staff user account ─────────────────
            $existingStaffId = $app['staff_user_id'] ?? null;
            if (!$existingStaffId) {
                // Check if a user with this email already exists
                $existUser = db()->prepare('SELECT id, role FROM users WHERE email=?');
                $existUser->execute([$app['email']]);
                $existUser = $existUser->fetch();

                if ($existUser) {
                    // Upgrade existing user to staff and assign hotel
                    db()->prepare('UPDATE users SET role=\'staff\', hotel_id=? WHERE id=?')
                       ->execute([$newHotelId, $existUser['id']]);
                    $staffUserId = (int)$existUser['id'];
                    $tempPassword = null; // they already have a password
                } else {
                    // Generate a random temporary password
                    $tempPassword = ucfirst(substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 5)) . rand(10,99) . '!';
                    $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost'=>10]);
                    $nameParts = explode(' ', trim($app['contact_name']), 2);
                    $firstName = $nameParts[0];
                    $lastName  = $nameParts[1] ?? $app['business_name'];
                    db()->prepare(
                        'INSERT INTO users (first_name,last_name,email,phone,password,role,hotel_id) VALUES (?,?,?,?,?,\'staff\',?)'
                    )->execute([$firstName, $lastName, $app['email'], $app['phone'], $hash, $newHotelId]);
                    $staffUserId = (int)db()->lastInsertId();
                }
            } else {
                $staffUserId  = (int)$existingStaffId;
                $tempPassword = null;
            }

            // ── 4. Update application record ─────────────────────────
            db()->prepare(
                'UPDATE hotel_applications SET status=\'approved\', admin_notes=?, staff_user_id=?, hotel_id=? WHERE id=?'
            )->execute([$notes ?: null, $staffUserId, $newHotelId, $id]);

            // ── 5. Build success message ─────────────────────────────
            $msg = '✅ Application approved. Hotel listing created and staff account ready.';
            if ($tempPassword) {
                $msg .= ' Temporary password for '.$app['email'].': <strong>'.$tempPassword.'</strong> — share this with them.';
            }
            flashSet('success', $msg);

        } elseif ($action === 'reject') {
            db()->prepare('UPDATE hotel_applications SET status=\'rejected\', admin_notes=? WHERE id=?')
               ->execute([$notes ?: null, $id]);
            flashSet('success', 'Application rejected.');

        } elseif ($action === 'pending') {
            db()->prepare('UPDATE hotel_applications SET status=\'pending\', admin_notes=? WHERE id=?')
               ->execute([$notes ?: null, $id]);
            flashSet('success', 'Application reset to pending.');
        }
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

  <!-- How approval works info box -->
  <div style="background:#eef6ff;border:1.5px solid #bfdbfe;border-radius:var(--r);padding:14px 18px;margin-bottom:20px;font-size:.84rem;color:#1e40af;line-height:1.6">
    <strong>ℹ️ How approval works:</strong>
    When you approve an application, the system automatically:
    <strong>(1)</strong> creates a hotel listing, &nbsp;
    <strong>(2)</strong> creates a staff account using the applicant's email, &nbsp;
    <strong>(3)</strong> shows you their temporary password to share with them. &nbsp;
    They log in and can immediately manage their hotel's bookings and rooms.
  </div>

  <!-- Filter tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['all'=>'All','pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected'] as $val=>$lbl): ?>
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
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:1rem;color:var(--navy)"><?= e($a['business_name']) ?></span>
            <span class="badge <?= $badgeClass ?>"><?= ucfirst($a['status']) ?></span>
            <span class="badge badge-grey" style="text-transform:capitalize"><?= e($a['property_type']) ?></span>
            <?php if ($a['hotel_id']): ?>
              <a href="hotels.php?edit=<?= $a['hotel_id'] ?>" class="badge badge-navy" style="text-decoration:none">🏨 Hotel #<?= $a['hotel_id'] ?></a>
            <?php endif; ?>
            <?php if ($a['staff_user_id']): ?>
              <span class="badge badge-green">👤 Staff created</span>
            <?php endif; ?>
          </div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:5px 20px;font-size:.83rem;color:var(--text2)">
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
            <div style="margin-top:8px;font-size:.8rem;color:var(--text3)">📝 Admin notes: <?= e($a['admin_notes']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <form method="POST" style="display:flex;flex-direction:column;gap:8px;min-width:200px">
          <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
          <input type="hidden" name="app_id"  value="<?= $a['id'] ?>">
          <textarea name="admin_notes" placeholder="Internal notes (optional)…" rows="2"
                    style="font-size:.8rem;padding:7px 9px;border:1px solid var(--border);border-radius:6px;resize:vertical"><?= e($a['admin_notes']??'') ?></textarea>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <?php if ($a['status'] !== 'approved'): ?>
              <button type="submit" name="action" value="approve" class="btn btn-navy btn-sm"
                      data-confirm="Approve this application? This will automatically create a hotel listing and staff account.">✓ Approve</button>
            <?php endif; ?>
            <?php if ($a['status'] !== 'rejected'): ?>
              <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                      data-confirm="Reject application from <?= e($a['business_name']) ?>?">✕ Reject</button>
            <?php endif; ?>
            <?php if ($a['status'] !== 'pending'): ?>
              <button type="submit" name="action" value="pending" class="btn btn-outline btn-sm">↩ Reset</button>
            <?php endif; ?>
          </div>
          <?php if ($a['status']==='approved' && $a['hotel_id']): ?>
            <a href="<?= SITE_URL ?>/pages/hotel.php?id=<?= $a['hotel_id'] ?>" target="_blank"
               class="btn btn-outline btn-sm" style="text-align:center">🔗 View listing</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
