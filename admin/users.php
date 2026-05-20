<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Manage Users';
$hotels = db()->query('SELECT id, name FROM hotels WHERE is_active=1 ORDER BY name')->fetchAll();

// UPDATE ROLE + HOTEL ASSIGNMENT
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_role') {
    verifyCsrf();
    $id      = (int)($_POST['user_id'] ?? 0);
    $role    = $_POST['role'] ?? 'user';
    $hotelId = (int)($_POST['hotel_id'] ?? 0) ?: null;
    if ($id !== $_SESSION['user_id'] && in_array($role, ['user','admin','staff'])) {
        if ($role === 'staff' && !$hotelId) {
            flashSet('error', 'Please select a hotel to assign this staff member to.');
        } else {
            $hid = ($role === 'staff') ? $hotelId : null;
            db()->prepare('UPDATE users SET role=?, hotel_id=? WHERE id=?')->execute([$role, $hid, $id]);
            flashSet('success', 'User updated to '.ucfirst($role).($hid ? ' (hotel assigned)' : '').'.');
        }
    } else {
        flashSet('error', 'Cannot change your own role.');
    }
    header('Location: users.php'); exit;
}
// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    verifyCsrf(); $id = (int)($_POST['user_id'] ?? 0);
    if ($id !== $_SESSION['user_id']) {
        db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flashSet('success', 'User deleted.');
    } else { flashSet('error', 'You cannot delete your own account.'); }
    header('Location: users.php'); exit;
}

$users = db()->query(
    'SELECT u.*, COUNT(b.id) bc, h.name hotel_name
     FROM users u
     LEFT JOIN bookings b ON b.user_id=u.id
     LEFT JOIN hotels h ON h.id=u.hotel_id
     GROUP BY u.id ORDER BY u.created_at DESC'
)->fetchAll();

$roleBadge = ['admin'=>'badge-coral','staff'=>'badge-navy','user'=>'badge-grey'];
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">👤 Users</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Assigned Hotel</th><th>Bookings</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone'] ?? '—') ?></td>
          <td><span class="badge <?= $roleBadge[$u['role']] ?? 'badge-grey' ?>"><?= ucfirst($u['role']) ?></span></td>
          <td><?= $u['hotel_name'] ? e($u['hotel_name']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $u['bc'] ?></td>
          <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
            <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center">
              <!-- Role + hotel assignment form -->
              <form method="POST" style="display:flex;gap:5px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
                <input type="hidden" name="action"  value="update_role">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <select name="role" id="role_<?= $u['id'] ?>" onchange="toggleHotelSel(<?= $u['id'] ?>)"
                        style="font-size:.78rem;padding:4px 7px;border:1px solid var(--border);border-radius:5px">
                  <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>User</option>
                  <option value="staff" <?= $u['role']==='staff'?'selected':'' ?>>Staff</option>
                  <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                </select>
                <select name="hotel_id" id="hotel_sel_<?= $u['id'] ?>"
                        style="font-size:.78rem;padding:4px 7px;border:1px solid var(--border);border-radius:5px;<?= $u['role']!=='staff'?'display:none':'' ?>">
                  <option value="">— Select hotel —</option>
                  <?php foreach ($hotels as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= $u['hotel_id']==$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">Save</button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"   value="<?= csrf() ?>">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Delete user <?= e($u['email']) ?>? All their bookings will be removed.">Delete</button>
              </form>
            </div>
            <?php else: ?><span class="text-sm text-muted">(You)</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
</div></div>
<script>
function toggleHotelSel(id) {
  const role = document.getElementById('role_'+id).value;
  const sel  = document.getElementById('hotel_sel_'+id);
  sel.style.display = role === 'staff' ? '' : 'none';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
