<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminOrStaff();
if (isAdmin()) { header('Location: '.SITE_URL.'/admin/rooms.php'); exit; }

$hotelId = (int)($_SESSION['hotel_id'] ?? 0);
if (!$hotelId) { header('Location: '.SITE_URL.'/index.php'); exit; }

$hotel = db()->query("SELECT * FROM hotels WHERE id={$hotelId}")->fetch();
$_SESSION['hotel_name'] = $hotel['name'] ?? '';
$pageTitle = 'Rooms — '.($hotel['name'] ?? '');
$errors = [];

// Toggle availability
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='toggle') {
    verifyCsrf();
    $rid = (int)($_POST['room_id'] ?? 0);
    $chk = db()->prepare('SELECT id FROM rooms WHERE id=? AND hotel_id=?');
    $chk->execute([$rid, $hotelId]);
    if ($chk->fetch()) {
        db()->prepare('UPDATE rooms SET is_available=NOT is_available WHERE id=?')->execute([$rid]);
        flashSet('success', 'Room availability updated.');
    }
    header('Location: rooms.php'); exit;
}

// Update price
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_price') {
    verifyCsrf();
    $rid   = (int)($_POST['room_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    if ($price < 1) { $errors[] = 'Price must be at least ₱1.'; }
    else {
        $chk = db()->prepare('SELECT id FROM rooms WHERE id=? AND hotel_id=?');
        $chk->execute([$rid, $hotelId]);
        if ($chk->fetch()) {
            db()->prepare('UPDATE rooms SET price_per_night=? WHERE id=?')->execute([$price, $rid]);
            flashSet('success', 'Room price updated.');
        }
    }
    header('Location: rooms.php'); exit;
}

$rooms = db()->query("SELECT * FROM rooms WHERE hotel_id={$hotelId} ORDER BY id")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">🛏 Rooms — <?= e($hotel['name'] ?? '') ?></div>
  <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Room type</th><th>Max guests</th><th>Price / night</th><th>Status</th><th>Update price</th><th>Availability</th></tr></thead>
      <tbody>
        <?php foreach ($rooms as $rm): ?>
        <tr>
          <td><?= $rm['id'] ?></td>
          <td><strong><?= e($rm['room_type']) ?></strong></td>
          <td><?= $rm['max_guests'] ?></td>
          <td><?= money($rm['price_per_night']) ?></td>
          <td><span class="badge <?= $rm['is_available']?'badge-green':'badge-grey' ?>"><?= $rm['is_available']?'Available':'Unavailable' ?></span></td>
          <td>
            <form method="POST" style="display:flex;gap:5px;align-items:center">
              <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
              <input type="hidden" name="action"   value="update_price">
              <input type="hidden" name="room_id"  value="<?= $rm['id'] ?>">
              <input type="number" name="price" value="<?= $rm['price_per_night'] ?>" min="1" step="0.01"
                     style="width:100px;font-size:.82rem;padding:4px 7px;border:1px solid var(--border);border-radius:5px">
              <button type="submit" class="btn btn-navy btn-sm" style="padding:4px 10px;font-size:.75rem">Save</button>
            </form>
          </td>
          <td>
            <form method="POST">
              <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
              <input type="hidden" name="action"   value="toggle">
              <input type="hidden" name="room_id"  value="<?= $rm['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $rm['is_available']?'btn-outline':'btn-navy' ?>"
                      style="font-size:.75rem;padding:4px 10px">
                <?= $rm['is_available'] ? 'Mark Unavailable' : 'Mark Available' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rooms)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:24px">No rooms found for this hotel.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
