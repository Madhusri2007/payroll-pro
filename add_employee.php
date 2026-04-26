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

if (!in_array($role, ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

$msg     = "";
$msgType = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['emp_id'] ?? '');

    // Check duplicate
    $existing = findOne($manager, $dbName, $employeesCollection, ['emp_id' => $emp_id]);
    if ($existing) {
        $msg     = "Employee ID '{$emp_id}' already exists.";
        $msgType = "danger";
    } else {
        insertDoc($manager, $dbName, $employeesCollection, [
            // Basic Info
            'emp_id'       => $emp_id,
            'name'         => trim($_POST['name']         ?? ''),
            'email'        => trim($_POST['email']        ?? ''),
            'phone'        => trim($_POST['phone']        ?? ''),
            'department'   => trim($_POST['department']   ?? ''),
            'designation'  => trim($_POST['designation']  ?? ''),
            'join_date'    => trim($_POST['join_date']    ?? ''),
            'status'       => 'active',

            // Salary Info
            'basic_salary' => (float)($_POST['basic_salary'] ?? 0),
            'hra_pct'      => (float)($_POST['hra_pct']      ?? 40),
            'da_pct'       => (float)($_POST['da_pct']       ?? 20),
            'pf_pct'       => (float)($_POST['pf_pct']       ?? 12),
            'tax_pct'      => (float)($_POST['tax_pct']      ?? 10),

            // Bank Info
            'bank_name'      => trim($_POST['bank_name']      ?? ''),
            'account_number' => trim($_POST['account_number'] ?? ''),
            'ifsc_code'      => strtoupper(trim($_POST['ifsc_code'] ?? '')),
            'account_holder' => trim($_POST['account_holder'] ?? ''),
            'account_type'   => trim($_POST['account_type']   ?? 'savings'),

            'created_by'   => $username,
            'created_at'   => date('Y-m-d H:i:s')
        ]);
        $msg     = "Employee '{$emp_id}' added successfully!";
        $msgType = "success";
    }
}

layoutHeader('Add Employee');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add New Employee</h4>
    <a href="view_employees.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-people me-1"></i>View Employees
    </a>
</div>

