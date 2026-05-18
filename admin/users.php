<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$pageTitle = 'Manage Users';

// UPDATE ROLE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_role') {
    verifyCsrf();
    $id=(int)($_POST['user_id']??0); $role=$_POST['role']??'user';
    if ($id!==$_SESSION['user_id'] && in_array($role,['user','admin'])) {
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$id]);
        flashSet('success','User role updated.');
    } else { flashSet('error','Cannot change your own role.'); }
    header('Location: users.php'); exit;
}
// DELETE
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='delete') {
    verifyCsrf(); $id=(int)($_POST['user_id']??0);
    if ($id!==$_SESSION['user_id']) {
        db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        flashSet('success','User deleted.');
    } else { flashSet('error','You cannot delete your own account.'); }
    header('Location: users.php'); exit;
}

$users=db()->query('SELECT u.*,COUNT(b.id) bc FROM users u LEFT JOIN bookings b ON b.user_id=u.id GROUP BY u.id ORDER BY u.created_at DESC')->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="pt-nav"><div class="admin-wrap">
<?php include __DIR__ . '/_sidebar.php'; ?>
<main class="admin-main">
  <div class="admin-title">👤 Users</div>
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Bookings</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['phone']??'—') ?></td>
          <td><span class="badge <?= $u['role']==='admin'?'badge-coral':'badge-grey' ?>"><?= ucfirst($u['role']) ?></span></td>
          <td><?= $u['bc'] ?></td>
          <td><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
          <td>
            <?php if ($u['id']!==$_SESSION['user_id']): ?>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
                <input type="hidden" name="action"   value="update_role">
                <input type="hidden" name="user_id"  value="<?= $u['id'] ?>">
                <input type="hidden" name="role"     value="<?= $u['role']==='admin'?'user':'admin' ?>">
                <button type="submit" class="btn btn-outline btn-sm" data-confirm="Change role to <?= $u['role']==='admin'?'User':'Admin' ?>?"><?= $u['role']==='admin'?'Make User':'Make Admin' ?></button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="_csrf"    value="<?= csrf() ?>">
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="user_id"  value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete user <?= e($u['email']) ?>? All their bookings will be removed.">Delete</button>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
