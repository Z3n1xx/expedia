<?php
// admin/_sidebar.php — include inside admin pages
$ap = basename($_SERVER['PHP_SELF'],'.php');
?>
<aside class="admin-side">
  <div style="padding:18px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:6px">
    <div style="font-family:var(--font-head);font-size:1.05rem;color:#fff;font-weight:700"><em style="color:var(--coral);font-style:italic">E</em>xpedia Admin</div>
  </div>
  <div class="side-label">Main</div>
  <nav>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $ap==='dashboard'?'on':'' ?>">📊 Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/hotels.php"    class="<?= $ap==='hotels'?'on':'' ?>">🏨 Hotels</a>
    <a href="<?= SITE_URL ?>/admin/rooms.php"     class="<?= $ap==='rooms'?'on':'' ?>">🛏 Rooms</a>
    <a href="<?= SITE_URL ?>/admin/bookings.php"  class="<?= $ap==='bookings'?'on':'' ?>">📋 Bookings</a>
    <a href="<?= SITE_URL ?>/admin/payments.php"  class="<?= $ap==='payments'?'on':'' ?>">💳 Payments</a>
    <a href="<?= SITE_URL ?>/admin/users.php"     class="<?= $ap==='users'?'on':'' ?>">👤 Users</a>
  </nav>
  <div class="side-label">Account</div>
  <nav>
    <a href="<?= SITE_URL ?>/index.php">🌐 View Site</a>
    <a href="<?= SITE_URL ?>/pages/logout.php">🚪 Sign out</a>
  </nav>
</aside>
