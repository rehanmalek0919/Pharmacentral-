<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['admin', 'pharmacist']);
$msg = '';
$err = '';
$categories = [
    'Fever & Pain Relief',
    'Antibiotics',
    'Acidity & Gastric',
    'Blood Pressure',
    'Diabetes Care',
    'Pain Relief',
    'Allergy & Cold',
    'Vitamins & Supplements'
];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add_med'
) {
    if (!hasRole(['admin', 'pharmacist'])) {
        $err = "You do not have permission to add medicines.";
    } else {
        try {
            $name = trim($_POST['name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $stock = intval($_POST['stock_quantity'] ?? 0);
            $expiry = trim($_POST['expiry_date'] ?? '');
            if ($name === '') {
                throw new Exception("Medicine name is required.");
            }
            if ($category === '') {
                throw new Exception("Please select a category.");
            }
            if ($price < 0) {
                throw new Exception("Price cannot be negative.");
            }
            if ($stock < 0) {
                throw new Exception("Stock quantity cannot be negative.");
            }
            if ($expiry === '') {
                throw new Exception("Expiry date is required.");
            }
            $stmt = $db->prepare("
                INSERT INTO medicines
                (
                    name,
                    category,
                    price,
                    stock_quantity,
                    expiry_date
                )
                VALUES
                (
                    :n,
                    :c,
                    :p,
                    :s,
                    :e
                )
            ");
            $stmt->execute([
                ':n' => $name,
                ':c' => $category,
                ':p' => $price,
                ':s' => $stock,
                ':e' => $expiry
            ]);
            $msg = "Medicine added successfully!";
        } catch (Exception $e) {
            $err = "Error: " . $e->getMessage();
        }
    }
}

if (isset($_GET['download_sample']) && $_GET['download_sample'] === '1') {
    if (!hasRole(['admin', 'pharmacist'])) {
        http_response_code(403);
        exit('Access denied.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="PharmaCentral_Medicine_Sample.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Category', 'Price', 'Stock Quantity', 'Expiry Date']);
    fputcsv($output, ['Paracetamol 500mg', 'Fever & Pain Relief', '25.00', '100', '2027-05-20']);
    fputcsv($output, ['Amoxicillin 500mg', 'Antibiotics', '80.00', '50', '2027-08-15']);
    fputcsv($output, ['Cetirizine 10mg', 'Allergy & Cold', '35.00', '75', '2027-11-10']);
    fclose($output);
    exit;
}

$bulkResult = null;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'bulk_upload'
) {
    if (!hasRole(['admin', 'pharmacist'])) {
        $err = 'You do not have permission to perform bulk upload.';
    } elseif (!isset($_FILES['medicine_csv']) || !is_array($_FILES['medicine_csv'])) {
        $err = 'Please select a CSV file.';
    } else {
        $file = $_FILES['medicine_csv'];
        $handle = false;
        try {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed. Please select a valid CSV file.');
            }
            if (($file['size'] ?? 0) <= 0) {
                throw new Exception('The uploaded file is empty.');
            }
            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new Exception('CSV file size cannot exceed 5 MB.');
            }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                throw new Exception('Only CSV files are allowed. Please upload a .csv file.');
            }
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new Exception('Unable to read the uploaded CSV file.');
            }
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new Exception('The CSV file does not contain a header row.');
            }
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            $normalizedHeader = array_map(
                static function ($value) {
                    return strtolower(trim(preg_replace('/[^a-z0-9]+/i', ' ', (string)$value)));
                },
                $header
            );
            $requiredHeaders = ['name', 'category', 'price', 'stock quantity', 'expiry date'];
            if ($normalizedHeader !== $requiredHeaders) {
                throw new Exception('Invalid CSV header. Use: Name, Category, Price, Stock Quantity, Expiry Date');
            }
            $bulkResult = [
                'total' => 0,
                'success' => 0,
                'skipped' => 0,
                'errors' => []
            ];
            $insertStmt = $db->prepare('
                INSERT INTO medicines (name, category, price, stock_quantity, expiry_date)
                VALUES (:name, :category, :price, :stock, :expiry)
            ');
            $duplicateStmt = $db->prepare('
                SELECT id FROM medicines
                WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
                  AND LOWER(TRIM(category)) = LOWER(TRIM(:category))
                LIMIT 1
            ');
            $seenInFile = [];
            $db->beginTransaction();
            $lineNumber = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                $hasData = false;
                foreach ($row as $value) {
                    if (trim((string)$value) !== '') {
                        $hasData = true;
                        break;
                    }
                }
                if (!$hasData) {
                    continue;
                }
                $bulkResult['total']++;
                if (count($row) !== 5) {
                    $bulkResult['errors'][] = 'Row ' . $lineNumber . ': Expected exactly 5 columns.';
                    continue;
                }
                $name = trim((string)$row[0]);
                $category = trim((string)$row[1]);
                $priceInput = trim((string)$row[2]);
                $stockInput = trim((string)$row[3]);
                $expiry = trim((string)$row[4]);
                $rowErrors = [];
                $price = 0;
                $stock = 0;
                if ($name === '') {
                    $rowErrors[] = 'Medicine name is required';
                }
                if ($category === '') {
                    $rowErrors[] = 'Category is required';
                } elseif (!in_array($category, $categories, true)) {
                    $rowErrors[] = 'Invalid category';
                }
                if ($priceInput === '' || !is_numeric($priceInput)) {
                    $rowErrors[] = 'Invalid price';
                } else {
                    $price = (float)$priceInput;
                    if ($price < 0) {
                        $rowErrors[] = 'Price cannot be negative';
                    }
                }
                if ($stockInput === '' || !ctype_digit($stockInput)) {
                    $rowErrors[] = 'Invalid stock quantity';
                } else {
                    $stock = (int)$stockInput;
                }
                if ($expiry === '') {
                    $rowErrors[] = 'Expiry date is required';
                } else {
                    $expiryDate = DateTime::createFromFormat('Y-m-d', $expiry);
                    if (!$expiryDate || $expiryDate->format('Y-m-d') !== $expiry) {
                        $rowErrors[] = 'Invalid expiry date; use YYYY-MM-DD';
                    }
                }
                if ($rowErrors) {
                    $bulkResult['errors'][] = 'Row ' . $lineNumber . ': ' . implode('; ', $rowErrors);
                    continue;
                }
                $fileKey = strtolower($name) . '|' . strtolower($category);
                if (isset($seenInFile[$fileKey])) {
                    $bulkResult['skipped']++;
                    $bulkResult['errors'][] = 'Row ' . $lineNumber . ': Duplicate medicine in the uploaded file; skipped.';
                    continue;
                }
                $seenInFile[$fileKey] = true;
                $duplicateStmt->execute([
                    ':name' => $name,
                    ':category' => $category
                ]);
                if ($duplicateStmt->fetch()) {
                    $bulkResult['skipped']++;
                    $bulkResult['errors'][] = 'Row ' . $lineNumber . ': Medicine already exists in inventory; skipped.';
                    continue;
                }
                $insertStmt->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':price' => $price,
                    ':stock' => $stock,
                    ':expiry' => $expiry
                ]);
                $bulkResult['success']++;
            }
            fclose($handle);
            $handle = false;
            $db->commit();
            if ($bulkResult['success'] > 0) {
                $msg = $bulkResult['success'] . ' medicine(s) imported successfully.';
            } else {
                $msg = 'No new medicines were imported.';
            }
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $err = 'Bulk upload failed: ' . $e->getMessage();
            $bulkResult = null;
        }
    }
}

