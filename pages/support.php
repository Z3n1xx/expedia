<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'Help & Support';

// Create support_tickets table if it doesn't exist
db()->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('booking','payment','account','technical','partnership','other') NOT NULL DEFAULT 'other',
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `admin_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $subject  = trim($_POST['subject']  ?? '');
    $category = trim($_POST['category'] ?? 'other');
    $message  = trim($_POST['message']  ?? '');

    $validCats = ['booking','payment','account','technical','partnership','other'];
    if (!$name)                            $errors[] = 'Name is required.';
    if (preg_match('/[0-9]/', $name))      $errors[] = 'Name must not contain numbers.';
    if (!$email || !validEmail($email))    $errors[] = 'A valid .com email address is required.';
    if (!$subject)                         $errors[] = 'Subject is required.';
    if (!$message || strlen($message)<10)  $errors[] = 'Please describe your issue (at least 10 characters).';
    if (!in_array($category, $validCats))  $category = 'other';

    if (!$errors) {
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        // Auto-set priority based on category
        $priority = match($category) {
            'payment' => 'high',
            'booking' => 'normal',
            default   => 'normal',
        };
        db()->prepare(
            'INSERT INTO support_tickets (user_id,name,email,subject,category,message,priority) VALUES (?,?,?,?,?,?,?)'
        )->execute([$userId, $name, $email, $subject, $category, $message, $priority]);
        $ticketId = db()->lastInsertId();
        $success  = true;
    }
}

$prefillName  = '';
$prefillEmail = '';
if (isLoggedIn()) {
    $u = db()->prepare('SELECT first_name, last_name, email FROM users WHERE id=?');
    $u->execute([$_SESSION['user_id']]);
    $u = $u->fetch();
    $prefillName  = trim(($u['first_name']??'').' '.($u['last_name']??''));
    $prefillEmail = $u['email'] ?? '';
}

include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav">

<!-- Hero banner -->
<div style="background:linear-gradient(135deg,var(--navy) 0%,#1a3a6e 100%);padding:56px 0 64px;text-align:center;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background-image:radial-gradient(circle at 20% 50%,rgba(255,107,74,.15) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(0,100,255,.1) 0%,transparent 50%)"></div>
  <div class="container" style="position:relative">
    <div style="font-size:3rem;margin-bottom:12px">💬</div>
    <h1 style="color:#fff;font-size:2.2rem;margin-bottom:10px">How can we <em style="color:var(--coral);font-style:italic">help you?</em></h1>
    <p style="color:rgba(255,255,255,.7);font-size:1rem;max-width:480px;margin:0 auto">Our support team is ready to assist you with any questions or issues.</p>
  </div>
</div>

<div class="container" style="padding-top:48px;padding-bottom:80px">

  <!-- Quick help cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:52px">
    <?php foreach ([
      ['📋','Booking Help','Issues with reservations, check-in, or cancellations','booking'],
      ['💳','Payment Issues','Billing problems, refunds, or payment errors','payment'],
      ['👤','Account Support','Login problems, profile, or account settings','account'],
      ['🔧','Technical Issue','Website bugs, errors, or technical difficulties','technical'],
      ['🏨','Partnership','Questions about listing your property','partnership'],
    ] as [$icon,$title,$desc,$cat]):?>
    <a href="#contact-form" onclick="document.getElementById('category').value='<?= $cat ?>'"
       style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:22px 18px;text-decoration:none;transition:all .2s;cursor:pointer;display:block"
       onmouseover="this.style.borderColor='var(--navy)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.09)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''">
      <div style="font-size:1.8rem;margin-bottom:10px"><?= $icon ?></div>
      <h3 style="font-size:.92rem;font-weight:700;color:var(--navy);margin-bottom:5px"><?= $title ?></h3>
      <p style="font-size:.78rem;color:var(--text2);line-height:1.5"><?= $desc ?></p>
    </a>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:44px;align-items:start">

    <!-- CONTACT FORM -->
    <div id="contact-form">
      <h2 style="margin-bottom:6px">Send us a <em style="color:var(--coral);font-style:italic">message</em></h2>
      <p class="text-muted" style="margin-bottom:28px">We usually respond within 24 hours on business days.</p>

      <?php if ($success): ?>
      <div style="background:#f0fdf4;border:2px solid #86efac;border-radius:var(--r);padding:28px;text-align:center">
        <div style="font-size:3rem;margin-bottom:12px">✅</div>
        <h3 style="color:#15803d;margin-bottom:8px">Ticket #<?= $ticketId ?> submitted!</h3>
        <p style="color:#166534;font-size:.9rem;line-height:1.6">Thank you for reaching out. We've received your message and will reply to <strong><?= e($email) ?></strong> within 24 hours.</p>
        <a href="support.php" class="btn btn-navy" style="margin-top:18px;display:inline-block">Submit another ticket</a>
      </div>
      <?php else: ?>

      <?php if ($errors): ?>
      <div class="flash flash-error" style="margin-bottom:20px">
        <?php foreach ($errors as $e): ?><div>⚠ <?= e($e) ?></div><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:30px;box-shadow:var(--shadow-sm)">
        <input type="hidden" name="_csrf" value="<?= csrf() ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
          <div class="form-group" style="margin:0">
            <label for="name">Full name <span style="color:var(--coral)">*</span></label>
            <input type="text" id="name" name="name" placeholder="Your name"
                   value="<?= e($_POST['name'] ?? $prefillName) ?>" required autocomplete="name">
          </div>
          <div class="form-group" style="margin:0">
            <label for="email">Email address <span style="color:var(--coral)">*</span></label>
            <input type="email" id="email" name="email" placeholder="you@example.com"
                   value="<?= e($_POST['email'] ?? $prefillEmail) ?>" required autocomplete="email">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
          <div class="form-group" style="margin:0">
            <label for="category">Category <span style="color:var(--coral)">*</span></label>
            <select id="category" name="category">
              <option value="booking"     <?= ($_POST['category']??'')==='booking'    ?'selected':'' ?>>📋 Booking Help</option>
              <option value="payment"     <?= ($_POST['category']??'')==='payment'    ?'selected':'' ?>>💳 Payment Issues</option>
              <option value="account"     <?= ($_POST['category']??'')==='account'    ?'selected':'' ?>>👤 Account Support</option>
              <option value="technical"   <?= ($_POST['category']??'')==='technical'  ?'selected':'' ?>>🔧 Technical Issue</option>
              <option value="partnership" <?= ($_POST['category']??'')==='partnership'?'selected':'' ?>>🏨 Partnership</option>
              <option value="other"       <?= ($_POST['category']??'other')==='other'  ?'selected':'' ?>>💬 Other</option>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label for="subject">Subject <span style="color:var(--coral)">*</span></label>
            <input type="text" id="subject" name="subject" placeholder="Brief summary of your issue"
                   value="<?= e($_POST['subject'] ?? '') ?>" required maxlength="255">
          </div>
        </div>

        <div class="form-group">
          <label for="message">Message <span style="color:var(--coral)">*</span></label>
          <textarea id="message" name="message" rows="6" placeholder="Please describe your issue in detail. Include booking reference numbers, dates, or any relevant information…" required><?= e($_POST['message'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
          <p style="font-size:.78rem;color:var(--text3)">🔒 Your information is safe with us.</p>
          <button type="submit" class="btn btn-primary" style="padding:12px 32px">Send Message →</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- SIDEBAR INFO -->
    <div>
      <!-- Contact info -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;box-shadow:var(--shadow-sm);margin-bottom:20px">
        <h3 style="font-size:1rem;font-weight:700;color:var(--navy);margin-bottom:18px">📞 Other ways to reach us</h3>
        <div style="display:flex;flex-direction:column;gap:14px">
          <div style="display:flex;align-items:flex-start;gap:12px">
            <div style="width:36px;height:36px;background:var(--sky);border-radius:8px;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0">📧</div>
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--navy)">Email</div>
              <div style="font-size:.8rem;color:var(--text2)">support@expediaph.com</div>
              <div style="font-size:.73rem;color:var(--text3)">Replies within 24 hours</div>
            </div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:12px">
            <div style="width:36px;height:36px;background:var(--sky);border-radius:8px;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0">📱</div>
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--navy)">Phone / SMS</div>
              <div style="font-size:.8rem;color:var(--text2)">+63 917 123 4567</div>
              <div style="font-size:.73rem;color:var(--text3)">Mon–Fri, 8AM–8PM PHT</div>
            </div>
          </div>
          <div style="display:flex;align-items:flex-start;gap:12px">
            <div style="width:36px;height:36px;background:var(--sky);border-radius:8px;display:grid;place-items:center;font-size:1.1rem;flex-shrink:0">💬</div>
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--navy)">Live Chat</div>
              <div style="font-size:.8rem;color:var(--text2)">Available on our mobile app</div>
              <div style="font-size:.73rem;color:var(--text3)">Average wait: &lt;5 minutes</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Response times -->
      <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;box-shadow:var(--shadow-sm);margin-bottom:20px">
        <h3 style="font-size:1rem;font-weight:700;color:var(--navy);margin-bottom:14px">⏱ Response Times</h3>
        <div style="display:flex;flex-direction:column;gap:10px">
          <?php foreach ([
            ['Urgent (Payment)','&lt;2 hours','#fef2f2','#b91c1c'],
            ['High Priority','&lt;6 hours','#fff7ed','#c2410c'],
            ['Normal','24 hours','#f0fdf4','#15803d'],
            ['General Inquiry','48 hours','#eff6ff','#1d4ed8'],
          ] as [$label,$time,$bg,$color]): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;background:<?= $bg ?>;border-radius:6px;padding:8px 12px">
            <span style="font-size:.8rem;color:var(--text)"><?= $label ?></span>
            <span style="font-size:.78rem;font-weight:700;color:<?= $color ?>"><?= $time ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- FAQ quick links -->
      <div style="background:var(--navy);border-radius:var(--r);padding:22px;color:#fff">
        <h3 style="font-size:.95rem;font-weight:700;margin-bottom:14px">❓ Common Questions</h3>
        <div style="display:flex;flex-direction:column;gap:8px">
          <?php foreach ([
            'How do I cancel a booking?',
            'When will I get my refund?',
            'Can I change my check-in date?',
            'How do I get my receipt?',
            'Why was my payment declined?',
          ] as $q): ?>
          <div style="font-size:.8rem;color:rgba(255,255,255,.75);padding:9px 12px;background:rgba(255,255,255,.06);border-radius:6px;cursor:pointer;border:1px solid rgba(255,255,255,.1);transition:.2s"
               onclick="document.getElementById('message').value+='<?= addslashes($q) ?>\n';document.getElementById('contact-form').scrollIntoView({behavior:'smooth'})"
               onmouseover="this.style.background='rgba(255,255,255,.12)'"
               onmouseout="this.style.background='rgba(255,255,255,.06)'">
            ↗ <?= $q ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
// Block numbers in name field
document.getElementById('name')?.addEventListener('input', function() {
  this.value = this.value.replace(/[0-9]/g, '');
});

// Client-side validation
document.querySelector('form')?.addEventListener('submit', function(e) {
  const name    = document.getElementById('name').value.trim();
  const email   = document.getElementById('email').value.trim();
  const subject = document.getElementById('subject').value.trim();
  const message = document.getElementById('message').value.trim();
  const errs    = [];

  if (!name)                                  errs.push('Name is required.');
  if (/[0-9]/.test(name))                     errs.push('Name must not contain numbers.');
  if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$/.test(email)) errs.push('Please enter a valid .com email.');
  if (!subject)                               errs.push('Subject is required.');
  if (message.length < 10)                    errs.push('Message must be at least 10 characters.');

  if (errs.length) {
    e.preventDefault();
    const div = document.createElement('div');
    div.className = 'flash flash-error';
    div.style.marginBottom = '20px';
    div.innerHTML = errs.map(x => `<div>⚠ ${x}</div>`).join('');
    const form = document.querySelector('form');
    form.parentNode.insertBefore(div, form);
    div.scrollIntoView({behavior:'smooth'});
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
