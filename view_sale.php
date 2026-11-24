<?php
require_once __DIR__.'/includes/header.php';
if(!isset($_GET['id'])){
    die("Invalid Sale ID");
}
$id = (int)$_GET['id'];
$stmt = $db->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$sale){
    die("Sale not found.");
}
?>
<div class="card">
<h2>Invoice Details</h2>
<table class="table">
    <tr>
        <th>Invoice No</th>
        <td><?= htmlspecialchars($sale['invoice_no']) ?></td>
    </tr>
    <tr>
        <th>Customer</th>
        <td><?= htmlspecialchars($sale['customer_name']) ?></td>
    </tr>
    <tr>
        <th>Phone</th>
        <td><?= htmlspecialchars($sale['customer_phone']) ?></td>
    </tr>
    <tr>
        <th>Doctor</th>
        <td><?= htmlspecialchars($sale['doctor_name']) ?></td>
    </tr>
    <tr>
        <th>Subtotal</th>
        <td>₹<?= number_format($sale['subtotal'],2) ?></td>
    </tr>
    <tr>
        <th>GST</th>
        <td>₹<?= number_format($sale['gst_amount'],2) ?></td>
    </tr>
    <tr>
        <th>Discount</th>
        <td>₹<?= number_format($sale['discount'],2) ?></td>
    </tr>
    <tr>
        <th>Total</th>
        <td><strong>₹<?= number_format($sale['total_amount'],2) ?></strong></td>
    </tr>
    <tr>
        <th>Payment</th>
        <td><?= htmlspecialchars($sale['payment_method']) ?></td>
    </tr>
    <tr>
        <th>Transaction ID</th>
        <td><?= htmlspecialchars($sale['transaction_id']) ?></td>
    </tr>
    <tr>
        <th>Date</th>
        <td><?= date('d-m-Y h:i A',strtotime($sale['created_at'])) ?></td>
    </tr>
</table>    
    <br>
        <a href="sales.php" class="btn btn-secondary">Back</a>
    </div>
<?php require_once __DIR__.'/includes/footer.php'; ?>