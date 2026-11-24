<?php
require_once __DIR__ . '/includes/auth.php';
$user = requireRole(['admin']);
$db = getDBConnection();
$msg = '';
$err = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add_user'
) {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = strtolower(trim($_POST['role'] ?? ''));
    if ($fullName === '') {
        $err = 'Please enter the full name.';
    } elseif ($username === '') {
        $err = 'Please enter the username.';
    } elseif ($password === '') {
        $err = 'Please enter the password.';
    } elseif (strlen($password) < 6) {
        $err = 'Password must contain at least 6 characters.';
    } elseif (!in_array($role, ['admin', 'pharmacist', 'staff'], true)) {
        $err = 'Please select a valid role.';
    } else {
        try {
            $stmt = $db->prepare("
                SELECT id
                FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $existingUser = $stmt->fetch();
            if ($existingUser) {
                $err = 'Username "' .
                       htmlspecialchars($username) .
                       '" already exists.';

            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
                $cols = array_column($db->query("PRAGMA table_info(users)")->fetchAll(), 'name');
                if (in_array('fullname', $cols, true)) {
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, fullname) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashedPassword, $role, $fullName, $fullName]);
                } else {
                    $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashedPassword, $role, $fullName]);
                }
                $newUserId = $db->lastInsertId();
                $verify = $db->prepare("
                    SELECT id, username, role, full_name
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");
                $verify->execute([$newUserId]);
                $newUser = $verify->fetch();
                if ($newUser) {
                    header(
                        'Location: users.php?success=' .
                        urlencode(
                            'User "' .
                            $username .
                            '" added successfully.'
                        )
                    );
                    exit;
                } else {
                    $err = 'User could not be verified after insertion.';
                }
            }
        } catch (PDOException $e) {
            $err = 'Database error: ' . $e->getMessage();
        }
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_user'
) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $currentUserId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        $err = 'Invalid user selected.';
    } elseif ($userId === $currentUserId) {
        $err = 'You cannot delete your own account.';
    } else {
        try {
            $stmt = $db->prepare("
                DELETE FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                header(
                    'Location: users.php?success=' .
                    urlencode('User deleted successfully.')
                );
                exit;
            } else {
                $err = 'User not found.';
            }
        } catch (PDOException $e) {
            $err = 'Database error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) {
    $msg = $_GET['success'];
}

try {
    $stmt = $db->query("
        SELECT
            id,
            username,
            role,
            full_name,
            created_at
        FROM users
        ORDER BY id DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $err = 'Unable to load users: ' .
           $e->getMessage();
}
require_once __DIR__ . '/includes/header.php'; ?>

<style>
.users-page {
    max-width: 1200px;
    margin: 0 auto;
}
.users-title {
    margin-bottom: 25px;
}
.users-title h1 {
    margin: 0 0 5px;
}
.users-title p {
    margin: 0;
    color: #fdfdfd;
}
.users-card {
    background: #ffffff;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(0,0,0,.08);
    margin-bottom: 25px;
}
.users-card h2 {
    margin-top: 0;
    margin-bottom: 20px;
}
.user-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}
.user-field label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
}
.user-field input,
.user-field select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    box-sizing: border-box;
}
.user-add-btn {
    margin-top: 20px;
    padding: 11px 20px;
    border: none;
    border-radius: 8px;
    background: #1976d2;
    color: white;
    font-weight: bold;
    cursor: pointer;
}
.user-add-btn:hover {
    background: #125ca8;
}
.users-table {
    width: 100%;
    border-collapse: collapse;
}
.users-table th,
.users-table td {
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.users-table th {
    background: #f8fafc;
}
.role-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
}
.role-admin {
    background: #dbeafe;
    color: #1d4ed8;
}
.role-pharmacist {
    background: #dcfce7;
    color: #15803d;
}
.role-staff {
    background: #ffedd5;
    color: #c2410c;
}
.delete-btn {
    border: none;
    padding: 7px 12px;
    border-radius: 6px;
    background: #dc2626;
    color: white;
    cursor: pointer;
}
.current-user {
    color: #64748b;
    font-size: 13px;
}
.success-message {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #dcfce7;
    border: 1px solid #86efac;
    color: #166534;
}
.error-message {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}
@media (max-width: 700px) {
    .user-form-grid {
        grid-template-columns: 1fr;
    }
    .users-table {
        font-size: 13px;
    }

}
</style>
<div class="users-page">
    <div class="users-title">
        <h1><i class="ri-team-line"></i>User Management</h1>
        <p>Administrator can add and manage system users. </p>
    </div>
    <?php if ($msg !== ''): ?>
        <div class="success-message">
            <i class="ri-checkbox-circle-line"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($err !== ''): ?>
        <div class="error-message">
            <i class="ri-error-warning-line"></i>
            <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>
    <div class="users-card">
        <h2> <i class="ri-user-add-line"></i>
            Add New User
        </h2>
        <form method="POST">
            <input
                type="hidden"
                name="action"
                value="add_user"
            >
            <div class="user-form-grid">
                <div class="user-field">
                    <label>Full Name</label>
                    <input
                        type="text"
                        name="full_name"
                        placeholder="Enter full name"
                        required >
                </div>
                <div class="user-field">
                    <label>Username</label>
                    <input
                        type="text"
                        name="username"
                        placeholder="Enter username"
                        required >
                </div>
                <div class="user-field">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Minimum 6 characters"
                        minlength="6"
                        required >
                </div>
                <div class="user-field">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Administrator</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="user-add-btn" >
                <i class="ri-user-add-line"></i>
                Add User
            </button>
        </form>
    </div>
    <div class="users-card">
        <h2>
            <i class="ri-group-line"></i>
            Existing Users
        </h2>
        <div style="overflow-x:auto;">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;" > No users found. </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $role = strtolower(
                            $u['role'] ?? 'staff'
                        );
                        if ($role === 'admin') {
                            $roleText = 'Administrator';
                        } elseif ($role === 'pharmacist') {
                            $roleText = 'Pharmacist';
                        } else { $roleText = 'Staff';}
                        ?>
                        <tr>
                            <td>
                                <?= (int)$u['id'] ?>
                            </td>
                            <td>
                                <?= htmlspecialchars( $u['full_name'] ?? '' ) ?>
                            </td>
                            <td>
                                <strong>
                                    <?= htmlspecialchars( $u['username'] ) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="role-badge role-<?= htmlspecialchars($role) ?>">
                                    <?= htmlspecialchars($roleText) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars( $u['created_at'] ?? '') ?>
                            </td>
                            <td>
                                <?php
                                $currentUserId =
                                    (int)($user['id'] ?? 0);
                                ?>
                                <?php if (
                                    (int)$u['id'] !==
                                    $currentUserId
                                ): ?>
                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this user?'); " >
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit"class="delete-btn">
                                            <i class="ri-delete-bin-line"></i>Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="current-user">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>