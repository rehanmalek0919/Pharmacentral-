<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['admin', 'pharmacist']);
require_once __DIR__ . '/includes/header.php';

if(!isset($_GET['id'])){
    die("Medicine not found.");
}
$id=(int)$_GET['id'];
$stmt=$db->prepare("SELECT * FROM medicines WHERE id=?");
$stmt->execute([$id]);
$medicine=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$medicine){
    die("Medicine not found.");
}
$msg="";
$err="";
if($_SERVER['REQUEST_METHOD']=="POST"){
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = $_POST['price'] ?? '';
    $stock = $_POST['stock_quantity'] ?? '';
    $expiry = trim($_POST['expiry_date'] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $expiry);

    if ($name === '' || $category === '') {
        $err = "Name and category are required.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $err = "Price must be a number and cannot be negative.";
    } elseif (filter_var($stock, FILTER_VALIDATE_INT) === false || (int)$stock < 0) {
        $err = "Stock must be a whole number and cannot be negative.";
    } elseif (!$d || $d->format('Y-m-d') !== $expiry) {
        $err = "Please enter a valid expiry date.";
    } else {
        $stmt=$db->prepare("UPDATE medicines SET name=?, category=?, price=?, stock_quantity=?, expiry_date=? WHERE id=? ");
        $stmt->execute([$name, $category, (float)$price, (int)$stock, $expiry, $id]);
        $msg="Medicine updated successfully.";
    }
    $stmt=$db->prepare("SELECT * FROM medicines WHERE id=?");
    $stmt->execute([$id]);
    $medicine=$stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<div class="card">
    <h2>Edit Medicine</h2>
    <?php if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] < date('Y-m-d')) { ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:12px;">
            ⚠ This medicine has expired and cannot be sold. Update the expiry date only if a new batch was received.
        </div>
    <?php } ?>
    <?php if($msg) { ?>
        <div class="alert-ok"> <?= htmlspecialchars($msg) ?>
        </div> <?php } ?>
    <?php if($err) { ?>
        <div class="alert-ok" style="background:#fee2e2;color:#991b1b;border-color:#fecaca;"> <?= htmlspecialchars($err) ?>
        </div> <?php } ?>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Medicine Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($medicine['name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category">
                    <option <?= $medicine['category']=="Fever & Pain Relief"?"selected":"" ?>> Fever & Pain Relief </option>
                    <option <?= $medicine['category']=="Antibiotics"?"selected":"" ?>> Antibiotics </option>
                    <option <?= $medicine['category']=="Acidity & Gastric"?"selected":"" ?>> Acidity & Gastric </option>
                    <option <?= $medicine['category']=="Blood Pressure"?"selected":"" ?>> Blood Pressure </option>
                    <option <?= $medicine['category']=="Diabetes Care"?"selected":"" ?>> Diabetes Care </option>
                    <option <?= $medicine['category']=="Pain Relief"?"selected":"" ?>> Pain Relief </option>
                    <option <?= $medicine['category']=="Allergy & Cold"?"selected":"" ?>> Allergy & Cold </option>
                    <option <?= $medicine['category']=="Vitamins & Supplements"?"selected":"" ?>> Vitamins & Supplements </option>
                </select>
            </div>
            <div class="form-group">
                <label>Price</label>
                <input type="number" step="0.01" name="price" min="0" value="<?= $medicine['price'] ?>" required>
            </div>
            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" name="stock_quantity" min="0" value="<?= $medicine['stock_quantity'] ?>" required>
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?= $medicine['expiry_date'] ?>" required>
            </div>
        </div>
        <button class="btn btn-success">
            <i class="ri-save-line"></i> Update Medicine
        </button>
        <a href="inventory.php" class="btn btn-secondary"> Cancel </a>
    </form>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>