<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminOrStaff();
if (isAdmin()) { header('Location: '.SITE_URL.'/admin/bookings.php'); exit; }

$hotelId = (int)($_SESSION['hotel_id'] ?? 0);
if (!$hotelId) { header('Location: '.SITE_URL.'/index.php'); exit; }

$hotel = db()->query("SELECT * FROM hotels WHERE id={$hotelId}")->fetch();
$_SESSION['hotel_name'] = $hotel['name'] ?? '';
$pageTitle = 'Bookings — '.($hotel['name'] ?? '');

// Update booking status
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $bkId  = (int)($_POST['booking_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if ($bkId && in_array($newStatus, ['confirmed','pending','cancelled','completed'])) {
        // Verify booking belongs to this hotel
        $chk = db()->prepare('SELECT id FROM bookings WHERE id=? AND hotel_id=?');
        $chk->execute([$bkId, $hotelId]);
        if ($chk->fetch()) {
            db()->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$newStatus, $bkId]);
            flashSet('success', 'Booking #'.$bkId.' updated to '.ucfirst($newStatus).'.');
        }
    }
    header('Location: bookings.php'); exit;
}

$filter = in_array($_GET['f']??'', ['confirmed','pending','cancelled','completed']) ? $_GET['f'] : '';
$where  = "b.hotel_id={$hotelId}";
if ($filter) $where .= " AND b.status='{$filter}'";

$bookings = db()->query(
    "SELECT b.*,u.first_name,u.last_name,u.email,r.room_type
     FROM bookings b
     JOIN users u ON u.id=b.user_id
     JOIN rooms r ON r.id=b.room_id
     WHERE {$where} ORDER BY b.created_at DESC LIMIT 200"
)->fetchAll();

$counts = db()->query("SELECT status, COUNT(*) c FROM bookings WHERE hotel_id={$hotelId} GROUP BY status")->fetchAll();
$cnt = [''=>(int)db()->query("SELECT COUNT(*) FROM bookings WHERE hotel_id={$hotelId}")->fetchColumn(),'confirmed'=>0,'pending'=>0,'cancelled'=>0,'completed'=>0];
foreach ($counts as $r) $cnt[$r['status']] = (int)$r['c'];

$bkBadge=['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-coral','completed'=>'badge-navy'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">📋 Bookings — <?= e($hotel['name'] ?? '') ?></div>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach (['' => 'All', 'confirmed'=>'Confirmed','pending'=>'Pending','cancelled'=>'Cancelled','completed'=>'Completed'] as $val=>$lbl): ?>
      <a href="?f=<?= $val ?>"
         style="padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:500;text-decoration:none;border:1.5px solid;
                <?= $filter===$val ? 'background:var(--navy);color:#fff;border-color:var(--navy)' : 'background:#fff;color:var(--text2);border-color:var(--border)' ?>">
        <?= $lbl ?> <span style="opacity:.7">(<?= $cnt[$val] ?? 0 ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Guest</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Nights</th><th>Total</th><th>Payment</th><th>Status</th><th>Update</th></tr></thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td>
            <strong><?= e($b['first_name'].' '.$b['last_name']) ?></strong>
            <div class="text-sm text-muted"><?= e($b['email']) ?></div>
          </td>
          <td><?= e($b['room_type']) ?></td>
          <td><?= date('M j, Y', strtotime($b['check_in'])) ?></td>
          <td><?= date('M j, Y', strtotime($b['check_out'])) ?></td>
          <td><?= $b['total_nights'] ?></td>
          <td><strong><?= money($b['total_price']) ?></strong></td>
          <td>
            <span class="badge <?= $b['payment_status']==='paid'?'badge-green':($b['payment_status']==='refunded'?'badge-coral':'badge-grey') ?>">
              <?= ucfirst($b['payment_status']) ?>
            </span>
            <?php if ($b['payment_method']): ?>
              <div class="text-sm text-muted"><?= e($b['payment_method']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= $bkBadge[$b['status']]??'badge-grey' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td>
            <form method="POST" style="display:flex;gap:5px;align-items:center">
              <input type="hidden" name="_csrf"       value="<?= csrf() ?>">
              <input type="hidden" name="booking_id"  value="<?= $b['id'] ?>">
              <select name="status" style="font-size:.78rem;padding:4px 6px;border:1px solid var(--border);border-radius:5px">
                <?php foreach (['confirmed','pending','cancelled','completed'] as $s): ?>
                  <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-navy btn-sm" style="padding:4px 10px;font-size:.75rem">Save</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="10" style="text-align:center;color:var(--text3);padding:24px">No bookings found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
