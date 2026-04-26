 <?php
session_start();
require_once("config/db.php");
require_once("layout.php");
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];

$msg = ""; $msgType = "success";

// Handle delete
if (isset($_GET['delete'])) {
    if (in_array($role, ['admin', 'hr', 'accountant'])) {
        try {
            deleteDoc($manager, $dbName, $payrollCollection, [
                '_id' => new MongoDB\BSON\ObjectId($_GET['delete'])
            ]);
            $msg = "Payroll record deleted.";
            $msgType = "warning";
        } catch (Exception $e) {
            $msg = "Error deleting record.";
            $msgType = "danger";
        }
    } else {
        $msg = "You do not have permission to delete payroll records.";
        $msgType = "danger";
    }
}

// Filters
$filterMonth = $_GET['month'] ?? '';
$filterName  = $_GET['name']  ?? '';

$filter = [];
if ($filterMonth) $filter['month'] = $filterMonth;
if ($filterName)  $filter['name']  = new MongoDB\BSON\Regex($filterName, 'i');

$records = findDocs($manager, $dbName, $payrollCollection, $filter, ['sort' => ['created_at' => -1]]);

layoutHeader('View Payroll');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-table me-2"></i>Payroll Records
        <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($records) ?></span>
    </h4>
    <div class="d-flex gap-2">
        <a href="export_payroll.php" class="btn btn-success btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <a href="generate_payroll.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Generate
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small">Month</label>
                <input type="month" name="month" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filterMonth) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1 small">Employee Name</label>
                <input type="text" name="name" class="form-control form-control-sm"
                       placeholder="Search by name..." value="<?= htmlspecialchars($filterName) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="view_payroll.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Month</th>
                        <th>Basic</th>
                        <th>Gross</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <?php if (in_array($role, ['admin', 'hr', 'accountant'])): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        <i class="bi bi-table display-6 d-block mb-2"></i>
                        No payroll records found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($records as $i => $r): ?>
                <?php
                    $status = $r->status ?? 'pending';
                    $badgeClass = match($status) {
                        'approved' => 'bg-success',
                        'rejected' => 'bg-danger',
                        default    => 'bg-warning text-dark'
                    };
                    $deductions = ($r->pf ?? 0) + ($r->tax ?? 0);
                ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($r->name ?? 'â€”') ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($r->emp_id ?? '') ?></small>
                    </td>
                    <td><?= htmlspecialchars($r->department ?? 'â€”') ?></td>
                    <td><?= htmlspecialchars($r->month ?? 'â€”') ?></td>
                    <td>â‚¹<?= number_format($r->basic ?? 0) ?></td>
                    <td>â‚¹<?= number_format($r->gross ?? 0) ?></td>
                    <td class="text-danger">â‚¹<?= number_format($deductions) ?></td>
                    <td><strong>â‚¹<?= number_format($r->net_pay ?? 0) ?></strong></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span></td>
                    <?php if (in_array($role, ['admin', 'hr', 'accountant'])): ?>
                    <td>
                        <a href="edit_payroll.php?id=<?= $r->_id ?>"
                           class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="?delete=<?= $r->_id ?><?= $filterMonth ? '&month='.urlencode($filterMonth) : '' ?>"
                           onclick="return confirm('Delete this payroll record?')"
                           class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i>
                        </a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php layoutFooter(); ?>