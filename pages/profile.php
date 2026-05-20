<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'My Profile';

$userId = (int)$_SESSION['user_id'];
$user   = db()->prepare('SELECT * FROM users WHERE id=?');
$user->execute([$userId]);
$user = $user->fetch();
if (!$user) { header('Location: '.SITE_URL.'/pages/logout.php'); exit; }

$errors   = [];
$success  = false;
$section  = $_POST['section'] ?? '';

// ── Update profile info ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && $section==='info') {
    verifyCsrf();
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));

    if (!$firstName || !$lastName)
        $errors[] = 'First and last name are required.';
    if (strlen($firstName) > 80 || strlen($lastName) > 80)
        $errors[] = 'Name must not exceed 80 characters.';
    if (preg_match('/[0-9]/', $firstName) || preg_match('/[0-9]/', $lastName))
        $errors[] = 'Name must not contain numbers.';
    if (!validEmail($email))
        $errors[] = 'Please enter a valid .com email address (e.g. you@example.com).';
    if ($phone !== '' && !preg_match('/^0\d{10}$/', $phone))
        $errors[] = 'Phone must be 11 digits starting with 0 (e.g. 09XXXXXXXXX).';

    if (empty($errors)) {
        // Check email uniqueness (allow same email for this user)
        $chk = db()->prepare('SELECT id FROM users WHERE email=? AND id!=?');
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            $errors[] = 'That email is already used by another account.';
        } else {
            db()->prepare('UPDATE users SET first_name=?,last_name=?,email=?,phone=? WHERE id=?')
               ->execute([$firstName, $lastName, $email, $phone ?: null, $userId]);
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name']  = $lastName;
            $_SESSION['email']      = $email;
            flashSet('success', 'Profile updated successfully.');
            header('Location: '.SITE_URL.'/pages/profile.php'); exit;
        }
    }
    // Re-populate $user with submitted values on error
    $user['first_name'] = $firstName;
    $user['last_name']  = $lastName;
    $user['email']      = $email;
    $user['phone']      = $_POST['phone'] ?? '';
}

// ── Change password ───────────────────────────────────────────
$pwErrors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && $section==='password') {
    verifyCsrf();
    $current = $_POST['current_password'] ?? '';
    $newPw   = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current)
        $pwErrors[] = 'Current password is required.';
    elseif (!password_verify($current, $user['password']))
        $pwErrors[] = 'Current password is incorrect.';

    if (strlen($newPw) < 8)
        $pwErrors[] = 'New password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $newPw))
        $pwErrors[] = 'New password must contain at least one uppercase letter.';
    if (!preg_match('/[0-9]/', $newPw))
        $pwErrors[] = 'New password must contain at least one number.';
    if ($newPw !== $confirm)
        $pwErrors[] = 'Passwords do not match.';

    if (empty($pwErrors)) {
        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 10]);
        db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([$hash, $userId]);
        flashSet('success', 'Password changed successfully.');
        header('Location: '.SITE_URL.'/pages/profile.php'); exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav">
<div class="page-header">
  <div class="container">
    <h1>My <em style="color:var(--coral);font-style:italic">Profile</em></h1>
    <p>Manage your account information and password</p>
  </div>
</div>

