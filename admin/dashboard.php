<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Admin Dashboard';

// ── Core stats ──────────────────────────────────────────────────
$stats = [
    'users'      => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'hotels'     => (int)db()->query("SELECT COUNT(*) FROM hotels WHERE is_active=1")->fetchColumn(),
    'bookings'   => (int)db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'revenue'    => (float)db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE payment_status='paid'")->fetchColumn(),
    'pending_app'=> (int)db()->query("SELECT COUNT(*) FROM hotel_applications WHERE status='pending'")->fetchColumn(),
    'cancelled'  => (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn(),
    'confirmed'  => (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status='confirmed'")->fetchColumn(),
    'avg_booking'=> (float)db()->query("SELECT COALESCE(AVG(total_price),0) FROM bookings WHERE payment_status='paid'")->fetchColumn(),
];
try {
    $stats['open_tickets']     = (int)db()->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();
    $stats['unreplied_tickets']= (int)db()->query("SELECT COUNT(*) FROM support_tickets WHERE status='open' AND admin_reply IS NULL")->fetchColumn();
} catch(Exception $e) {
    $stats['open_tickets']     = 0;
    $stats['unreplied_tickets']= 0;
}

// ── Revenue last 30 days (daily) ────────────────────────────────
$revTrend = db()->query(
    "SELECT DATE(created_at) d, COALESCE(SUM(total_price),0) rev, COUNT(*) cnt
     FROM bookings WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(),INTERVAL 29 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
)->fetchAll();
$revLabels = []; $revData = []; $bkTrendData = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $revLabels[] = date('M j', strtotime($d));
    $rev = 0; $cnt = 0;
    foreach ($revTrend as $r) { if ($r['d'] === $d) { $rev = (float)$r['rev']; $cnt = (int)$r['cnt']; break; } }
    $revData[]     = $rev;
    $bkTrendData[] = $cnt;
}

// ── Bookings by status ──────────────────────────────────────────
$bkStatus = db()->query("SELECT status, COUNT(*) c FROM bookings GROUP BY status")->fetchAll();
$statusMap = ['confirmed'=>0,'pending'=>0,'cancelled'=>0,'completed'=>0];
foreach ($bkStatus as $r) $statusMap[$r['status']] = (int)$r['c'];

// ── Revenue by hotel (top 6) ────────────────────────────────────
$hotelRev = db()->query(
    "SELECT h.name, COALESCE(SUM(b.total_price),0) rev
     FROM hotels h LEFT JOIN bookings b ON b.hotel_id=h.id AND b.payment_status='paid'
     GROUP BY h.id ORDER BY rev DESC LIMIT 6"
)->fetchAll();
$hotelLabels = array_column($hotelRev,'name');
$hotelRevData = array_map(fn($r) => (float)$r['rev'], $hotelRev);

// ── Bookings by payment method ──────────────────────────────────
$payMethods = db()->query(
    "SELECT COALESCE(payment_method,'unknown') pm, COUNT(*) c FROM bookings GROUP BY pm"
)->fetchAll();
$pmLabels = []; $pmData = [];
foreach ($payMethods as $r) { $pmLabels[] = ucfirst(str_replace('_',' ',$r['pm'])); $pmData[] = (int)$r['c']; }

// ── New users last 30 days ──────────────────────────────────────
$userTrend = db()->query(
    "SELECT DATE(created_at) d, COUNT(*) c FROM users
     WHERE created_at >= DATE_SUB(NOW(),INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY d"
)->fetchAll();
$userTrendData = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $found = 0;
    foreach ($userTrend as $r) { if ($r['d'] === $d) { $found = (int)$r['c']; break; } }
    $userTrendData[] = $found;
}

// ── Top hotels by bookings ──────────────────────────────────────
$topHotels = db()->query(
    "SELECT h.name, COUNT(b.id) cnt, COALESCE(SUM(b.total_price),0) rev, h.rating
     FROM hotels h LEFT JOIN bookings b ON b.hotel_id=h.id
     GROUP BY h.id ORDER BY cnt DESC LIMIT 5"
)->fetchAll();

// ── Recent bookings ─────────────────────────────────────────────
$recent = db()->query(
    "SELECT b.*,u.first_name,u.last_name,h.name AS hotel_name,r.room_type
     FROM bookings b
     JOIN users u ON u.id=b.user_id
     JOIN hotels h ON h.id=b.hotel_id
     JOIN rooms r ON r.id=b.room_id
     ORDER BY b.created_at DESC LIMIT 8"
)->fetchAll();

$bkBadge = ['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-coral','completed'=>'badge-navy'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">📊 Dashboard</div>
  <p class="text-muted mb-3">Welcome back, <?= e($_SESSION['first_name']) ?> 👋 &nbsp;·&nbsp; <?= date('l, F j, Y') ?></p>

  <!-- ── Stat cards ── -->
  <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
    <?php foreach ([
      ['👤','Registered Users',  number_format($stats['users']),                  'Total accounts'],
      ['🏨','Active Hotels',     number_format($stats['hotels']),                  'Live listings'],
      ['📋','Total Bookings',    number_format($stats['bookings']),                'All time'],
      ['✅','Confirmed',         number_format($stats['confirmed']),               'Active stays'],
      ['❌','Cancelled',         number_format($stats['cancelled']),               'All time'],
      ['💰','Total Revenue',     money($stats['revenue']),                         'Paid bookings'],
      ['📈','Avg Booking Value', money($stats['avg_booking']),                     'Per paid booking'],
      ['🏢','Pending Apps',      number_format($stats['pending_app']),             'Partner applications'],
      ['💬','Open Tickets',      number_format($stats['open_tickets']),            $stats['unreplied_tickets'].' awaiting reply'],
    ] as [$ic,$ti,$va,$su]): ?>
    <div class="stat-card">
      <div class="stat-icon"><?= $ic ?></div>
      <div><div class="stat-val" style="font-size:1.3rem"><?= $va ?></div><div class="stat-lbl"><?= $su ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Revenue + Bookings trend ── -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:22px">
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <span style="font-weight:600;color:var(--navy);font-size:.95rem">Revenue — last 30 days</span>
        <span style="font-size:.78rem;color:var(--text3)">Paid bookings only</span>
      </div>
      <canvas id="revChart" height="100"></canvas>
    </div>
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="font-weight:600;color:var(--navy);font-size:.95rem;margin-bottom:16px">Booking status</div>
      <canvas id="statusChart" height="180"></canvas>
    </div>
  </div>

  <!-- ── Hotel revenue + Payment methods ── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px">
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="font-weight:600;color:var(--navy);font-size:.95rem;margin-bottom:16px">Revenue by hotel</div>
      <canvas id="hotelRevChart" height="160"></canvas>
    </div>
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm)">
      <div style="font-weight:600;color:var(--navy);font-size:.95rem;margin-bottom:16px">Payment methods</div>
      <canvas id="payChart" height="160"></canvas>
    </div>
  </div>

  <!-- ── New user registrations ── -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm);margin-bottom:22px">
    <div style="font-weight:600;color:var(--navy);font-size:.95rem;margin-bottom:16px">New registrations — last 30 days</div>
    <canvas id="userChart" height="70"></canvas>
  </div>

  <!-- ── Top hotels table ── -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px;box-shadow:var(--shadow-sm);margin-bottom:22px">
    <div style="font-weight:600;color:var(--navy);font-size:.95rem;margin-bottom:16px">Top hotels by bookings</div>
    <div class="tbl-wrap" style="margin:0">
      <table>
        <thead><tr><th>Hotel</th><th>Bookings</th><th>Revenue</th><th>Rating</th></tr></thead>
        <tbody>
          <?php foreach ($topHotels as $th): ?>
          <tr>
            <td><strong><?= e($th['name']) ?></strong></td>
            <td><?= number_format($th['cnt']) ?></td>
            <td><?= money($th['rev']) ?></td>
            <td>⭐ <?= number_format($th['rating'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Recent bookings ── -->
  <div class="flex jb ac mb-2" style="flex-wrap:wrap;gap:10px">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy)">Recent bookings</h3>
    <a href="bookings.php" class="btn btn-outline btn-sm">View all →</a>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>#</th><th>Guest</th><th>Hotel / Room</th><th>Dates</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $b): ?>
        <tr>
          <td><?= $b['id'] ?></td>
          <td><strong><?= e($b['first_name'].' '.$b['last_name']) ?></strong></td>
          <td><?= e($b['hotel_name']) ?><br><span class="text-sm text-muted"><?= e($b['room_type']) ?></span></td>
          <td><?= date('M j', strtotime($b['check_in'])) ?> – <?= date('M j Y', strtotime($b['check_out'])) ?></td>
          <td><strong><?= money($b['total_price']) ?></strong></td>
          <td><span class="badge <?= $bkBadge[$b['status']] ?? 'badge-grey' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td><a href="bookings.php?edit=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</main>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const navy = '#003580', coral = '#FF6B35', green = '#22c55e', amber = '#f59e0b', sky = '#38bdf8';
const gridColor = 'rgba(0,0,0,.05)';

// Revenue trend
new Chart(document.getElementById('revChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($revLabels) ?>,
    datasets: [
      { label:'Revenue (₱)', data: <?= json_encode($revData) ?>, backgroundColor: 'rgba(0,53,128,.15)', borderColor: navy, borderWidth:1.5, borderRadius:4, yAxisID:'y' },
      { label:'Bookings',    data: <?= json_encode($bkTrendData) ?>, type:'line', borderColor: coral, backgroundColor:'transparent', borderWidth:2, pointRadius:2, tension:0.4, yAxisID:'y1' }
    ]
  },
  options: {
    plugins:{ legend:{ position:'top', labels:{ font:{size:11}, padding:12 } } },
    scales:{
      y:  { beginAtZero:true, grid:{color:gridColor}, ticks:{ callback: v => '₱'+v.toLocaleString() } },
      y1: { beginAtZero:true, position:'right', grid:{display:false}, ticks:{stepSize:1} },
      x:  { grid:{display:false}, ticks:{ maxTicksLimit:10 } }
    }
  }
});

