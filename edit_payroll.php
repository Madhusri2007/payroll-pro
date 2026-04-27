<?php
session_start();
require_once("config/db.php");
require_once("layout.php");
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

$user = $_SESSION['user'];
$role = $user['role'];

if (!in_array($role, ['admin', 'hr', 'accountant'])) {
   header("Location: view_payroll.php");
   exit();
}

$msg = ""; $msgType = "success";

if (!isset($_GET['id'])) {
   header("Location: view_payroll.php");
   exit();
}

try {
   $record = findOne($manager, $dbName, $payrollCollection, [
       '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
   ]);
} catch (Exception $e) {
   header("Location: view_payroll.php");
   exit();
}

if (!$record) {
   header("Location: view_payroll.php");
   exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $basic = (float)$_POST['basic'];
   $hra   = (float)$_POST['hra'];
   $da    = (float)$_POST['da'];
   $pf    = (float)$_POST['pf'];
   $tax   = (float)$_POST['tax'];
   $gross = $basic + $hra + $da;
   $net   = $gross - $pf - $tax;

   updateDoc($manager, $dbName, $payrollCollection,
       ['_id' => new MongoDB\BSON\ObjectId($_GET['id'])],
       [
           'basic'    => $basic,
           'hra'      => $hra,
           'da'       => $da,
           'pf'       => $pf,
           'tax'      => $tax,
           'gross'    => round($gross, 2),
           'net_pay'  => round($net, 2),
           'status'   => 'pending',
           'edited_by'=> $user['username'],
           'edited_at'=> date('Y-m-d H:i:s')
       ]
   );
   $msg = "Payroll updated. Status reset to Pending.";
   $msgType = "success";

   // Reload record
   $record = findOne($manager, $dbName, $payrollCollection, [
       '_id' => new MongoDB\BSON\ObjectId($_GET['id'])
   ]);
}

layoutHeader('Edit Payroll');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
   <?= htmlspecialchars($msg) ?>
   <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
   <h4 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Payroll</h4>
   <a href="view_payroll.php" class="btn btn-outline-secondary btn-sm">
       <i class="bi bi-arrow-left me-1"></i>Back
   </a>
</div>

<div class="row g-4">
   <div class="col-md-5">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-person me-2 text-primary"></i><?= htmlspecialchars($record->name ?? 'â€”') ?>
               <span class="text-muted ms-1 fw-normal">(<?= htmlspecialchars($record->emp_id ?? '') ?>)</span>
               â€” <span class="badge bg-light text-dark"><?= htmlspecialchars($record->month ?? '') ?></span>
           </div>
           <div class="card-body">
               <form method="POST" id="editForm">
                   <div class="mb-3">
                       <label class="form-label fw-semibold">Basic Salary (&#8377;)</label>
                       <input type="number" name="basic" id="basic" class="form-control" step="0.01"
                              value="<?= $record->basic ?? 0 ?>" oninput="calcPreview()">
                   </div>
                   <div class="row g-2 mb-3">
                       <div class="col-6">
                           <label class="form-label">HRA (&#8377;)</label>
                           <input type="number" name="hra" id="hra" class="form-control form-control-sm" step="0.01"
                                  value="<?= $record->hra ?? 0 ?>" oninput="calcPreview()">
                       </div>
                       <div class="col-6">
                           <label class="form-label">DA (&#8377;)</label>
                           <input type="number" name="da" id="da" class="form-control form-control-sm" step="0.01"
                                  value="<?= $record->da ?? 0 ?>" oninput="calcPreview()">
                       </div>
                   </div>
                   <div class="row g-2 mb-3">
                       <div class="col-6">
                           <label class="form-label">PF Deduction (&#8377;)</label>
                           <input type="number" name="pf" id="pf" class="form-control form-control-sm" step="0.01"
                                  value="<?= $record->pf ?? 0 ?>" oninput="calcPreview()">
                       </div>
                       <div class="col-6">
                           <label class="form-label">Tax Deduction (&#8377;)</label>
                           <input type="number" name="tax" id="tax" class="form-control form-control-sm" step="0.01"
                                  value="<?= $record->tax ?? 0 ?>" oninput="calcPreview()">
                       </div>
                   </div>
                   <div class="alert alert-warning py-2 small mb-3">
                       <i class="bi bi-info-circle me-1"></i>Saving will reset status to <strong>Pending</strong>.
                   </div>
                   <button type="submit" class="btn btn-primary w-100">
                       <i class="bi bi-save me-1"></i>Save Changes
                   </button>
               </form>
           </div>
       </div>
   </div>

   <!-- Live Preview -->
   <div class="col-md-7">
       <div class="card shadow-sm">
           <div class="card-header bg-white fw-semibold">
               <i class="bi bi-calculator me-2 text-success"></i>Live Net Pay Preview
           </div>
           <div class="card-body" id="previewArea"></div>
       </div>
   </div>
</div>

<script>
function calcPreview() {
   const basic = parseFloat(document.getElementById('basic').value) || 0;
   const hra   = parseFloat(document.getElementById('hra').value)   || 0;
   const da    = parseFloat(document.getElementById('da').value)    || 0;
   const pf    = parseFloat(document.getElementById('pf').value)    || 0;
   const tax   = parseFloat(document.getElementById('tax').value)   || 0;
   const gross = basic + hra + da;
   const net   = gross - pf - tax;
    const fmt   = v => '₹' + v.toLocaleString('en-IN', {minimumFractionDigits: 2});

   document.getElementById('previewArea').innerHTML = `
       <table class="table table-sm mb-0">
           <tbody>
               <tr class="table-light"><td colspan="2" class="fw-semibold">Earnings</td></tr>
               <tr><td>Basic</td><td class="text-end">${fmt(basic)}</td></tr>
               <tr><td>HRA</td><td class="text-end">${fmt(hra)}</td></tr>
               <tr><td>DA</td><td class="text-end">${fmt(da)}</td></tr>
               <tr class="table-success fw-semibold"><td>Gross Pay</td><td class="text-end">${fmt(gross)}</td></tr>
               <tr class="table-light"><td colspan="2" class="fw-semibold">Deductions</td></tr>
               <tr><td>PF</td><td class="text-end text-danger">${fmt(pf)}</td></tr>
               <tr><td>Tax</td><td class="text-end text-danger">${fmt(tax)}</td></tr>
               <tr class="table-primary fw-bold fs-6"><td>Net Pay</td><td class="text-end">${fmt(net)}</td></tr>
           </tbody>
       </table>`;
}
calcPreview();
</script>

<?php layoutFooter(); ?>