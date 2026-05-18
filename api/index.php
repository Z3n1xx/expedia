<?php
// api/index.php — Expedia PH REST API
// Base URL: http://localhost/expedia/api/
// All responses are JSON. Auth via Bearer token (API key stored in users table).

require_once __DIR__ . '/../includes/config.php';

// ── CORS headers ─────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Helpers ───────────────────────────────────────────────────
function resp(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
function ok(array $data, int $code = 200): void  { resp($code, ['success' => true,  'data' => $data]); }
function err(string $msg, int $code = 400): void { resp($code, ['success' => false, 'error' => $msg]); }

function getBody(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── Auth middleware ───────────────────────────────────────────
// Pass ?api_key=YOUR_KEY or Authorization: Bearer YOUR_KEY header
// For this project api_key = user email + ":" + password hash stored separately.
// Simple approach: we use a plain secret token per user stored in a query param or header.
// For demo: admin token = "expedia-admin-token", any logged user = their email.
// Production would use JWT — but for a school project this is sufficient.

function requireApiAuth(): array {
    $token = '';
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
    if (str_starts_with($auth, 'Bearer ')) $token = trim(substr($auth, 7));
    if (!$token) $token = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    if (!$token) err('Missing API key. Pass ?api_key=YOUR_KEY or Authorization: Bearer YOUR_KEY', 401);

    $stmt = db()->prepare('SELECT * FROM users WHERE api_token=?');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) err('Invalid or expired API key.', 401);
    return $user;
}

function requireAdminApi(): array {
    $user = requireApiAuth();
    if ($user['role'] !== 'admin') err('Admin access required.', 403);
    return $user;
}

// ── Router ────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Strip the base path (expedia/api)
$uri = preg_replace('#^.*?/api/?#', '', $uri);
$parts = explode('/', $uri);
$resource = $parts[0] ?? '';
$id       = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;

// ─────────────────────────────────────────────────────────────
// PUBLIC ENDPOINTS (no auth needed)
// ─────────────────────────────────────────────────────────────

// GET /api/hotels
if ($resource === 'hotels' && $method === 'GET' && $id === null) {
    $locId  = (int)($_GET['location_id'] ?? 0);
    $stars  = (int)($_GET['stars']       ?? 0);
    $limit  = min(100, (int)($_GET['limit']  ?? 20));
    $offset = max(0,   (int)($_GET['offset'] ?? 0));
    $sort   = in_array($_GET['sort']??'',['rating','name','stars']) ? $_GET['sort'] : 'rating';

    $where = ['h.is_active=1']; $params = [];
    if ($locId) { $where[] = 'h.location_id=?'; $params[] = $locId; }
    if ($stars)  { $where[] = 'h.stars=?';       $params[] = $stars; }
    $orderMap = ['rating'=>'h.rating DESC','name'=>'h.name ASC','stars'=>'h.stars DESC'];

    $sql = "SELECT h.*,l.city,l.country,
                   (SELECT MIN(r.price_per_night) FROM rooms r WHERE r.hotel_id=h.id AND r.is_available=1) AS min_price,
                   (SELECT COUNT(*) FROM rooms r WHERE r.hotel_id=h.id AND r.is_available=1) AS room_count
            FROM hotels h JOIN locations l ON l.id=h.location_id
            WHERE ".implode(' AND ',$where)." ORDER BY ".$orderMap[$sort]." LIMIT ? OFFSET ?";
    $params[] = $limit; $params[] = $offset;
    $stmt = db()->prepare($sql); $stmt->execute($params);
    $hotels = $stmt->fetchAll();

    foreach ($hotels as &$h) {
        $h['amenities'] = json_decode($h['amenities'] ?? '[]', true);
    }
    ok(['hotels' => $hotels, 'count' => count($hotels), 'limit' => $limit, 'offset' => $offset]);
}

// GET /api/hotels/{id}
if ($resource === 'hotels' && $method === 'GET' && $id !== null) {
    $stmt = db()->prepare('SELECT h.*,l.city,l.country FROM hotels h JOIN locations l ON l.id=h.location_id WHERE h.id=? AND h.is_active=1');
    $stmt->execute([$id]);
    $hotel = $stmt->fetch();
    if (!$hotel) err('Hotel not found.', 404);
    $hotel['amenities'] = json_decode($hotel['amenities'] ?? '[]', true);

    $rooms = db()->prepare('SELECT * FROM rooms WHERE hotel_id=? AND is_available=1 ORDER BY price_per_night');
    $rooms->execute([$id]); $roomList = $rooms->fetchAll();
    foreach ($roomList as &$r) $r['amenities'] = json_decode($r['amenities'] ?? '[]', true);
    $hotel['rooms'] = $roomList;

    $revs = db()->prepare('SELECT r.rating,r.comment,r.created_at,u.first_name,u.last_name FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.hotel_id=? ORDER BY r.created_at DESC LIMIT 10');
    $revs->execute([$id]);
    $hotel['reviews'] = $revs->fetchAll();

    ok(['hotel' => $hotel]);
}

// GET /api/locations
if ($resource === 'locations' && $method === 'GET') {
    $locs = db()->query('SELECT * FROM locations ORDER BY city')->fetchAll();
    ok(['locations' => $locs]);
}

// GET /api/rooms/{id}
if ($resource === 'rooms' && $method === 'GET' && $id !== null) {
    $stmt = db()->prepare('SELECT r.*,h.name AS hotel_name FROM rooms r JOIN hotels h ON h.id=r.hotel_id WHERE r.id=?');
    $stmt->execute([$id]); $room = $stmt->fetch();
    if (!$room) err('Room not found.', 404);
    $room['amenities'] = json_decode($room['amenities'] ?? '[]', true);
    ok(['room' => $room]);
}

// GET /api/rooms  (all rooms, optional ?hotel_id=)
if ($resource === 'rooms' && $method === 'GET' && $id === null) {
    $hid = (int)($_GET['hotel_id'] ?? 0);
    if ($hid) { $stmt=db()->prepare('SELECT * FROM rooms WHERE hotel_id=? AND is_available=1 ORDER BY price_per_night'); $stmt->execute([$hid]); }
    else { $stmt=db()->query('SELECT r.*,h.name AS hotel_name FROM rooms r JOIN hotels h ON h.id=r.hotel_id WHERE r.is_available=1 ORDER BY r.price_per_night LIMIT 100'); }
    $rooms = $stmt->fetchAll();
    foreach ($rooms as &$r) $r['amenities'] = json_decode($r['amenities'] ?? '[]', true);
    ok(['rooms' => $rooms]);
}

// ─────────────────────────────────────────────────────────────
// AUTH ENDPOINTS
// ─────────────────────────────────────────────────────────────

// POST /api/auth/login  — returns api_token
if ($resource === 'auth' && ($parts[1] ?? '') === 'login' && $method === 'POST') {
    $body = getBody();
    $email = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';
    if (!$email || !$password) err('email and password are required.');
    $stmt = db()->prepare('SELECT * FROM users WHERE email=?');
    $stmt->execute([$email]); $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) err('Invalid credentials.', 401);

    // Generate token if not set
    if (empty($user['api_token'])) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET api_token=? WHERE id=?')->execute([$token, $user['id']]);
        $user['api_token'] = $token;
    }
    ok([
        'token'  => $user['api_token'],
        'user'   => ['id'=>$user['id'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'email'=>$user['email'],'role'=>$user['role']],
    ]);
}

