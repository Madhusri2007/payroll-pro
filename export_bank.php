<?php
session_start();
require_once("config/db.php");

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$sessionUser = $_SESSION['user'];
if (is_array($sessionUser)) {
    $role = $sessionUser['role'];
} else {
    $role = 'staff';
}

if (!in_array($role, ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

$month = $_GET['month'] ?? date('Y-m');

// Get approved payrolls for the month
$payrolls = findDocs($manager, $dbName, $payrollCollection,
    ['month' => $month, 'status' => 'approved'],
    ['sort' => ['name' => 1]]
);

// Get employee bank details
$employees = findDocs($manager, $dbName, $employeesCollection, [], []);
$bankMap = [];
foreach ($employees as $emp) {
    $bankMap[$emp->emp_id ?? ''] = $emp;
}

// Output CSV
$filename = "salary_transfer_{$month}.csv";
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header('Pragma: no-cache');

$out = fopen('php://output', 'w');

// NEFT format headers
fputcsv($out, [
    'Sr No',
    'Employee ID',
    'Employee Name',
    'Department',
    'Bank Name',
    'Account Number',
    'IFSC Code',
    'Account Type',
    'Account Holder Name',
    'Net Pay (Rs.)',
    'Month',
    'Transfer Mode',
    'Remarks'
]);

$sr = 1;
foreach ($payrolls as $p) {
    $empId = $p->emp_id ?? '';
    $emp   = $bankMap[$empId] ?? null;

    if (!$emp || empty($emp->account_number)) continue;

    fputcsv($out, [
        $sr++,
        $empId,
        $p->name       ?? '',
        $p->department ?? '',
        $emp->bank_name      ?? '',
        $emp->account_number ?? '',
        $emp->ifsc_code      ?? '',
        strtoupper($emp->account_type ?? 'SAVINGS'),
        $emp->account_holder ?? $p->name ?? '',
        number_format((float)($p->net_pay ?? 0), 2, '.', ''),
        $month,
        (($p->net_pay ?? 0) >= 200000) ? 'RTGS' : 'NEFT',
        'Salary for ' . $month
    ]);
}

fclose($out);
exit();