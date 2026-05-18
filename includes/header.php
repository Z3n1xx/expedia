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
        <a href="<?= SITE_URL ?>/pages/login.php" class="<?= $page==='login'?'active':'' ?>">Sign in</a>
        <a href="<?= SITE_URL ?>/pages/register.php" class="btn-coral-sm">Join free</a>
      <?php endif; ?>

      <!-- Region / Currency picker -->
      <div class="locale-wrap">
        <button class="locale-btn" id="localeBtn" onclick="document.getElementById('localeDropdown').classList.toggle('open')" type="button">
          <?= $_REGIONS[SEL_REGION] ?? '🌐' ?> · <?= CURR_CODE ?>
        </button>
        <div class="locale-dropdown" id="localeDropdown">
          <form method="POST" action="<?= SITE_URL ?>/pages/set-locale.php">
            <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
            <div style="margin-bottom:12px">
              <label style="font-size:.78rem;font-weight:600;color:var(--text2);display:block;margin-bottom:5px">Region</label>
              <select name="region" onchange="this.form.submit()" style="width:100%;font-size:.84rem">
                <?php foreach ($_REGIONS as $code => $label): ?>
                  <option value="<?= $code ?>" <?= SEL_REGION===$code?'selected':'' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:.78rem;font-weight:600;color:var(--text2);display:block;margin-bottom:5px">Currency</label>
              <select name="currency" onchange="this.form.submit()" style="width:100%;font-size:.84rem">
                <?php foreach ($_CURRENCIES as $code => $info): ?>
                  <option value="<?= $code ?>" <?= CURR_CODE===$code?'selected':'' ?>><?= $code ?> — <?= $info['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</nav>

<?php if ($flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" id="flashMsg">
  <?= e($flash['msg']) ?>
  <button onclick="document.getElementById('flashMsg').remove()">×</button>
</div>
<?php endif; ?>