// ─────────────────────────────────────────────────────────────
// PROTECTED USER ENDPOINTS
// ─────────────────────────────────────────────────────────────

// GET /api/bookings  — my bookings
if ($resource === 'bookings' && $method === 'GET' && $id === null) {
    $user = requireApiAuth();
    $stmt = db()->prepare(
        "SELECT b.*,h.name AS hotel_name,r.room_type,l.city
         FROM bookings b JOIN hotels h ON h.id=b.hotel_id JOIN rooms r ON r.id=b.room_id
         JOIN locations l ON l.id=h.location_id
         WHERE b.user_id=? ORDER BY b.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    ok(['bookings' => $stmt->fetchAll()]);
}

// GET /api/bookings/{id}
if ($resource === 'bookings' && $method === 'GET' && $id !== null) {
    $user = requireApiAuth();
    $stmt = db()->prepare(
        "SELECT b.*,h.name AS hotel_name,r.room_type,l.city
         FROM bookings b JOIN hotels h ON h.id=b.hotel_id JOIN rooms r ON r.id=b.room_id
         JOIN locations l ON l.id=h.location_id
         WHERE b.id=? AND (b.user_id=? OR ? = 'admin')"
    );
    $stmt->execute([$id, $user['id'], $user['role']]);
    $booking = $stmt->fetch();
    if (!$booking) err('Booking not found.', 404);
    ok(['booking' => $booking]);
}

// POST /api/bookings — create booking
if ($resource === 'bookings' && $method === 'POST') {
    $user = requireApiAuth();
    $body = getBody();
    $roomId   = (int)($body['room_id']   ?? 0);
    $hotelId  = (int)($body['hotel_id']  ?? 0);
    $checkIn  = trim($body['check_in']   ?? '');
    $checkOut = trim($body['check_out']  ?? '');
    $guests   = max(1,(int)($body['guests'] ?? 1));
    $special  = trim($body['special_requests'] ?? '');
    $payMethod= $body['pay_method'] ?? 'cash';

    if (!$roomId || !$hotelId || !$checkIn || !$checkOut) err('room_id, hotel_id, check_in, check_out are required.');
    if (!in_array($payMethod,['credit_card','debit_card','gcash','maya','bank_transfer','cash'])) err('Invalid pay_method.');

    $ci = DateTime::createFromFormat('Y-m-d', $checkIn);
    $co = DateTime::createFromFormat('Y-m-d', $checkOut);
    if (!$ci || !$co || $co <= $ci) err('Invalid dates. check_out must be after check_in.');
    if ($ci < new DateTime('today')) err('check_in cannot be in the past.');

    $rs = db()->prepare('SELECT * FROM rooms WHERE id=? AND hotel_id=? AND is_available=1');
    $rs->execute([$roomId,$hotelId]); $room=$rs->fetch();
    if (!$room) err('Room not found or unavailable.', 404);
    if ($guests > $room['max_guests']) err('Exceeds max guests ('.$room['max_guests'].') for this room.');

    $nights = (int)$ci->diff($co)->days;
    $total  = $nights * (float)$room['price_per_night'];

    try {
        db()->beginTransaction();
        $ref = null;
        if (in_array($payMethod,['gcash','maya']))  $ref=strtoupper($payMethod).'-'.strtoupper(substr(md5(uniqid()),0,10));
        elseif ($payMethod==='cash')                 $ref='CASH-'.strtoupper(substr(md5(uniqid()),0,8));
        elseif (isset($body['bank_ref']))            $ref=trim($body['bank_ref']);

        $bk=db()->prepare('INSERT INTO bookings (user_id,hotel_id,room_id,check_in,check_out,guests,total_nights,total_price,special_requests,status,payment_status,payment_method) VALUES (?,?,?,?,?,?,?,?,?,\'confirmed\',\'paid\',?)');
        $bk->execute([$user['id'],$hotelId,$roomId,$checkIn,$checkOut,$guests,$nights,$total,$special,$payMethod]);
        $bookingId=(int)db()->lastInsertId();

        db()->commit();
        ok(['booking_id'=>$bookingId,'total_nights'=>$nights,'total_price'=>$total,'currency'=>'PHP','status'=>'confirmed','reference'=>$ref], 201);
    } catch (Exception $e) {
        db()->rollBack();
        err('Could not create booking: '.$e->getMessage(), 500);
    }
}

// PUT /api/bookings/{id}/cancel
if ($resource === 'bookings' && $id !== null && ($parts[2]??'') === 'cancel' && $method === 'PUT') {
    $user = requireApiAuth();
    $stmt = db()->prepare('UPDATE bookings SET status=\'cancelled\' WHERE id=? AND user_id=? AND status=\'confirmed\'');
    $stmt->execute([$id,$user['id']]);
    if ($stmt->rowCount() === 0) err('Cannot cancel. Booking not found, not yours, or not confirmed.', 400);
    ok(['message'=>'Booking #'.$id.' cancelled successfully.']);
}

// ─────────────────────────────────────────────────────────────
// ADMIN-ONLY ENDPOINTS
// ─────────────────────────────────────────────────────────────

// POST /api/hotels
if ($resource === 'hotels' && $method === 'POST') {
    requireAdminApi();
    $b=getBody();
    $name=trim($b['name']??''); $locId=(int)($b['location_id']??0);
    $desc=trim($b['description']??''); $addr=trim($b['address']??'');
    $stars=max(1,min(5,(int)($b['stars']??3)));
    $rating=max(0,min(5,(float)($b['rating']??0)));
    $rev=(int)($b['review_count']??0);
    $ams=is_array($b['amenities']??null)?$b['amenities']:[];
    $thumb=trim($b['thumbnail']??'');
    $active=(int)($b['is_active']??1);
    if (!$name) err('name is required.');
    if (!$locId) err('location_id is required.');
    db()->prepare('INSERT INTO hotels (location_id,name,description,address,stars,rating,review_count,thumbnail,amenities,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$locId,$name,$desc,$addr,$stars,$rating,$rev,$thumb,json_encode($ams),$active]);
    ok(['hotel_id'=>(int)db()->lastInsertId(),'message'=>'Hotel created.'], 201);
}

// PUT /api/hotels/{id}
if ($resource === 'hotels' && $method === 'PUT' && $id !== null) {
    requireAdminApi();
    $b=getBody();
    $fields=[]; $params=[];
    if (isset($b['name']))         { $fields[]='name=?';         $params[]=$b['name']; }
    if (isset($b['description']))  { $fields[]='description=?';  $params[]=$b['description']; }
    if (isset($b['address']))      { $fields[]='address=?';      $params[]=$b['address']; }
    if (isset($b['stars']))        { $fields[]='stars=?';        $params[]=(int)$b['stars']; }
    if (isset($b['rating']))       { $fields[]='rating=?';       $params[]=(float)$b['rating']; }
    if (isset($b['is_active']))    { $fields[]='is_active=?';    $params[]=(int)$b['is_active']; }
    if (isset($b['thumbnail']))    { $fields[]='thumbnail=?';    $params[]=$b['thumbnail']; }
    if (isset($b['amenities']))    { $fields[]='amenities=?';    $params[]=json_encode($b['amenities']); }
    if (empty($fields)) err('No fields to update.');
    $params[]=$id;
    db()->prepare('UPDATE hotels SET '.implode(',',$fields).' WHERE id=?')->execute($params);
    ok(['message'=>'Hotel #'.$id.' updated.']);
}

// DELETE /api/hotels/{id}
if ($resource === 'hotels' && $method === 'DELETE' && $id !== null) {
    requireAdminApi();
    db()->prepare('DELETE FROM hotels WHERE id=?')->execute([$id]);
    ok(['message'=>'Hotel #'.$id.' deleted.']);
}

// GET /api/admin/bookings  — all bookings (admin)
if ($resource === 'admin' && ($parts[1]??'') === 'bookings' && $method === 'GET') {
    requireAdminApi();
    $filter=$_GET['status']??'';
    $where=$filter?'WHERE b.status='.db()->quote($filter):'';
    $rows=db()->query("SELECT b.*,u.first_name,u.last_name,h.name AS hotel_name,r.room_type FROM bookings b JOIN users u ON u.id=b.user_id JOIN hotels h ON h.id=b.hotel_id JOIN rooms r ON r.id=b.room_id $where ORDER BY b.created_at DESC LIMIT 500")->fetchAll();
    ok(['bookings'=>$rows,'count'=>count($rows)]);
}

// GET /api/admin/payments  — all payments (admin)
if ($resource === 'admin' && ($parts[1]??'') === 'payments' && $method === 'GET') {
    requireAdminApi();
    $rows=db()->query("SELECT b.id,b.user_id,b.total_price,b.payment_method,b.payment_status,b.created_at,u.email FROM bookings b JOIN users u ON u.id=b.user_id WHERE b.payment_status IN ('paid','refunded') ORDER BY b.created_at DESC LIMIT 500")->fetchAll();
    ok(['payments'=>$rows,'count'=>count($rows)]);
}

// GET /api/admin/stats (admin)
if ($resource === 'admin' && ($parts[1]??'') === 'stats' && $method === 'GET') {
    requireAdminApi();
    ok(['stats'=>[
        'total_users'    => (int)db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
        'total_hotels'   => (int)db()->query("SELECT COUNT(*) FROM hotels WHERE is_active=1")->fetchColumn(),
        'total_bookings' => (int)db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'total_revenue'  => (float)db()->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE payment_status='paid'")->fetchColumn(),
    ]]);
}

// 404 fallback
err('Unknown endpoint: '.$method.' /'.$uri, 404);
