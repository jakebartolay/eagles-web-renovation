<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

/* ================= LOGIN SESSION CHECK ================= */
if (
    !isset($_SESSION['admin_logged_in']) ||
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role_id'])
) {
    header("Location: admin-login");
    exit;
}

$error = '';

/* ================= HANDLE ADD USER ================= */
if (isset($_POST['add_user'])) {
    $name      = trim($_POST['name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = (string)($_POST['password'] ?? '');
    $role_id   = intval($_POST['role_id'] ?? 0);

    if ($name === '' || $username === '' || $password === '' || $role_id === 0) {
        $error = "Please complete all fields.";
    } elseif ($_SESSION['role_id'] !== 1 && $role_id == 1) {
        $error = "Access denied.";
    } elseif ($_SESSION['role_id'] !== 1 && $role_id > 3) {
        $error = "Access denied.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists!";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO users (name, username, password_hash, role_id)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("sssi", $name, $username, $password_hash, $role_id);
            $stmt->execute();
            header("Location: user-management");
            exit;
        }
    }
}

/* ================= HANDLE UPDATE USER (EDIT MODAL) ================= */
if (isset($_POST['update_user'])) {
    $user_id   = intval($_POST['user_id'] ?? 0);
    $name      = trim($_POST['edit_name'] ?? '');
    $username  = trim($_POST['edit_username'] ?? '');
    $password  = (string)($_POST['edit_password'] ?? '');
    $new_role  = isset($_POST['edit_role_id']) ? intval($_POST['edit_role_id']) : null;

    if ($user_id <= 0) {
        $error = "Invalid user.";
    } else {
        // Fetch target user role (for permission checks)
        $check = $conn->prepare("SELECT id, role_id FROM users WHERE id=?");
	$check->bind_result($tid, $trole);
	$check->fetch();
	$target = $tid ? ['id' => $tid, 'role_id' => $trole] : null;

        if (!$target) {
            $error = "User not found.";
        } else {
            $targetRole = (int)$target['role_id'];

            // Permission:
            // - Only Super Admin (1) or Admin (2) can edit users
            // - Admin (2) cannot edit Super Admin (1)
            if (!in_array((int)$_SESSION['role_id'], [1, 2], true)) {
                $error = "Access denied.";
            } elseif ((int)$_SESSION['role_id'] === 2 && $targetRole === 1) {
                $error = "Access denied.";
            } elseif ($name === '' || $username === '') {
                $error = "Name and Username are required.";
            } else {
                // Username uniqueness (exclude current user)
                $u = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>?");
                $u->bind_param("si", $username, $user_id);
                $u->execute();
                $u->store_result();
                if ($u->num_rows > 0) {
                    $error = "Username already exists!";
                } else {
                    // Role edit rules:
                    // - Only Super Admin can change roles
                    // - Nobody can change their own role
                    if ($new_role !== null) {
                        if ((int)$_SESSION['role_id'] !== 1) {
                            $new_role = null; // ignore role changes from non-superadmin
                        } elseif ($user_id === (int)$_SESSION['id']) {
                            $new_role = null; // disallow self role change
                        }
                    }

                    // Build update query dynamically (password optional, role optional)
                    $set = [];
                    $types = "";
                    $vals = [];

                    $set[] = "name=?";
                    $types .= "s";
                    $vals[] = $name;

                    $set[] = "username=?";
                    $types .= "s";
                    $vals[] = $username;

                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $set[] = "password_hash=?";
                        $types .= "s";
                        $vals[] = $hash;
                    }

                    if ($new_role !== null) {
                        // prevent role 2 assigning super admin? (super admin can set any role)
                        $set[] = "role_id=?";
                        $types .= "i";
                        $vals[] = $new_role;
                    }

                    $types .= "i";
                    $vals[] = $user_id;

                    $sql = "UPDATE users SET " . implode(", ", $set) . " WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$vals);
                    $stmt->execute();

                    header("Location: user-management");
                    exit;
                }
            }
        }
    }
}

