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
    $role     = 'staff';
    $_SESSION['user'] = ['username' => $username, 'role' => $role];
}

// Only admin and hr can edit employees
if (!in_array($role, ['admin', 'hr'])) {
    header("Location: view_employees.php");
    exit();
}

// Must have an id in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_employees.php");
    exit();
}

$msg     = "";
$msgType = "success";

// Load the employee record
try {
    $emp = findOne($manager, $dbName, $employeesCollection, [
        '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
    ]);
} catch (Exception $e) {
    header("Location: view_employees.php");
    exit();
}

if (!$emp) {
    header("Location: view_employees.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $email       = trim($_POST['email']       ?? '');
    $department  = trim($_POST['department']  ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $basic       = (float)($_POST['basic_salary'] ?? 0);
    $hra_pct     = (float)($_POST['hra_pct']  ?? 40);
    $da_pct      = (float)($_POST['da_pct']   ?? 20);
    $pf_pct      = (float)($_POST['pf_pct']   ?? 12);
    $tax_pct     = (float)($_POST['tax_pct']  ?? 10);
    $status      = trim($_POST['status']      ?? 'active');

    if (!$name || !$designation || $basic <= 0) {
        $msg     = "Name, Designation and Basic Salary are required.";
        $msgType = "danger";
    } else {
        try {
            updateDoc($manager, $dbName, $employeesCollection,
                ['_id' => new MongoDB\BSON\ObjectId($_GET['id'])],
                [
                    'name'        => $name,
                    'email'       => $email,
                    'department'  => $department,
                    'designation' => $designation,
                    'basic_salary'=> $basic,
                    'hra_pct'     => $hra_pct,
                    'da_pct'      => $da_pct,
                    'pf_pct'      => $pf_pct,
                    'tax_pct'     => $tax_pct,
                    'status'      => $status,
                    'updated_by'  => $username,
                    'updated_at'  => date('Y-m-d H:i:s')
                ]
            );
            $msg     = "Employee updated successfully!";
            $msgType = "success";

            // Reload updated record
            $emp = findOne($manager, $dbName, $employeesCollection, [
                '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
            ]);
        } catch (Exception $e) {
            $msg     = "Error updating employee: " . $e->getMessage();
            $msgType = "danger";
        }
    }
}

layoutHeader('Edit Employee');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-pencil-square me-2"></i>Edit Employee
        <span class="text-muted fs-6 fw-normal ms-2">(<?= htmlspecialchars($emp->emp_id ?? '') ?>)</span>
    </h4>
    <a href="view_employees.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Employees
    </a>
</div>

<div class="row g-4">

    <!-- Edit Form -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person me-2 text-primary"></i>Employee Details
            </div>
            <div class="card-body">
                <form method="POST" id="editForm">

                    <!-- Basic Info -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars($emp->emp_id ?? '') ?>" disabled>
                            <small class="text-muted">Employee ID cannot be changed.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($emp->name ?? '') ?>"
                                   placeholder="e.g. John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($emp->email ?? '') ?>"
                                   placeholder="e.g. john@company.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active"   <?= ($emp->status ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($emp->status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select name="department" class="form-select">
                                <?php
                                $departments = ['IT','HR','Finance','Marketing','Operations','Sales'];
                                foreach ($departments as $dept):
                                ?>
                                <option value="<?= $dept ?>" <?= ($emp->department ?? '') === $dept ? 'selected' : '' ?>>
                                    <?= $dept ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Designation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required
                                   value="<?= htmlspecialchars($emp->designation ?? '') ?>"
                                   placeholder="e.g. Software Engineer">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-semibold text-muted mb-3">Salary Components</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" name="basic_salary" id="basicSalary"
                                   class="form-control" required min="0" step="0.01"
                                   value="<?= $emp->basic_salary ?? 0 ?>"
                                   oninput="calcPreview()">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">HRA %</label>
                            <input type="number" name="hra_pct" id="hraPct"
                                   class="form-control" min="0" max="100" step="0.1"
                                   value="<?= $emp->hra_pct ?? 40 ?>"
                                   oninput="calcPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">DA %</label>
                            <input type="number" name="da_pct" id="daPct"
                                   class="form-control" min="0" max="100" step="0.1"
                                   value="<?= $emp->da_pct ?? 20 ?>"
                                   oninput="calcPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PF %</label>
                            <input type="number" name="pf_pct" id="pfPct"
                                   class="form-control" min="0" max="100" step="0.1"
                                   value="<?= $emp->pf_pct ?? 12 ?>"
                                   oninput="calcPreview()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tax %</label>
                            <input type="number" name="tax_pct" id="taxPct"
                                   class="form-control" min="0" max="100" step="0.1"
                                   value="<?= $emp->tax_pct ?? 10 ?>"
                                   oninput="calcPreview()">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                        <a href="view_employees.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- Live Salary Preview -->
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-calculator me-2 text-success"></i>Live Salary Preview
            </div>
            <div class="card-body" id="previewArea">
                <!-- filled by JS -->
            </div>
        </div>

        <!-- Employee Meta Info -->
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-info-circle me-2 text-secondary"></i>Record Info
            </div>
            <div class="card-body p-3">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted small">Created</td>
                        <td class="small"><?= htmlspecialchars($emp->created_at ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Last Updated</td>
                        <td class="small"><?= htmlspecialchars($emp->updated_at ?? '—') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Updated By</td>
                        <td class="small"><?= htmlspecialchars($emp->updated_by ?? '—') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function calcPreview() {
    const basic  = parseFloat(document.getElementById('basicSalary').value) || 0;
    const hraPct = parseFloat(document.getElementById('hraPct').value)      || 0;
    const daPct  = parseFloat(document.getElementById('daPct').value)       || 0;
    const pfPct  = parseFloat(document.getElementById('pfPct').value)       || 0;
    const taxPct = parseFloat(document.getElementById('taxPct').value)      || 0;

    const hra   = basic * hraPct / 100;
    const da    = basic * daPct  / 100;
    const gross = basic + hra + da;
    const pf    = basic * pfPct  / 100;
    const tax   = gross * taxPct / 100;
    const net   = gross - pf - tax;

    const fmt = v => 'Rs.' + v.toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });

    document.getElementById('previewArea').innerHTML = `
        <table class="table table-sm mb-0">
            <tbody>
                <tr class="table-light">
                    <td colspan="2" class="fw-semibold text-uppercase small text-muted">Earnings</td>
                </tr>
                <tr>
                    <td>Basic Salary</td>
                    <td class="text-end fw-semibold">${fmt(basic)}</td>
                </tr>
                <tr>
                    <td>HRA (${hraPct}%)</td>
                    <td class="text-end">${fmt(hra)}</td>
                </tr>
                <tr>
                    <td>DA (${daPct}%)</td>
                    <td class="text-end">${fmt(da)}</td>
                </tr>
                <tr class="table-success fw-semibold">
                    <td>Gross Pay</td>
                    <td class="text-end">${fmt(gross)}</td>
                </tr>
                <tr class="table-light">
                    <td colspan="2" class="fw-semibold text-uppercase small text-muted">Deductions</td>
                </tr>
                <tr>
                    <td>PF (${pfPct}%)</td>
                    <td class="text-end text-danger">${fmt(pf)}</td>
                </tr>
                <tr>
                    <td>Income Tax (${taxPct}%)</td>
                    <td class="text-end text-danger">${fmt(tax)}</td>
                </tr>
                <tr class="table-primary fw-bold fs-6">
                    <td>Net Pay</td>
                    <td class="text-end">${fmt(net)}</td>
                </tr>
            </tbody>
        </table>`;
}

// Run on page load to populate preview immediately
calcPreview();
</script>

<?php layoutFooter(); ?>