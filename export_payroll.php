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

// Handle CSV export
if (isset($_GET['export'])) {
   $filterMonth = $_GET['month'] ?? '';
   $filterName  = $_GET['name']  ?? '';
   $filter = [];
   if ($filterMonth) $filter['month'] = $filterMonth;
   if ($filterName)  $filter['name']  = new MongoDB\BSON\Regex($filterName, 'i');

   $records = findDocs($manager, $dbName, $payrollCollection, $filter, ['sort' => ['month' => -1, 'name' => 1]]);

   $filename = 'payroll_' . ($filterMonth ?: 'all') . '_' . date('Ymd_His') . '.csv';
   header('Content-Type: text/csv');
   header('Content-Disposition: attachment; filename="' . $filename . '"');

   $out = fopen('php://output', 'w');
   fputcsv($out, ['Emp ID', 'Name', 'Department', 'Month', 'Basic', 'HRA', 'DA', 'Gross', 'PF', 'Tax', 'Net Pay', 'Status']);
   foreach ($records as $r) {
       fputcsv($out, [
           $r->emp_id     ?? '',
           $r->name       ?? '',
           $r->department ?? '',
           $r->month      ?? '',
           $r->basic      ?? 0,
           $r->hra        ?? 0,
           $r->da         ?? 0,
           $r->gross      ?? 0,
           $r->pf         ?? 0,
           $r->tax        ?? 0,
           $r->net_pay    ?? 0,
           $r->status     ?? ''
       ]);
   }
   fclose($out);
   exit();
}

// Preview data
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterName  = $_GET['name']  ?? '';
$filter = [];
if ($filterMonth) $filter['month'] = $filterMonth;
if ($filterName)  $filter['name']  = new MongoDB\BSON\Regex($filterName, 'i');
$records = findDocs($manager, $dbName, $payrollCollection, $filter, ['sort' => ['name' => 1]]);

$totalGross  = array_sum(array_map(fn($r) => $r->gross   ?? 0, $records));
$totalNet    = array_sum(array_map(fn($r) => $r->net_pay ?? 0, $records));
$totalDeduct = array_sum(array_map(fn($r) => ($r->pf ?? 0) + ($r->tax ?? 0), $records));

layoutHeader('Export Payroll');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
   <h4 class="mb-0"><i class="bi bi-download me-2"></i>Export Payroll</h4>
</div>

<!-- Filters -->
<form method="GET" class="card shadow-sm mb-4">
   <div class="card-body py-2">
       <div class="row g-2 align-items-end">
           <div class="col-md-3">
               <label class="form-label mb-1 small">Month</label>
               <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($filterMonth) ?>">
           </div>
           <div class="col-md-4">
               <label class="form-label mb-1 small">Employee Name</label>
               <input type="text" name="name" class="form-control form-control-sm" placeholder="Filter by name..." value="<?= htmlspecialchars($filterName) ?>">
           </div>
           <div class="col-auto d-flex gap-2 flex-wrap">
               <button type="submit" class="btn btn-outline-primary btn-sm">
                   <i class="bi bi-funnel"></i> Preview
               </button>
               <a href="?export=1&month=<?= urlencode($filterMonth) ?>&name=<?= urlencode($filterName) ?>"
                  class="btn btn-success btn-sm">
                   <i class="bi bi-file-earmark-spreadsheet me-1"></i>Download CSV
               </a>
           </div>
       </div>
   </div>
</form>

<!-- Summary -->
<div class="row g-3 mb-4">
   <div class="col-md-3">
       <div class="card text-center border-0 bg-primary bg-opacity-10">
           <div class="card-body py-2">
               <div class="small text-muted">Records</div>
               <div class="fs-5 fw-bold text-primary"><?= count($records) ?></div>
           </div>
       </div>
   </div>
   <div class="col-md-3">
       <div class="card text-center border-0 bg-success bg-opacity-10">
           <div class="card-body py-2">
               <div class="small text-muted">Total Gross</div>
               <div class="fs-6 fw-bold text-success">₹<?= number_format($totalGross) ?></div>
           </div>
       </div>
   </div>
   <div class="col-md-3">
       <div class="card text-center border-0 bg-danger bg-opacity-10">
           <div class="card-body py-2">
               <div class="small text-muted">Total Deductions</div>
               <div class="fs-6 fw-bold text-danger">₹<?= number_format($totalDeduct) ?></div>
           </div>
       </div>
   </div>
   <div class="col-md-3">
       <div class="card text-center border-0 bg-warning bg-opacity-10">
           <div class="card-body py-2">
               <div class="small text-muted">Total Net Pay</div>
               <div class="fs-6 fw-bold text-warning">₹<?= number_format($totalNet) ?></div>
           </div>
       </div>
   </div>
</div>

<!-- Preview Table -->
<div class="card shadow-sm">
   <div class="card-header bg-white fw-semibold">
       <i class="bi bi-table me-2"></i>Preview — <?= htmlspecialchars($filterMonth) ?>
   </div>
   <div class="card-body p-0">
       <div class="table-responsive">
           <table class="table table-hover align-middle mb-0" style="font-size:0.9rem;">
               <thead class="table-light">
                   <tr>
                       <th>Emp ID</th>
                       <th>Name</th>
                       <th>Dept</th>
                       <th>Month</th>
                       <th>Basic</th>
                       <th>Gross</th>
                       <th>PF</th>
                       <th>Tax</th>
                       <th>Net Pay</th>
                       <th>Status</th>
                   </tr>
               </thead>
               <tbody>
               <?php if (empty($records)): ?>
               <tr>
                   <td colspan="10" class="text-center py-4 text-muted">No records found for this filter.</td>
               </tr>
               <?php else: ?>
               <?php foreach ($records as $r): ?>
               <?php
                   $st = $r->status ?? 'pending';
                   $bc = match($st) { 'approved' => 'bg-success', 'rejected' => 'bg-danger', default => 'bg-warning text-dark' };
               ?>
               <tr>
                   <td><?= htmlspecialchars($r->emp_id ?? '—') ?></td>
                   <td><?= htmlspecialchars($r->name ?? '—') ?></td>
                   <td><?= htmlspecialchars($r->department ?? '—') ?></td>
                   <td><?= htmlspecialchars($r->month ?? '—') ?></td>
                   <td>₹<?= number_format($r->basic ?? 0) ?></td>
                   <td>₹<?= number_format($r->gross ?? 0) ?></td>
                   <td class="text-danger">₹<?= number_format($r->pf ?? 0) ?></td>
                   <td class="text-danger">₹<?= number_format($r->tax ?? 0) ?></td>
                   <td><strong>₹<?= number_format($r->net_pay ?? 0) ?></strong></td>
                   <td><span class="badge <?= $bc ?>"><?= ucfirst($st) ?></span></td>
               </tr>
               <?php endforeach; ?>
               <?php endif; ?>
               </tbody>
           </table>
       </div>
   </div>
</div>

<?php layoutFooter(); ?>