/* ================= HANDLE ROLE CHANGE (KEEPING YOUR ORIGINAL) ================= */
if (isset($_POST['change_role'], $_POST['role_id'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);

    if ($_SESSION['role_id'] === 1 && $user_id !== $_SESSION['id']) {
        $stmt = $conn->prepare("UPDATE users SET role_id=? WHERE id=?");
        $stmt->bind_param("ii", $role_id, $user_id);
        $stmt->execute();
    }

    header("Location: user-management");
    exit;
}

/* ================= HANDLE DELETE USER ================= */
if (isset($_POST['delete_user'])) {
    $delete_id = intval($_POST['user_id']);

    if ($delete_id !== $_SESSION['id']) {

        if ($_SESSION['role_id'] === 2) {
            $check = $conn->prepare("SELECT role_id FROM users WHERE id=?");
            $check->bind_param("i", $delete_id);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();

            if ($res && (int)$res['role_id'] === 1) {
                die("Access denied.");
            }
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
    }

    header("Location: user-management");
    exit;
}

/* ================= FETCH USERS ================= */
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$totalCount = $result->num_rows;

function roleLabel(int $roleId): string {
    return match ($roleId) {
        1 => 'Super Admin',
        2 => 'Admin',
        3 => 'Maintenance',
        default => 'User',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
<link rel="icon" type="image/png" href="/../static/eagles.png">
<link rel="stylesheet" href="../admin styles/user_man.css">
<link rel="stylesheet" href="../admin styles/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* Small modal polish (keeps your existing css intact) */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:9999; }
.modal.show { display:flex; align-items:center; justify-content:center; padding:18px; }
.modal-content { width:min(520px, 100%); background:#fff; border-radius:16px; padding:18px; box-shadow:0 20px 60px rgba(0,0,0,.2); position:relative; }
.close-btn { position:absolute; right:14px; top:10px; font-size:26px; cursor:pointer; user-select:none; }
.modern-form { display:grid; gap:10px; margin-top:10px; }
.modern-form input, .modern-form select { width:100%; padding:12px 12px; border-radius:12px; border:1px solid rgba(0,0,0,.12); outline:none; }
.modern-form button { padding:12px; border:0; border-radius:12px; cursor:pointer; background:#111827; color:#fff; font-weight:600; }
.error { margin-top:12px; padding:10px 12px; border-radius:12px; background:#fee2e2; color:#7f1d1d; border:1px solid #fecaca; }
.action-btn.edit-btn { background:transparent; border:1px solid rgba(0,0,0,.12); }
.action-btn.edit-btn i { color:#111827; }
</style>
</head>
<body>

<button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<?php include 'sidebar.php'; ?>

<div class="main-content">
<h1>User Management</h1>

<div class="top-buttons">
<?php if($_SESSION['role_id'] === 1 || $_SESSION['role_id'] === 2): ?>
<button class="add-btn" id="open-add-modal">
    <i class="fas fa-plus"></i> Add User
</button>
<?php endif; ?>
</div>

<?php if($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($totalCount === 0): ?>
<div class="content-area">
    <div class="empty-state">
        <i class="fas fa-user-xmark"></i>
        <h3>No users yet</h3>
        <p>Click Add Userù to register your first admin or user.</p>
    </div>
</div>
<?php else: ?>

<table id="users-table">
<thead>
<tr>
<th>Name</th>
<th>Username</th>
<th>Role</th>
<?php if($_SESSION['role_id'] === 1 || $_SESSION['role_id'] === 2): ?>
<th>Actions</th>
<?php endif; ?>
</tr>
</thead>
<tbody>

<?php while($row = $result->fetch_assoc()): ?>
<?php
    $canEdit = in_array((int)$_SESSION['role_id'], [1,2], true)
               && !((int)$_SESSION['role_id'] === 2 && (int)$row['role_id'] === 1); // admin can't edit super admin
?>
<tr>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= htmlspecialchars($row['username']) ?></td>
<td><?= htmlspecialchars(roleLabel((int)$row['role_id'])) ?></td>

<?php if($_SESSION['role_id'] === 1 || $_SESSION['role_id'] === 2): ?>
<td>
<div class="action-wrapper">

<?php if ($canEdit): ?>
<button
  type="button"
  class="action-btn edit-btn"
  data-edit="1"
  data-id="<?= (int)$row['id'] ?>"
  data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
  data-username="<?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?>"
  data-role="<?= (int)$row['role_id'] ?>"
  title="Edit"
>
  <i class="fas fa-pen"></i>
</button>
<?php endif; ?>

<?php if($_SESSION['id'] !== $row['id']): ?>
<form method="POST" class="delete-form"
onsubmit="return confirm('Are you sure you want to delete this user?');">
<input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
<button type="submit" name="delete_user" class="action-btn delete-btn" title="Delete">
<i class="fas fa-trash"></i>
</button>
</form>
<?php endif; ?>

</div>
</td>
<?php endif; ?>

</tr>
<?php endwhile; ?>

</tbody>
</table>
<?php endif; ?>
</div>

<!-- ADD USER MODAL -->
<?php if($_SESSION['role_id'] === 1 || $_SESSION['role_id'] === 2): ?>
<div class="modal" id="user-modal">
  <div class="modal-content modern-modal">
    <span class="close-btn" id="close-user">&times;</span>
    <h2>Add User</h2>

    <form method="POST" class="modern-form">
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="text" name="username" placeholder="Username" required>
      <input type="password" name="password" placeholder="Password" required>

      <select name="role_id" required>
        <?php if($_SESSION['role_id'] === 1): ?>
          <option value="1">Super Admin</option>
          <option value="2">Admin</option>
          <option value="3">Maintenance</option>
          <option value="4">User</option>
        <?php else: ?>
          <option value="2">Admin</option>
          <option value="3">Maintenance</option>
        <?php endif; ?>
      </select>

      <button type="submit" name="add_user">Add User</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- EDIT USER MODAL -->
<?php if($_SESSION['role_id'] === 1 || $_SESSION['role_id'] === 2): ?>
<div class="modal" id="edit-modal">
  <div class="modal-content modern-modal">
    <span class="close-btn" id="close-edit">&times;</span>
    <h2>Edit User</h2>

    <form method="POST" class="modern-form" id="edit-form">
      <input type="hidden" name="user_id" id="edit_user_id">
      <input type="text" name="edit_name" id="edit_name" placeholder="Full Name" required>
      <input type="text" name="edit_username" id="edit_username" placeholder="Username" required>

      <input type="password" name="edit_password" id="edit_password" placeholder="New Password (leave blank to keep current)">

      <?php if ((int)$_SESSION['role_id'] === 1): ?>
        <select name="edit_role_id" id="edit_role_id">
          <option value="1">Super Admin</option>
          <option value="2">Admin</option>
          <option value="3">Maintenance</option>
          <option value="4">User</option>
        </select>
        <div style="font-size:12px; color: rgba(0,0,0,.6); margin-top:-4px;">
          Note: You cannot change your own role.
        </div>
      <?php endif; ?>

      <button type="submit" name="update_user">Save Changes</button>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
/* ===== Add modal ===== */
const addModal = document.getElementById("user-modal");
document.getElementById("open-add-modal")?.addEventListener('click', () => addModal?.classList.add("show"));
document.getElementById("close-user")?.addEventListener('click', () => addModal?.classList.remove("show"));

/* ===== Edit modal ===== */
const editModal = document.getElementById("edit-modal");
const closeEdit = document.getElementById("close-edit");

const editUserId = document.getElementById("edit_user_id");
const editName = document.getElementById("edit_name");
const editUsername = document.getElementById("edit_username");
const editPassword = document.getElementById("edit_password");
const editRole = document.getElementById("edit_role_id");

document.querySelectorAll('[data-edit="1"]').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('data-id') || '';
    const name = btn.getAttribute('data-name') || '';
    const username = btn.getAttribute('data-username') || '';
    const role = btn.getAttribute('data-role') || '';

    editUserId.value = id;
    editName.value = name;
    editUsername.value = username;
    if (editPassword) editPassword.value = '';
    if (editRole) editRole.value = role;

    editModal.classList.add('show');
  });
});

closeEdit?.addEventListener('click', () => editModal.classList.remove('show'));

document.querySelectorAll(".modal").forEach(m => {
  m.addEventListener("click", (e) => {
    // do nothing on overlay click
    // (modal will only close via X button, Cancel button, or Esc if you keep it)
  });
});


/* ===== Sidebar toggle ===== */
const sidebar = document.querySelector('.sidebar');
document.querySelector('.sidebar-toggle')?.addEventListener('click', () => sidebar?.classList.toggle('show'));
</script>

</body>
</html>