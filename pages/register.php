<?php
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) { header('Location: '.SITE_URL.'/index.php'); exit; }
$pageTitle = 'Create Account';
$errors = []; $v = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $v = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'email'      => strtolower(trim($_POST['email'] ?? '')),
        'phone'      => preg_replace('/\D/', '', trim($_POST['phone'] ?? '')),
    ];
    $pw = $_POST['password'] ?? '';
    $cf = $_POST['confirm']  ?? '';
    if (!$v['first_name'] || !$v['last_name']) $errors[] = 'First and last name are required.';
    if (strlen($v['first_name']) > 80 || strlen($v['last_name']) > 80) $errors[] = 'Name must not exceed 80 characters.';
    if (preg_match('/[0-9]/', $v['first_name']) || preg_match('/[0-9]/', $v['last_name'])) $errors[] = 'Name must not contain numbers.';
    if (!validEmail($v['email'])) $errors[] = 'Please enter a valid .com email address (e.g. you@example.com).';
    if ($v['phone'] !== '' && !preg_match('/^0\d{10}$/', $v['phone'])) $errors[] = 'Phone must be 11 digits starting with 0 (e.g. 09XXXXXXXXX).';
    if (strlen($pw) < 8)           $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $pw)) $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $pw)) $errors[] = 'Password must contain at least one number.';
    if ($pw !== $cf)               $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $chk = db()->prepare('SELECT id FROM users WHERE email=?');
        $chk->execute([$v['email']]);
        if ($chk->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
            $ins  = db()->prepare('INSERT INTO users (first_name,last_name,email,phone,password,role) VALUES (?,?,?,?,?,\'user\')');
            $ins->execute([$v['first_name'],$v['last_name'],$v['email'],$v['phone'],$hash]);
            $newUserId = (int)db()->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id']    = $newUserId;
            $_SESSION['email']      = $v['email'];
            $_SESSION['first_name'] = $v['first_name'];
            $_SESSION['last_name']  = $v['last_name'];
            $_SESSION['role']       = 'user';
            // ── Sync new user to Firebase (DB + Auth) ──
            // Passing $pw creates a matching Firebase Auth account so
            // the Android app can sign in with the same email/password.
            require_once __DIR__ . '/../includes/firebase.php';
            Firebase::syncUser([
                'id'         => $newUserId,
                'first_name' => $v['first_name'],
                'last_name'  => $v['last_name'],
                'email'      => $v['email'],
                'phone'      => $v['phone'],
                'role'       => 'user',
                'hotel_id'   => null,
                'created_at' => date('Y-m-d H:i:s'),
            ], $pw);  // $pw = plain password — used once for Firebase Auth, never stored
            flashSet('success', 'Welcome to Expedia PH, '.$v['first_name'].'! 🎉');
            header('Location: '.SITE_URL.'/index.php'); exit;
        }
    }
}
include __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-left">
    <img src="https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=900&q=80"
         alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;opacity:.5;z-index:1"
         onerror="this.style.display='none'">
    <div style="position:absolute;inset:0;background:linear-gradient(160deg,rgba(0,32,96,.72),rgba(0,10,40,.82));z-index:2"></div>
    <div style="position:relative;z-index:3">
      <div style="font-family:var(--font-head);font-size:2rem;font-weight:700;color:#fff;margin-bottom:14px"><em style="color:var(--coral);font-style:italic">E</em>xpedia PH</div>
      <h2 style="font-size:1.4rem;color:#fff;margin-bottom:10px">Join millions of<br>happy travellers</h2>
      <p style="color:rgba(255,255,255,.6);font-size:.9rem;max-width:270px;line-height:1.7">Create your free account and start exploring the best hotels in the Philippines.</p>
    </div>
  </div>
  <div class="auth-right">
    <div class="inner">
      <span class="auth-logo"><em>E</em>xpedia PH</span>
      <h2 style="font-size:1.5rem;margin-bottom:6px">Create your account</h2>
      <p class="text-muted mb-3">Free — takes less than a minute</p>
      <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>
      <form method="POST" novalidate>
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>First name *</label>
            <input type="text" name="first_name" id="reg_first" value="<?= e($v['first_name']??'') ?>" placeholder="Juan" maxlength="80" required>
            <div class="form-error-msg" id="err_reg_first"></div>
          </div>
          <div class="form-group">
            <label>Last name *</label>
            <input type="text" name="last_name" id="reg_last" value="<?= e($v['last_name']??'') ?>" placeholder="Dela Cruz" maxlength="80" required>
            <div class="form-error-msg" id="err_reg_last"></div>
          </div>
        </div>
        <div class="form-group">
          <label>Email address *</label>
          <input type="email" name="email" id="reg_email" value="<?= e($v['email']??'') ?>" placeholder="juan@example.com" required>
          <div class="form-error-msg" id="err_reg_email"></div>
        </div>
        <div class="form-group">
          <label>Phone number <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
          <input type="tel" name="phone" id="reg_phone" value="<?= e($v['phone']??'') ?>" placeholder="09XXXXXXXXX" maxlength="11">
          <div class="form-hint">11 digits, starting with 0</div>
          <div class="form-error-msg" id="err_reg_phone"></div>
        </div>
        <div class="form-group">
          <label>Password * <span style="font-weight:400;color:var(--text3)">(min 8 chars)</span></label>
          <input type="password" name="password" id="reg_pw" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
          <div class="form-error-msg" id="err_reg_pw"></div>
        </div>
        <div class="form-group">
          <label>Confirm password *</label>
          <input type="password" name="confirm" id="reg_cf" placeholder="Repeat your password" required>
          <div class="form-error-msg" id="err_reg_cf"></div>
        </div>
        <button type="submit" class="btn btn-navy btn-full" style="padding:13px">Create account</button>
      </form>
      <p style="text-align:center;font-size:.83rem;color:var(--text2);margin-top:16px">
        Already have an account? <a href="<?= SITE_URL ?>/pages/login.php" style="color:var(--coral);font-weight:500">Sign in</a>
      </p>
    </div>
  </div>
