<?php
require_once __DIR__ . '/includes/header.php';
date_default_timezone_set('Asia/Kolkata');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');
$payment  = trim($_GET['payment'] ?? '');
$search   = trim($_GET['search'] ?? '');
if ($fromDate !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $fromDate);
    if (!$date || $date->format('Y-m-d') !== $fromDate) {
        $fromDate = '';
    }
}
if ($toDate !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $toDate);
    if (!$date || $date->format('Y-m-d') !== $toDate) {
        $toDate = '';
    }
}
$dateError = '';
if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {
    $dateError = 'From Date cannot be greater than To Date.';
    $fromDate = '';
    $toDate = '';
}

$allowedPayments = [
    '',
    'Cash',
    'UPI',
    'Card',
    'Credit'
];
if (!in_array($payment, $allowedPayments, true)) {
    $payment = '';
}

$where = [];
$params = [];
if ($fromDate !== '') {
    $where[] = "created_at >= ?";
    $params[] = $fromDate . ' 00:00:00';
}

if ($toDate !== '') {
    $nextDate = date(
        'Y-m-d',
        strtotime($toDate . ' +1 day')
    );
    $where[] = "created_at < ?";
    $params[] = $nextDate . ' 00:00:00';
}

if ($payment !== '') {
    $where[] = "LOWER(payment_method) = LOWER(?)";
    $params[] = $payment;
}

if ($search !== '') {
    $where[] = "(
        invoice_no LIKE ?
        OR customer_name LIKE ?
        OR customer_phone LIKE ?
        OR doctor_name LIKE ?
        OR transaction_id LIKE ?
    )";
    $searchValue = '%' . $search . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

$stmt = $db->prepare("
    SELECT
        id,
        invoice_no,
        customer_name,
        customer_phone,
        doctor_name,
        subtotal,
        gst_amount,
        discount,
        total_amount,
        payment_method,
        transaction_id,
        created_at
    FROM sales
    $whereSQL
    ORDER BY created_at DESC, id DESC
");
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalInvoices = count($sales);
$totalSubtotal = 0;
$totalGST = 0;
$totalDiscount = 0;
$totalRevenue = 0;
foreach ($sales as $sale) {
    $subtotal = (float)($sale['subtotal'] ?? 0);
    $gst = (float)($sale['gst_amount'] ?? 0);
    $discount = (float)($sale['discount'] ?? 0);
    $total = (float)($sale['total_amount'] ?? 0);
    $totalSubtotal += $subtotal;
    $totalGST += $gst;
    $totalDiscount += $discount;
    $totalRevenue += $total;
}

$exportParams = [];
if ($fromDate !== '') {
    $exportParams['from_date'] = $fromDate;
}
if ($toDate !== '') {
    $exportParams['to_date'] = $toDate;
}
if ($payment !== '') {
    $exportParams['payment'] = $payment;
}
if ($search !== '') {
    $exportParams['search'] = $search;
}
$exportURL = 'export_sales.php';
if (!empty($exportParams)) {
    $exportURL .= '?' .
        http_build_query($exportParams);
}
?>

<style>
.sales-filter-card {
    margin-bottom: 20px;
}
.sales-filter-grid {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(180px, 1fr));
    gap: 15px;
    align-items: end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.filter-group label {
    font-size: 14px;
    font-weight: 700;
    color: #334155;
}
.filter-group input,
.filter-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    font-size: 14px;
}
.filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
}
.filter-btn {
    border: none;
    border-radius: 8px;
    padding: 11px 17px;
    cursor: pointer;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: .2s;
}
.filter-btn:hover {
    transform: translateY(-1px);
    opacity: .92;
}
.btn-filter {
    background: #0284c7;
    color: white;
}
.btn-reset {
    background: #64748b;
    color: white;
}
.btn-excel {
    background: #16a34a;
    color: white;
}
.filter-info {
    margin-top: 15px;
    padding: 11px 14px;
    background: #eff6ff;
    border-left: 4px solid #0284c7;
    border-radius: 6px;
    color: #334155;
    font-size: 14px;
}
.filter-error {
    margin-bottom: 15px;
    padding: 12px 15px;
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #dc2626;
    border-radius: 6px;
    font-weight: 600;
}
.sales-table-wrapper {
    width: 100%;
    overflow-x: auto;
}
.sales-table {
    width: 100%;
    min-width: 1400px;
    border-collapse: collapse;
}
.sales-table th {
    background: #f8fafc;
    color: #334155;
    padding: 13px;
    border-bottom: 2px solid #e2e8f0;
    font-size: 13px;
    text-align: left;
    white-space: nowrap;
}
.sales-table td {
    padding: 13px;
    border-bottom: 1px solid #edf2f7;
    font-size: 14px;
    white-space: nowrap;
}
.sales-table tbody tr:hover {
    background: #f8fbff;
}
.invoice-number {
    font-weight: 700;
    color: #0284c7;
}
.customer-name {
    font-weight: 600;
    color: #0f172a;
}
.customer-phone {
    color: #64748b;
    font-size: 12px;
    margin-top: 3px;
}
.amount {
    font-weight: 600;
}
.grand-total {
    color: #0284c7;
    font-weight: 800;
}
.payment-badge {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 20px;
    color: white;
    font-size: 12px;
    font-weight: 700;
}
.transaction-id {
    color: #475569;
    font-size: 13px;
}
.action-btn {
    width: 34px;
    height: 34px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    margin-right: 4px;
    transition: .2s;
}
.action-btn:hover {
    transform: translateY(-2px);
}
.view-btn {
    background: #2563eb;
}
.edit-btn {
    background: #16a34a;
}
.delete-btn {
    background: #dc2626;
}
@media (max-width: 900px) {
    .sales-filter-grid {
        grid-template-columns:
            repeat(2, minmax(180px, 1fr));
    }
}
@media (max-width: 600px) {
    .sales-filter-grid {
        grid-template-columns: 1fr;
    }
    .filter-buttons {
        flex-direction: column;
    }
    .filter-btn {
        width: 100%;
    }
}
</style>
<div class="grid-summary" style="margin-bottom: 1.5rem;">
    <div class="summary-card"
         style="border-left-color:#0284c7;">
        <p>Total Invoices</p>
        <h2 style="color:#0284c7;">
            <?= $totalInvoices ?>
        </h2>
    </div>
    <div class="summary-card"
         style="border-left-color:#0d9488;">
        <p>Total Revenue</p>
        <h2 style="color:#0d9488;">
            ₹<?= number_format(
                $totalRevenue,
                2
            ) ?>
        </h2>
    </div>
    <div class="summary-card"
         style="border-left-color:#7c3aed;">
        <p>Total GST</p>
        <h2 style="color:#7c3aed;">
            ₹<?= number_format(
                $totalGST,
                2
            ) ?>
        </h2>
    </div>
    <div class="summary-card"
         style="border-left-color:#f59e0b;">
        <p>Total Discount</p>
        <h2 style="color:#f59e0b;">
            ₹<?= number_format(
                $totalDiscount,
                2
            ) ?>
        </h2>
    </div>
