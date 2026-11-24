<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['admin', 'pharmacist']);
require_once __DIR__.'/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$sale){
    die("Sale not found.");
}
if($_SERVER['REQUEST_METHOD']=="POST"){
    $customerName  = trim($_POST['customer_name']);
    $doctorName    = trim($_POST['doctor_name']);
    $paymentMethod = trim($_POST['payment_method']);
    $transactionId = trim($_POST['transaction_id']);
    if($paymentMethod == "Cash"){
        $transactionId = "";
    }
    $stmt = $db->prepare("
        UPDATE sales
        SET customer_name = ?,
            doctor_name = ?,
            payment_method = ?,
            transaction_id = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $customerName,
        $doctorName,
        $paymentMethod,
        $transactionId,
        $id
    ]);
    header("Location: sales.php?updated=1");
    exit;
}
?>
<div class="card" style="max-width:700px;margin:auto;">
<h2>Edit Sale</h2>
<form method="post">
    <label>Customer Name</label>
        <input type="text" name="customer_name"
            value="<?= htmlspecialchars($sale['customer_name']) ?>" required>
            <br><br> 
    <label>Doctor Name</label>  
        <input type="text" name="doctor_name"
            value="<?= htmlspecialchars($sale['doctor_name']) ?>">
            <br><br>           
    <label>Payment Method</label>
        <select name="payment_method" id="payment_method" onchange="toggleTransaction()">
            <option value="Cash" <?= $sale['payment_method']=="Cash"?"selected":"" ?>>Cash</option>
            <option value="UPI" <?= $sale['payment_method']=="UPI"?"selected":"" ?>>UPI</option>
            <option value="Card" <?= $sale['payment_method']=="Card"?"selected":"" ?>>Card</option>
            <option value="Credit" <?= $sale['payment_method']=="Credit"?"selected":"" ?>>Credit</option>
        </select>
        <br><br>
    <div id="transaction_box">
    <label>Transaction ID</label>
        <input type="text" name="transaction_id" id="transaction_id"
            value="<?= htmlspecialchars($sale['transaction_id'] ?? '') ?>"
            placeholder="Enter Transaction ID">
    </div>
    <br>
    <button class="btn btn-primary">Update Sale</button>
        <a href="sales.php" class="btn btn-secondary">Cancel</a>
</form>
</div>
<script>
    
function toggleTransaction(){
    let payment=document.getElementById("payment_method").value;
    let box=document.getElementById("transaction_box");
    let input=document.getElementById("transaction_id");
    if(payment=="Cash"){
        box.style.display="none";
        input.value="";
    }else{
        box.style.display="block";
    }
}
toggleTransaction();
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>