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

// Only admin and hr can see bank details
if (!in_array($role, ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

// Fetch all active employees
$employees = findDocs($manager, $dbName, $employeesCollection,
    ['status' => 'active'],
    ['sort' => ['name' => 1]]
);

// Get latest approved payroll per employee for this month
$currentMonth = date('Y-m');
$payrollMap   = [];
$payrolls = findDocs($manager, $dbName, $payrollCollection,
    ['month' => $currentMonth],
    []
);
foreach ($payrolls as $p) {
    $payrollMap[$p->emp_id ?? ''] = $p;
}

layoutHeader('Bank Details & Salary Transfer');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-bank me-2"></i>Bank Details &amp; Salary Transfer
    </h4>
    <div class="d-flex gap-2">
        <span class="badge bg-primary align-self-center">
            <?= date('F Y') ?>
        </span>
        <a href="export_bank.php" class="btn btn-success btn-sm">
            <i class="bi bi-download me-1"></i>Export for NEFT/RTGS
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $totalEmployees  = count($employees);
    $withBank        = 0;
    $withoutBank     = 0;
    $totalTransfer   = 0;
    foreach ($employees as $emp) {
        if (!empty($emp->account_number)) {
            $withBank++;
            $empId = $emp->emp_id ?? '';
            if (isset($payrollMap[$empId])) {
                $totalTransfer += (float)($payrollMap[$empId]->net_pay ?? 0);
            }
        } else {
            $withoutBank++;
        }
    }
    ?>
    <div class="col-md-3">
        <div class="card shadow-sm text-center p-3">
            <div class="text-primary fs-2 fw-bold"><?= $totalEmployees ?></div>
            <div class="text-muted small">Total Employees</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center p-3">
            <div class="text-success fs-2 fw-bold"><?= $withBank ?></div>
            <div class="text-muted small">Bank Details Added</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center p-3">
            <div class="text-danger fs-2 fw-bold"><?= $withoutBank ?></div>
            <div class="text-muted small">Bank Details Missing</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm text-center p-3">
            <div class="text-warning fs-2 fw-bold">
                &#8377;<?= number_format($totalTransfer, 0) ?>
            </div>
            <div class="text-muted small">Total Transfer This Month</div>
        </div>
    </div>
</div>

<!-- Bank Details Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-table me-2 text-primary"></i>Employee Bank Accounts</span>
        <span class="badge bg-secondary"><?= $totalEmployees ?> employees</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($employees)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bank display-4 d-block mb-3 opacity-50"></i>
            No employees found.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Bank Name</th>
                        <th>Account Number</th>
                        <th>IFSC Code</th>
                        <th>Account Type</th>
                        <th>Net Pay (<?= $currentMonth ?>)</th>
                        <th>Transfer Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $emp): ?>
                <?php
                    $empId      = $emp->emp_id ?? '';
                    $hasBank    = !empty($emp->account_number);
                    $payroll    = $payrollMap[$empId] ?? null;
                    $netPay     = $payroll ? (float)($payroll->net_pay ?? 0) : 0;
                    $pStatus    = $payroll ? ($payroll->status ?? 'pending') : null;
                    $accNum     = $emp->account_number ?? '';
                    // Mask account number: show only last 4 digits
                    $maskedAcc  = $accNum
                        ? str_repeat('*', max(0, strlen($accNum) - 4)) . substr($accNum, -4)
                        : 'â€”';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($empId) ?></strong></td>
                    <td><?= htmlspecialchars($emp->name ?? 'â€”') ?></td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            <?= htmlspecialchars($emp->department ?? 'â€”') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($hasBank): ?>
                            <i class="bi bi-bank text-success me-1"></i>
                            <?= htmlspecialchars($emp->bank_name ?? 'â€”') ?>
                        <?php else: ?>
                            <span class="text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i>Not Added
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="font-monospace">
                            <?= $hasBank ? htmlspecialchars($maskedAcc) : 'â€”' ?>
                        </span>
                        <?php if ($hasBank): ?>
                        <button class="btn btn-link btn-sm p-0 ms-1"
                                onclick="toggleAcc('acc_<?= $empId ?>','<?= htmlspecialchars($accNum) ?>','<?= htmlspecialchars($maskedAcc) ?>')"
                                title="Show/Hide">
                            <i class="bi bi-eye" id="eye_<?= $empId ?>"></i>
                        </button>
                        <span id="acc_<?= $empId ?>" style="display:none;"></span>
                        <?php endif; ?>
                    </td>
                    <td class="font-monospace">
                        <?= $hasBank ? htmlspecialchars($emp->ifsc_code ?? 'â€”') : 'â€”' ?>
                    </td>
                    <td>
                        <?php if ($hasBank): ?>
                        <span class="badge bg-light text-dark border">
                            <?= ucfirst($emp->account_type ?? 'savings') ?>
                        </span>
                        <?php else: ?>â€”<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($payroll): ?>
                            <strong class="text-success">
                                &#8377;<?= number_format($netPay, 2) ?>
                            </strong>
                        <?php else: ?>
                            <span class="text-muted small">No payroll</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$payroll): ?>
                            <span class="badge bg-secondary">No Payroll</span>
                        <?php elseif (!$hasBank): ?>
                            <span class="badge bg-danger">
                                <i class="bi bi-x-circle me-1"></i>Bank Missing
                            </span>
                        <?php elseif ($pStatus === 'approved'): ?>
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle me-1"></i>Ready to Transfer
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-clock me-1"></i>Pending Approval
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_bank.php?emp_id=<?= urlencode($empId) ?>"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i>
                            <?= $hasBank ? 'Edit' : 'Add' ?>
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

<!-- How Salary Transfer Works -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-info-circle me-2 text-info"></i>How Salary Bank Transfer Works
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex
                            align-items-center justify-content-center mb-2"
                     style="width:56px;height:56px;font-size:24px;">
                    &#x1F4CB;
                </div>
                <div class="fw-semibold small">1. Generate Payroll</div>
                <div class="text-muted" style="font-size:12px;">
                    Admin/HR generates payroll for the month
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex
                            align-items-center justify-content-center mb-2"
                     style="width:56px;height:56px;font-size:24px;">
                    &#x2705;
                </div>
                <div class="fw-semibold small">2. Approve Payroll</div>
                <div class="text-muted" style="font-size:12px;">
                    Accountant/Admin approves the payroll records
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex
                            align-items-center justify-content-center mb-2"
                     style="width:56px;height:56px;font-size:24px;">
                    &#x1F4E5;
                </div>
                <div class="fw-semibold small">3. Export NEFT File</div>
                <div class="text-muted" style="font-size:12px;">
                    Export CSV with bank details + net pay amounts
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex
                            align-items-center justify-content-center mb-2"
                     style="width:56px;height:56px;font-size:24px;">
                    &#x1F3E6;
                </div>
                <div class="fw-semibold small">4. Upload to Bank</div>
                <div class="text-muted" style="font-size:12px;">
                    Upload the CSV to your company's net banking portal
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAcc(spanId, fullNum, maskedNum) {
    const span = document.getElementById(spanId);
    const eye  = document.getElementById('eye_' + spanId.replace('acc_',''));
    // Find the sibling font-monospace span
    const cell = span.parentElement;
    const display = span.style.display === 'none' ? 'inline' : 'none';
    span.style.display = display;
    span.textContent   = fullNum;
    // Toggle the masked number visibility
    cell.querySelector('.font-monospace').style.display =
        display === 'none' ? 'inline' : 'none';
    eye.className = display === 'none' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

<?php layoutFooter(); ?>