if (
    isset($_GET['action'], $_GET['id']) &&
    $_GET['action'] === 'delete'
) {
    if (!hasRole('admin')) {
        http_response_code(403);
        echo '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Access Denied</title>
            <style>
                body {
                    margin:0;
                    min-height:100vh;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#f1f5f9;
                    font-family:Arial,sans-serif;
                }
                .access-box {
                    width:430px;
                    max-width:90%;
                    background:white;
                    padding:40px;
                    text-align:center;
                    border-radius:18px;
                    box-shadow:0 15px 40px rgba(0,0,0,.15);
                }
                .access-box h1 {
                    color:#dc2626;
                    margin-bottom:10px;
                }
                .access-box p {
                    color:#475569;
                    line-height:1.6;
                }
                .back-btn {
                    display:inline-block;
                    margin-top:20px;
                    padding:12px 22px;
                    background:#1976d2;
                    color:white;
                    text-decoration:none;
                    border-radius:8px;
                }
            </style>
        </head>
        <body>
            <div class="access-box">
                <h1>🚫 Access Denied</h1>
                <p>
                    Only the Administrator can delete medicines.
                </p>
                <a class="back-btn" href="inventory.php">
                    Back to Medicines
                </a>
            </div>
        </body>
        </html>
        ';
        exit;
    }

    $medicineId = intval($_GET['id'] ?? 0);
    if ($medicineId > 0) {
        try {
            $stmt = $db->prepare("
                DELETE FROM medicines
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $medicineId
            ]);
            $msg = "Medicine deleted successfully.";
        } catch (Exception $e) {
            $err = "Unable to delete medicine: " . $e->getMessage();
        }
    } else {
        $err = "Invalid medicine ID.";
    }
}

