<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) { header('Location: '.SITE_URL.'/index.php'); exit; }
$pageTitle = 'Sign In';
$errors = []; $emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $emailVal = $email;
    if (!$email || !$password) {
        $errors[] = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email=?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['hotel_id']   = $user['hotel_id'] ?? null;
            flashSet('success', 'Welcome back, '.$user['first_name'].'! 👋');
            $next = $_POST['next'] ?? '';
            if ($next && str_starts_with($next, '/')) { header('Location: '.$next); }
            elseif ($user['role'] === 'admin') { header('Location: '.SITE_URL.'/admin/dashboard.php'); }
            elseif ($user['role'] === 'staff') { header('Location: '.SITE_URL.'/staff/dashboard.php'); }
            else { header('Location: '.SITE_URL.'/index.php'); }
            exit;
        } else {
            $errors[] = 'Incorrect email or password.';
        }
    }
}
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-left">
    <div style="position:relative;z-index:1">
      <div style="font-family:var(--font-head);font-size:2rem;font-weight:700;color:#fff;margin-bottom:14px"><em style="color:var(--coral);font-style:italic">E</em>xpedia PH</div>
      <h2 style="font-size:1.5rem;color:#fff;margin-bottom:10px">Your next adventure<br>starts here</h2>
      <p style="color:rgba(255,255,255,.6);font-size:.9rem;max-width:270px;line-height:1.7">Thousands of hotels across the most beautiful islands in the Philippines.</p>
    </div>
  </div>
  <div class="auth-right">
    <div class="inner">
      <span class="auth-logo"><em>E</em>xpedia PH</span>
      <h2 style="font-size:1.6rem;margin-bottom:6px">Welcome back</h2>
      <p class="text-muted mb-3">Sign in to manage your bookings</p>
      <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>
      <form method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <input type="hidden" name="next"  value="<?= e($_GET['next'] ?? '') ?>">
        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" value="<?= e($emailVal) ?>" placeholder="you@example.com" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn-navy btn-full" style="padding:13px;margin-top:4px">Sign in</button>
      </form>
      <p style="text-align:center;font-size:.83rem;color:var(--text2);margin-top:18px">
        New to Expedia PH? <a href="<?= SITE_URL ?>/pages/register.php" style="color:var(--coral);font-weight:500">Create a free account</a>
      </p>
      <div style="margin-top:20px;padding:12px 14px;background:var(--sky);border-radius:8px;font-size:.8rem;color:var(--text2)">
        <strong style="color:var(--navy)">Demo admin:</strong> admin@expedia.ph / Admin@1234
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
