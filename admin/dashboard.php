<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Admin Dashboard';

$stats = [
    'users'    => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
    'hotels'   => (int)db()->query("SELECT COUNT(*) FROM hotels WHERE is_active=1")->fetchColumn(),
    'bookings' => (int)db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'revenue'  => (float)db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE payment_status='paid'")->fetchColumn(),
];

$recent = db()->query(
    "SELECT b.*,u.first_name,u.last_name,h.name AS hotel_name,r.room_type
     FROM bookings b
     JOIN users u ON u.id=b.user_id
     JOIN hotels h ON h.id=b.hotel_id
     JOIN rooms r ON r.id=b.room_id
     ORDER BY b.created_at DESC LIMIT 8"
)->fetchAll();

$bkBadge=['confirmed'=>'badge-green','pending'=>'badge-yellow','cancelled'=>'badge-red','completed'=>'badge-navy'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav">
<div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">Dashboard</div>
  <p class="text-muted mb-3">Welcome back, <?= e($_SESSION['first_name']) ?> 👋</p>

  <div class="stat-grid">
    <?php foreach ([['👤','Registered Users',number_format($stats['users']),'Total accounts'],['🏨','Active Hotels',number_format($stats['hotels']),'Live listings'],['📋','Total Bookings',number_format($stats['bookings']),'All time'],['💰','Revenue',money($stats['revenue']),'Confirmed payments']] as [$ic,$ti,$va,$su]): ?>
    <div class="stat-card">
      <div class="stat-icon"><?= $ic ?></div>
      <div><div class="stat-val"><?= $va ?></div><div class="stat-lbl"><?= $su ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

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
          <td><?= date('M j',strtotime($b['check_in'])) ?> – <?= date('M j Y',strtotime($b['check_out'])) ?></td>
          <td><strong><?= money($b['total_price']) ?></strong></td>
          <td><span class="badge <?= $bkBadge[$b['status']]??'badge-grey' ?>"><?= ucfirst($b['status']) ?></span></td>
          <td><a href="bookings.php?edit=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