</div>
<script>
// Name fields: block digits on input
['reg_first','reg_last'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', function() {
    this.value = this.value.replace(/[0-9]/g, '');
  });
});

// Phone: digits only, max 11
document.getElementById('reg_phone')?.addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').substring(0, 11);
});

function regShowErr(id, msg) {
  const el = document.getElementById(id);
  if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
}

document.querySelector('form')?.addEventListener('submit', function(e) {
  let ok = true;
  ['err_reg_first','err_reg_last','err_reg_email','err_reg_phone','err_reg_pw','err_reg_cf']
    .forEach(id => regShowErr(id, ''));

  const first = document.getElementById('reg_first').value.trim();
  const last  = document.getElementById('reg_last').value.trim();
  const email = document.getElementById('reg_email').value.trim();
  const phone = document.getElementById('reg_phone').value.trim();
  const pw    = document.getElementById('reg_pw').value;
  const cf    = document.getElementById('reg_cf').value;

  if (!first) { regShowErr('err_reg_first', 'First name is required.'); ok = false; }
  else if (/[0-9]/.test(first)) { regShowErr('err_reg_first', 'Name must not contain numbers.'); ok = false; }
  if (!last)  { regShowErr('err_reg_last',  'Last name is required.');  ok = false; }
  else if (/[0-9]/.test(last))  { regShowErr('err_reg_last',  'Name must not contain numbers.'); ok = false; }
  if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/.test(email)) { regShowErr('err_reg_email', 'Only .com email addresses are accepted.'); ok = false; }
  if (phone !== '' && !/^0\d{10}$/.test(phone))   { regShowErr('err_reg_phone', 'Must be 11 digits starting with 0 (e.g. 09XXXXXXXXX).'); ok = false; }
  if (pw.length < 8)            { regShowErr('err_reg_pw', 'At least 8 characters required.'); ok = false; }
  else if (!/[A-Z]/.test(pw))   { regShowErr('err_reg_pw', 'Must contain at least one uppercase letter.'); ok = false; }
  else if (!/[0-9]/.test(pw))   { regShowErr('err_reg_pw', 'Must contain at least one number.'); ok = false; }
  if (ok && pw !== cf)          { regShowErr('err_reg_cf',  'Passwords do not match.'); ok = false; }

  if (!ok) e.preventDefault();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
