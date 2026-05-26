<?php
$flash = flashGet();
$page  = basename($_SERVER['PHP_SELF'], '.php');
$dir   = basename(dirname($_SERVER['PHP_SELF']));
global $_CURRENCIES, $_REGIONS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle) ? e($pageTitle).' — '.SITE_NAME : SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css">
</head>
<body>

<nav class="nav" id="mainNav">
  <div class="nav-inner">
    <a href="<?= SITE_URL ?>/index.php" class="logo">
      <em>E</em>xpedia <span class="logo-ph">PH</span>
    </a>
    <div class="nav-links">
      <a href="<?= SITE_URL ?>/pages/search.php" class="<?= $page==='search'?'active':'' ?>">Explore Hotels</a>
      <?php if (isLoggedIn()): ?>
        <a href="<?= SITE_URL ?>/pages/my-bookings.php" class="<?= $page==='my-bookings'?'active':'' ?>">My Trips</a>
        <a href="<?= SITE_URL ?>/pages/support.php" class="<?= $page==='support'?'active':'' ?>">Support</a>
        <?php if (isAdmin()): ?>
          <a href="<?= SITE_URL ?>/admin/dashboard.php" class="<?= $dir==='admin'?'active':'' ?>">Admin</a>
        <?php endif; ?>
        <div class="nav-user">
          <a href="<?= SITE_URL ?>/pages/profile.php" class="nav-avatar-link" title="My Profile">
            <div class="avatar"><?= strtoupper(substr($_SESSION['first_name']??'U',0,1)) ?></div>
            <span class="nav-name"><?= e($_SESSION['first_name']??'') ?></span>
          </a>
          <a href="<?= SITE_URL ?>/pages/logout.php" class="btn-ghost">Sign out</a>
        </div>
      <?php else: ?>
        <a href="<?= SITE_URL ?>/pages/partner-apply.php" class="<?= $page==='partner-apply'?'active':'' ?>">List your property</a>
        <a href="<?= SITE_URL ?>/pages/login.php" class="<?= $page==='login'?'active':'' ?>">Sign in</a>
        <a href="<?= SITE_URL ?>/pages/register.php" class="btn-coral-sm">Join free</a>
      <?php endif; ?>

      <!-- Region / Currency picker -->
      <button class="locale-btn" id="localeBtn" onclick="document.getElementById('localeModal').classList.add('open')" type="button">
        <?= $_REGIONS[SEL_REGION] ?? '🌐' ?> · <?= CURR_CODE ?>
      </button>
    </div>
  </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
  <?= e($flash['msg']) ?>
  <button onclick="document.getElementById('flashMsg').remove()">×</button>
</div>
<?php endif; ?>

<!-- Locale modal -->
<div class="locale-modal" id="localeModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="locale-sheet">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
      <h3 style="font-family:var(--font-body);font-weight:600;font-size:1.05rem;color:var(--navy);margin:0">Region &amp; Currency</h3>
      <button onclick="document.getElementById('localeModal').classList.remove('open')"
              style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text3);line-height:1">×</button>
    </div>
    <form method="POST" action="<?= SITE_URL ?>/pages/set-locale.php" id="localeForm">
      <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
      <div style="margin-bottom:20px">
        <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Select your region</div>
        <div class="locale-region-grid">
          <?php foreach ($_REGIONS as $code => $label): ?>
            <label class="locale-region-opt <?= SEL_REGION===$code?'sel':'' ?>">
              <input type="radio" name="region" value="<?= $code ?>" <?= SEL_REGION===$code?'checked':'' ?> style="display:none">
              <?= $label ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="margin-bottom:24px">
        <div style="font-size:.78rem;font-weight:600;color:var(--text2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Select currency</div>
        <div class="locale-curr-grid">
          <?php foreach ($_CURRENCIES as $code => $info): ?>
            <label class="locale-curr-opt <?= CURR_CODE===$code?'sel':'' ?>">
              <input type="radio" name="currency" value="<?= $code ?>" <?= CURR_CODE===$code?'checked':'' ?> style="display:none">
              <span style="font-weight:600;font-size:.9rem"><?= $info['symbol'] ?> <?= $code ?></span>
              <span style="font-size:.75rem;color:var(--text3)"><?= $info['label'] ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-navy btn-full" style="padding:12px">Save preferences</button>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('.locale-region-opt,.locale-curr-opt').forEach(lbl=>{
  lbl.addEventListener('click',function(){
    const name=this.querySelector('input').name;
    document.querySelectorAll(`.locale-region-opt,.locale-curr-opt`).forEach(el=>{
      if(el.querySelector('input').name===name) el.classList.remove('sel');
    });
    this.classList.add('sel');
  });
});
// Close on Escape
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('localeModal')?.classList.remove('open');});
</script>