// Booking status doughnut
new Chart(document.getElementById('statusChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: ['Confirmed','Pending','Cancelled','Completed'],
    datasets: [{ data: [<?= $statusMap['confirmed'] ?>,<?= $statusMap['pending'] ?>,<?= $statusMap['cancelled'] ?>,<?= $statusMap['completed'] ?>], backgroundColor:[green,amber,'#ef4444',navy], borderWidth:0 }]
  },
  options:{ plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{size:11} } } }, cutout:'62%' }
});

// Hotel revenue bar
new Chart(document.getElementById('hotelRevChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($n) => mb_substr($n,0,18).(mb_strlen($n)>18?'…':''), $hotelLabels)) ?>,
    datasets: [{ label:'Revenue (₱)', data: <?= json_encode($hotelRevData) ?>, backgroundColor:[navy,coral,green,amber,sky,'#a855f7'], borderRadius:5 }]
  },
  options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{ beginAtZero:true, grid:{color:gridColor}, ticks:{callback:v=>'₱'+v.toLocaleString()} }, y:{grid:{display:false}} } }
});

// Payment methods doughnut
new Chart(document.getElementById('payChart').getContext('2d'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($pmLabels) ?>,
    datasets: [{ data: <?= json_encode($pmData) ?>, backgroundColor:[navy,coral,green,amber,sky,'#a855f7','#ec4899'], borderWidth:0 }]
  },
  options:{ plugins:{ legend:{ position:'bottom', labels:{ padding:12, font:{size:11} } } } }
});

// New users line
new Chart(document.getElementById('userChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: <?= json_encode($revLabels) ?>,
    datasets: [{ label:'New users', data: <?= json_encode($userTrendData) ?>, borderColor:coral, backgroundColor:'rgba(255,107,53,.08)', borderWidth:2, pointRadius:2, tension:0.4, fill:true }]
  },
  options:{ plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{stepSize:1},grid:{color:gridColor}}, x:{grid:{display:false},ticks:{maxTicksLimit:10}} } }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
