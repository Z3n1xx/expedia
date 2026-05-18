<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'My Trips';

// Cancel booking
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_id'])) {
    verifyCsrf();
    $bid = (int)$_POST['cancel_id'];
    db()->prepare('UPDATE bookings SET status=\'cancelled\' WHERE id=? AND user_id=? AND status=\'confirmed\'')
       ->execute([$bid,$_SESSION['user_id']]);
    flashSet('success','Booking #'.$bid.' has been cancelled.');
    header('Location: '.SITE_URL.'/pages/my-bookings.php'); exit;
}

$filter = in_array($_GET['filter']??'',['confirmed','completed','cancelled'])?$_GET['filter']:'all';
$params = [$_SESSION['user_id']];
$extra  = '';
if ($filter!=='all') { $extra='AND b.status=?'; $params[]=$filter; }

$bookings = db()->prepare(
    "SELECT b.*,h.name AS hotel_name,h.thumbnail,r.room_type,l.city
     FROM bookings b
     JOIN hotels h    ON h.id=b.hotel_id
     JOIN rooms r     ON r.id=b.room_id
     JOIN locations l ON l.id=h.location_id
     WHERE b.user_id=? $extra
     ORDER BY b.created_at DESC"
);
$bookings->execute($params);
$bookings = $bookings->fetchAll();

$bkBadge = ['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-red','completed'=>'badge-navy'];
$payIcon  = ['credit_card'=>'💳','debit_card'=>'🏧','gcash'=>'📱','maya'=>'🟣','bank_transfer'=>'🏦','cash'=>'💵'];
$payLabel = ['credit_card'=>'Credit Card','debit_card'=>'Debit Card','gcash'=>'GCash','maya'=>'Maya','bank_transfer'=>'Bank Transfer','cash'=>'Pay at Hotel'];

include __DIR__ . '/../includes/header.php';
?>
<div>
<div class="page-header">
  <div class="container">
    <h1>My <em style="color:var(--coral);font-style:italic">Trips</em></h1>
    <p>All your bookings and payment records</p>
  </div>
</div>
<div class="container" style="padding-top:28px;padding-bottom:68px">
  <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap">
    <?php foreach(['all'=>'All trips','confirmed'=>'Upcoming','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$lbl): ?>
      <a href="?filter=<?= $k ?>" style="padding:7px 18px;border-radius:20px;font-size:.84rem;font-weight:500;border:1.5px solid;
         <?= $filter===$k?'background:var(--navy);color:#fff;border-color:var(--navy)':'background:#fff;color:var(--text2);border-color:var(--border)' ?>">
        <?= $lbl ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="empty"><div class="icon">✈️</div><h3>No trips yet</h3><p>Start exploring and book your perfect stay.</p><a href="search.php" class="btn btn-primary">Find hotels</a></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($bookings as $bk): ?>
    <div class="bk-card fade-up">
      <div class="bk-thumb">
        <img src="<?= SITE_URL ?>/<?= e($bk['thumbnail']??'') ?>" alt="<?= e($bk['hotel_name']) ?>" style="height:100%;width:100%"
             onerror="this.src='https://placehold.co/120x88/003580/ffffff?text=Hotel'">
      </div>
      <div style="flex:1">
        <div class="flex jb ac" style="flex-wrap:wrap;gap:8px;margin-bottom:6px">
          <div>
            <a href="hotel.php?id=<?= $bk['hotel_id'] ?>" style="font-family:var(--font-head);font-size:1rem;font-weight:700;color:var(--navy)"><?= e($bk['hotel_name']) ?></a>
            <div class="text-sm text-muted mt-1"><?= e($bk['room_type']) ?> · 📍 <?= e($bk['city']) ?></div>
          </div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <span class="badge <?= $bkBadge[$bk['status']]??'badge-grey' ?>"><?= ucfirst($bk['status']) ?></span>
            <?php if ($bk['payment_status']): ?>
              <span class="badge <?= $bk['payment_status']==='paid'?'badge-green':'badge-yellow' ?>">
                <?= $bk['payment_status']==='paid'?'✓ Paid':ucfirst($bk['payment_status']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:16px;font-size:.82rem;color:var(--text2);margin-bottom:8px;flex-wrap:wrap">
          <span>📅 <?= date('D, M j, Y',strtotime($bk['check_in'])) ?></span>
          <span>→ <?= date('D, M j, Y',strtotime($bk['check_out'])) ?></span>
          <span>🌙 <?= $bk['total_nights'] ?> night<?= $bk['total_nights']>1?'s':'' ?></span>
          <span>👤 <?= $bk['guests'] ?> guest<?= $bk['guests']>1?'s':'' ?></span>
        </div>
        <?php if ($bk['payment_method']): ?>
        <div style="display:inline-flex;align-items:center;gap:5px;background:var(--sky);border-radius:6px;padding:3px 10px;font-size:.76rem;color:var(--navy);margin-bottom:8px">
          <?= $payIcon[$bk['payment_method']]??'💰' ?> <?= $payLabel[$bk['payment_method']]??e($bk['payment_method']) ?>
        </div>
        <?php endif; ?>
        <div class="flex jb ac" style="flex-wrap:wrap;gap:10px">
          <div>
            <span class="text-sm text-muted">Total: </span>
            <strong style="font-size:1.05rem;color:var(--navy);font-family:var(--font-head)"><?= money($bk['total_price']) ?></strong>
            <span class="text-sm text-muted" style="margin-left:6px">Ref #<?= $bk['id'] ?></span>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="hotel.php?id=<?= $bk['hotel_id'] ?>" class="btn btn-outline btn-sm">View hotel</a>
            <?php if ($bk['status']==='confirmed'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf"     value="<?= csrf() ?>">
              <input type="hidden" name="cancel_id" value="<?= $bk['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="Cancel booking #<?= $bk['id'] ?>? This cannot be undone.">Cancel</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