$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT *,
        CASE
            WHEN expiry_date < :today
            THEN 1
            ELSE 0
        END AS expired
    FROM medicines
    ORDER BY name
");
$stmt->execute([
    ':today' => $today
]);
$medicines = $stmt->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert-ok">
        <i class="ri-checkbox-circle-fill"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert-err">
        <i class="ri-error-warning-fill"></i>
        <?= htmlspecialchars($err) ?>
    </div>
<?php endif; ?>

<?php if (hasRole(['admin', 'pharmacist'])): ?>
<div class="card">
    <div class="card-title">
        <span>
            <i class="ri-upload-cloud-2-line" style="color:#0284c7;"></i>
            Bulk Upload Medicines
        </span>
    </div>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:16px;">
        <strong>CSV columns:</strong>
        <span style="color:#475569;">Name, Category, Price, Stock Quantity, Expiry Date</span>
        <div style="margin-top:8px;color:#64748b;font-size:.85rem;">
            Expiry date must use <strong>YYYY-MM-DD</strong>.
            Existing medicine with the same name and category will be skipped.
        </div>
    </div>
    <form method="POST" action="inventory.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="bulk_upload">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="file" name="medicine_csv" accept=".csv,text/csv" required
                style="padding:9px;border:1px solid #e2e8f0;border-radius:7px;background:#fff;">
            <button type="submit" class="btn btn-primary"
                onclick="return confirm('Are you sure you want to import medicines from this CSV file?');">
                <i class="ri-upload-2-line"></i>
                Upload CSV
            </button>
            <a href="inventory.php?download_sample=1" class="btn btn-secondary"
                style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="ri-file-download-line"></i>
                Download Sample CSV
            </a>
        </div>
        <div style="margin-top:10px;color:#64748b;font-size:.82rem;">
            Maximum file size: 5 MB. Only CSV files are accepted.
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (is_array($bulkResult)): ?>
<div class="card">
    <div class="card-title">
        <span>
            <i class="ri-file-list-3-line" style="color:#0284c7;"></i>
            Bulk Upload Result
        </span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:15px;">
        <div style="padding:14px;background:#f8fafc;border-radius:10px;">
            <strong>Total Rows</strong>
            <div style="font-size:1.35rem;margin-top:5px;"><?= (int)$bulkResult['total'] ?></div>
        </div>
        <div style="padding:14px;background:#ecfdf5;border-radius:10px;">
            <strong>Imported</strong>
            <div style="font-size:1.35rem;margin-top:5px;color:#15803d;"><?= (int)$bulkResult['success'] ?></div>
        </div>
        <div style="padding:14px;background:#fff7ed;border-radius:10px;">
            <strong>Skipped</strong>
            <div style="font-size:1.35rem;margin-top:5px;color:#c2410c;"><?= (int)$bulkResult['skipped'] ?></div>
        </div>
        <div style="padding:14px;background:#fef2f2;border-radius:10px;">
            <strong>Errors</strong>
            <div style="font-size:1.35rem;margin-top:5px;color:#dc2626;"><?= count($bulkResult['errors']) ?></div>
        </div>
    </div>

    <?php if (!empty($bulkResult['errors'])): ?>
    <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px;">
        <strong style="color:#9a3412;">Rows requiring attention</strong>
        <ul style="margin:10px 0 0 20px;color:#7c2d12;">
            <?php foreach ($bulkResult['errors'] as $bulkError): ?>
                <li style="margin-bottom:5px;"><?= htmlspecialchars($bulkError) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (hasRole(['admin', 'pharmacist'])): ?>