<form method="POST">
<div class="row g-4">

    <!-- BASIC INFO -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-person me-2 text-primary"></i>Basic Information
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Employee ID <span class="text-danger">*</span></label>
                        <input type="text" name="emp_id" class="form-control"
                               placeholder="e.g. EMP001" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="Full name" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="email@company.com">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="10-digit number">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                        <select name="department" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option>IT</option>
                            <option>HR</option>
                            <option>Finance</option>
                            <option>Marketing</option>
                            <option>Sales</option>
                            <option>Operations</option>
                            <option>Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Designation</label>
                        <input type="text" name="designation" class="form-control"
                               placeholder="e.g. Software Engineer">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Join Date</label>
                        <input type="date" name="join_date" class="form-control"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SALARY INFO -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-cash-stack me-2 text-success"></i>Salary Configuration
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Basic Salary (Rs.) <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="basic_salary" class="form-control"
                               placeholder="e.g. 30000" required min="0" step="0.01"
                               oninput="calcSalaryPreview()">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">HRA %</label>
                        <div class="input-group">
                            <input type="number" name="hra_pct" class="form-control"
                                   value="40" min="0" max="100" oninput="calcSalaryPreview()">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">DA %</label>
                        <div class="input-group">
                            <input type="number" name="da_pct" class="form-control"
                                   value="20" min="0" max="100" oninput="calcSalaryPreview()">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">PF %</label>
                        <div class="input-group">
                            <input type="number" name="pf_pct" class="form-control"
                                   value="12" min="0" max="100" oninput="calcSalaryPreview()">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tax %</label>
                        <div class="input-group">
                            <input type="number" name="tax_pct" class="form-control"
                                   value="10" min="0" max="100" oninput="calcSalaryPreview()">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <!-- Mini preview -->
                    <div class="col-12">
                        <div class="bg-light rounded p-3 small" id="salaryMini">
                            <span class="text-muted">Enter basic salary to see preview...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BANK INFO -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bank me-2 text-warning"></i>Bank Account Details
                <span class="badge bg-warning text-dark ms-2" style="font-size:11px;">
                    Required for Salary Transfer
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Account Holder Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="account_holder" class="form-control"
                               placeholder="As per bank records" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Bank Name <span class="text-danger">*</span>
                        </label>
                        <select name="bank_name" class="form-select" required>
                            <option value="">-- Select Bank --</option>
                            <optgroup label="Public Sector Banks">
                                <option>State Bank of India (SBI)</option>
                                <option>Bank of Baroda</option>
                                <option>Punjab National Bank</option>
                                <option>Canara Bank</option>
                                <option>Union Bank of India</option>
                                <option>Bank of India</option>
                                <option>Indian Bank</option>
                                <option>Central Bank of India</option>
                            </optgroup>
                            <optgroup label="Private Sector Banks">
                                <option>HDFC Bank</option>
                                <option>ICICI Bank</option>
                                <option>Axis Bank</option>
                                <option>Kotak Mahindra Bank</option>
                                <option>IndusInd Bank</option>
                                <option>Yes Bank</option>
                                <option>IDFC First Bank</option>
                                <option>Federal Bank</option>
                            </optgroup>
                            <optgroup label="Other">
                                <option>Post Office Savings Bank</option>
                                <option>Other</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Account Type</label>
                        <select name="account_type" class="form-select">
                            <option value="savings">Savings Account</option>
                            <option value="current">Current Account</option>
                            <option value="salary">Salary Account</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Account Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="account_number" class="form-control"
                               placeholder="Enter account number"
                               pattern="[0-9]{9,18}" title="9 to 18 digit account number"
                               required oninput="this.value=this.value.replace(/\D/g,'')">
                        <div class="form-text">Numbers only, 9â€“18 digits</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            IFSC Code <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="ifsc_code" class="form-control"
                               placeholder="e.g. SBIN0001234" maxlength="11"
                               pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}"
                               title="Valid IFSC: 4 letters + 0 + 6 alphanumeric"
                               required
                               oninput="this.value=this.value.toUpperCase()"
                               onblur="validateIFSC(this)">
                        <div class="form-text" id="ifscMsg"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">UPI ID / Phone Pay</label>
                        <input type="text" name="upi_id" class="form-control"
                               placeholder="e.g. name@upi (optional)">
                        <div class="form-text">Optional â€” for UPI salary transfer</div>
                    </div>
                </div>

                <!-- Bank info notice -->
                <div class="alert alert-info mt-3 mb-0 py-2 small">
                    <i class="bi bi-shield-lock me-1"></i>
                    <strong>Security Notice:</strong> Bank details are sensitive. They are only visible to
                    Admin and HR roles. Always verify account details with the employee before saving.
                </div>
            </div>
        </div>
    </div>

    <!-- SUBMIT -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body d-flex gap-3">
                <button type="submit" class="btn btn-primary px-5">
                    <i class="bi bi-person-plus me-1"></i>Add Employee
                </button>
                <a href="view_employees.php" class="btn btn-outline-secondary px-4">
                    Cancel
                </a>
            </div>
        </div>
    </div>

</div>
</form>

<script>
function calcSalaryPreview() {
    const basic  = parseFloat(document.querySelector('[name=basic_salary]').value) || 0;
    const hraPct = parseFloat(document.querySelector('[name=hra_pct]').value)      || 0;
    const daPct  = parseFloat(document.querySelector('[name=da_pct]').value)       || 0;
    const pfPct  = parseFloat(document.querySelector('[name=pf_pct]').value)       || 0;
    const taxPct = parseFloat(document.querySelector('[name=tax_pct]').value)      || 0;

    if (!basic) {
        document.getElementById('salaryMini').innerHTML =
            '<span class="text-muted">Enter basic salary to see preview...</span>';
        return;
    }

    const hra   = basic * hraPct / 100;
    const da    = basic * daPct  / 100;
    const gross = basic + hra + da;
    const pf    = basic * pfPct  / 100;
    const tax   = gross * taxPct / 100;
    const net   = gross - pf - tax;
    const fmt   = v => 'Rs.' + v.toLocaleString('en-IN', {minimumFractionDigits: 0});

    document.getElementById('salaryMini').innerHTML = `
        <div class="d-flex justify-content-between flex-wrap gap-2">
            <span>Gross: <strong class="text-success">${fmt(gross)}</strong></span>
            <span>PF: <strong class="text-danger">-${fmt(pf)}</strong></span>
            <span>Tax: <strong class="text-danger">-${fmt(tax)}</strong></span>
            <span>Net Pay: <strong class="text-primary">${fmt(net)}</strong></span>
        </div>`;
}

function validateIFSC(input) {
    const val = input.value.trim();
    const msg = document.getElementById('ifscMsg');
    const pattern = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    if (!val) { msg.textContent = ''; return; }
    if (pattern.test(val)) {
        msg.textContent  = 'âœ“ Valid IFSC format';
        msg.className    = 'form-text text-success';
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        msg.textContent  = 'âœ— Invalid IFSC. Format: ABCD0123456';
        msg.className    = 'form-text text-danger';
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    }
}
</script>

<?php layoutFooter(); ?>