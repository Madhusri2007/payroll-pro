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
    $username = (string)$sessionUser;
    $dbUser   = findOne($manager, $dbName, $usersCollection, ['username' => $username]);
    $role     = ($dbUser && isset($dbUser->role) && $dbUser->role !== '')
                ? (string)$dbUser->role : 'staff';
    $_SESSION['user'] = ['username' => $username, 'role' => $role];
}

if (!isset($_GET['id'])) {
    header("Location: payslips.php");
    exit();
}

try {
    $record = findOne($manager, $dbName, $payrollCollection, [
        '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
    ]);
} catch (Exception $e) {
    header("Location: payslips.php");
    exit();
}

if (!$record) {
    header("Location: payslips.php");
    exit();
}

// ── Safely read ALL fields with correct field names ──────────────────────────
// Payroll is saved with: basic, hra, da, gross, pf, tax, net_pay
// Employee extra info may come from employees collection
$emp_id     = $record->emp_id     ?? '—';
$name       = $record->name       ?? '—';
$department = $record->department ?? '—';
$month      = $record->month      ?? '—';
$genBy      = $record->generated_by ?? $username;

// Core salary fields — stored as 'basic' NOT 'basic_salary'
$basic = (float)($record->basic   ?? 0);
$hra   = (float)($record->hra     ?? 0);
$da    = (float)($record->da      ?? 0);
$gross = (float)($record->gross   ?? 0);
$pf    = (float)($record->pf      ?? 0);
$tax   = (float)($record->tax     ?? 0);
$net   = (float)($record->net_pay ?? 0);   // stored as 'net_pay'

// If gross/net somehow still 0 but basic exists, recalculate
if ($gross == 0 && $basic > 0) {
    $gross = $basic + $hra + $da;
}
if ($net == 0 && $gross > 0) {
    $net = $gross - $pf - $tax;
}

// Fetch employee record for designation
$emp         = findOne($manager, $dbName, $employeesCollection, ['emp_id' => $emp_id]);
$designation = $emp->designation ?? '—';

// Calculate percentages for display
$hraPct = ($basic > 0) ? round(($hra / $basic) * 100) : 40;
$daPct  = ($basic > 0) ? round(($da  / $basic) * 100) : 20;
$pfPct  = ($basic > 0) ? round(($pf  / $basic) * 100) : 12;
$taxPct = ($gross > 0) ? round(($tax / $gross) * 100) : 10;

