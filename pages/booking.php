<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$roomId  = (int)($_REQUEST['room_id']  ?? 0);
$hotelId = (int)($_REQUEST['hotel_id'] ?? 0);
$checkIn  = trim($_REQUEST['check_in']  ?? '');
$checkOut = trim($_REQUEST['check_out'] ?? '');
$guests   = max(1,(int)($_REQUEST['guests'] ?? 1));

// Load room
$stmt = db()->prepare(
    'SELECT r.*,h.name AS hotel_name,h.thumbnail AS hotel_thumb,l.city
     FROM rooms r
     JOIN hotels h ON h.id=r.hotel_id
     JOIN locations l ON l.id=h.location_id
     WHERE r.id=? AND r.hotel_id=? AND r.is_available=1'
);
$stmt->execute([$roomId,$hotelId]);
$room = $stmt->fetch();
if (!$room) { flashSet('error','Room not found or unavailable.'); header('Location: '.SITE_URL.'/pages/search.php'); exit; }

$errors  = [];
$step    = (int)($_POST['step'] ?? 1);
$special = trim($_POST['special_requests'] ?? '');
$payMethod = $_POST['pay_method'] ?? 'credit_card';

// ── STEP 1 → validate trip, advance to step 2 ────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && $step===1) {
    verifyCsrf();
    $checkIn  = trim($_POST['check_in']  ?? '');
    $checkOut = trim($_POST['check_out'] ?? '');
    $guests   = max(1,(int)($_POST['guests'] ?? 1));
    $special  = trim($_POST['special_requests'] ?? '');

    if (!$checkIn || !$checkOut) { $errors[]='Please select check-in and check-out dates.'; }
    else {
        $ci = DateTime::createFromFormat('Y-m-d',$checkIn);
        $co = DateTime::createFromFormat('Y-m-d',$checkOut);
        if (!$ci||!$co)       $errors[]='Invalid date format.';
        elseif ($ci < new DateTime('today')) $errors[]='Check-in cannot be in the past.';
        elseif ($co<=$ci)     $errors[]='Check-out must be after check-in.';
        elseif ($guests>$room['max_guests']) $errors[]='Max '.$room['max_guests'].' guests for this room.';
    }
    if (empty($errors)) $step=2;
}

