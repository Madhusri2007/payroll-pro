<?php
session_start();
require_once("config/db.php");
require_once("layout.php");
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];

if ($role !== 'admin') {
   header("Location: dashboard.php");
   exit();
}

$auditCollection = 'audit_log';
$search = $_GET['search'] ?? '';

$filter = [];
if ($search) {
   $filter['$or'] = [
       ['action'   => new MongoDB\BSON\Regex($search, 'i')],
       ['user'     => new MongoDB\BSON\Regex($search, 'i')],
       ['details'  => new MongoDB\BSON\Regex($search, 'i')],
   ];
}

$logs = findDocs($manager, $dbName, $auditCollection, $filter, ['sort' => ['created_at' => -1], 'limit' => 200]);

layoutHeader('Audit Log');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
   <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Audit Log
       <span class="badge bg-secondary ms-2" style="font-size:0.75rem;"><?= count($logs) ?></span>
   </h4>
</div>

<!-- Search -->
<form method="GET" class="card shadow-sm mb-3">
   <div class="card-body py-2">
       <div class="row g-2 align-items-end">
           <div class="col-md-5">
               <input type="text" name="search" class="form-control form-control-sm"
                      placeholder="Search by action, user, or details..."
                      value="<?= htmlspecialchars($search) ?>">
           </div>
           <div class="col-auto d-flex gap-2">
               <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
               <a href="audit_log.php" class="btn btn-outline-secondary btn-sm">Clear</a>
           </div>
       </div>
   </div>
</form>

<div class="card shadow-sm">
   <div class="card-body p-0">
       <div class="table-responsive">
           <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
               <thead class="table-light">
                   <tr>
                       <th>#</th>
                       <th>Timestamp</th>
                       <th>User</th>
                       <th>Action</th>
                       <th>Details</th>
                       <th>IP</th>
                   </tr>
               </thead>
               <tbody>
               <?php if (empty($logs)): ?>
               <tr>
                   <td colspan="6" class="text-center py-5 text-muted">
                       <i class="bi bi-clock-history display-6 d-block mb-2"></i>
                       No audit log entries found.
                   </td>
               </tr>
               <?php else: ?>
               <?php foreach ($logs as $i => $log): ?>
               <tr>
                   <td class="text-muted"><?= $i + 1 ?></td>
                   <td><small><?= htmlspecialchars($log->created_at ?? 'â€”') ?></small></td>
                   <td>
                       <span class="badge bg-primary bg-opacity-10 text-primary">
                           <?= htmlspecialchars($log->user ?? 'â€”') ?>
                       </span>
                   </td>
                   <td>
                       <?php
                       $action = strtolower($log->action ?? '');
                       $ac = str_contains($action, 'delete') ? 'danger'
                          : (str_contains($action, 'add') || str_contains($action, 'create') ? 'success'
                          : (str_contains($action, 'login') ? 'info' : 'secondary'));
                       ?>
                       <span class="badge bg-<?= $ac ?>"><?= htmlspecialchars($log->action ?? 'â€”') ?></span>
                   </td>
                   <td><small class="text-muted"><?= htmlspecialchars($log->details ?? 'â€”') ?></small></td>
                   <td><small class="text-muted"><?= htmlspecialchars($log->ip ?? 'â€”') ?></small></td>
               </tr>
               <?php endforeach; ?>
               <?php endif; ?>
               </tbody>
           </table>
       </div>
   </div>
</div>

<?php layoutFooter(); ?>