<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Find Hotels in the Philippines';
$locations = db()->query('SELECT * FROM locations ORDER BY city')->fetchAll();
$featured  = db()->query('SELECT h.*,l.city FROM hotels h JOIN locations l ON l.id=h.location_id WHERE h.is_active=1 ORDER BY h.rating DESC LIMIT 6')->fetchAll();
$dests     = db()->query('SELECT l.*,COUNT(h.id) cnt,MAX(h.rating) top FROM locations l JOIN hotels h ON h.location_id=l.id WHERE h.is_active=1 GROUP BY l.id ORDER BY top DESC LIMIT 4')->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div>
<!-- HERO -->
<section class="hero">
  <div class="hero-bg">
    <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&q=80"
         alt="Beach" class="hero-bg-img"
         onerror="this.style.display='none'">
    <div class="hero-bg-overlay"></div>
  </div>
  <div class="hero-content">
    <div class="hero-tag">🌴 Discover the Philippines</div>
    <h1>Find your <em>perfect</em><br>hotel stay</h1>
    <p class="hero-sub">Thousands of hotels across the most beautiful islands in the world — book with confidence.</p>

    <form class="search-bar" action="<?= SITE_URL ?>/pages/search.php" method="GET">
      <div class="sf">
        <label for="hl">Destination</label>
        <select id="hl" name="location_id">
          <option value="">Anywhere in PH</option>
          <?php foreach ($locations as $l): ?>
            <option value="<?= $l['id'] ?>"><?= e($l['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sdiv"></div>
      <div class="sf">
        <label for="hci">Check-in</label>
        <input type="date" id="hci" name="check_in">
      </div>
      <div class="sdiv"></div>
      <div class="sf">
        <label for="hco">Check-out</label>
        <input type="date" id="hco" name="check_out">
      </div>
      <div class="sdiv"></div>
      <div class="sf" style="max-width:90px">
        <label for="hg">Guests</label>
        <select id="hg" name="guests">
          <?php for ($i=1;$i<=8;$i++): ?><option><?= $i ?></option><?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </form>
  </div>
  <div class="hero-wave">
    <svg viewBox="0 0 1440 80" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width:100%;height:80px">
      <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="white"/>
    </svg>
  </div>
</section>

<!-- DESTINATIONS -->
<section class="section" style="background:var(--off)">
  <div class="container">
    <div style="margin-bottom:36px">
      <h2>Popular <em style="color:var(--coral);font-style:italic">destinations</em></h2>
      <p class="text-muted mt-1">Hand-picked locations our guests love most</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px">
      <?php foreach ($dests as $d): ?>
      <a href="<?= SITE_URL ?>/pages/search.php?location_id=<?= $d['id'] ?>" class="card fade-up" style="text-decoration:none">
        <div class="card-img" style="height:185px">
          <img src="<?= SITE_URL ?>/assets/images/dest/dest_<?= $d['id'] ?>.jpg" alt="<?= e($d['city']) ?>"
               onerror="this.src='https://placehold.co/400x185/003580/ffffff?text=<?= urlencode($d['city']) ?>'">
          <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,32,96,.65),transparent)"></div>
          <div style="position:absolute;bottom:12px;left:14px;color:#fff">
            <div style="font-family:var(--font-head);font-size:1.15rem;font-weight:700"><?= e($d['city']) ?></div>
            <div style="font-size:.78rem;opacity:.8"><?= $d['cnt'] ?> hotel<?= $d['cnt']>1?'s':'' ?></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FEATURED HOTELS -->
<section class="section">
  <div class="container">
    <div class="flex jb ac mb-3" style="flex-wrap:wrap;gap:12px">
      <div>
        <h2>Top-rated <em style="color:var(--coral);font-style:italic">hotels</em></h2>
        <p class="text-muted mt-1">Handpicked for quality and guest satisfaction</p>
      </div>
      <a href="<?= SITE_URL ?>/pages/search.php" class="btn btn-outline btn-sm">View all →</a>
    </div>
    <div class="card-grid">
      <?php foreach ($featured as $h):
        $stmt = db()->prepare('SELECT MIN(price_per_night) FROM rooms WHERE hotel_id=? AND is_available=1');
        $stmt->execute([$h['id']]); $from = (float)$stmt->fetchColumn();
      ?>
      <a href="<?= SITE_URL ?>/pages/hotel.php?id=<?= $h['id'] ?>" class="card fade-up" style="text-decoration:none">
        <div class="card-img" style="height:200px">
          <img src="<?= preg_match('#^https?://#',$h['thumbnail']??'') ? e($h['thumbnail']) : SITE_URL.'/'.e($h['thumbnail']??'') ?>" alt="<?= e($h['name']) ?>"
               onerror="this.src='https://placehold.co/600x200/003580/ffffff?text=<?= urlencode($h['name']) ?>'">
          <div style="position:absolute;top:10px;right:10px">
            <span class="rating-pill">★ <?= number_format($h['rating'],2) ?></span>
          </div>
        </div>
        <div class="card-body">
          <div class="flex jb ac" style="gap:8px;margin-bottom:5px">
            <h3 style="font-size:.98rem;font-weight:700"><?= e($h['name']) ?></h3>
            <div class="stars" style="flex-shrink:0"><?= str_repeat('★',$h['stars']) ?></div>
          </div>
          <p class="text-sm text-muted mb-2">📍 <?= e($h['city']) ?></p>
          <p class="text-sm text-muted" style="line-height:1.6"><?= e(mb_substr($h['description'],0,90)) ?>…</p>
        </div>
        <div class="card-footer">
          <span class="text-sm text-muted"><?= number_format($h['review_count']) ?> reviews</span>
          <div style="text-align:right">
            <span class="text-sm text-muted">From </span>
            <strong style="font-size:1.05rem;color:var(--navy)"><?= money($from) ?></strong>
            <span class="text-sm text-muted">/night</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- WHY US -->
<section class="section" style="background:var(--navy)">
  <div class="container" style="text-align:center">
    <h2 style="color:#fff;margin-bottom:44px">Why book with <em style="color:var(--coral);font-style:italic">Expedia PH?</em></h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:24px">
      <?php foreach ([['🏆','Best Price Guarantee','Find it cheaper anywhere — we\'ll match it.'],['🔒','Secure Payments','SSL-encrypted checkout on every booking.'],['✈️','Free Cancellation','Flexible bookings, cancel anytime on most stays.'],['💬','24/7 Support','Our team is always here to help you.']] as [$i,$t,$d]): ?>
      <div class="fade-up" style="padding:26px 18px;background:rgba(255,255,255,.05);border-radius:var(--r);border:1px solid rgba(255,255,255,.08)">
        <div style="font-size:1.9rem;margin-bottom:12px"><?= $i ?></div>
        <h3 style="font-family:var(--font-head);color:#fff;font-size:.98rem;margin-bottom:7px"><?= $t ?></h3>
        <p style="font-size:.83rem;color:rgba(255,255,255,.55);line-height:1.7"><?= $d ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