// ── STEP 2 → validate payment, save booking ────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && $step===3) {
    verifyCsrf();
    $checkIn   = trim($_POST['check_in']  ?? '');
    $checkOut  = trim($_POST['check_out'] ?? '');
    $guests    = max(1,(int)($_POST['guests'] ?? 1));
    $special   = trim($_POST['special_requests'] ?? '');
    $payMethod = $_POST['pay_method'] ?? 'credit_card';

    $cardName   = trim($_POST['card_name']      ?? '');
    $cardNumber = preg_replace('/\s+/','',$_POST['card_number'] ?? '');
    $cardExpiry = trim($_POST['card_expiry']    ?? '');
    $cardCvv    = trim($_POST['card_cvv']       ?? '');
    $ewalletNum = trim($_POST['ewallet_number'] ?? '');
    $bankRef    = trim($_POST['bank_ref']       ?? '');

    // Payment validation — only validate fields relevant to the chosen method
    if (in_array($payMethod,['credit_card','debit_card'])) {
        if (!$cardName)                                    $errors[]='Cardholder name is required.';
        if (!preg_match('/^\d{13,19}$/',$cardNumber))     $errors[]='Enter a valid card number (digits only).';
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2,4}$/',$cardExpiry)) $errors[]='Enter expiry as MM/YY.';
        if (!preg_match('/^\d{3,4}$/',$cardCvv))         $errors[]='Enter a valid CVV (3–4 digits).';
    } elseif (in_array($payMethod,['gcash','maya'])) {
        if (!preg_match('/^(09|\+639)\d{9}$/',$ewalletNum)) $errors[]='Enter a valid PH mobile number (e.g. 09XXXXXXXXX).';
    } elseif ($payMethod==='bank_transfer') {
        if (strlen($bankRef)<6) $errors[]='Please enter your bank reference number (min 6 characters).';
    }
    // cash and other methods: no extra validation needed

    if (empty($errors)) {
        $ci     = DateTime::createFromFormat('Y-m-d',$checkIn);
        $co     = DateTime::createFromFormat('Y-m-d',$checkOut);
        $nights = (int)$ci->diff($co)->days;
        $total  = $nights * (float)$room['price_per_night'];

        try {
            $bkStmt = db()->prepare(
                'INSERT INTO bookings (user_id,hotel_id,room_id,check_in,check_out,guests,total_nights,total_price,special_requests,status,payment_status,payment_method)
                 VALUES (?,?,?,?,?,?,?,?,?,\'confirmed\',\'paid\',?)'
            );
            $bkStmt->execute([$_SESSION['user_id'],$hotelId,$roomId,$checkIn,$checkOut,$guests,$nights,$total,$special,$payMethod]);
            $bookingId = (int)db()->lastInsertId();

            // ── Sync new booking to Firebase Realtime Database ──
            require_once __DIR__ . '/../includes/firebase.php';
            Firebase::syncBooking([
                'id'               => $bookingId,
                'user_id'          => $_SESSION['user_id'],
                'hotel_id'         => $hotelId,
                'room_id'          => $roomId,
                'check_in'         => $checkIn,
                'check_out'        => $checkOut,
                'guests'           => $guests,
                'total_nights'     => $nights,
                'total_price'      => $total,
                'special_requests' => $special,
                'status'           => 'confirmed',
                'payment_status'   => 'paid',
                'payment_method'   => $payMethod,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            flashSet('success','Booking #'.$bookingId.' confirmed! Payment of '.money($total).' received.');
            header('Location: '.SITE_URL.'/pages/my-bookings.php');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Something went wrong saving your booking. Please try again.';
            $step = 2;
        }
    } else {
        $step = 2;
    }
}

// Compute display totals
$nights = 0; $totalPrice = 0.0;
if ($checkIn && $checkOut) {
    $ci = DateTime::createFromFormat('Y-m-d',$checkIn);
    $co = DateTime::createFromFormat('Y-m-d',$checkOut);
    if ($ci && $co && $co > $ci) { $nights=(int)$ci->diff($co)->days; $totalPrice=$nights*(float)$room['price_per_night']; }
}

$pageTitle = $step===2 ? 'Payment' : 'Confirm Booking';
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav">
<div class="container" style="padding:36px 24px 80px;max-width:980px">

  <!-- Step indicator -->
  <div class="steps mb-3">
    <?php foreach(['1'=>'Trip details','2'=>'Payment'] as $n=>$lbl):
      $done=$step>(int)$n; $cur=$step==(int)$n;
    ?>
    <div class="step-dot" style="background:<?=($done||$cur)?'var(--navy)':'var(--border)'?>;color:<?=($done||$cur)?'#fff':'var(--text3)'?>">
      <?= $done?'✓':$n ?>
    </div>
    <span class="step-lbl" style="font-weight:<?=$cur?'600':'400'?>;color:<?=$cur?'var(--navy)':($done?'var(--text2)':'var(--text3)')?>">
      <?= $lbl ?>
    </span>
    <?php if ($n<2): ?><div class="step-line" style="background:<?=$step>1?'var(--navy)':'var(--border)'?>"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <h1 style="font-size:1.75rem;margin-bottom:22px"><?= $step===2?'💳 Payment details':'🗓 Your trip details' ?></h1>

  <?php foreach ($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:26px;align-items:start">

  <?php if ($step===1): ?>
  <!-- ═══ STEP 1: Trip details ═══ -->
  <form method="POST">
    <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
    <input type="hidden" name="step"     value="1">
    <input type="hidden" name="room_id"  value="<?= $roomId ?>">
    <input type="hidden" name="hotel_id" value="<?= $hotelId ?>">
    <input type="hidden" id="ppp"        value="<?= $room['price_per_night'] ?>">
    <input type="hidden" id="total_nights" name="total_nights" value="<?= $nights ?>">
    <input type="hidden" id="total_price"  name="total_price"  value="<?= number_format($totalPrice,2,'.','') ?>">

    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;margin-bottom:14px;box-shadow:var(--shadow-sm)">
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:16px">Your trip</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div class="form-group" style="margin:0"><label>Check-in *</label><input type="date" id="check_in" name="check_in" value="<?= e($checkIn) ?>" required></div>
        <div class="form-group" style="margin:0"><label>Check-out *</label><input type="date" id="check_out" name="check_out" value="<?= e($checkOut) ?>" required></div>
      </div>
      <div class="form-group">
        <label>Guests *</label>
        <select name="guests">
          <?php for ($i=1;$i<=$room['max_guests'];$i++): ?><option value="<?= $i ?>" <?= $guests===$i?'selected':'' ?>><?= $i ?> guest<?= $i>1?'s':'' ?></option><?php endfor; ?>
        </select>
        <div class="form-hint">Max <?= $room['max_guests'] ?> guests for this room</div>
      </div>
      <div class="form-group" style="margin:0">
        <label>Special requests <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
        <textarea name="special_requests" placeholder="Early check-in, high floor, dietary needs…"><?= e($special) ?></textarea>
      </div>
    </div>

    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;margin-bottom:16px;box-shadow:var(--shadow-sm)">
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:16px">Guest information</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div><label>First name</label><input type="text" value="<?= e($_SESSION['first_name']) ?>" disabled style="background:var(--off)"></div>
        <div><label>Last name</label><input type="text" value="<?= e($_SESSION['last_name']) ?>" disabled style="background:var(--off)"></div>
      </div>
      <div><label>Email</label><input type="text" value="<?= e($_SESSION['email']) ?>" disabled style="background:var(--off)"></div>
    </div>

    <button type="submit" class="btn btn-primary btn-full" style="padding:14px;font-size:.98rem">Continue to Payment →</button>
  </form>

  <?php elseif ($step===2): ?>
  <!-- ═══ STEP 2: Payment ═══ -->
  <form method="POST" id="payForm">
    <input type="hidden" name="_csrf"            value="<?= csrf() ?>">
    <input type="hidden" name="step"             value="3">
    <input type="hidden" name="room_id"          value="<?= $roomId ?>">
    <input type="hidden" name="hotel_id"         value="<?= $hotelId ?>">
    <input type="hidden" name="check_in"         value="<?= e($checkIn) ?>">
    <input type="hidden" name="check_out"        value="<?= e($checkOut) ?>">
    <input type="hidden" name="guests"           value="<?= $guests ?>">
    <input type="hidden" name="special_requests" value="<?= e($special) ?>">
    <input type="hidden" name="total_nights"     value="<?= $nights ?>">
    <input type="hidden" name="total_price"      value="<?= number_format($totalPrice,2,'.','') ?>">

    <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);padding:24px;margin-bottom:14px;box-shadow:var(--shadow-sm)">
      <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:16px">Choose payment method</h3>

      <div class="pay-methods">
        <?php
        $methods=['credit_card'=>['💳','Credit Card'],'debit_card'=>['🏧','Debit Card'],'gcash'=>['📱','GCash'],'maya'=>['🟣','Maya'],'bank_transfer'=>['🏦','Bank Transfer'],'cash'=>['💵','Pay at Hotel']];
        foreach ($methods as $val=>[$icon,$lbl]):
          $sel=($payMethod===$val);
        ?>
        <div class="pay-method <?= $sel?'sel':'' ?>" data-v="<?= $val ?>" onclick="switchPay('<?= $val ?>')">
          <input type="radio" name="pay_method" value="<?= $val ?>" <?= $sel?'checked':'' ?> style="display:none">
          <span class="icon"><?= $icon ?></span>
          <span class="lbl"><?= $lbl ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Credit / Debit Card (shared panel — both methods point here) -->
      <div class="pay-fields <?= in_array($payMethod,['credit_card','debit_card'])?'show':'' ?>" id="pf_credit_card" data-also="pf_debit_card">
        <div class="pay-info-box">🔒 Your card is encrypted. Only the last 4 digits are ever stored.</div>
        <div class="form-group"><label>Cardholder name *</label><input type="text" name="card_name" placeholder="As printed on card" value="<?= e($_POST['card_name']??'') ?>"></div>
        <div class="form-group"><label>Card number *</label><input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" value="<?= e($_POST['card_number']??'') ?>"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="margin:0"><label>Expiry (MM/YY) *</label><input type="text" name="card_expiry" placeholder="MM/YY" maxlength="7" value="<?= e($_POST['card_expiry']??'') ?>"></div>
          <div class="form-group" style="margin:0"><label>CVV *</label><input type="text" name="card_cvv" placeholder="123" maxlength="4" value="<?= e($_POST['card_cvv']??'') ?>"></div>
        </div>
      </div>
      <!-- GCash -->
      <div class="pay-fields <?= $payMethod==='gcash'?'show':'' ?>" id="pf_gcash">
        <div class="pay-info-box">📲 A payment request will be sent to your GCash number. Accept it to confirm.</div>
        <div class="form-group"><label>GCash mobile number *</label><input type="text" name="ewallet_number" placeholder="09XXXXXXXXX" maxlength="13" value="<?= e($_POST['ewallet_number']??'') ?>"></div>
      </div>
      <!-- Maya -->
      <div class="pay-fields <?= $payMethod==='maya'?'show':'' ?>" id="pf_maya">
        <div class="pay-info-box">🟣 A payment request will be sent to your Maya account. Accept it to confirm.</div>
        <div class="form-group"><label>Maya mobile number *</label><input type="text" name="ewallet_number" placeholder="09XXXXXXXXX" maxlength="13" value="<?= e($_POST['ewallet_number']??'') ?>"></div>
      </div>
      <!-- Bank Transfer -->
      <div class="pay-fields <?= $payMethod==='bank_transfer'?'show':'' ?>" id="pf_bank_transfer">
        <div class="pay-info-box">
          🏦 <strong>Transfer to:</strong><br>
          Bank: BDO Unibank · Account: Expedia PH Inc.<br>
          Account no.: <strong>001-234-567-890</strong><br>
          Amount: <strong><?= money($totalPrice) ?></strong>
        </div>
        <div class="form-group"><label>Bank reference / transaction number *</label><input type="text" name="bank_ref" placeholder="e.g. 20240610-ABCD1234" value="<?= e($_POST['bank_ref']??'') ?>"></div>
      </div>
      <!-- Cash -->
      <div class="pay-fields <?= $payMethod==='cash'?'show':'' ?>" id="pf_cash">
        <div class="pay-info-box green">
          ✅ <strong>Pay at the hotel front desk on check-in.</strong><br>
          Amount due: <strong><?= money($totalPrice) ?></strong><br>
          Please bring a valid government-issued ID.
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px">
      <a href="booking.php?room_id=<?= $roomId ?>&hotel_id=<?= $hotelId ?>&check_in=<?= urlencode($checkIn) ?>&check_out=<?= urlencode($checkOut) ?>&guests=<?= $guests ?>"
         class="btn btn-outline" style="flex:1;text-align:center">← Back</a>
      <button type="submit" class="btn btn-primary" style="flex:2;padding:14px;font-size:.95rem">
        Pay <?= money($totalPrice) ?> &amp; Confirm Booking
      </button>
    </div>
  </form>
  <?php endif; ?>

  <!-- SUMMARY PANEL -->
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:var(--r);overflow:hidden;box-shadow:var(--shadow);position:sticky;top:calc(var(--nav-h)+20px)">
    <div style="height:140px;overflow:hidden">
      <img src="<?= SITE_URL ?>/<?= e($room['hotel_thumb']??'') ?>" alt="<?= e($room['hotel_name']) ?>" style="width:100%;height:100%"
           onerror="this.src='https://placehold.co/300x140/003580/ffffff?text=<?= urlencode($room['hotel_name']) ?>'">
    </div>
    <div style="padding:16px">
      <div class="text-sm text-muted" style="margin-bottom:2px"><?= e($room['hotel_name']) ?></div>
      <div style="font-family:var(--font-head);font-size:.98rem;font-weight:700;color:var(--navy);margin-bottom:2px"><?= e($room['room_type']) ?></div>
      <div class="text-sm text-muted mb-2">📍 <?= e($room['city']) ?></div>
      <?php if ($checkIn && $checkOut): ?>
      <div style="background:var(--off);border-radius:6px;padding:8px 10px;margin-bottom:12px;font-size:.8rem;color:var(--text2)">
        <div class="flex jb" style="margin-bottom:3px"><span>Check-in</span><strong style="color:var(--text)"><?= date('M j, Y',strtotime($checkIn)) ?></strong></div>
        <div class="flex jb"><span>Check-out</span><strong style="color:var(--text)"><?= date('M j, Y',strtotime($checkOut)) ?></strong></div>
      </div>
      <?php endif; ?>
      <hr class="divider" style="margin:0 0 10px">
      <div class="flex jb text-sm text-muted" style="margin-bottom:5px">
        <span><?= money($room['price_per_night']) ?> × <span id="nights_display"><?= $nights>0?$nights.' night'.($nights>1?'s':''):'—' ?></span></span>
        <span id="total_display"><?= $nights>0?money($totalPrice):'—' ?></span>
      </div>
      <div class="flex jb text-sm text-muted" style="margin-bottom:10px"><span>Taxes &amp; fees</span><span>Included</span></div>
      <hr class="divider" style="margin:0 0 10px">
      <div class="flex jb" style="font-weight:700;font-size:.98rem">
        <span>Total</span>
        <span id="grand_total" style="color:var(--navy);font-family:var(--font-head)"><?= $nights>0?money($totalPrice):'—' ?></span>
      </div>
      <div class="text-sm text-muted" style="text-align:center;margin-top:10px">🔒 Secure encrypted checkout</div>
    </div>
  </div>

  </div><!-- grid -->
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
