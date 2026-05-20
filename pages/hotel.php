<?php
require_once __DIR__ . '/../includes/config.php';
$hotelId  = (int)($_GET['id']       ?? 0);
$checkIn  = $_GET['check_in']  ?? '';
$checkOut = $_GET['check_out'] ?? '';
$guests   = max(1,(int)($_GET['guests'] ?? 1));

$stmt = db()->prepare('SELECT h.*,l.city,l.country FROM hotels h JOIN locations l ON l.id=h.location_id WHERE h.id=? AND h.is_active=1');
$stmt->execute([$hotelId]);
$hotel = $stmt->fetch();
if (!$hotel) { header('Location: '.SITE_URL.'/pages/search.php'); exit; }

$roomStmt = db()->prepare('SELECT * FROM rooms WHERE hotel_id=? AND is_available=1 ORDER BY price_per_night');
$roomStmt->execute([$hotelId]);
$rooms = $roomStmt->fetchAll();

$revStmt = db()->prepare('SELECT r.*,u.first_name,u.last_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.hotel_id=? ORDER BY r.created_at DESC LIMIT 8');
$revStmt->execute([$hotelId]);
$reviews = $revStmt->fetchAll();

$amenities = json_decode($hotel['amenities']??'[]',true)?:[];
$pageTitle = e($hotel['name']);
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav">
<div class="container" style="padding-top:30px;padding-bottom:80px">

  <!-- Gallery -->
  <div class="gallery">
    <div class="gallery-main">
      <img src="<?= preg_match('#^https?://#',$hotel['thumbnail']??'') ? e($hotel['thumbnail']) : SITE_URL.'/'.e($hotel['thumbnail']??'') ?>" alt="<?= e($hotel['name']) ?>"
           onerror="this.src='https://placehold.co/800x470/003580/ffffff?text=<?= urlencode($hotel['name']) ?>'">
    </div>
    <div class="gallery-sub">
      <img src="https://placehold.co/400x270/EBF4FF/003580?text=Pool+%26+Amenities" alt="Amenities" style="height:100%">
    </div>
    <div class="gallery-sub">
      <img src="https://placehold.co/400x200/003580/ffffff?text=Room+Interior" alt="Room" style="height:100%">
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:44px;align-items:start">
    <!-- LEFT -->
    <div>
      <div class="flex jb ac" style="flex-wrap:wrap;gap:12px;margin-bottom:12px">
        <div>
          <div class="stars" style="margin-bottom:5px"><?= str_repeat('★',$hotel['stars']) ?></div>
          <h1 style="font-size:1.9rem;margin-bottom:6px"><?= e($hotel['name']) ?></h1>
          <p class="text-muted">📍 <?= e($hotel['address']) ?>, <?= e($hotel['city']) ?></p>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="rating-pill" style="font-size:1rem;padding:6px 14px">★ <?= number_format($hotel['rating'],2) ?></div>
          <div class="text-sm text-muted mt-1"><?= number_format($hotel['review_count']) ?> reviews</div>
        </div>
      </div>

      <hr class="divider">
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:10px">About this hotel</h3>
      <p class="text-muted" style="line-height:1.8;margin-bottom:26px"><?= nl2br(e($hotel['description'])) ?></p>

      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:12px">Amenities</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:8px;margin-bottom:32px">
        <?php foreach ($amenities as $am): ?>
          <div style="display:flex;align-items:center;gap:7px;font-size:.87rem;color:var(--text2)"><span style="color:var(--coral)">✓</span><?= e($am) ?></div>
        <?php endforeach; ?>
      </div>

      <!-- ROOMS -->
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:14px">Available rooms</h3>
      <?php if (empty($rooms)): ?>
        <p class="text-muted">No rooms currently available.</p>
      <?php else: foreach ($rooms as $rm):
        $rmAms = json_decode($rm['amenities']??'[]',true)?:[];
      ?>
      <div style="border:1.5px solid var(--border);border-radius:var(--r);overflow:hidden;margin-bottom:12px">
        <div style="display:grid;grid-template-columns:130px 1fr">
          <div style="height:110px;overflow:hidden">
            <img src="<?= preg_match('#^https?://#',$rm['thumbnail']??'') ? e($rm['thumbnail']) : SITE_URL.'/'.e($rm['thumbnail']??'') ?>" alt="<?= e($rm['room_type']) ?>" style="height:100%;width:100%"
                 onerror="this.src='https://placehold.co/130x110/EBF4FF/003580?text=Room'">
          </div>
          <div style="padding:14px;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <div style="flex:1">
              <h4 style="font-family:var(--font-head);font-size:.98rem;font-weight:700;color:var(--navy);margin-bottom:4px"><?= e($rm['room_type']) ?></h4>
              <p class="text-sm text-muted" style="margin-bottom:5px"><?= e($rm['description']) ?></p>
              <p class="text-sm text-muted" style="margin-bottom:7px">👤 Max <?= $rm['max_guests'] ?> guests</p>
              <div class="pills"><?php foreach (array_slice($rmAms,0,4) as $a): ?><span class="pill"><?= e($a) ?></span><?php endforeach; ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-size:1.4rem;font-weight:700;color:var(--navy);font-family:var(--font-head)"><?= money($rm['price_per_night']) ?></div>
              <div class="text-sm text-muted" style="margin-bottom:10px">per night</div>
              <?php if (isLoggedIn()): ?>
                <a href="booking.php?room_id=<?= $rm['id'] ?>&hotel_id=<?= $hotel['id'] ?>&check_in=<?= urlencode($checkIn) ?>&check_out=<?= urlencode($checkOut) ?>&guests=<?= $guests ?>"
                   class="btn btn-primary btn-sm">Select room</a>
              <?php else: ?>
                <a href="login.php?next=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-navy btn-sm">Sign in to book</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; endif; ?>

      <!-- REVIEWS -->
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-top:28px;margin-bottom:14px">Guest reviews</h3>
      <?php if (empty($reviews)): ?>
        <p class="text-muted text-sm">No reviews yet. Be the first to review after your stay!</p>
      <?php else: foreach ($reviews as $rv): ?>
        <div style="padding:16px 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:7px">
            <div style="width:34px;height:34px;background:var(--navy);color:#fff;border-radius:50%;display:grid;place-items:center;font-weight:600;font-size:.82rem;flex-shrink:0"><?= strtoupper(substr($rv['first_name'],0,1)) ?></div>
            <div>
              <div style="font-weight:500;font-size:.88rem"><?= e($rv['first_name'].' '.$rv['last_name']) ?></div>
              <div class="stars" style="font-size:.78rem"><?= str_repeat('★',$rv['rating']) ?></div>
            </div>
            <div style="margin-left:auto;font-size:.75rem;color:var(--text3)"><?= date('M Y',strtotime($rv['created_at'])) ?></div>
          </div>
          <?php if ($rv['comment']): ?><p class="text-sm text-muted" style="line-height:1.7"><?= e($rv['comment']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- BOOKING PANEL -->
    <div class="book-panel">
      <?php $minFrom = $rooms ? min(array_column($rooms,'price_per_night')) : 0; ?>
      <div style="margin-bottom:18px">
        <span class="price-big"><?= money($minFrom) ?></span>
        <span class="price-per"> / night</span>
        <div class="text-sm text-muted mt-1">Starting room price</div>
      </div>
      <?php if (!isLoggedIn()): ?>
        <div style="background:var(--sky);border-radius:var(--r-sm);padding:16px;text-align:center;margin-bottom:14px">
          <p class="text-sm text-muted mb-2">Sign in to view and book rooms</p>
          <a href="login.php?next=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-navy btn-full">Sign in</a>
        </div>
      <?php else: ?>
        <div style="background:var(--sky);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:14px;font-size:.83rem;color:var(--navy)">
          ℹ Select a room above to book
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <div class="form-group" style="margin:0"><label>Check-in</label><input type="date" id="check_in" value="<?= e($checkIn) ?>" onchange="updateLinks()"></div>
          <div class="form-group" style="margin:0"><label>Check-out</label><input type="date" id="check_out" value="<?= e($checkOut) ?>" onchange="updateLinks()"></div>
        </div>
        <div class="form-group">
          <label>Guests</label>
          <select id="guests_sel" onchange="updateLinks()">
            <?php for ($i=1;$i<=6;$i++): ?><option value="<?= $i ?>" <?= $guests==$i?'selected':'' ?>><?= $i ?> guest<?= $i>1?'s':'' ?></option><?php endfor; ?>
          </select>
        </div>
      <?php endif; ?>
      <hr class="divider">
      <p class="text-sm text-muted" style="text-align:center">Free cancellation · No booking fees</p>
    </div>
  </div>
</div>
</div>
<script>
function updateLinks(){
  const ci=document.getElementById('check_in')?.value||'';
  const co=document.getElementById('check_out')?.value||'';
  const g=document.getElementById('guests_sel')?.value||'1';
  document.querySelectorAll('a[href*="booking.php"]').forEach(a=>{
    const u=new URL(a.href);u.searchParams.set('check_in',ci);u.searchParams.set('check_out',co);u.searchParams.set('guests',g);a.href=u;
  });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
