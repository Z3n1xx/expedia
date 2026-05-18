<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Payments';

// REFUND — mark booking as refunded/cancelled
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='refund') {
    verifyCsrf();
    $id = (int)($_POST['booking_id'] ?? 0);
    db()->prepare("UPDATE bookings SET payment_status='refunded',status='cancelled' WHERE id=?")->execute([$id]);
    flashSet('success', 'Booking #'.$id.' refunded and cancelled.');
    header('Location: payments.php'); exit;
}

$filter  = $_GET['method'] ?? '';
$allowed = ['credit_card','debit_card','gcash','maya','bank_transfer','cash'];
if (!in_array($filter, $allowed)) $filter = '';
$where = $filter ? 'AND b.payment_method='.db()->quote($filter) : '';

$payments = db()->query(
    "SELECT b.id, b.user_id, b.hotel_id, b.check_in, b.check_out, b.total_nights,
            b.total_price, b.payment_status, b.payment_method, b.created_at,
            u.first_name, u.last_name, u.email,
            h.name AS hotel_name
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN hotels h ON h.id = b.hotel_id
     WHERE b.payment_status IN ('paid','refunded') $where
     ORDER BY b.created_at DESC LIMIT 300"
)->fetchAll();

$total = db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE payment_status='paid'")->fetchColumn();

$pBadge = ['paid'=>'badge-green','pending'=>'badge-yellow','failed'=>'badge-red','refunded'=>'badge-grey'];
$pIcon  = ['credit_card'=>'💳','debit_card'=>'🏧','gcash'=>'📱','maya'=>'🟣','bank_transfer'=>'🏦','cash'=>'💵'];
$pLabel = ['credit_card'=>'Credit Card','debit_card'=>'Debit Card','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer','cash'=>'Pay at Hotel'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="flex jb ac mb-3" style="flex-wrap:wrap;gap:10px">
    <div class="admin-title" style="margin:0">💳 Payments</div>
    <div>Total collected: <strong style="color:var(--navy);font-size:1.05rem"><?= money((float)$total) ?></strong></div>
  </div>
  <div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap">
    <a href="payments.php" style="padding:5px 12px;border-radius:20px;font-size:.78rem;border:1.5px solid;<?= !$filter?'background:var(--navy);color:#fff;border-color:var(--navy)':'background:#fff;color:var(--text2);border-color:var(--border)' ?>">All</a>
    <?php foreach ($pLabel as $k=>$lbl): ?>
      <a href="?method=<?= $k ?>" style="padding:5px 12px;border-radius:20px;font-size:.78rem;border:1.5px solid;<?= $filter===$k?'background:var(--navy);color:#fff;border-color:var(--navy)':'background:#fff;color:var(--text2);border-color:var(--border)' ?>"><?= $pIcon[$k] ?> <?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>Booking #</th><th>Guest</th><th>Hotel</th><th>Method</th><th>Amount</th><th>Stay</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr><td colspan="9" style="text-align:center;padding:28px;color:var(--text3)">No payments yet.</td></tr>
        <?php else: foreach ($payments as $p):
          $method = $p['payment_method'] ?? '';
        ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><strong><?= e($p['first_name'].' '.$p['last_name']) ?></strong><br><span class="text-sm text-muted"><?= e($p['email']) ?></span></td>
          <td><?= e($p['hotel_name']) ?></td>
          <td><?= $pIcon[$method] ?? '💰' ?> <?= $pLabel[$method] ?? e($method ?: '—') ?></td>
          <td><strong><?= money($p['total_price']) ?></strong></td>
          <td class="text-sm text-muted"><?= date('M j', strtotime($p['check_in'])) ?> – <?= date('M j Y', strtotime($p['check_out'])) ?></td>
          <td><span class="badge <?= $pBadge[$p['payment_status']] ?? 'badge-grey' ?>"><?= ucfirst($p['payment_status']) ?></span></td>
          <td class="text-sm"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
          <td>
            <?php if ($p['payment_status']==='paid'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf"      value="<?= csrf() ?>">
              <input type="hidden" name="action"     value="refund">
              <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm" data-confirm="Issue refund for booking #<?= $p['id'] ?>? The booking will be cancelled.">Refund</button>
            </form>
            <?php else: ?><span class="text-sm text-muted">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
