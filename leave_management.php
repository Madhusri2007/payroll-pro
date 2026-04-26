<?php
session_start();
require_once("config/db.php");
require_once("layout.php");
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];
$leavesCollection = 'leaves';

$msg = ""; $msgType = "success";

// Handle delete
if (isset($_GET['delete'])) {
   if (in_array($role, ['admin', 'hr'])) {
       try {
           deleteDoc($manager, $dbName, $leavesCollection, [
               '_id' => new MongoDB\BSON\ObjectId($_GET['delete'])
           ]);
           $msg = "Leave record deleted.";
           $msgType = "warning";
       } catch (Exception $e) {
           $msg = "Error deleting leave.";
           $msgType = "danger";
       }
   }
}

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['leave_id'])) {
   if (in_array($role, ['admin', 'hr'])) {
       $action = $_POST['action'];
       if (in_array($action, ['approved', 'rejected'])) {
           try {
               updateDoc($manager, $dbName, $leavesCollection,
                   ['_id' => new MongoDB\BSON\ObjectId($_POST['leave_id'])],
                   ['status' => $action, 'reviewed_by' => $user['username'], 'reviewed_at' => date('Y-m-d H:i:s')]
               );
               $msg = "Leave " . ucfirst($action) . ".";
               $msgType = $action === 'approved' ? 'success' : 'warning';
           } catch (Exception $e) {
               $msg = "Error updating leave.";
               $msgType = "danger";
           }
       }
   }
}

// Handle apply leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
   $emp_id     = trim($_POST['emp_id']);
   $emp_name   = trim($_POST['emp_name']);
   $leave_type = trim($_POST['leave_type']);
   $from_date  = trim($_POST['from_date']);
   $to_date    = trim($_POST['to_date']);
   $reason     = trim($_POST['reason']);

   $days = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;
   if ($days < 1) {
       $msg = "To date must be on or after from date.";
       $msgType = "danger";
   } else {
       insertDoc($manager, $dbName, $leavesCollection, [
           'emp_id'     => $emp_id,
           'emp_name'   => $emp_name,
           'leave_type' => $leave_type,
           'from_date'  => $from_date,
           'to_date'    => $to_date,
           'days'       => (int)$days,
           'reason'     => $reason,
           'status'     => 'pending',
           'applied_by' => $user['username'],
           'created_at' => date('Y-m-d H:i:s')
       ]);
       $msg = "Leave applied successfully for $emp_name ($days days).";
       $msgType = "success";
   }
}

$employees = findDocs($manager, $dbName, $employeesCollection, ['status' => 'active'], ['sort' => ['name' => 1]]);
$leaves    = findDocs($manager, $dbName, $leavesCollection, [], ['sort' => ['created_at' => -1]]);

layoutHeader('Leave Management');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
   <?= htmlspecialchars($msg) ?>
   <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
   <h4 class="mb-0"><i class="bi bi-calendar-x me-2"></i>Leave Management</h4>
</div>

<div class="row g-4">
   <!-- Apply Leave Form -->
   <div class="col-md-4">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-plus-circle me-2 text-primary"></i>Apply Leave
           </div>
           <div class="card-body">
               <form method="POST">
                   <input type="hidden" name="apply_leave" value="1">
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Employee</label>
                       <select name="emp_id" id="empSel" class="form-select" required onchange="fillName()">
                           <option value="">â€” Select â€”</option>
                           <?php foreach ($employees as $emp): ?>
                           <option value="<?= htmlspecialchars($emp->emp_id) ?>"
                                   data-name="<?= htmlspecialchars($emp->name) ?>">
                               <?= htmlspecialchars($emp->name) ?> (<?= htmlspecialchars($emp->emp_id) ?>)
                           </option>
                           <?php endforeach; ?>
                       </select>
                       <input type="hidden" name="emp_name" id="empName">
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Leave Type</label>
                       <select name="leave_type" class="form-select">
                           <option>Sick Leave</option>
                           <option>Casual Leave</option>
                           <option>Earned Leave</option>
                           <option>Maternity Leave</option>
                           <option>Unpaid Leave</option>
                       </select>
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">From Date</label>
                       <input type="date" name="from_date" class="form-control" required>
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">To Date</label>
                       <input type="date" name="to_date" class="form-control" required>
                   </div>
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Reason</label>
                       <textarea name="reason" class="form-control" rows="2" placeholder="Optional..."></textarea>
                   </div>
                   <button type="submit" class="btn btn-primary w-100">
                       <i class="bi bi-send me-1"></i>Apply Leave
                   </button>
               </form>
           </div>
       </div>
   </div>

   <!-- Leave List -->
   <div class="col-md-8">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-list-ul me-2"></i>Leave Records
               <span class="badge bg-secondary ms-1"><?= count($leaves) ?></span>
           </div>
           <div class="card-body p-0">
               <div class="table-responsive">
                   <table class="table table-hover align-middle mb-0">
                       <thead class="table-light">
                           <tr>
                               <th>Employee</th>
                               <th>Type</th>
                               <th>From</th>
                               <th>To</th>
                               <th>Days</th>
                               <th>Status</th>
                               <?php if (in_array($role, ['admin', 'hr'])): ?>
                               <th>Action</th>
                               <?php endif; ?>
                           </tr>
                       </thead>
                       <tbody>
                       <?php if (empty($leaves)): ?>
                       <tr>
                           <td colspan="7" class="text-center py-4 text-muted">No leave records found.</td>
                       </tr>
                       <?php else: ?>
                       <?php foreach ($leaves as $l): ?>
                       <?php
                           $st = $l->status ?? 'pending';
                           $bc = match($st) {
                               'approved' => 'bg-success',
                               'rejected' => 'bg-danger',
                               default    => 'bg-warning text-dark'
                           };
                       ?>
                       <tr>
                           <td>
                               <strong><?= htmlspecialchars($l->emp_name ?? 'â€”') ?></strong><br>
                               <small class="text-muted"><?= htmlspecialchars($l->emp_id ?? '') ?></small>
                           </td>
                           <td><span class="badge bg-info text-dark"><?= htmlspecialchars($l->leave_type ?? 'â€”') ?></span></td>
                           <td><?= htmlspecialchars($l->from_date ?? 'â€”') ?></td>
                           <td><?= htmlspecialchars($l->to_date ?? 'â€”') ?></td>
                           <td><?= $l->days ?? 'â€”' ?></td>
                           <td><span class="badge <?= $bc ?>"><?= ucfirst($st) ?></span></td>
                           <?php if (in_array($role, ['admin', 'hr'])): ?>
                           <td>
                               <?php if ($st === 'pending'): ?>
                               <form method="POST" class="d-inline">
                                   <input type="hidden" name="leave_id" value="<?= $l->_id ?>">
                                   <button name="action" value="approved" class="btn btn-success btn-sm">âœ“</button>
                                   <button name="action" value="rejected" class="btn btn-danger btn-sm">âœ—</button>
                               </form>
                               <?php endif; ?>
                               <a href="?delete=<?= $l->_id ?>"
                                  onclick="return confirm('Delete this leave record?')"
                                  class="btn btn-outline-secondary btn-sm ms-1">
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
   </div>
</div>

<script>
function fillName() {
   const sel = document.getElementById('empSel');
   const opt = sel.options[sel.selectedIndex];
   document.getElementById('empName').value = opt.dataset.name || '';
}
</script>

<?php layoutFooter(); ?>