$status = $record->status ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($name) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #eef1f5;
            display: flex;
            justify-content: center;
            padding: 30px 15px;
        }
        .slip {
            background: white;
            width: 720px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 30px rgba(0,0,0,0.12);
        }

        /* HEADER */
        .slip-header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 28px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .slip-header .brand h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .slip-header .brand p  { font-size: 12px; opacity: 0.75; margin-top: 4px; }
        .slip-header .title    { text-align: right; }
        .slip-header .title h2 { font-size: 20px; letter-spacing: 2px; font-weight: 700; }
        .slip-header .title p  { font-size: 12px; opacity: 0.85; margin-top: 4px; }

        /* STATUS BANNER */
        .status-banner {
            padding: 8px 36px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending  { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }

        /* EMPLOYEE INFO */
        .emp-info {
            margin: 28px 36px 0;
            background: #f8f9fc;
            border-radius: 10px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
        }
        .emp-info .field label {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 3px;
        }
        .emp-info .field span {
            font-size: 14px;
            font-weight: 600;
            color: #1e3c72;
        }

        /* SALARY TABLE */
        .salary-section { margin: 24px 36px; }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e8eaf0;
        }
        .salary-table thead tr {
            background: #1e3c72;
            color: white;
        }
        .salary-table thead th {
            padding: 13px 18px;
            font-size: 13px;
            font-weight: 600;
            text-align: left;
        }
        .salary-table thead th:last-child { text-align: right; }
        .salary-table tbody tr { border-bottom: 1px solid #f0f2f5; }
        .salary-table tbody tr:hover { background: #fafbff; }
        .salary-table tbody td {
            padding: 13px 18px;
            font-size: 14px;
            color: #444;
        }
        .salary-table tbody td:last-child { text-align: right; font-weight: 500; }

        /* Row types */
        .row-earning td:first-child  { color: #27ae60; font-weight: 500; }
        .row-earning td:last-child   { color: #27ae60; }
        .row-deduction td:first-child { color: #e74c3c; font-weight: 500; }
        .row-deduction td:last-child  { color: #e74c3c; }
        .row-gross {
            background: #f0f4ff !important;
            font-weight: 700 !important;
        }
        .row-gross td { font-weight: 700 !important; color: #1e3c72 !important; }

        /* NET PAY */
        .net-pay-row {
            background: linear-gradient(135deg, #e8f5e9, #f0fdf4);
            border-top: 2px solid #27ae60 !important;
        }
        .net-pay-row td {
            padding: 16px 18px !important;
            font-size: 17px !important;
            font-weight: 800 !important;
            color: #1e7e34 !important;
        }

        /* SUMMARY BOXES */
        .summary-boxes {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin: 0 36px 28px;
        }
        .summary-box {
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
        }
        .box-earnings   { background: #e8f8f0; }
        .box-deductions { background: #fdecea; }
        .box-net        { background: #e8f0fe; }
        .summary-box .box-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-box .box-value { font-size: 18px; font-weight: 700; margin-top: 4px; }
        .box-earnings   .box-value { color: #27ae60; }
        .box-deductions .box-value { color: #e74c3c; }
        .box-net        .box-value { color: #1e3c72; }

        /* FOOTER */
        .slip-footer {
            margin: 0 36px 28px;
            padding: 14px 18px;
            background: #f8f9fc;
            border-radius: 8px;
            font-size: 12px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* PRINT BUTTON */
        .print-bar {
            text-align: center;
            margin: 20px 0 10px;
        }
        .btn-print {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 11px 32px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 11px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
        }

        @media print {
            body { background: white; padding: 0; }
            .slip { box-shadow: none; border-radius: 0; width: 100%; }
            .print-bar { display: none; }
        }
    </style>
</head>
<body>

<!-- Print Button -->
<div style="width:720px; text-align:right; margin: 0 auto 12px;">
    <a href="payslips.php" class="btn-back">&#x2190; Back</a>
    <button class="btn-print" onclick="window.print()">&#x1F5A8; Print / Save PDF</button>
</div>

<div class="slip">

    <!-- Header -->
    <div class="slip-header">
        <div class="brand">
            <h1>&#x1F4BC; PayRoll Pro</h1>
            <p>Employee Payroll Management System</p>
        </div>
        <div class="title">
            <h2>SALARY SLIP</h2>
            <p>Month: <?= htmlspecialchars($month) ?></p>
            <p>Generated: <?= date('d M Y') ?></p>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="status-banner status-<?= $status ?>">
        <?php if ($status === 'approved'): ?>
            ✅ Approved Payslip
        <?php elseif ($status === 'rejected'): ?>
            ❌ Rejected — Contact HR
        <?php else: ?>
            ⏳ Pending Approval
        <?php endif; ?>
    </div>

    <!-- Employee Info -->
    <div class="emp-info">
        <div class="field">
            <label>Employee ID</label>
            <span><?= htmlspecialchars($emp_id) ?></span>
        </div>
        <div class="field">
            <label>Name</label>
            <span><?= htmlspecialchars($name) ?></span>
        </div>
        <div class="field">
            <label>Department</label>
            <span><?= htmlspecialchars($department) ?></span>
        </div>
        <div class="field">
            <label>Designation</label>
            <span><?= htmlspecialchars($designation) ?></span>
        </div>
        <div class="field">
            <label>Pay Period</label>
            <span><?= htmlspecialchars($month) ?></span>
        </div>
        <div class="field">
            <label>Generated By</label>
            <span><?= htmlspecialchars($genBy) ?></span>
        </div>
    </div>

    <!-- Salary Table -->
    <div class="salary-section">
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <!-- Earnings -->
                <tr class="row-earning">
                    <td>Basic Salary</td>
                    <td>Earning</td>
                    <td>&#x20B9;<?= number_format($basic, 2) ?></td>
                </tr>
                <tr class="row-earning">
                    <td>House Rent Allowance (HRA - <?= $hraPct ?>%)</td>
                    <td>Earning</td>
                    <td>&#x20B9;<?= number_format($hra, 2) ?></td>
                </tr>
                <tr class="row-earning">
                    <td>Dearness Allowance (DA - <?= $daPct ?>%)</td>
                    <td>Earning</td>
                    <td>&#x20B9;<?= number_format($da, 2) ?></td>
                </tr>

                <!-- Gross -->
                <tr class="row-gross">
                    <td><strong>Gross Salary</strong></td>
                    <td></td>
                    <td><strong>&#x20B9;<?= number_format($gross, 2) ?></strong></td>
                </tr>

                <!-- Deductions -->
                <tr class="row-deduction">
                    <td>Provident Fund (PF - <?= $pfPct ?>%)</td>
                    <td>Deduction</td>
                    <td>- &#x20B9;<?= number_format($pf, 2) ?></td>
                </tr>
                <tr class="row-deduction">
                    <td>Income Tax (<?= $taxPct ?>%)</td>
                    <td>Deduction</td>
                    <td>- &#x20B9;<?= number_format($tax, 2) ?></td>
                </tr>

                <!-- Net Pay -->
                <tr class="net-pay-row">
                    <td colspan="2">&#x1F4B0; <strong>NET SALARY</strong></td>
                    <td><strong>&#x20B9;<?= number_format($net, 2) ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Summary Boxes -->
    <div class="summary-boxes">
        <div class="summary-box box-earnings">
            <div class="box-label">Total Earnings</div>
            <div class="box-value">&#x20B9;<?= number_format($gross, 2) ?></div>
        </div>
        <div class="summary-box box-deductions">
            <div class="box-label">Total Deductions</div>
            <div class="box-value">&#x20B9;<?= number_format($pf + $tax, 2) ?></div>
        </div>
        <div class="summary-box box-net">
            <div class="box-label">Net Take Home</div>
            <div class="box-value">&#x20B9;<?= number_format($net, 2) ?></div>
        </div>
    </div>

    <!-- Footer -->
    <div class="slip-footer">
        &#x2705; This is a computer-generated payslip and does not require a physical signature.
    </div>

</div>

</body>
</html>