<div class="card">
    <div class="card-title">
        <span>
            <i
                class="ri-add-circle-line"
                style="color:#0284c7;"
            ></i>
            Add New Medicine
        </span>
    </div>
    <form
        method="POST"
        action="inventory.php"
    >
        <input
            type="hidden"
            name="action"
            value="add_med"
        >
        <div class="form-grid">
            <div class="form-group">
                <label>
                    Medicine Name *
                </label>
                <input
                    type="text"
                    name="name"
                    required
                    placeholder="e.g. Dolo 650mg Tablet"
                >
            </div>
            <div class="form-group">
                <label>
                    Category *
                </label>
                <select
                    name="category"
                    required
                >
                    <option value="">
                        -- Select --
                    </option>
                    <option>
                        Fever &amp; Pain Relief
                    </option>
                    <option>
                        Antibiotics
                    </option>
                    <option>
                        Acidity &amp; Gastric
                    </option>
                    <option>
                        Blood Pressure
                    </option>
                    <option>
                        Diabetes Care
                    </option>
                    <option>
                        Pain Relief
                    </option>
                    <option>
                        Allergy &amp; Cold
                    </option>
                    <option>
                        Vitamins &amp; Supplements
                    </option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    Selling Price (₹) *
                </label>
                <input
                    type="number"
                    name="price"
                    step="0.01"
                    min="0"
                    required
                    placeholder="0.00"
                >
            </div>
            <div class="form-group">
                <label>
                    Stock Quantity *
                </label>
                <input
                    type="number"
                    name="stock_quantity"
                    min="0"
                    required
                    placeholder="100"
                >
            </div>
            <div class="form-group">
                <label>
                    Expiry Date *
                </label>
                <input
                    type="date"
                    name="expiry_date"
                    required
                >
            </div>
        </div>
        <button
            type="submit"
            class="btn btn-primary"
        >
            <i class="ri-save-line"></i>
            Add to Stock
        </button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">
        <span>
            <i
                class="ri-medicine-bottle-line"
                style="color:#0284c7;"
            ></i>
            Medicine Stock List
            (<span id="medicineCount"><?= count($medicines) ?> items</span>)
        </span>
<div style="
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
">
    <input
        type="text"
        id="medicineSearch"
        class="search-input"
        placeholder="Search medicine..."
        autocomplete="off"
        style="
            font-size:0.85rem;
            padding:0.55rem 0.8rem;
            border:1px solid #e2e8f0;
            border-radius:6px;
            outline:none;
            width:220px;
        "
    >
    <select
        id="categoryFilter"
        style="
            font-size:0.85rem;
            padding:0.55rem 0.8rem;
            border:1px solid #e2e8f0;
            border-radius:6px;
            outline:none;
            width:210px;
            background:#fff;
            cursor:pointer;
        "
    >
        <option value="">All Categories</option>
        <option value="Fever & Pain Relief">
            Fever &amp; Pain Relief
        </option>
        <option value="Antibiotics">
            Antibiotics
        </option>
        <option value="Acidity & Gastric">
            Acidity &amp; Gastric
        </option>
        <option value="Blood Pressure">
            Blood Pressure
        </option>
        <option value="Diabetes Care">
            Diabetes Care
        </option>
        <option value="Pain Relief">
            Pain Relief
        </option>
        <option value="Allergy & Cold">
            Allergy &amp; Cold
        </option>
        <option value="Vitamins & Supplements">
            Vitamins &amp; Supplements
        </option>
    </select>
