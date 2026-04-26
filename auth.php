<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

function hasRole(...$roles) {
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

/**
 * Role permissions map
 * admin      â†’ full access
 * hr         â†’ employees, payroll generate/view/edit/delete, leave, reports, allowances, approval
 * accountant â†’ payroll view/edit/delete/approve, allowances, export, reports
 * staff      â†’ view employees, view payroll (read-only)
 */
function canDo($action) {
    $role = getCurrentUser()['role'] ?? '';

    $permissions = [
        'view_employees'    => ['admin', 'hr', 'accountant', 'staff'],
        'add_employee'      => ['admin', 'hr'],
        'edit_employee'     => ['admin', 'hr'],
        'delete_employee'   => ['admin'],

        'view_payroll'      => ['admin', 'hr', 'accountant', 'staff'],
        'generate_payroll'  => ['admin', 'hr'],
        'edit_payroll'      => ['admin', 'hr', 'accountant'],
        'delete_payroll'    => ['admin', 'hr', 'accountant'],
        'approve_payroll'   => ['admin', 'accountant'],

        'view_payslips'     => ['admin', 'hr', 'accountant'],
        'print_payslip'     => ['admin', 'hr', 'accountant'],

        'manage_leave'      => ['admin', 'hr'],
        'apply_leave'       => ['admin', 'hr', 'staff'],

        'manage_allowances' => ['admin', 'hr', 'accountant'],
        'export_payroll'    => ['admin', 'hr', 'accountant'],

        'view_reports'      => ['admin', 'hr', 'accountant'],
        'view_audit'        => ['admin'],
        'manage_users'      => ['admin'],
    ];

    return in_array($role, $permissions[$action] ?? []);
}

function requirePermission($action) {
    if (!canDo($action)) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-4"><strong>Access Denied.</strong> You do not have permission to perform this action.</div>';
        exit;
    }
}