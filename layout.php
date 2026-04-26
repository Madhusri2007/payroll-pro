 <?php
function layoutHeader($title = 'Payroll System') {
    $sessionUser = $_SESSION['user'] ?? '';
    if (is_array($sessionUser)) {
        $user = $sessionUser;
    } else {
        $user = ['username' => (string)$sessionUser, 'role' => 'staff'];
    }
    $role = $user['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — Payroll System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f0f2f5; }
        .navbar { background: linear-gradient(135deg, #1a237e, #283593); }
        .navbar-brand { font-weight: 700; letter-spacing: 1px; }
        .sidebar { min-height: 100vh; background: #fff; border-right: 1px solid #dee2e6; padding-top: 20px; }
        .sidebar .nav-link { color: #333; padding: 8px 16px; border-radius: 8px; margin: 2px 8px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #e8eaf6; color: #1a237e; font-weight: 600; }
        .sidebar .nav-section { font-size: 0.7rem; text-transform: uppercase; color: #999; padding: 12px 24px 4px; letter-spacing: 1px; }
        .main-content { padding: 20px; }
        .role-badge { font-size: 0.7rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark px-3 py-2">
    <span class="navbar-brand"><i class="bi bi-cash-stack me-2"></i>PayrollPro</span>
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-light text-dark role-badge">
            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['username']) ?>
            &mdash; <strong><?= strtoupper($role) ?></strong>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>

<div class="container-fluid">
<div class="row">
    <div class="col-md-2 sidebar">
        <ul class="nav flex-column">
            
          <div class="menu-label">Payroll</div>
<a href="view_payroll.php"><span>&#x1F4CA;</span> View Payroll</a>
<?php if (in_array($role, ['admin', 'hr'])): ?>
<a href="generate_payroll.php"><span>&#x1F4B0;</span> Generate Payroll</a>
<a href="bank_details.php"><span>&#x1F3E6;</span> Bank Details</a>   <!-- ADD THIS -->
<?php endif; ?>

            <li><div class="nav-section">Main</div></li>
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            </li>

            <li><div class="nav-section">Employees</div></li>
            <li class="nav-item">
                <a href="view_employees.php" class="nav-link"><i class="bi bi-people me-2"></i>View Employees</a>
            </li>
            <?php if (in_array($role, ['admin', 'hr'])): ?>
            <li class="nav-item">
                <a href="add_employee.php" class="nav-link"><i class="bi bi-person-plus me-2"></i>Add Employee</a>
            </li>
            <?php endif; ?>

            <li><div class="nav-section">Payroll</div></li>
            <li class="nav-item">
                <a href="view_payroll.php" class="nav-link"><i class="bi bi-table me-2"></i>View Payroll</a>
            </li>
            <?php if (in_array($role, ['admin', 'hr'])): ?>
            <li class="nav-item">
                <a href="generate_payroll.php" class="nav-link"><i class="bi bi-plus-circle me-2"></i>Generate Payroll</a>
            </li>
            <?php endif; ?>
            <?php if (in_array($role, ['admin', 'accountant', 'hr'])): ?>
            <li class="nav-item">
                <a href="payroll_approval.php" class="nav-link"><i class="bi bi-check2-circle me-2"></i>Approval</a>
            </li>
            <li class="nav-item">
                <a href="payslips.php" class="nav-link"><i class="bi bi-receipt me-2"></i>Payslips</a>
            </li>
            <li class="nav-item">
                <a href="allowances.php" class="nav-link"><i class="bi bi-currency-rupee me-2"></i>Allowances</a>
            </li>
            <?php endif; ?>

            <li><div class="nav-section">HR</div></li>
            <li class="nav-item">
                <a href="leave_management.php" class="nav-link"><i class="bi bi-calendar-x me-2"></i>Leave</a>
            </li>

            <?php if (in_array($role, ['admin', 'hr', 'accountant'])): ?>
            <li><div class="nav-section">Reports</div></li>
            <li class="nav-item">
                <a href="export_payroll.php" class="nav-link"><i class="bi bi-download me-2"></i>Export CSV</a>
            </li>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
            <li><div class="nav-section">Admin</div></li>
            <li class="nav-item">
                <a href="add_user.php" class="nav-link"><i class="bi bi-person-lock me-2"></i>Manage Users</a>
            </li>
            <li class="nav-item">
                <a href="audit_log.php" class="nav-link"><i class="bi bi-clock-history me-2"></i>Audit Log</a>
            </li>
            <?php endif; ?>

        </ul>
        
    </div>

    <div class="col-md-10 main-content">
<?php
}

function layoutFooter() {
?>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}