</div>
<div class="card sales-filter-card">
    <div class="card-title">
        <span>
            <i class="ri-filter-3-line"
               style="color:#0284c7;"></i>
            Sales Filters
        </span>
    </div>
    <?php if ($dateError !== ''): ?>
        <div class="filter-error">
            <?= htmlspecialchars(
                $dateError
            ) ?>
        </div>
    <?php endif; ?>
    <form
        method="GET"
        action="sales.php"
    >
        <div class="sales-filter-grid">
            <div class="filter-group">
                <label for="from_date">
                    From Date
                </label>
                <input
                    type="date"
                    id="from_date"
                    name="from_date"
                    value="<?= htmlspecialchars(
                        $fromDate
                    ) ?>"
                >
            </div>
            <div class="filter-group">
                <label for="to_date">
                    To Date
                </label>
                <input
                    type="date"
                    id="to_date"
                    name="to_date"
                    value="<?= htmlspecialchars(
                        $toDate
                    ) ?>"
                >
            </div>
            <div class="filter-group">
                <label for="payment">
                    Payment Method
                </label>
                <select
                    id="payment"
                    name="payment"
                >
                    <option value="">
                        All Payments
                    </option>
                    <option value="Cash"
                        <?= $payment === 'Cash'
                            ? 'selected'
                            : '' ?>>
                        Cash
                    </option>
                    <option value="UPI"
                        <?= $payment === 'UPI'
                            ? 'selected'
                            : '' ?>>
                        UPI
                    </option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">
                    Search
                </label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars(
                        $search
                    ) ?>"
                    placeholder="Invoice / Customer / Phone"
                >
            </div>
        </div>
        <div class="filter-buttons">
            <button
                type="submit"
                class="filter-btn btn-filter"
            >
                <i class="ri-search-line"></i>
                Apply Filter
            </button>
            <a
                href="sales.php"
                class="filter-btn btn-reset"
            >
                <i class="ri-refresh-line"></i>
                Reset
            </a>
            <a
                href="<?= htmlspecialchars(
                    $exportURL
                ) ?>"
                class="filter-btn btn-excel"
            >
                <i class="ri-file-excel-2-line"></i>
                Download Excel
            </a>
        </div>
        <?php if (
            $fromDate !== '' ||
            $toDate !== '' ||
            $payment !== '' ||
            $search !== ''
        ): ?>
            <div class="filter-info">
                <strong>Showing:</strong>
                <?php if ($fromDate !== ''): ?>
                    <?= date(
                        'd-m-Y',
                        strtotime($fromDate)
                    ) ?>
                <?php else: ?>
                    All dates
                <?php endif; ?>
                <strong>to</strong>
                <?php if ($toDate !== ''): ?>
                    <?= date(
                        'd-m-Y',
                        strtotime($toDate)
                    ) ?>
                <?php else: ?>
                    All dates
                <?php endif; ?>
                <?php if ($payment !== ''): ?>
                    &nbsp; | &nbsp;
                    <strong>Payment:</strong>
                    <?= htmlspecialchars(
                        $payment
                    ) ?>
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                    &nbsp; | &nbsp;
                    <strong>Search:</strong>
                    <?= htmlspecialchars(
                        $search
                    ) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
