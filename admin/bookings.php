<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Manage Bookings';
$errors = [];

// UPDATE STATUS
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    verifyCsrf();
    $id=(int)($_POST['booking_id']??0);
    $st=$_POST['status']??'';
    if (in_array($st,['pending','confirmed','completed','cancelled'])) {
        db()->prepare('UPDATE bookings SET status=? WHERE id=?')->execute([$st,$id]);
        flashSet('success','Booking #'.$id.' updated to '.ucfirst($st).'.');
    }
    header('Location: bookings.php'); exit;
}
// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    verifyCsrf(); $id=(int)($_POST['booking_id']??0);
    db()->prepare('DELETE FROM bookings WHERE id=?')->execute([$id]);
    flashSet('success','Booking #'.$id.' deleted.');
    header('Location: bookings.php'); exit;
}

$filter=$_GET['status']??'';
if (!in_array($filter,['pending','confirmed','completed','cancelled'])) $filter='';
$where=$filter ? 'WHERE b.status='.db()->quote($filter) : '';

$bookings=db()->query(
    "SELECT b.*,u.first_name,u.last_name,u.email,h.name AS hotel_name,r.room_type
     FROM bookings b
     JOIN users u ON u.id=b.user_id
     JOIN hotels h ON h.id=b.hotel_id
     JOIN rooms r ON r.id=b.room_id
     $where ORDER BY b.created_at DESC LIMIT 300"
)->fetchAll();

$bkBadge=['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-red','completed'=>'badge-navy'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="flex jb ac mb-3" style="flex-wrap:wrap;gap:10px">
    <div class="admin-title" style="margin:0">📋 Bookings</div>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach([''=> 'All','confirmed'=>'Confirmed','pending'=>'Pending','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$lbl): ?>
        <a href="?status=<?= $k ?>" style="padding:5px 13px;border-radius:20px;font-size:.78rem;border:1.5px solid;
           <?= $filter===$k?'background:var(--navy);color:#fff;border-color:var(--navy)':'background:#fff;color:var(--text2);border-color:var(--border)' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Guest</th><th>Hotel / Room</th><th>Dates</th><th>Guests</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="9" style="text-align:center;padding:28px;color:var(--text3)">No bookings found.</td></tr>
        <?php else: foreach($bookings as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><strong><?= e($b['first_name'].' '.$b['last_name']) ?></strong><br><span class="text-sm text-muted"><?= e($b['email']) ?></span></td>
          <td><?= e($b['hotel_name']) ?><br><span class="text-sm text-muted"><?= e($b['room_type']) ?></span></td>
          <td><?= date('M j',strtotime($b['check_in'])) ?> – <?= date('M j Y',strtotime($b['check_out'])) ?><br><span class="text-sm text-muted"><?= $b['total_nights'] ?> night<?= $b['total_nights']>1?'s':'' ?></span></td>
          <td>👤 <?= $b['guests'] ?></td>
          <td><strong><?= money($b['total_price']) ?></strong></td>
          <td>
            <?php if ($b['payment_method']): ?>
              <span class="badge <?= $b['payment_status']==='paid'?'badge-green':'badge-yellow' ?>"><?= ucfirst($b['payment_status']??'') ?></span>
              <div class="text-sm text-muted"><?= e(ucfirst(str_replace('_',' ',$b['payment_method']))) ?></div>
            <?php else: ?><span class="text-sm text-muted">—</span><?php endif; ?>
          </td>
          <td><span class="badge <?= $bkBadge[$b['status']]??'badge-grey' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td>
            <form method="POST" style="display:flex;gap:4px;align-items:center;margin-bottom:4px">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="action"     value="update">
              <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
              <select name="status" style="font-size:.74rem;padding:3px 6px;border-radius:4px;border:1px solid var(--border)">
                <?php foreach(['pending','confirmed','completed','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-navy btn-sm">Save</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="action"     value="delete">
              <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="Permanently delete booking #<?= $b['id'] ?>?">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
