<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Manage Hotels';
$locations = db()->query('SELECT * FROM locations ORDER BY city')->fetchAll();
$errors = []; $editHotel = null;

// CREATE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create') {
    verifyCsrf();
    $name=$_POST['name']??''; $locId=(int)($_POST['location_id']??0);
    $desc=$_POST['description']??''; $addr=$_POST['address']??'';
    $stars=max(1,min(5,(int)($_POST['stars']??3)));
    $rating=max(0,min(5,(float)($_POST['rating']??0)));
    $rev=max(0,(int)($_POST['review_count']??0));
    $ams=array_filter(array_map('trim',explode(',', $_POST['amenities']??'')));
    $thumb=trim($_POST['thumbnail']??'');
    $active=isset($_POST['is_active'])?1:0;
    if (!$name) $errors[]='Hotel name is required.';
    if (!$locId) $errors[]='Please select a location.';
    if (empty($errors)) {
        db()->prepare('INSERT INTO hotels (location_id,name,description,address,stars,rating,review_count,thumbnail,amenities,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$locId,$name,$desc,$addr,$stars,$rating,$rev,$thumb,json_encode(array_values($ams)),$active]);
        flashSet('success','Hotel "'.$name.'" created.');
        header('Location: hotels.php'); exit;
    }
}
// UPDATE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update') {
    verifyCsrf();
    $id=(int)($_POST['hotel_id']??0);
    $name=$_POST['name']??''; $locId=(int)($_POST['location_id']??0);
    $desc=$_POST['description']??''; $addr=$_POST['address']??'';
    $stars=max(1,min(5,(int)($_POST['stars']??3)));
    $rating=max(0,min(5,(float)($_POST['rating']??0)));
    $rev=max(0,(int)($_POST['review_count']??0));
    $ams=array_filter(array_map('trim',explode(',', $_POST['amenities']??'')));
    $thumb=trim($_POST['thumbnail']??'');
    $active=isset($_POST['is_active'])?1:0;
    if (!$name) $errors[]='Hotel name is required.';
    if (empty($errors)) {
        db()->prepare('UPDATE hotels SET location_id=?,name=?,description=?,address=?,stars=?,rating=?,review_count=?,thumbnail=?,amenities=?,is_active=? WHERE id=?')->execute([$locId,$name,$desc,$addr,$stars,$rating,$rev,$thumb,json_encode(array_values($ams)),$active,$id]);
        flashSet('success','Hotel updated.'); header('Location: hotels.php'); exit;
    }
}
// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    verifyCsrf(); $id=(int)($_POST['hotel_id']??0);
    db()->prepare('DELETE FROM hotels WHERE id=?')->execute([$id]);
    flashSet('success','Hotel deleted.'); header('Location: hotels.php'); exit;
}
// TOGGLE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='toggle') {
    verifyCsrf(); $id=(int)($_POST['hotel_id']??0);
    db()->prepare('UPDATE hotels SET is_active=NOT is_active WHERE id=?')->execute([$id]);
    header('Location: hotels.php'); exit;
}
// Load edit
if (isset($_GET['edit'])) {
    $s=db()->prepare('SELECT * FROM hotels WHERE id=?'); $s->execute([(int)$_GET['edit']]); $editHotel=$s->fetch();
}
$hotels = db()->query('SELECT h.*,l.city,(SELECT COUNT(*) FROM rooms r WHERE r.hotel_id=h.id) rc,(SELECT COUNT(*) FROM bookings b WHERE b.hotel_id=h.id) bc FROM hotels h JOIN locations l ON l.id=h.location_id ORDER BY h.id DESC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="flex jb ac mb-3" style="flex-wrap:wrap;gap:10px">
    <div class="admin-title" style="margin:0">🏨 Hotels</div>
    <a href="hotels.php?add=1" class="btn btn-primary btn-sm">+ Add hotel</a>
  </div>
  <?php foreach($errors as $er): ?><div class="err-box">⚠ <?= e($er) ?></div><?php endforeach; ?>

  <?php if (isset($_GET['add'])||$editHotel): ?>
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:26px;margin-bottom:24px;box-shadow:var(--shadow-sm)">
    <h3 style="font-family:var(--font-body);font-weight:600;color:var(--navy);margin-bottom:18px"><?= $editHotel?'Edit: '.e($editHotel['name']):'Add new hotel' ?></h3>
    <form method="POST">
      <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
      <input type="hidden" name="action"   value="<?= $editHotel?'update':'create' ?>">
      <?php if ($editHotel): ?><input type="hidden" name="hotel_id" value="<?= $editHotel['id'] ?>"><?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group"><label>Hotel name *</label><input type="text" name="name" value="<?= e($editHotel['name']??'') ?>" required></div>
        <div class="form-group"><label>Location *</label>
          <select name="location_id" required><option value="">Select...</option>
            <?php foreach($locations as $l): ?><option value="<?= $l['id'] ?>" <?= ($editHotel['location_id']??'')==$l['id']?'selected':'' ?>><?= e($l['city']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="grid-column:1/-1"><label>Description</label><textarea name="description"><?= e($editHotel['description']??'') ?></textarea></div>
        <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= e($editHotel['address']??'') ?>"></div>
        <div class="form-group"><label>Thumbnail path</label><input type="text" name="thumbnail" value="<?= e($editHotel['thumbnail']??'') ?>" placeholder="assets/images/hotels/hotel_1.jpg"><div class="form-hint">Relative path from project root</div></div>
        <div class="form-group"><label>Stars (1–5)</label>
          <select name="stars"><?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= ($editHotel['stars']??3)==$i?'selected':'' ?>><?= $i ?> Star<?= $i>1?'s':'' ?></option><?php endfor; ?></select>
        </div>
        <div class="form-group"><label>Rating (0.00–5.00)</label><input type="number" name="rating" step="0.01" min="0" max="5" value="<?= $editHotel['rating']??'0.00' ?>"></div>
        <div class="form-group"><label>Review count</label><input type="number" name="review_count" min="0" value="<?= $editHotel['review_count']??'0' ?>"></div>
        <div class="form-group" style="grid-column:1/-1"><label>Amenities <span style="font-weight:400;color:var(--text3)">(comma-separated)</span></label><input type="text" name="amenities" value="<?= e(implode(', ',json_decode($editHotel['amenities']??'[]',true)?:[])) ?>" placeholder="Free WiFi, Pool, Spa, Gym"></div>
        <div class="form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" <?= ($editHotel['is_active']??1)?'checked':'' ?> style="width:auto"> Active (visible on site)</label></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="submit" class="btn btn-primary"><?= $editHotel?'Save changes':'Create hotel' ?></button>
        <a href="hotels.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Hotel</th><th>Location</th><th>Stars</th><th>Rating</th><th>Rooms</th><th>Bookings</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($hotels as $h): ?>
        <tr>
          <td><?= $h['id'] ?></td>
          <td><strong><?= e($h['name']) ?></strong></td>
          <td><?= e($h['city']) ?></td>
          <td><?= str_repeat('★',$h['stars']) ?></td>
          <td>⭐ <?= number_format($h['rating'],2) ?></td>
          <td><?= $h['rc'] ?></td>
          <td><?= $h['bc'] ?></td>
          <td><span class="badge <?= $h['is_active']?'badge-green':'badge-grey' ?>"><?= $h['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <a href="hotels.php?edit=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <a href="rooms.php?hotel_id=<?= $h['id'] ?>" class="btn btn-outline btn-sm">Rooms</a>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
                <input type="hidden" name="action"   value="toggle">
                <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
                <button type="submit" class="btn btn-sm" style="background:var(--off);color:var(--text2);border:1px solid var(--border)"><?= $h['is_active']?'Deactivate':'Activate' ?></button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="hotel_id" value="<?= $h['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete '<?= e($h['name']) ?>'? All its rooms will be deleted too.">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