</div>
<div class="card">
    <div class="card-title">
        <span>
            <i class="ri-receipt-line"
               style="color:#0284c7;"></i>
            Sales Records
        </span>
        <span style="
            color:#64748b;
            font-size:14px;
        ">
            <?= $totalInvoices ?>
            record(s)
        </span>
    </div>
    <?php if (empty($sales)): ?>
        <div style="
            padding:40px;
            text-align:center;
            color:#64748b;
        ">
            <i
                class="ri-file-search-line"
                style="
                    font-size:40px;
                    display:block;
                    margin-bottom:10px;
                "
            ></i>
            No sales records found.
        </div>
    <?php else: ?>
        <div class="sales-table-wrapper">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Doctor</th>
                        <th>Subtotal</th>
                        <th>GST</th>
                        <th>Discount</th>
                        <th>Grand Total</th>
                        <th>Payment</th>
                        <th>Transaction ID</th>
                        <th>Date & Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (
                    $sales as $index => $sale
                ): ?>
                    <?php
                    $paymentMethod =
                        $sale['payment_method']
                        ?? 'Cash';
                    $paymentColor =
                        '#64748b';
                    if (
                        strtolower(
                            $paymentMethod
                        ) === 'cash'
                    ) {
                        $paymentColor =
                            '#16a34a';
                    } elseif (
                        strtolower(
                            $paymentMethod
                        ) === 'upi'
                    ) {
                        $paymentColor =
                            '#2563eb';
                    } elseif (
                        strtolower(
                            $paymentMethod
                        ) === 'card'
                    ) {
                        $paymentColor =
                            '#7c3aed';
                    } elseif (
                        strtolower(
                            $paymentMethod
                        ) === 'credit'
                    ) {
                        $paymentColor =
                            '#ea580c';
                    }
                    ?>
                    <tr>
                        <td>
                            <?= $index + 1 ?>
                        </td>
                        <td>
                            <span
                                class="invoice-number"
                            >
                                <?= htmlspecialchars(
                                    $sale['invoice_no']
                                    ?? '-'
                                ) ?>
                            </span>
                        </td>
                        <td>
                            <div
                                class="customer-name"
                            >
                                <?= htmlspecialchars(
                                    $sale['customer_name']
                                    ?? '-'
                                ) ?>
                            </div>
                            <?php if (
                                !empty(
                                    $sale['customer_phone']
                                )
                            ): ?>
                                <div
                                    class="customer-phone"
                                >
                                    <?= htmlspecialchars(
                                        $sale['customer_phone']
                                    ) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $sale['doctor_name']
                                ?: '-'
                            ) ?>
                        </td>
                        <td class="amount">
                            ₹<?= number_format(
                                (float)(
                                    $sale['subtotal']
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                        <td class="amount">
                            ₹<?= number_format(
                                (float)(
                                    $sale['gst_amount']
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                        <td class="amount">
                            ₹<?= number_format(
                                (float)(
                                    $sale['discount']
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                        <td class="grand-total">
                            ₹<?= number_format(
                                (float)(
                                    $sale['total_amount']
                                    ?? 0
                                ),
                                2
                            ) ?>
                        </td>
                        <td>
                            <span
                                class="payment-badge"
                                style="
                                    background:
                                    <?= $paymentColor ?>;
                                "
                            >
                                <?= htmlspecialchars(
                                    $paymentMethod
                                ) ?>
                            </span>
                        </td>
                        <td>
                            <span
                                class="transaction-id"
                            >
                                <?= htmlspecialchars(
                                    $sale['transaction_id']
                                    ?: '-'
                                ) ?>
                            </span>
                        </td>
                        <td>
                            <?= !empty(
                                $sale['created_at']
                            )
                                ? date(
                                    'd-m-Y h:i A',
                                    strtotime(
                                        $sale['created_at']
                                    )
                                )
                                : '-'
                            ?>
                        </td>
                        <td>
                            <a
                                href="view_sale.php?id=<?= (int)$sale['id'] ?>"
                                class="action-btn view-btn"
                                title="View Sale"
                            >
                                <i class="ri-eye-line"></i>
                            </a>
                            <a
                                href="edit_sale.php?id=<?= (int)$sale['id'] ?>"
                                class="action-btn edit-btn"
                                title="Edit Sale"
                            >
                                <i class="ri-pencil-line"></i>
                            </a>
                            <form method="POST" action="delete_sale.php" style="display:inline;"
                                onsubmit="return confirm('Are you sure you want to delete this sale? Stock will be restored.');">
                                <input type="hidden" name="id" value="<?= (int)$sale['id'] ?>">
                                <button type="submit" class="action-btn delete-btn" title="Delete Sale"
                                    style="border:none;cursor:pointer;">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>