<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Manage Rooms';
$hotels  = db()->query('SELECT id,name FROM hotels WHERE is_active=1 ORDER BY name')->fetchAll();
$errors  = []; $editRoom = null;
$fHotel  = (int)($_GET['hotel_id'] ?? 0);

// CREATE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create') {
    verifyCsrf();
    $hid=(int)($_POST['hotel_id']??0); $type=trim($_POST['room_type']??'');
    $desc=trim($_POST['description']??''); $maxG=max(1,(int)($_POST['max_guests']??2));
    $price=(float)($_POST['price_per_night']??0); $thumb=trim($_POST['thumbnail']??'');
    $ams=array_filter(array_map('trim',explode(',', $_POST['amenities']??'')));
    $avail=isset($_POST['is_available'])?1:0;
    if (!$hid)    $errors[]='Please select a hotel.';
    if (!$type)   $errors[]='Room type is required.';
    if ($price<=0) $errors[]='Price must be greater than 0.';
    if (empty($errors)) {
        db()->prepare('INSERT INTO rooms (hotel_id,room_type,description,max_guests,price_per_night,thumbnail,amenities,is_available) VALUES (?,?,?,?,?,?,?,?)')->execute([$hid,$type,$desc,$maxG,$price,$thumb,json_encode(array_values($ams)),$avail]);
        flashSet('success','Room created.'); header('Location: rooms.php'.($fHotel?'?hotel_id='.$fHotel:'')); exit;
    }
}
// UPDATE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    verifyCsrf();
    $id=(int)($_POST['room_id']??0); $hid=(int)($_POST['hotel_id']??0);
    $type=trim($_POST['room_type']??''); $desc=trim($_POST['description']??'');
    $maxG=max(1,(int)($_POST['max_guests']??2)); $price=(float)($_POST['price_per_night']??0);
    $thumb=trim($_POST['thumbnail']??'');
    $ams=array_filter(array_map('trim',explode(',', $_POST['amenities']??'')));
    $avail=isset($_POST['is_available'])?1:0;
    if (!$type)    $errors[]='Room type is required.';
    if ($price<=0) $errors[]='Price must be greater than 0.';
    if (empty($errors)) {
        db()->prepare('UPDATE rooms SET hotel_id=?,room_type=?,description=?,max_guests=?,price_per_night=?,thumbnail=?,amenities=?,is_available=? WHERE id=?')->execute([$hid,$type,$desc,$maxG,$price,$thumb,json_encode(array_values($ams)),$avail,$id]);
        flashSet('success','Room updated.'); header('Location: rooms.php'.($fHotel?'?hotel_id='.$fHotel:'')); exit;
    }
}
// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    verifyCsrf(); $id=(int)($_POST['room_id']??0);
    db()->prepare('DELETE FROM rooms WHERE id=?')->execute([$id]);
    flashSet('success','Room deleted.'); header('Location: rooms.php'.($fHotel?'?hotel_id='.$fHotel:'')); exit;
}
// Load edit
if (isset($_GET['edit'])) {
    $s=db()->prepare('SELECT * FROM rooms WHERE id=?'); $s->execute([(int)$_GET['edit']]); $editRoom=$s->fetch();
    if ($editRoom) $fHotel=(int)$editRoom['hotel_id'];
}
$sql='SELECT r.*,h.name AS hotel_name FROM rooms r JOIN hotels h ON h.id=r.hotel_id';
$rp=[];
if ($fHotel) { $sql.=' WHERE r.hotel_id=?'; $rp[]=$fHotel; }
$sql.=' ORDER BY r.hotel_id,r.price_per_night';
$rs=db()->prepare($sql); $rs->execute($rp); $rooms=$rs->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="flex jb ac mb-3" style="flex-wrap:wrap;gap:10px">
    <div class="admin-title" style="margin:0">🛏 Rooms</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <form method="GET">
        <select name="hotel_id" onchange="this.form.submit()" style="padding:7px 12px;font-size:.85rem;border-radius:6px;border:1.5px solid var(--border)">
          <option value="">All hotels</option>
          <?php foreach($hotels as $h): ?><option value="<?= $h['id'] ?>" <?= $fHotel===$h['id']?'selected':'' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
        </select>
      </form>
      <a href="rooms.php?add=1<?= $fHotel?'&hotel_id='.$fHotel:'' ?>" class="btn btn-primary btn-sm">+ Add room</a>
    </div>
  </div>
  <?php foreach($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

  <?php if (isset($_GET['add'])||$editRoom): ?>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:26px;margin-bottom:24px;box-shadow:var(--shadow-sm)">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:18px"><?= $editRoom?'Edit: '.e($editRoom['room_type']):'Add new room' ?></h3>
    <form method="POST">
      <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action"  value="<?= $editRoom?'update':'create' ?>">
      <?php if ($editRoom): ?><input type="hidden" name="room_id" value="<?= $editRoom['id'] ?>"><?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group"><label>Hotel *</label>
          <select name="hotel_id" required><option value="">Select hotel...</option>
            <?php foreach($hotels as $h): ?><option value="<?= $h['id'] ?>" <?= ($editRoom['hotel_id']??$fHotel)==$h['id']?'selected':'' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Room type *</label><input type="text" name="room_type" value="<?= e($editRoom['room_type']??'') ?>" required placeholder="e.g. Deluxe Ocean View"></div>
        <div class="form-group" style="grid-column:1/-1"><label>Description</label><textarea name="description"><?= e($editRoom['description']??'') ?></textarea></div>
        <div class="form-group"><label>Price per night (₱) *</label><input type="number" name="price_per_night" step="0.01" min="1" value="<?= $editRoom['price_per_night']??'' ?>" required></div>
        <div class="form-group"><label>Max guests *</label><input type="number" name="max_guests" min="1" max="20" value="<?= $editRoom['max_guests']??2 ?>" required></div>
        <div class="form-group" style="grid-column:1/-1"><label>Thumbnail path</label><input type="text" name="thumbnail" value="<?= e($editRoom['thumbnail']??'') ?>" placeholder="assets/images/rooms/room_1_1.jpg"><div class="form-hint">Relative path from project root</div></div>
        <div class="form-group" style="grid-column:1/-1"><label>Amenities <span style="font-weight:400;color:var(--text3)">(comma-separated)</span></label><input type="text" name="amenities" value="<?= e(implode(', ',json_decode($editRoom['amenities']??'[]',true)?:[])) ?>" placeholder="King Bed, Balcony, Mini Bar, Rain Shower"></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_available" value="1" <?= ($editRoom['is_available']??1)?'checked':'' ?> style="width:auto"> Available for booking</label></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="submit" class="btn btn-primary"><?= $editRoom?'Save changes':'Create room' ?></button>
        <a href="rooms.php<?= $fHotel?'?hotel_id='.$fHotel:'' ?>" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Hotel</th><th>Room type</th><th>Max guests</th><th>Price/night</th><th>Available</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($rooms)): ?>
          <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--text3)">No rooms found. <a href="rooms.php?add=1">Add one?</a></td></tr>
        <?php else: foreach($rooms as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= e($r['hotel_name']) ?></td>
          <td><strong><?= e($r['room_type']) ?></strong></td>
          <td>👤 <?= $r['max_guests'] ?></td>
          <td><strong><?= money($r['price_per_night']) ?></strong></td>
          <td><span class="badge <?= $r['is_available']?'badge-green':'badge-red' ?>"><?= $r['is_available']?'Yes':'No' ?></span></td>
          <td>
            <div style="display:flex;gap:5px">
              <a href="rooms.php?edit=<?= $r['id'] ?><?= $fHotel?'&hotel_id='.$fHotel:'' ?>" class="btn btn-outline btn-sm">Edit</a>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete '<?= e($r['room_type']) ?>'?">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
