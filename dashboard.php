 <?php
session_start();
require_once("config/db.php");

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$sessionUser = $_SESSION['user'];

if (is_array($sessionUser)) {
    $username = $sessionUser['username'];
    $role     = $sessionUser['role'];
} else {
    // Legacy string session — look up REAL role from DB
    $username = (string)$sessionUser;
    $dbUser   = findOne($manager, $dbName, $usersCollection, ['username' => $username]);
    $role     = ($dbUser && isset($dbUser->role) && $dbUser->role !== '')
                ? (string)$dbUser->role
                : 'staff';
    // Upgrade session to array format
    $_SESSION['user'] = ['username' => $username, 'role' => $role];
}

// Count employees
$query          = new MongoDB\Driver\Query([]);
$cursor         = $manager->executeQuery("$dbName.$employeesCollection", $query);
$employees      = $cursor->toArray();
$totalEmployees = count($employees);

// Count payroll records
$query2         = new MongoDB\Driver\Query([]);
$cursor2        = $manager->executeQuery("$dbName.$payrollCollection", $query2);
$payrollRecords = $cursor2->toArray();
$totalPayroll   = count($payrollRecords);

// Total net salary
$totalNetSalary = 0;
foreach ($payrollRecords as $record) {
    $totalNetSalary += $record->net_pay ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PayRoll Pro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; min-height: 100vh; }

        /* SIDEBAR */
        .sidebar { width: 260px; background: linear-gradient(180deg, #1e3c72, #2a5298); color: white; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .sidebar-logo { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); text-align: center; }
        .sidebar-logo h2 { font-size: 22px; }
        .sidebar-logo p  { font-size: 11px; opacity: 0.7; margin-top: 4px; }
        .sidebar-menu { padding: 20px 0; }
        .menu-label { font-size: 10px; text-transform: uppercase; opacity: 0.5; padding: 10px 20px 5px; letter-spacing: 1px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.2s; }
        .sidebar-menu a:hover,
        .sidebar-menu a.active { background: rgba(255,255,255,0.15); color: white; border-left: 3px solid white; }
        .sidebar-menu a span { font-size: 18px; }

        /* MAIN */
        .main { margin-left: 260px; flex: 1; padding: 30px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .topbar h1 { font-size: 24px; color: #1e3c72; }
        .topbar-right { display: flex; align-items: center; gap: 15px; }
        .user-badge { background: #1e3c72; color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; }
        .logout-btn { background: #e74c3c; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 13px; }
        .logout-btn:hover { background: #c0392b; }

        /* STAT CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .stat-icon.blue   { background: #e8f0fe; }
        .stat-icon.green  { background: #e8f8f0; }
        .stat-icon.orange { background: #fff3e0; }
        .stat-info h3 { font-size: 28px; color: #1e3c72; }
        .stat-info p  { font-size: 13px; color: #888; margin-top: 3px; }

        /* QUICK ACTIONS */
        .section-title { font-size: 18px; color: #1e3c72; margin-bottom: 15px; font-weight: 600; }
        .actions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .action-card { background: white; border-radius: 12px; padding: 20px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.07); text-align: center; transition: all 0.3s; border: 2px solid transparent; }
        .action-card:hover { border-color: #2a5298; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(42,82,152,0.15); }
        .action-card .icon { font-size: 32px; margin-bottom: 10px; }
        .action-card h3 { font-size: 15px; color: #1e3c72; }
        .action-card p  { font-size: 12px; color: #888; margin-top: 4px; }

        /* TABLE */
        .info-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f0f2f5; padding: 12px; text-align: left; font-size: 13px; color: #555; }
        td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        tr:last-child td { border-bottom: none; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-green { background: #e8f8f0; color: #27ae60; }

        /* ROLE ALERT */
        .role-alert { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 10px 16px; margin-bottom: 20px; font-size: 13px; color: #856404; }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h2>&#x1F4BC; PayRoll Pro</h2>
        <p>Employee Payroll Management</p>
    </div>
    <div class="sidebar-menu">
        <div class="menu-label">Main Menu</div>
        <a href="dashboard.php" class="active"><span>&#x1F3E0;</span> Dashboard</a>
        <a href="view_employees.php"><span>&#x1F465;</span> View Employees</a>
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <a href="add_employee.php"><span>&#x2795;</span> Add Employee</a>
        <?php endif; ?>

        <div class="menu-label">Payroll</div>
        <a href="view_payroll.php"><span>&#x1F4CA;</span> View Payroll</a>
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <a href="generate_payroll.php"><span>&#x1F4B0;</span> Generate Payroll</a>
        <?php endif; ?>
        <?php if (in_array($role, ['admin', 'hr', 'accountant'])): ?>
        <a href="payroll_approval.php"><span>&#x2705;</span> Approval</a>
        <a href="payslips.php"><span>&#x1F9FE;</span> Payslips</a>
        <a href="allowances.php"><span>&#x1F4B5;</span> Allowances</a>
        <a href="export_payroll.php"><span>&#x1F4E5;</span> Export CSV</a>
        <?php endif; ?>

        <div class="menu-label">HR</div>
        <a href="leave_management.php"><span>&#x1F4C5;</span> Leave</a>

        <?php if ($role === 'admin'): ?>
        <div class="menu-label">Admin</div>
        <a href="add_user.php"><span>&#x1F510;</span> Manage Users</a>
        <a href="audit_log.php"><span>&#x1F554;</span> Audit Log</a>
        <?php endif; ?>

        <div class="menu-label">Account</div>
        <a href="logout.php"><span>&#x1F6AA;</span> Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="topbar">
        <h1>Dashboard</h1>
        <div class="topbar-right">
            <span class="user-badge">
                &#x1F464; <?php echo htmlspecialchars($username); ?>
                &mdash; <strong><?php echo strtoupper($role); ?></strong>
            </span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <?php if ($role === 'staff'): ?>
    <div class="role-alert">
        &#x26A0; You are logged in as <strong>STAFF</strong>. If this is wrong, please
        <a href="logout.php">logout</a> and log back in, or ask admin to run
        <a href="setup.php">setup.php</a> to fix roles.
    </div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">&#x1F465;</div>
            <div class="stat-info">
                <h3><?php echo $totalEmployees; ?></h3>
                <p>Total Employees</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">&#x1F4CB;</div>
            <div class="stat-info">
                <h3><?php echo $totalPayroll; ?></h3>
                <p>Payroll Records</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">&#x1F4B0;</div>
            <div class="stat-info">
                <h3>Rs.<?php echo number_format($totalNetSalary, 0); ?></h3>
                <p>Total Net Salary Paid</p>
            </div>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="section-title">Quick Actions</div>
    <div class="actions-grid">
        <?php if (in_array($role, ['admin', 'hr'])): ?>
        <a href="add_employee.php" class="action-card">
            <div class="icon">&#x2795;</div>
            <h3>Add Employee</h3>
            <p>Register a new employee</p>
        </a>
        <a href="generate_payroll.php" class="action-card">
            <div class="icon">&#x1F4B0;</div>
            <h3>Generate Payroll</h3>
            <p>Calculate &amp; process salary</p>
        </a>
        <?php endif; ?>
        <a href="view_payroll.php" class="action-card">
            <div class="icon">&#x1F4CA;</div>
            <h3>View Payroll</h3>
            <p>See all payroll records</p>
        </a>
        <a href="view_employees.php" class="action-card">
            <div class="icon">&#x1F465;</div>
            <h3>View Employees</h3>
            <p>Browse all employees</p>
        </a>
        <?php if (in_array($role, ['admin', 'hr', 'accountant'])): ?>
        <a href="payroll_approval.php" class="action-card">
            <div class="icon">&#x2705;</div>
            <h3>Approval</h3>
            <p>Approve pending payrolls</p>
        </a>
        <a href="payslips.php" class="action-card">
            <div class="icon">&#x1F9FE;</div>
            <h3>Payslips</h3>
            <p>View & download payslips</p>
        </a>
        <?php endif; ?>
    </div>

    <!-- RECENT EMPLOYEES -->
    <div class="section-title">Recent Employees</div>
    <div class="info-card">
        <table>
            <tr>
                <th>Emp ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Basic Salary</th>
                <th>Status</th>
            </tr>
            <?php if ($totalEmployees == 0): ?>
            <tr>
                <td colspan="5" style="text-align:center; color:#888; padding:30px;">
                    No employees yet.
                    <?php if (in_array($role, ['admin', 'hr'])): ?>
                    <a href="add_employee.php">Add your first employee &rarr;</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach (array_slice($employees, 0, 5) as $emp): ?>
            <tr>
                <td><?php echo htmlspecialchars($emp->emp_id     ?? '&mdash;'); ?></td>
                <td><?php echo htmlspecialchars($emp->name       ?? '&mdash;'); ?></td>
                <td><?php echo htmlspecialchars($emp->department ?? '&mdash;'); ?></td>
                <td>Rs.<?php echo number_format($emp->basic_salary ?? 0); ?></td>
                <td><span class="badge badge-green"><?php echo ucfirst($emp->status ?? 'active'); ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>