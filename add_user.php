 <?php
session_start();
require_once("config/db.php");
require_once("layout.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Safe session read
$sessionUser = $_SESSION['user'];
if (is_array($sessionUser)) {
    $username = $sessionUser['username'];
    $role     = $sessionUser['role'];
} else {
    $username = (string)$sessionUser;
    $dbUser   = findOne($manager, $dbName, $usersCollection, ['username' => $username]);
    $role     = ($dbUser && isset($dbUser->role) && $dbUser->role !== '')
                ? (string)$dbUser->role : 'staff';
    $_SESSION['user'] = ['username' => $username, 'role' => $role];
}

// Only admin can manage users
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$msg     = "";
$msgType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $newRole     = $_POST['role'] ?? 'staff';

        // Validate role
        $allowedRoles = ['admin', 'hr', 'accountant', 'staff'];
        if (!in_array($newRole, $allowedRoles)) {
            $newRole = 'staff';
        }

        if ($newUsername && strlen($newPassword) >= 6) {
            $exists = findOne($manager, $dbName, $usersCollection, ['username' => $newUsername]);
            if ($exists) {
                $msg     = "Username already exists.";
                $msgType = "warning";
            } else {
                insertDoc($manager, $dbName, $usersCollection, [
                    'username'   => $newUsername,
                    'password'   => password_hash($newPassword, PASSWORD_DEFAULT),
                    'role'       => $newRole,
                    'created_by' => $username,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $msg     = "User '{$newUsername}' created successfully with role '{$newRole}'.";
                $msgType = "success";
            }
        } else {
            $msg     = "Username is required and password must be at least 6 characters.";
            $msgType = "danger";
        }
    }

    if ($action === 'delete') {
        $uid = $_POST['uid'] ?? '';
        if ($uid) {
            try {
                $targetUser = findOne($manager, $dbName, $usersCollection, [
                    '_id' => new MongoDB\BSON\ObjectId($uid)
                ]);
                // Protect admin account from deletion
                if ($targetUser && $targetUser->username === 'admin') {
                    $msg     = "The admin account cannot be deleted.";
                    $msgType = "warning";
                } else {
                    $bulk = new MongoDB\Driver\BulkWrite();
                    $bulk->delete(['_id' => new MongoDB\BSON\ObjectId($uid)], ['limit' => 1]);
                    $manager->executeBulkWrite("$dbName.$usersCollection", $bulk);
                    $msg     = "User deleted successfully.";
                    $msgType = "success";
                }
            } catch (Exception $e) {
                $msg     = "Error deleting user.";
                $msgType = "danger";
            }
        }
    }

    if ($action === 'change_role') {
        $uid     = $_POST['uid'] ?? '';
        $newRole = $_POST['new_role'] ?? '';
        $allowedRoles = ['admin', 'hr', 'accountant', 'staff'];

        if ($uid && in_array($newRole, $allowedRoles)) {
            try {
                updateDoc($manager, $dbName, $usersCollection,
                    ['_id' => new MongoDB\BSON\ObjectId($uid)],
                    ['role' => $newRole]
                );
                $msg     = "Role updated successfully.";
                $msgType = "success";
            } catch (Exception $e) {
                $msg     = "Error updating role.";
                $msgType = "danger";
            }
        }
    }
}

// Fetch all users
$query  = new MongoDB\Driver\Query([], ['sort' => ['created_at' => -1]]);
$cursor = $manager->executeQuery("$dbName.$usersCollection", $query);
$users  = $cursor->toArray();

layoutHeader('Manage Users');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Manage Users</h4>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-house me-1"></i>Dashboard
    </a>
</div>

<div class="row g-4">

    <!-- Add User Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-plus me-2 text-primary"></i>Add New User
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Username <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="username" class="form-control"
                               placeholder="e.g. hr_manager" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Password <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">(min 6 chars)</small>
                        </label>
                        <input type="password" name="password" class="form-control"
                               placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" minlength="6" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select">
                            <option value="staff">Staff (View Only)</option>
                            <option value="hr">HR Manager</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                        <div class="form-text">
                            <strong>Admin:</strong> Full access &nbsp;|&nbsp;
                            <strong>HR:</strong> Add employees, payroll &nbsp;|&nbsp;
                            <strong>Accountant:</strong> Approve, payslips &nbsp;|&nbsp;
                            <strong>Staff:</strong> View only
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i>Create User
                    </button>
                </form>
            </div>
        </div>

        <!-- Role legend -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2 text-info"></i>Role Permissions
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th>Access Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-danger">Admin</span></td>
                            <td class="small">Full system access</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">HR</span></td>
                            <td class="small">Employees + Payroll generation</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-info text-dark">Accountant</span></td>
                            <td class="small">Approve payroll + Payslips</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-secondary">Staff</span></td>
                            <td class="small">View only</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2 text-success"></i>
                System Users
                <span class="badge bg-secondary ms-1"><?= count($users) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($users)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people display-4 d-block mb-3 opacity-50"></i>
                    <p>No users found.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $i => $u): ?>
                        <?php
                            $uRole = $u->role ?? 'staff';
                            $roleBadge = match($uRole) {
                                'admin'     => 'bg-danger',
                                'hr'        => 'bg-primary',
                                'accountant'=> 'bg-info text-dark',
                                default     => 'bg-secondary'
                            };
                            $isProtected = ($u->username === 'admin');
                        ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary d-flex align-items-center
                                                justify-content-center text-white fw-bold"
                                         style="width:32px;height:32px;font-size:13px;flex-shrink:0;">
                                        <?= strtoupper(substr($u->username, 0, 1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars((string)($u->username ?? '')) ?></strong>
                                    <?php if ($isProtected): ?>
                                    <span class="badge bg-warning text-dark" style="font-size:10px;">Protected</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $roleBadge ?>">
                                    <?= htmlspecialchars((string)($u->role ?? 'staff')) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?php
                                // created_by might be array or string â€” handle both
                                $createdBy = $u->created_by ?? 'â€”';
                                if (is_array($createdBy)) {
                                    $createdBy = $createdBy['username'] ?? 'system';
                                }
                                echo htmlspecialchars((string)$createdBy);
                                ?>
                            </td>
                            <td class="text-muted small">
                                <?= htmlspecialchars((string)($u->created_at ?? 'â€”')) ?>
                            </td>
                            <td>
                                <?php if (!$isProtected): ?>
                                <div class="d-flex gap-1 flex-wrap">
                                    <!-- Change Role -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="uid" value="<?= $u->_id ?>">
                                        <select name="new_role" class="form-select form-select-sm d-inline-block"
                                                style="width:auto;"
                                                onchange="this.form.submit()">
                                            <option value="staff"      <?= $uRole==='staff'      ? 'selected':'' ?>>Staff</option>
                                            <option value="hr"         <?= $uRole==='hr'         ? 'selected':'' ?>>HR</option>
                                            <option value="accountant" <?= $uRole==='accountant' ? 'selected':'' ?>>Accountant</option>
                                            <option value="admin"      <?= $uRole==='admin'      ? 'selected':'' ?>>Admin</option>
                                        </select>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete user <?= htmlspecialchars((string)($u->username ?? '')) ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="uid" value="<?= $u->_id ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">
                                    <i class="bi bi-lock me-1"></i>Protected
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php layoutFooter(); ?>