 <?php
session_start();
require_once("config/db.php");
require_once("layout.php");

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

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

if (!in_array($role, ['admin', 'hr', 'accountant'])) {
    header("Location: dashboard.php");
    exit();
}

$month  = $_GET['month'] ?? '';
$filter = [];
if ($month) $filter['month'] = $month;

$records = findDocs($manager, $dbName, $payrollCollection, $filter, ['sort' => ['created_at' => -1]]);

layoutHeader('Payslips');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Payslips</h4>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-house me-1"></i>Dashboard
    </a>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter by Month</label>
                <input type="month" name="month" class="form-control"
                       value="<?= htmlspecialchars($month) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="payslips.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-receipt me-2 text-primary"></i>
        All Payslips
        <span class="badge bg-secondary ms-1"><?= count($records) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($records)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-receipt display-4 d-block mb-3 opacity-50"></i>
            <p>No payslips found<?= $month ? " for $month" : "" ?>.</p>
            <a href="generate_payroll.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Generate Payroll First
            </a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Emp ID</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Month</th>
                        <th>Gross</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $i => $r): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($r->emp_id ?? 'â€”') ?></strong></td>
                    <td><?= htmlspecialchars($r->name ?? 'â€”') ?></td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            <?= htmlspecialchars($r->department ?? 'â€”') ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-warning bg-opacity-10 text-warning-emphasis">
                            <?= htmlspecialchars($r->month ?? 'â€”') ?>
                        </span>
                    </td>
                    <td>Rs.<?= number_format($r->gross ?? 0, 2) ?></td>
                    <td>
                        <strong class="text-success">
                            Rs.<?= number_format($r->net_pay ?? 0, 2) ?>
                        </strong>
                    </td>
                    <td>
                        <?php
                        $status = $r->status ?? 'pending';
                        $badgeClass = match($status) {
                            'approved' => 'bg-success',
                            'rejected' => 'bg-danger',
                            default    => 'bg-warning text-dark'
                        };
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td>
                        <a href="print_payslip.php?id=<?= $r->_id ?>"
                           target="_blank"
                           class="btn btn-success btn-sm">
                            <i class="bi bi-printer me-1"></i>Print
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php layoutFooter(); ?>