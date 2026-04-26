 <?php
session_start();
require_once("config/db.php");
require_once("layout.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Safe session read — always re-fetch role from DB if session is legacy string
$sessionUser = $_SESSION['user'];
if (is_array($sessionUser)) {
    $username = $sessionUser['username'];
    $role     = $sessionUser['role'];
} else {
    $username = (string)$sessionUser;
    $dbUser   = findOne($manager, $dbName, $usersCollection, ['username' => $username]);
    $role     = ($dbUser && isset($dbUser->role) && $dbUser->role !== '')
                ? (string)$dbUser->role
                : 'staff';
    $_SESSION['user'] = ['username' => $username, 'role' => $role];
}

// Only admin and hr can access this page
if (!in_array($role, ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

$msg     = "";
$msgType = "success";

// Fetch active employees
$employees = findDocs(
    $manager, $dbName, $employeesCollection,
    ['status' => 'active'],
    ['sort' => ['name' => 1]]
);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // SINGLE GENERATE
    if (isset($_POST['single_submit'])) {
        $emp_id = trim($_POST['emp_id'] ?? '');
        $month  = trim($_POST['month']  ?? '');

        if (!$emp_id || !$month) {
            $msg     = "Please select an employee and a month.";
            $msgType = "danger";
        } else {
            $emp = findOne($manager, $dbName, $employeesCollection, ['emp_id' => $emp_id]);
            if (!$emp) {
                $msg     = "Employee not found.";
                $msgType = "danger";
            } else {
                $existing = findOne($manager, $dbName, $payrollCollection, [
                    'emp_id' => $emp_id, 'month' => $month
                ]);
                if ($existing) {
                    $msg     = "Payroll for " . htmlspecialchars($emp->name) . " ($month) already exists.";
                    $msgType = "warning";
                } else {
                    $basic = (float)($emp->basic_salary ?? 0);
                    $hra   = $basic * (($emp->hra_pct ?? 40) / 100);
                    $da    = $basic * (($emp->da_pct  ?? 20) / 100);
                    $gross = $basic + $hra + $da;
                    $pf    = $basic * (($emp->pf_pct  ?? 12) / 100);
                    $tax   = $gross * (($emp->tax_pct  ?? 10) / 100);
                    $net   = $gross - $pf - $tax;

                    insertDoc($manager, $dbName, $payrollCollection, [
                        'emp_id'       => $emp_id,
                        'name'         => $emp->name       ?? '',
                        'department'   => $emp->department  ?? '',
                        'month'        => $month,
                        'basic'        => $basic,
                        'hra'          => round($hra,   2),
                        'da'           => round($da,    2),
                        'gross'        => round($gross, 2),
                        'pf'           => round($pf,    2),
                        'tax'          => round($tax,   2),
                        'net_pay'      => round($net,   2),
                        'status'       => 'pending',
                        'generated_by' => $username,
                        'created_at'   => date('Y-m-d H:i:s')
                    ]);

                    $msg     = "Payroll generated for " . htmlspecialchars($emp->name) . " — $month. Net Pay: Rs." . number_format($net, 2);
                    $msgType = "success";
                }
            }
        }
    }

    // BULK GENERATE
    elseif (isset($_POST['bulk_submit'])) {
        $month   = trim($_POST['bulk_month'] ?? '');
        $created = 0;
        $skipped = 0;

        if (!$month) {
            $msg     = "Please select a month for bulk generate.";
            $msgType = "danger";
        } else {
            foreach ($employees as $emp) {
                $emp_id = $emp->emp_id ?? '';
                if (!$emp_id) continue;

                $existing = findOne($manager, $dbName, $payrollCollection, [
                    'emp_id' => $emp_id, 'month' => $month
                ]);
                if ($existing) { $skipped++; continue; }

                $basic = (float)($emp->basic_salary ?? 0);
                $hra   = $basic * (($emp->hra_pct ?? 40) / 100);
                $da    = $basic * (($emp->da_pct  ?? 20) / 100);
                $gross = $basic + $hra + $da;
                $pf    = $basic * (($emp->pf_pct  ?? 12) / 100);
                $tax   = $gross * (($emp->tax_pct  ?? 10) / 100);
                $net   = $gross - $pf - $tax;

                insertDoc($manager, $dbName, $payrollCollection, [
                    'emp_id'       => $emp_id,
                    'name'         => $emp->name       ?? '',
                    'department'   => $emp->department  ?? '',
                    'month'        => $month,
                    'basic'        => $basic,
                    'hra'          => round($hra,   2),
                    'da'           => round($da,    2),
                    'gross'        => round($gross, 2),
                    'pf'           => round($pf,    2),
                    'tax'          => round($tax,   2),
                    'net_pay'      => round($net,   2),
                    'status'       => 'pending',
                    'generated_by' => $username,
                    'created_at'   => date('Y-m-d H:i:s')
                ]);
                $created++;
            }

            if ($created > 0) {
                $msg     = "Bulk done: $created record(s) created, $skipped already existed.";
                $msgType = "success";
            } elseif ($skipped > 0) {
                $msg     = "All $skipped employee(s) already have payroll for $month.";
                $msgType = "warning";
            } else {
                $msg     = "No records created. Make sure active employees exist.";
                $msgType = "danger";
            }
        }
    }
}

layoutHeader('Generate Payroll');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Generate Payroll</h4>
    <a href="view_payroll.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-table me-1"></i>View Payroll
    </a>
</div>

<?php if (empty($employees)): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    No active employees found.
    <a href="add_employee.php" class="alert-link">Add employees first &rarr;</a>
</div>
<?php else: ?>

<div class="row g-4">

    <!-- Single Generate -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person-check me-2 text-primary"></i>Single Employee
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                        <select name="emp_id" id="empSelect" class="form-select" required onchange="updatePreview()">
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option
                                value="<?= htmlspecialchars($emp->emp_id ?? '') ?>"
                                data-basic="<?= (float)($emp->basic_salary ?? 0) ?>"
                                data-hra="<?=   (float)($emp->hra_pct ?? 40) ?>"
                                data-da="<?=    (float)($emp->da_pct  ?? 20) ?>"
                                data-pf="<?=    (float)($emp->pf_pct  ?? 12) ?>"
                                data-tax="<?=   (float)($emp->tax_pct ?? 10) ?>">
                                <?= htmlspecialchars($emp->name ?? '') ?>
                                (<?= htmlspecialchars($emp->emp_id ?? '') ?>)
                                &mdash; <?= htmlspecialchars($emp->department ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                        <input type="month" name="month" id="monthInput" class="form-control"
                               required value="<?= date('Y-m') ?>" onchange="updatePreview()">
                    </div>
                    <button type="submit" name="single_submit" value="1" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Generate Payroll
                    </button>
                </form>
            </div>
        </div>

        <!-- Bulk Generate -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2 text-success"></i>Bulk Generate
                <span class="badge bg-secondary ms-1"><?= count($employees) ?> employees</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Generates payroll for <strong>all active employees</strong> at once.
                    Existing records for the month are skipped automatically.
                </p>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Month <span class="text-danger">*</span></label>
                        <input type="month" name="bulk_month" class="form-control"
                               required value="<?= date('Y-m') ?>">
                    </div>
                    <button type="submit" name="bulk_submit" value="1"
                            class="btn btn-success w-100"
                            onclick="return confirm('Generate payroll for ALL <?= count($employees) ?> active employees?')">
                        <i class="bi bi-lightning me-1"></i>Bulk Generate All
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-calculator me-2 text-success"></i>Live Salary Preview
            </div>
            <div class="card-body" id="previewBody">
                <p class="text-muted text-center py-5">
                    <i class="bi bi-arrow-left-circle display-6 d-block mb-3 opacity-50"></i>
                    Select an employee to see the salary breakdown.
                </p>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

<script>
function updatePreview() {
    const sel  = document.getElementById('empSelect');
    const opt  = sel.options[sel.selectedIndex];
    const body = document.getElementById('previewBody');

    if (!opt || !opt.value) {
        body.innerHTML = '<p class="text-muted text-center py-5">'
            + '<i class="bi bi-arrow-left-circle display-6 d-block mb-3 opacity-50"></i>'
            + 'Select an employee to see the salary breakdown.</p>';
        return;
    }

    const basic  = parseFloat(opt.dataset.basic) || 0;
    const hraPct = parseFloat(opt.dataset.hra)   || 40;
    const daPct  = parseFloat(opt.dataset.da)    || 20;
    const pfPct  = parseFloat(opt.dataset.pf)    || 12;
    const taxPct = parseFloat(opt.dataset.tax)   || 10;

    const hra   = basic * hraPct / 100;
    const da    = basic * daPct  / 100;
    const gross = basic + hra + da;
    const pf    = basic * pfPct  / 100;
    const tax   = gross * taxPct / 100;
    const net   = gross - pf - tax;

    const fmt = v => 'Rs.' + v.toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

    body.innerHTML = `
        <table class="table table-sm mb-0">
            <tbody>
                <tr class="table-light">
                    <td colspan="2" class="fw-semibold text-uppercase small text-muted">Earnings</td>
                </tr>
                <tr><td>Basic Salary</td><td class="text-end fw-semibold">${fmt(basic)}</td></tr>
                <tr><td>HRA (${hraPct}%)</td><td class="text-end">${fmt(hra)}</td></tr>
                <tr><td>DA (${daPct}%)</td><td class="text-end">${fmt(da)}</td></tr>
                <tr class="table-success fw-semibold">
                    <td>Gross Pay</td><td class="text-end">${fmt(gross)}</td>
                </tr>
                <tr class="table-light">
                    <td colspan="2" class="fw-semibold text-uppercase small text-muted">Deductions</td>
                </tr>
                <tr><td>PF (${pfPct}%)</td><td class="text-end text-danger">${fmt(pf)}</td></tr>
                <tr><td>Income Tax (${taxPct}%)</td><td class="text-end text-danger">${fmt(tax)}</td></tr>
                <tr class="table-primary fw-bold fs-5">
                    <td>Net Pay</td><td class="text-end">${fmt(net)}</td>
                </tr>
            </tbody>
        </table>
        <div class="mt-3 p-2 bg-light rounded small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Preview based on current employee salary settings.
        </div>`;
}
</script>

<?php layoutFooter(); ?>