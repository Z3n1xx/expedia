<?php
$sp = basename($_SERVER['PHP_SELF'], '.php');
$hotelName = $_SESSION['hotel_name'] ?? 'My Hotel';
?>
<aside class="admin-side">
  <div style="padding:18px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:6px">
    <div style="font-family:var(--font-head);font-size:.95rem;color:#fff;font-weight:700"><em style="color:var(--coral);font-style:italic">E</em>xpedia Staff</div>
    <div style="font-size:.75rem;color:rgba(255,255,255,.45);margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($hotelName) ?></div>
  </div>
  <div class="side-label">Hotel</div>
  <nav>
    <a href="<?= SITE_URL ?>/staff/dashboard.php"  class="<?= $sp==='dashboard'?'on':'' ?>">📊 Overview</a>
    <a href="<?= SITE_URL ?>/staff/bookings.php"   class="<?= $sp==='bookings'?'on':'' ?>">📋 Bookings</a>
    <a href="<?= SITE_URL ?>/staff/rooms.php"      class="<?= $sp==='rooms'?'on':'' ?>">🛏 Rooms</a>
  </nav>
  <div class="side-label">Account</div>
  <nav>
    <a href="<?= SITE_URL ?>/pages/profile.php">👤 My Profile</a>
    <a href="<?= SITE_URL ?>/pages/logout.php">🚪 Sign out</a>
  </nav>
</aside>
