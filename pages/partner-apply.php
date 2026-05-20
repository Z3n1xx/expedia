<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'List Your Property';

// Create table if it doesn't exist yet (safe on both local and Railway)
db()->exec("CREATE TABLE IF NOT EXISTS `hotel_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `business_name` varchar(160) NOT NULL,
  `contact_name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(220) NOT NULL,
  `property_type` varchar(60) NOT NULL,
  `rooms_count` smallint unsigned DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `message` text,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$errors = []; $success = false; $v = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $v = [
        'business_name'  => trim($_POST['business_name']  ?? ''),
        'contact_name'   => trim($_POST['contact_name']   ?? ''),
        'email'          => strtolower(trim($_POST['email'] ?? '')),
        'phone'          => preg_replace('/\D/', '', trim($_POST['phone'] ?? '')),
        'location'       => trim($_POST['location']       ?? ''),
        'property_type'  => trim($_POST['property_type']  ?? ''),
        'rooms_count'    => (int)($_POST['rooms_count']   ?? 0),
        'website'        => trim($_POST['website']        ?? ''),
        'message'        => trim($_POST['message']        ?? ''),
    ];

    $validTypes = ['hotel','resort','villa','hostel','guesthouse','apartment','lodge','other'];

    if (!$v['business_name'])   $errors[] = 'Property / business name is required.';
    if (!$v['contact_name'])    $errors[] = 'Contact person name is required.';
    if (preg_match('/[0-9]/', $v['contact_name'])) $errors[] = 'Contact name must not contain numbers.';
    if (!validEmail($v['email']))
        $errors[] = 'Please enter a valid .com email address (e.g. you@example.com).';
    if ($v['phone'] !== '' && !preg_match('/^0\d{10}$/', $v['phone']))
        $errors[] = 'Phone must be 11 digits starting with 0 (e.g. 09XXXXXXXXX).';
    if (!$v['location'])        $errors[] = 'Location / city is required.';
    if (!in_array($v['property_type'], $validTypes)) $errors[] = 'Please select a property type.';
    if ($v['rooms_count'] < 1)  $errors[] = 'Number of rooms must be at least 1.';

    if (empty($errors)) {
        db()->prepare(
            'INSERT INTO hotel_applications
             (business_name,contact_name,email,phone,location,property_type,rooms_count,website,message)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $v['business_name'], $v['contact_name'], $v['email'],
            $v['phone'] ?: null, $v['location'], $v['property_type'],
            $v['rooms_count'], $v['website'] ?: null, $v['message'] ?: null,
        ]);
        $success = true;
        $v = [];
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div>
<div class="page-header">
  <div class="container">
    <h1>List your <em style="color:var(--coral);font-style:italic">property</em></h1>
    <p>Reach millions of travellers across the Philippines — apply to be a partner today</p>
  </div>
</div>

<div class="container" style="max-width:860px;padding-top:40px;padding-bottom:80px">

  <!-- Benefits strip -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:40px">
    <?php foreach ([
      ['📈','Reach more guests','Get discovered by millions of travellers searching across the Philippines.'],
      ['💰','More bookings','Increase occupancy with our seamless booking platform.'],
      ['🛠','Easy management','Manage your rooms, rates, and availability all in one place.'],
      ['🔒','Secure payouts','Guaranteed payments directly to your account.'],
    ] as [$icon,$title,$desc]): ?>
    <div class="fade-up" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:20px;text-align:center;box-shadow:var(--shadow-sm)">
      <div style="font-size:1.7rem;margin-bottom:8px"><?= $icon ?></div>
      <div style="font-weight:600;color:var(--navy);font-size:.92rem;margin-bottom:5px"><?= $title ?></div>
      <div style="font-size:.8rem;color:var(--text3);line-height:1.6"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($success): ?>
  <div style="background:#f0faf4;border:1.5px solid #34d399;border-radius:var(--r);padding:28px 32px;text-align:center;margin-bottom:32px">
    <div style="font-size:2.2rem;margin-bottom:10px">🎉</div>
    <h3 style="color:#065f46;margin-bottom:6px">Application submitted!</h3>
    <p style="color:#047857;font-size:.9rem">Thank you for your interest. Our team will review your application and get back to you within 2–3 business days.</p>
    <a href="<?= SITE_URL ?>/index.php" class="btn btn-navy" style="margin-top:18px">Back to home</a>
  </div>
  <?php else: ?>

  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:32px 36px;box-shadow:var(--shadow-sm)">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:6px">Property application</h3>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:24px">Fill in your details and our partnerships team will be in touch.</p>

    <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

    <form method="POST" id="applyForm" novalidate>
      <input type="hidden" name="_csrf" value="<?= csrf() ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
          <label>Property / Business name *</label>
          <input type="text" name="business_name" id="ap_biz" value="<?= e($v['business_name']??'') ?>" placeholder="e.g. Sunset Beach Resort" maxlength="160" required>
          <div class="form-error-msg" id="err_biz"></div>
        </div>
        <div class="form-group">
          <label>Property type *</label>
          <select name="property_type" id="ap_type" required>
            <option value="">Select type…</option>
            <?php foreach (['hotel'=>'Hotel','resort'=>'Resort','villa'=>'Villa','hostel'=>'Hostel','guesthouse'=>'Guesthouse','apartment'=>'Apartment / Condo','lodge'=>'Lodge / Cabin','other'=>'Other'] as $val=>$lbl): ?>
              <option value="<?= $val ?>" <?= ($v['property_type']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-error-msg" id="err_type"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
          <label>Contact person name *</label>
          <input type="text" name="contact_name" id="ap_contact" value="<?= e($v['contact_name']??'') ?>" placeholder="Your full name" maxlength="160" required>
          <div class="form-error-msg" id="err_contact"></div>
        </div>
        <div class="form-group">
          <label>Email address *</label>
          <input type="email" name="email" id="ap_email" value="<?= e($v['email']??'') ?>" placeholder="you@yourproperty.com" required>
          <div class="form-error-msg" id="err_email"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
          <label>Phone number <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
          <input type="tel" name="phone" id="ap_phone" value="<?= e($v['phone']??'') ?>" placeholder="09XXXXXXXXX" maxlength="11">
          <div class="form-hint">11 digits starting with 0</div>
          <div class="form-error-msg" id="err_phone"></div>
        </div>
        <div class="form-group">
          <label>Location / City *</label>
          <input type="text" name="location" id="ap_loc" value="<?= e($v['location']??'') ?>" placeholder="e.g. Boracay, Aklan" maxlength="220" required>
          <div class="form-error-msg" id="err_loc"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="form-group">
          <label>Number of rooms *</label>
          <input type="number" name="rooms_count" id="ap_rooms" value="<?= e($v['rooms_count']??'') ?>" placeholder="e.g. 12" min="1" max="9999" required>
          <div class="form-error-msg" id="err_rooms"></div>
        </div>
        <div class="form-group">
          <label>Website <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
          <input type="url" name="website" id="ap_web" value="<?= e($v['website']??'') ?>" placeholder="https://yourproperty.com">
        </div>
      </div>

      <div class="form-group">
        <label>Tell us about your property <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
        <textarea name="message" id="ap_msg" rows="4" placeholder="Describe your property, highlights, target guests, etc." style="resize:vertical"><?= e($v['message']??'') ?></textarea>
      </div>

      <button type="submit" class="btn btn-navy" style="min-width:200px;padding:13px">Submit application</button>
    </form>
  </div>
  <?php endif; ?>

</div>
</div>

<script>
document.getElementById('ap_contact')?.addEventListener('input', function() {
  this.value = this.value.replace(/[0-9]/g, '');
});
document.getElementById('ap_phone')?.addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').substring(0, 11);
});

function apErr(id, msg) {
  const el = document.getElementById(id);
  if (el) { el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }
}

document.getElementById('applyForm')?.addEventListener('submit', function(e) {
  let ok = true;
  ['err_biz','err_type','err_contact','err_email','err_phone','err_loc','err_rooms']
    .forEach(id => apErr(id, ''));

  const biz     = document.getElementById('ap_biz').value.trim();
  const type    = document.getElementById('ap_type').value;
  const contact = document.getElementById('ap_contact').value.trim();
  const email   = document.getElementById('ap_email').value.trim();
  const phone   = document.getElementById('ap_phone').value.trim();
  const loc     = document.getElementById('ap_loc').value.trim();
  const rooms   = parseInt(document.getElementById('ap_rooms').value, 10);

  if (!biz)   { apErr('err_biz',     'Property name is required.'); ok = false; }
  if (!type)  { apErr('err_type',    'Please select a property type.'); ok = false; }
  if (!contact)              { apErr('err_contact', 'Contact name is required.'); ok = false; }
  else if (/[0-9]/.test(contact)) { apErr('err_contact', 'Name must not contain numbers.'); ok = false; }
  if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/.test(email))
    { apErr('err_email', 'Only .com email addresses are accepted.'); ok = false; }
  if (phone !== '' && !/^0\d{10}$/.test(phone))
    { apErr('err_phone', 'Must be 11 digits starting with 0.'); ok = false; }
  if (!loc)   { apErr('err_loc',   'Location is required.'); ok = false; }
  if (!rooms || rooms < 1) { apErr('err_rooms', 'Enter a valid number of rooms.'); ok = false; }

  if (!ok) e.preventDefault();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