<div class="container" style="max-width:760px;padding-top:36px;padding-bottom:72px">

  <!-- Account overview -->
  <div style="display:flex;align-items:center;gap:18px;background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px 24px;margin-bottom:28px;box-shadow:var(--shadow-sm)">
    <div style="width:60px;height:60px;border-radius:50%;background:var(--navy);color:#fff;display:grid;place-items:center;font-size:1.5rem;font-weight:700;flex-shrink:0">
      <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
    </div>
    <div>
      <div style="font-family:var(--font-head);font-size:1.15rem;font-weight:700;color:var(--navy)"><?= e($user['first_name'].' '.$user['last_name']) ?></div>
      <div style="font-size:.85rem;color:var(--text2)"><?= e($user['email']) ?></div>
      <div style="font-size:.75rem;color:var(--text3);margin-top:3px">Member since <?= date('F Y', strtotime($user['created_at'])) ?></div>
    </div>
    <div style="margin-left:auto">
      <span class="badge <?= $user['role']==='admin'?'badge-navy':'badge-green' ?>"><?= ucfirst($user['role']) ?></span>
    </div>
  </div>

  <!-- Personal info form -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:28px;margin-bottom:22px;box-shadow:var(--shadow-sm)">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:20px">Personal information</h3>

    <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

    <form method="POST" id="infoForm" novalidate>
      <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="section" value="info">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:0">
        <div class="form-group" style="margin:0">
          <label>First name *</label>
          <input type="text" name="first_name" id="inf_first" value="<?= e($user['first_name']) ?>" maxlength="80" required>
          <div class="form-error-msg" id="err_first"></div>
        </div>
        <div class="form-group" style="margin:0">
          <label>Last name *</label>
          <input type="text" name="last_name" id="inf_last" value="<?= e($user['last_name']) ?>" maxlength="80" required>
          <div class="form-error-msg" id="err_last"></div>
        </div>
      </div>

      <div class="form-group" style="margin-top:14px">
        <label>Email address *</label>
        <input type="email" name="email" id="inf_email" value="<?= e($user['email']) ?>" maxlength="180" required>
        <div class="form-error-msg" id="err_email"></div>
      </div>

      <div class="form-group">
        <label>Phone number <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
        <input type="tel" name="phone" id="inf_phone" value="<?= e($user['phone'] ?? '') ?>" maxlength="11" placeholder="09XXXXXXXXX">
        <div class="form-hint">Philippine mobile number — 11 digits starting with 0</div>
        <div class="form-error-msg" id="err_phone"></div>
      </div>

      <button type="submit" class="btn btn-navy" style="min-width:160px">Save changes</button>
    </form>
  </div>

  <!-- Change password form -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:28px;box-shadow:var(--shadow-sm)">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:20px">Change password</h3>

    <?php foreach ($pwErrors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

    <form method="POST" id="pwForm" novalidate>
      <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="section" value="password">

      <div class="form-group">
        <label>Current password *</label>
        <input type="password" name="current_password" id="pw_current" placeholder="Your current password" required>
        <div class="form-error-msg" id="err_current"></div>
      </div>
      <div class="form-group">
        <label>New password *</label>
        <input type="password" name="new_password" id="pw_new" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
        <div class="form-hint">At least 8 characters, one uppercase letter, one number</div>
        <div class="form-error-msg" id="err_pwNew"></div>
      </div>
      <div class="form-group">
        <label>Confirm new password *</label>
        <input type="password" name="confirm_password" id="pw_confirm" placeholder="Repeat your new password" required>
        <div class="form-error-msg" id="err_pwConfirm"></div>
      </div>

      <button type="submit" class="btn btn-navy" style="min-width:160px">Change password</button>
    </form>
  </div>

</div>
</div>

<script>
// ── Name fields: block digits on input ───────────────────────
['inf_first','inf_last'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', function() {
    this.value = this.value.replace(/[0-9]/g, '');
  });
});

// ── Phone: digits only, max 11 ────────────────────────────────
const phoneEl = document.getElementById('inf_phone');
if (phoneEl) {
  phoneEl.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').substring(0, 11);
  });
}

// ── Client-side validation helpers ───────────────────────────
function showErr(id, msg) {
  const el = document.getElementById(id);
  if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
}
function clearErrs(...ids) { ids.forEach(id => showErr(id, '')); }

// Info form
document.getElementById('infoForm')?.addEventListener('submit', function(e) {
  let ok = true;
  clearErrs('err_first','err_last','err_email','err_phone');

  const first = document.getElementById('inf_first').value.trim();
  const last  = document.getElementById('inf_last').value.trim();
  const email = document.getElementById('inf_email').value.trim();
  const phone = document.getElementById('inf_phone').value.trim();

  if (!first) { showErr('err_first', 'First name is required.'); ok = false; }
  else if (/[0-9]/.test(first)) { showErr('err_first', 'Name must not contain numbers.'); ok = false; }
  if (!last)  { showErr('err_last',  'Last name is required.');  ok = false; }
  else if (/[0-9]/.test(last))  { showErr('err_last',  'Name must not contain numbers.'); ok = false; }
  if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/.test(email)) { showErr('err_email', 'Only .com email addresses are accepted.'); ok = false; }
  if (phone !== '' && !/^0\d{10}$/.test(phone))   { showErr('err_phone', 'Must be 11 digits starting with 0 (e.g. 09XXXXXXXXX).'); ok = false; }

  if (!ok) e.preventDefault();
});

// Password form
document.getElementById('pwForm')?.addEventListener('submit', function(e) {
  let ok = true;
  clearErrs('err_current','err_pwNew','err_pwConfirm');

  const cur     = document.getElementById('pw_current').value;
  const newPw   = document.getElementById('pw_new').value;
  const confirm = document.getElementById('pw_confirm').value;

  if (!cur) { showErr('err_current', 'Current password is required.'); ok = false; }
  if (newPw.length < 8)              { showErr('err_pwNew', 'At least 8 characters required.'); ok = false; }
  else if (!/[A-Z]/.test(newPw))     { showErr('err_pwNew', 'Must contain at least one uppercase letter.'); ok = false; }
  else if (!/[0-9]/.test(newPw))     { showErr('err_pwNew', 'Must contain at least one number.'); ok = false; }
  if (ok && newPw !== confirm)        { showErr('err_pwConfirm', 'Passwords do not match.'); ok = false; }

  if (!ok) e.preventDefault();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