</div>
    </div>
    <table
        class="table"
        id="med-table"
    >
        <thead>
            <tr>
                <th>No</th>
                <th>Medicine Name</th>
                <th>Category</th>
                <th>Price (₹)</th>
                <th>Stock</th>
                <th>Expiry</th>
                <th width="180">
                    Actions
                </th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($medicines as $i => $m): ?>
            <tr
                <?= (
                    $m['expiry_date'] < $today
                )
                    ? 'style="background:#ffe5e5;"'
                    : ''
                ?>
            >
                <td>
                    <?= $i + 1 ?>
                </td>
                <td>
                    <strong>
                        <?= htmlspecialchars($m['name']) ?>
                    </strong>
                </td>
                <td>
                    <span class="badge badge-info">
                        <?= htmlspecialchars($m['category']) ?>
                    </span>
                </td>
                <td
                    style="
                        font-weight:700;
                        color:#0284c7;
                    "
                >
                    ₹<?= number_format(
                        $m['price'],
                        2
                    ) ?>
                </td>
                <td>
                    <?php
                    if ($m['expiry_date'] < $today) {
                        echo '
                            <span class="badge badge-danger">
                                Expired
                            </span>
                        ';
                    } elseif ($m['stock_quantity'] <= 15) {
                        echo '
                            <span class="badge badge-warning">
                                ' .
                                (int)$m['stock_quantity'] .
                                ' Low
                            </span>
                        ';
                    } else {
                        echo '
                            <span class="badge badge-success">
                                ' .
                                (int)$m['stock_quantity'] .
                            '</span>
                        ';
                    }
                    ?>
                </td>
                <td
                    style="
                        color:#64748b;
                        font-size:0.82rem;
                    "
                >
                    <?= date(
                        'd-m-Y',
                        strtotime($m['expiry_date'])
                    ) ?>
                </td>
                <td>
                    <?php if (hasRole(['admin', 'pharmacist'])): ?>
                        <a
                            href="edit_medicine.php?id=<?= (int)$m['id'] ?>"
                            class="btn btn-primary btn-sm"
                        >
                            <i class="ri-edit-line"></i>
                            Edit
                        </a>
                    <?php endif; ?>

                    <?php if (hasRole('admin')): ?>
                        <a
                            href="inventory.php?action=delete&id=<?= (int)$m['id'] ?>"
                            onclick="
                                return confirm(
                                    'Are you sure you want to delete this medicine?'
                                );
                            "
                            class="btn btn-secondary btn-sm"
                            style="
                                background:#ef4444;
                                color:#fff;
                            "
                        >
                            <i class="ri-delete-bin-line"></i>
                            Delete
                        </a>
                    <?php endif; ?>

                    <?php if (
                        !hasRole(['admin', 'pharmacist'])
                    ): ?>
                        <span
                            style="
                                color:#64748b;
                                font-size:.85rem;
                            "
                        >
                            View Only

                        </span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        
        <?php if (count($medicines) === 0): ?>
            <tr>
                <td colspan="7" style="text-align:center; padding:30px; color:#64748b;">
                    No medicines found.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('medicineSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const table = document.getElementById('med-table');
    if (!searchInput || !categoryFilter || !table) {
        return;
    }
    const rows = table.querySelectorAll('tbody tr');
    function filterMedicines() {
        const searchText = searchInput.value
            .trim()
            .toLowerCase();
        const selectedCategory = categoryFilter.value
            .trim()
            .toLowerCase();
        let visibleCount = 0;
        rows.forEach(function (row) {
            if (row.cells.length < 7) {
                return;
            }

            const medicineName = row.cells[1]
                ? row.cells[1].textContent.trim().toLowerCase()
                : '';
            const category = row.cells[2]
                ? row.cells[2].textContent.trim().toLowerCase()
                : '';
            const matchesSearch =
                searchText === '' ||
                medicineName.includes(searchText) ||
                category.includes(searchText);
            const matchesCategory =
                selectedCategory === '' ||
                category === selectedCategory;

            if (matchesSearch && matchesCategory) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        const countText = document.getElementById('medicineCount');
        if (countText) {
            countText.textContent = visibleCount + ' items';
        }

    }
    searchInput.addEventListener('input', filterMedicines);
    categoryFilter.addEventListener('change', filterMedicines);

});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csvInput = document.querySelector('input[name="medicine_csv"]');
    if (csvInput) {
        csvInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (file && !file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a CSV file only.');
                this.value = '';
            }
        });
    }
    const searchInput = document.getElementById('medicineSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const table = document.getElementById('med-table');
    if (!searchInput || !categoryFilter || !table) return;
    const rows = table.querySelectorAll('tbody tr');
    function filterMedicines() {
        const searchText = searchInput.value.trim().toLowerCase();
        const selectedCategory = categoryFilter.value.trim().toLowerCase();
        let visibleCount = 0;
        rows.forEach(function (row) {
            if (row.cells.length < 7) return;
            const medicineName = row.cells[1] ? row.cells[1].textContent.trim().toLowerCase() : '';
            const category = row.cells[2] ? row.cells[2].textContent.trim().toLowerCase() : '';
            const matchesSearch = searchText === '' ||
                medicineName.includes(searchText) ||
                category.includes(searchText);
            const matchesCategory = selectedCategory === '' || category === selectedCategory;

            row.style.display = matchesSearch && matchesCategory ? '' : 'none';
            if (matchesSearch && matchesCategory) visibleCount++;
        });
        const countText = document.getElementById('medicineCount');
        if (countText) countText.textContent = visibleCount + ' items';
    }
    searchInput.addEventListener('input', filterMedicines);
    categoryFilter.addEventListener('change', filterMedicines);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>