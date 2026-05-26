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
<?php
// Curated Unsplash photos per city — beautiful, travel-ready shots
$destPhotos = [
  'Boracay'   => ['https://images.unsplash.com/photo-1559628233-100c798642d5?w=800&q=80', '🌊', 'Crystal-white beaches & turquoise water'],
  'El Nido'   => ['https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?w=800&q=80', '🏝️', 'Dramatic limestone cliffs & hidden lagoons'],
  'Siargao'   => ['https://images.unsplash.com/photo-1573790387438-4da905039392?w=800&q=80', '🏄', 'Surf capital of the Philippines'],
  'Cebu City' => ['https://images.unsplash.com/photo-1597149091879-f7957c354a19?w=800&q=80', '🌺', 'History, beaches & vibrant nightlife'],
  'Makati'    => ['https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80', '🏙️', 'The heart of Philippine business & luxury'],
  'Baguio'    => ['https://images.unsplash.com/photo-1455587734955-081b22074882?w=800&q=80', '🌲', 'Cool mountain air & pine forests'],
  'Davao'     => ['https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80', '🦅', 'Gateway to Mt. Apo & fresh durian'],
  'Batangas'  => ['https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80', '🤿', 'World-class diving & beach escapes'],
];
?>
<section class="section" style="background:var(--off)">
  <div class="container">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px;flex-wrap:wrap;gap:12px">
      <div>
        <h2>Popular <em style="color:var(--coral);font-style:italic">destinations</em></h2>
        <p class="text-muted mt-1">Hand-picked locations our guests love most</p>
      </div>
      <a href="<?= SITE_URL ?>/pages/search.php" class="btn btn-outline btn-sm">View all →</a>
    </div>

    <!-- Featured big card + smaller grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;grid-template-rows:auto auto;gap:16px">
      <?php
      $destList = db()->query('SELECT l.*,COUNT(h.id) cnt,MAX(h.rating) top FROM locations l JOIN hotels h ON h.location_id=l.id WHERE h.is_active=1 GROUP BY l.id ORDER BY top DESC LIMIT 6')->fetchAll();
      foreach ($destList as $i => $d):
        [$photo, $icon, $tagline] = $destPhotos[$d['city']] ?? ['https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80','🌴','Beautiful Philippines'];
        $isFeatured = ($i === 0);
      ?>
      <a href="<?= SITE_URL ?>/pages/search.php?location_id=<?= $d['id'] ?>"
         class="fade-up dest-card <?= $isFeatured ? 'dest-featured' : '' ?>"
         style="text-decoration:none;<?= $isFeatured ? 'grid-row:span 2;' : '' ?>">
        <div style="position:relative;height:<?= $isFeatured ? '100%' : '200px' ?>;min-height:<?= $isFeatured ? '420px' : '200px' ?>;overflow:hidden;border-radius:14px">
          <img src="<?= $photo ?>" alt="<?= e($d['city']) ?>"
               style="width:100%;height:100%;object-fit:cover;transition:transform .6s"
               onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'"
               onerror="this.style.background='#003580'">
          <!-- Gradient overlay -->
          <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,15,50,.85) 0%,rgba(0,15,50,.2) 50%,transparent 100%)"></div>
          <!-- Top badge -->
          <div style="position:absolute;top:14px;left:14px;background:rgba(255,255,255,.15);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.25);color:#fff;font-size:.72rem;font-weight:600;padding:4px 10px;border-radius:20px">
            <?= $icon ?> <?= $d['cnt'] ?> hotel<?= $d['cnt']>1?'s':'' ?>
          </div>
          <?php if ($d['top'] >= 4.8): ?>
          <div style="position:absolute;top:14px;right:14px;background:var(--coral);color:#fff;font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:20px">
            ★ Top Rated
          </div>
          <?php endif; ?>
          <!-- Bottom content -->
          <div style="position:absolute;bottom:0;left:0;right:0;padding:<?= $isFeatured ? '28px' : '16px' ?>">
            <div style="font-family:var(--font-head);font-size:<?= $isFeatured ? '1.9rem' : '1.2rem' ?>;font-weight:700;color:#fff;line-height:1.1;margin-bottom:6px">
              <?= e($d['city']) ?>
            </div>
            <?php if ($isFeatured): ?>
            <div style="font-size:.88rem;color:rgba(255,255,255,.75);margin-bottom:14px"><?= $tagline ?></div>
            <span style="display:inline-block;background:var(--coral);color:#fff;font-size:.78rem;font-weight:600;padding:7px 18px;border-radius:20px">
              Explore hotels →
            </span>
            <?php else: ?>
            <div style="font-size:.78rem;color:rgba(255,255,255,.7)"><?= $tagline ?></div>
            <?php endif; ?>
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
