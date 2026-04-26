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
    if (in_array($role, ['admin', 'hr'])) {
        try {
            deleteDoc($manager, $dbName, $employeesCollection, [
                '_id' => new MongoDB\BSON\ObjectId($_GET['delete'])
            ]);
            $msg = "Employee deleted successfully.";
            $msgType = "warning";
        } catch (Exception $e) {
            $msg = "Error deleting employee: " . $e->getMessage();
            $msgType = "danger";
        }
    } else {
        $msg = "You do not have permission to delete employees.";
        $msgType = "danger";
    }
}

$employees = findDocs($manager, $dbName, $employeesCollection, [], ['sort' => ['created_at' => -1]]);

layoutHeader('View Employees');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>All Employees
        <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($employees) ?></span>
    </h4>
    <?php if (in_array($role, ['admin', 'hr'])): ?>
    <a href="add_employee.php" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Add Employee
    </a>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Basic Salary</th>
                        <th>Status</th>
                        <?php if (in_array($role, ['admin', 'hr'])): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-people display-6 d-block mb-2"></i>
                        No employees found.
                        <?php if (in_array($role, ['admin', 'hr'])): ?>
                        <a href="add_employee.php">Add your first employee →</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($employees as $i => $emp): ?>
                <tr>
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($emp->emp_id ?? '—') ?></strong></td>
                    <td><?= htmlspecialchars($emp->name ?? '—') ?></td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            <?= htmlspecialchars($emp->department ?? '—') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($emp->designation ?? '—') ?></td>
                    <td><strong>₹<?= number_format($emp->basic_salary ?? 0) ?></strong></td>
                    <td>
                        <?php $status = $emp->status ?? 'active'; ?>
                        <span class="badge <?= $status === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <?php if (in_array($role, ['admin', 'hr'])): ?>
                    <td>
                        <a href="edit_employee.php?id=<?= $emp->_id ?>"
                           class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="?delete=<?= $emp->_id ?>"
                           onclick="return confirm('Delete <?= htmlspecialchars(addslashes($emp->name ?? '')) ?>?')"
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