<?php
require_once __DIR__ . '/../includes/config.php';
requireAdminOrStaff();
$pageTitle = 'Staff Dashboard';

// If admin hits this URL, redirect them home
if (isAdmin()) { header('Location: '.SITE_URL.'/admin/dashboard.php'); exit; }

$hotelId = (int)($_SESSION['hotel_id'] ?? 0);
if (!$hotelId) { flashSet('error','No hotel assigned to your account.'); header('Location: '.SITE_URL.'/index.php'); exit; }

$hotel = db()->prepare('SELECT * FROM hotels WHERE id=?');
$hotel->execute([$hotelId]);
$hotel = $hotel->fetch();
if (!$hotel) { flashSet('error','Assigned hotel not found.'); header('Location: '.SITE_URL.'/index.php'); exit; }

$_SESSION['hotel_name'] = $hotel['name'];

// Stats for this hotel only
$stats = [
    'bookings'   => (int)db()->prepare('SELECT COUNT(*) FROM bookings WHERE hotel_id=?')->execute([$hotelId]) ? (int)db()->prepare('SELECT COUNT(*) FROM bookings WHERE hotel_id=?')->execute([$hotelId]) : 0,
    'confirmed'  => 0,
    'revenue'    => 0.0,
    'rooms'      => (int)db()->prepare('SELECT COUNT(*) FROM rooms WHERE hotel_id=?')->execute([$hotelId]) ? 0 : 0,
];

$row = db()->prepare('SELECT COUNT(*) total, SUM(CASE WHEN status="confirmed" THEN 1 ELSE 0 END) confirmed, COALESCE(SUM(CASE WHEN payment_status="paid" THEN total_price ELSE 0 END),0) revenue FROM bookings WHERE hotel_id=?');
$row->execute([$hotelId]);
$row = $row->fetch();
$stats['bookings']  = (int)$row['total'];
$stats['confirmed'] = (int)$row['confirmed'];
$stats['revenue']   = (float)$row['revenue'];
$stats['rooms']     = (int)db()->prepare('SELECT COUNT(*) FROM rooms WHERE hotel_id=?')->execute([$hotelId]) ? (int)db()->query("SELECT COUNT(*) FROM rooms WHERE hotel_id=$hotelId")->fetchColumn() : 0;

$stats['rooms'] = (int)db()->prepare('SELECT COUNT(*) FROM rooms WHERE hotel_id=?')
    ->execute([$hotelId]) ? (int)db()->query("SELECT COUNT(*) FROM rooms WHERE hotel_id={$hotelId} AND is_available=1")->fetchColumn() : 0;

// Simpler direct queries
$stats['bookings']  = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE hotel_id={$hotelId}")->fetchColumn();
$stats['confirmed'] = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE hotel_id={$hotelId} AND status='confirmed'")->fetchColumn();
$stats['revenue']   = (float)db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE hotel_id={$hotelId} AND payment_status='paid'")->fetchColumn();
$stats['rooms']     = (int)db()->query("SELECT COUNT(*) FROM rooms WHERE hotel_id={$hotelId} AND is_available=1")->fetchColumn();

// Bookings by status for mini chart
$bkByStatus = db()->query("SELECT status, COUNT(*) c FROM bookings WHERE hotel_id={$hotelId} GROUP BY status")->fetchAll();
$statusCounts = ['confirmed'=>0,'pending'=>0,'cancelled'=>0,'completed'=>0];
foreach ($bkByStatus as $r) $statusCounts[$r['status']] = (int)$r['c'];

// Last 7 days bookings trend
$trend = db()->query("SELECT DATE(created_at) d, COUNT(*) c FROM bookings WHERE hotel_id={$hotelId} AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
$trendLabels = []; $trendData = [];
for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('M j', strtotime($d));
    $found = 0;
    foreach ($trend as $t) { if ($t['d'] === $d) { $found = (int)$t['c']; break; } }
    $trendData[] = $found;
}

// Recent bookings
$recent = db()->query("SELECT b.*,u.first_name,u.last_name,r.room_type FROM bookings b JOIN users u ON u.id=b.user_id JOIN rooms r ON r.id=b.room_id WHERE b.hotel_id={$hotelId} ORDER BY b.created_at DESC LIMIT 8")->fetchAll();

$bkBadge=['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-coral','completed'=>'badge-navy'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">📊 <?= e($hotel['name']) ?> — Overview</div>
  <p class="text-muted mb-3">Welcome back, <?= e($_SESSION['first_name']) ?> 👋 &nbsp;·&nbsp; <?= e($hotel['address'] ?? '') ?></p>

  <div class="stat-grid">
    <?php foreach ([
      ['📋','Total Bookings', number_format($stats['bookings']), 'All time'],
      ['✅','Confirmed',       number_format($stats['confirmed']),'Active stays'],
      ['💰','Revenue',         money($stats['revenue']),         'Paid bookings'],
      ['🛏','Available Rooms', number_format($stats['rooms']),   'Currently open'],
    ] as [$ic,$ti,$va,$su]): ?>
    <div class="stat-card">
      <div class="stat-icon"><?= $ic ?></div>
      <div><div class="stat-val"><?= $va ?></div><div class="stat-lbl"><?= $su ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Charts row -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">
    <!-- Booking trend -->
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="font-weight:600;color:var(--navy);margin-bottom:16px;font-size:.95rem">Bookings — last 7 days</div>
      <canvas id="trendChart" height="140"></canvas>
    </div>
    <!-- Status breakdown -->
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="font-weight:600;color:var(--navy);margin-bottom:16px;font-size:.95rem">Booking status breakdown</div>
      <canvas id="statusChart" height="140"></canvas>
    </div>
  </div>

  <div class="flex jb ac mb-2">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy)">Recent bookings</h3>
    <a href="<?= SITE_URL ?>/staff/bookings.php" class="btn btn-outline btn-sm">View all →</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Guest</th><th>Room</th><th>Dates</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><strong><?= e($b['first_name'].' '.$b['last_name']) ?></strong></td>
          <td><?= e($b['room_type']) ?></td>
          <td><?= date('M j',strtotime($b['check_in'])) ?> – <?= date('M j',strtotime($b['check_out'])) ?></td>
          <td><strong><?= money($b['total_price']) ?></strong></td>
          <td><span class="badge <?= $bkBadge[$b['status']]??'badge-grey' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td><span class="badge <?= $b['payment_status']==='paid'?'badge-green':'badge-grey' ?>"><?= ucfirst($b['payment_status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:24px">No bookings yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
  type: 'line',
  data: {
    labels: <?= json_encode($trendLabels) ?>,
    datasets: [{
      label: 'Bookings',
      data: <?= json_encode($trendData) ?>,
      borderColor: '#003580',
      backgroundColor: 'rgba(0,53,128,.08)',
      borderWidth: 2,
      pointBackgroundColor: '#003580',
      tension: 0.4,
      fill: true,
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{stepSize:1}}, x:{grid:{display:false}} } }
});

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: ['Confirmed','Pending','Cancelled','Completed'],
    datasets: [{
      data: [<?= $statusCounts['confirmed'] ?>,<?= $statusCounts['pending'] ?>,<?= $statusCounts['cancelled'] ?>,<?= $statusCounts['completed'] ?>],
      backgroundColor: ['#22c55e','#f59e0b','#ef4444','#003580'],
      borderWidth: 0,
    }]
  },
  options: { plugins:{ legend:{ position:'bottom', labels:{ padding:14, font:{size:12} } } }, cutout:'65%' }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
