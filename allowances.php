<?php
session_start();
require_once("config/db.php");
require_once("layout.php");
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];

if (!in_array($role, ['admin', 'hr', 'accountant'])) {
   header("Location: dashboard.php");
   exit();
}

$msg = ""; $msgType = "success";
$allowancesCollection = 'allowances';

// Handle delete
if (isset($_GET['delete'])) {
   try {
       deleteDoc($manager, $dbName, $allowancesCollection, [
           '_id' => new MongoDB\BSON\ObjectId($_GET['delete'])
       ]);
       $msg = "Allowance/deduction deleted.";
       $msgType = "warning";
   } catch (Exception $e) {
       $msg = "Error deleting record.";
       $msgType = "danger";
   }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allowance'])) {
   $emp_id  = trim($_POST['emp_id']);
   $month   = trim($_POST['month']);
   $type    = trim($_POST['type']);
   $amount  = (float)$_POST['amount'];
   $note    = trim($_POST['note']);

   // Remove existing same type+month+emp
   $existing = findOne($manager, $dbName, $allowancesCollection, [
       'emp_id' => $emp_id, 'month' => $month, 'type' => $type
   ]);

   if ($existing) {
       updateDoc($manager, $dbName, $allowancesCollection,
           ['_id' => $existing->_id],
           ['amount' => $amount, 'note' => $note, 'updated_by' => $user['username']]
       );
       $msg = "Updated $type for $emp_id â€” $month.";
   } else {
       insertDoc($manager, $dbName, $allowancesCollection, [
           'emp_id'     => $emp_id,
           'month'      => $month,
           'type'       => $type,
           'amount'     => $amount,
           'note'       => $note,
           'created_by' => $user['username'],
           'created_at' => date('Y-m-d H:i:s')
       ]);
       $msg = "Added $type for $emp_id â€” $month.";
   }
   $msgType = "success";
}

$employees   = findDocs($manager, $dbName, $employeesCollection, ['status' => 'active'], ['sort' => ['name' => 1]]);
$filterMonth = $_GET['month'] ?? date('Y-m');
$allowances  = findDocs($manager, $dbName, $allowancesCollection, ['month' => $filterMonth], ['sort' => ['emp_id' => 1]]);

// Summary by type
$summary = [];
foreach ($allowances as $a) {
   $t = $a->type ?? 'Other';
   $summary[$t] = ($summary[$t] ?? 0) + ($a->amount ?? 0);
}

layoutHeader('Allowances & Deductions');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
   <?= htmlspecialchars($msg) ?>
   <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
   <h4 class="mb-0"><i class="bi bi-currency-rupee me-2"></i>Allowances & Deductions</h4>
   <form method="GET" class="d-flex gap-2 align-items-center">
       <label class="mb-0 small fw-semibold">Month:</label>
       <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>">
       <button class="btn btn-outline-primary btn-sm">Go</button>
   </form>
</div>

<!-- Summary Cards -->
<?php if (!empty($summary)): ?>
<div class="row g-3 mb-4">
   <?php
   $cardColors = ['HRA' => 'primary', 'PF' => 'danger', 'Bonus' => 'success', 'Tax' => 'warning', 'Other' => 'secondary'];
   foreach ($summary as $type => $total):
       $color = $cardColors[$type] ?? 'secondary';
   ?>
   <div class="col-md-2 col-6">
       <div class="card border-0 bg-<?= $color ?> bg-opacity-10 text-center py-2">
           <div class="fw-bold text-<?= $color ?>"><?= htmlspecialchars($type) ?></div>
           <div class="fs-6 fw-semibold">â‚¹<?= number_format($total) ?></div>
       </div>
   </div>
   <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4">
   <!-- Add Form -->
   <div class="col-md-4">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-plus-circle me-2 text-primary"></i>Add / Update
           </div>
           <div class="card-body">
               <form method="POST">
                   <input type="hidden" name="save_allowance" value="1">
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Employee</label>
                       <select name="emp_id" class="form-select" required>
                           <option value="">â€” Select â€”</option>
                           <?php foreach ($employees as $emp): ?>
                           <option value="<?= htmlspecialchars($emp->emp_id) ?>">
                               <?= htmlspecialchars($emp->name) ?> (<?= htmlspecialchars($emp->emp_id) ?>)
                           </option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Month</label>
                       <input type="month" name="month" class="form-control" required value="<?= htmlspecialchars($filterMonth) ?>">
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Type</label>
                       <select name="type" class="form-select">
                           <option>HRA</option>
                           <option>PF</option>
                           <option>Bonus</option>
                           <option>Tax</option>
                           <option>Other</option>
                       </select>
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Amount (â‚¹)</label>
                       <input type="number" name="amount" class="form-control" required step="0.01" min="0">
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Note</label>
                       <input type="text" name="note" class="form-control" placeholder="Optional...">
                   </div>
                   <button type="submit" class="btn btn-primary w-100">
                       <i class="bi bi-save me-1"></i>Save
                   </button>
               </form>
           </div>
       </div>
   </div>

   <!-- Records Table -->
   <div class="col-md-8">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-list-ul me-2"></i>Records for <?= htmlspecialchars($filterMonth) ?>
               <span class="badge bg-secondary ms-1"><?= count($allowances) ?></span>
           </div>
           <div class="card-body p-0">
               <div class="table-responsive">
                   <table class="table table-hover align-middle mb-0">
                       <thead class="table-light">
                           <tr>
                               <th>Employee</th>
                               <th>Type</th>
                               <th>Amount</th>
                               <th>Note</th>
                               <th>Action</th>
                           </tr>
                       </thead>
                       <tbody>
                       <?php if (empty($allowances)): ?>
                       <tr>
                           <td colspan="5" class="text-center py-4 text-muted">No records for this month.</td>
                       </tr>
                       <?php else: ?>
                       <?php foreach ($allowances as $a): ?>
                       <?php
                           $typeColors = ['HRA' => 'primary', 'PF' => 'danger', 'Bonus' => 'success', 'Tax' => 'warning', 'Other' => 'secondary'];
                           $tc = $typeColors[$a->type ?? 'Other'] ?? 'secondary';
                       ?>
                       <tr>
                           <td><?= htmlspecialchars($a->emp_id ?? 'â€”') ?></td>
                           <td><span class="badge bg-<?= $tc ?>"><?= htmlspecialchars($a->type ?? 'â€”') ?></span></td>
                           <td><strong>â‚¹<?= number_format($a->amount ?? 0) ?></strong></td>
                           <td><small class="text-muted"><?= htmlspecialchars($a->note ?? 'â€”') ?></small></td>
                           <td>
                               <a href="?delete=<?= $a->_id ?>&month=<?= urlencode($filterMonth) ?>"
                                  onclick="return confirm('Delete this record?')"
                                  class="btn btn-outline-danger btn-sm">
                                   <i class="bi bi-trash"></i>
                               </a>
                           </td>
                       </tr>
                       <?php endforeach; ?>
                       <?php endif; ?>
                       </tbody>
                   </table>
               </div>
           </div>
       </div>
   </div>
</div>

<?php layoutFooter(); ?>