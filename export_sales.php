<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/includes/auth.php';
requireRole(['admin', 'pharmacist']);
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');
$payment  = trim($_GET['payment'] ?? '');
$search   = trim($_GET['search'] ?? '');
if ($fromDate !== '') {
    $date = DateTime::createFromFormat(
        'Y-m-d',
        $fromDate
    );
    if (
        !$date ||
        $date->format('Y-m-d') !== $fromDate
    ) {

        $fromDate = '';
    }
}
if ($toDate !== '') {
    $date = DateTime::createFromFormat(
        'Y-m-d',
        $toDate
    );
    if (
        !$date ||
        $date->format('Y-m-d') !== $toDate
    ) {
        $toDate = '';
    }
}
if (
    $fromDate !== '' &&
    $toDate !== '' &&
    $fromDate > $toDate
) {
    die(
        'Invalid date range: From Date cannot be greater than To Date.'
    );
}
$allowedPayments = [
    '',
    'Cash',
    'UPI',
    'Card',
    'Credit'
];
if (!in_array(
    $payment,
    $allowedPayments,
    true
)) {
    $payment = '';
}

$where = [];
$params = [];
if ($fromDate !== '') {
    $where[] = "
        created_at >= ?
    ";
    $params[] =
        $fromDate . ' 00:00:00';
}

if ($toDate !== '') {
    $nextDate = date(
        'Y-m-d',
        strtotime(
            $toDate . ' +1 day'
        )
    );
    $where[] = "
        created_at < ?
    ";
    $params[] =
        $nextDate . ' 00:00:00';
}

if ($payment !== '') {
    $where[] = "
        LOWER(payment_method) = LOWER(?)
    ";
    $params[] = $payment;
}

if ($search !== '') {
    $where[] = "
        (
            invoice_no LIKE ?
            OR customer_name LIKE ?
            OR customer_phone LIKE ?
            OR doctor_name LIKE ?
            OR transaction_id LIKE ?
        )
    ";
    $searchValue = '%' . $search . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$whereSQL = '';
if (!empty($where)) {
    $whereSQL =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

$db = getDBConnection();
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
    ORDER BY created_at ASC, id ASC
");
$stmt->execute($params);
$sales = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);
$totalInvoices = count($sales);
$totalSubtotal = 0;
$totalGST = 0;
$totalDiscount = 0;
$totalRevenue = 0;
foreach ($sales as $sale) {
    $totalSubtotal +=
        (float)(
            $sale['subtotal']
            ?? 0
        );
    $totalGST +=
        (float)(
            $sale['gst_amount']
            ?? 0
        );
    $totalDiscount +=
        (float)(
            $sale['discount']
            ?? 0
        );
    $totalRevenue +=
        (float)(
            $sale['total_amount']
            ?? 0
        );
}
function excelEscape(
    $value
): string {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$fileName =
    'PharmaCentral_Sales_' .
    (
        $fromDate !== ''
            ? $fromDate
            : 'All'
    ) .
    '_to_' .
    (
        $toDate !== ''
            ? $toDate
            : 'All'
    ) .
    '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {font-family: Arial, sans-serif;}
.title { font-size: 22px; font-weight: bold; text-align: center; background: #0284c7; color: white; padding: 12px;}
.filter { font-size: 14px; font-weight: bold; padding: 8px;}
.summary-title { font-size: 14px; font-weight: bold; background: #0f766e; color: white; padding: 8px;}
.summary { font-size: 13px; font-weight: bold; padding: 8px; border: 1px solid #d1d5db;}
table { border-collapse: collapse; width: 100%;}
th { background: #0284c7; color: white; font-weight: bold; border: 1px solid #94a3b8; padding: 9px; text-align: center;}
td { border: 1px solid #cbd5e1; padding: 8px;}
.amount {text-align: right;}
.total {font-weight: bold;color: #0284c7;}
.no-records { text-align: center; padding: 20px; font-weight: bold;}

</style>
</head>
<body>
<table>
    <tr>
        <td colspan="12"class="title">
            PharmaCentral Medical Store - Sales Report
        </td>
    </tr>
</table>
<br>
<table>
    <tr>
        <td colspan="12"class="filter">
            Date Range:
            <?php if ($fromDate !== ''): ?>
                <?= excelEscape(
                    date(
                        'd-m-Y',
                        strtotime($fromDate)
                    )
                ) ?>
            <?php else: ?>
                All Dates
            <?php endif; ?>
            &nbsp; to &nbsp;
            <?php if ($toDate !== ''): ?>
                <?= excelEscape(
                    date(
                        'd-m-Y',
                        strtotime($toDate)
                    )
                ) ?>
            <?php else: ?>
                All Dates
            <?php endif; ?>
            &nbsp; | &nbsp;
            Payment:
            <?= $payment !== ''
                ? excelEscape($payment)
                : 'All Payments'
            ?>
            <?php if ($search !== ''): ?>
                &nbsp; | &nbsp;
                Search:
                <?= excelEscape(
                    $search
                ) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
<br>
<table>
    <tr>
        <td colspan="5"class="summary-title">
            Sales Summary
        </td>
    </tr>
    <tr>
        <td class="summary">
            Total Invoices
        </td>
        <td class="summary">
            <?= $totalInvoices ?>
        </td>
        <td class="summary">
            Subtotal
        </td>
        <td class="summary">
            ₹<?= number_format(
                $totalSubtotal,
                2
            ) ?>
        </td>
        <td class="summary">
            GST
            ₹<?= number_format(
                $totalGST,
                2
            ) ?>
        </td>
    </tr>
    <tr>
        <td class="summary">
            Total Discount
        </td>
        <td class="summary">
            ₹<?= number_format(
                $totalDiscount,
                2
            ) ?>
        </td>
        <td class="summary">
            Grand Total
        </td>
        <td colspan="2"class="summary">
            ₹<?= number_format(
                $totalRevenue,
                2
            ) ?>
        </td>
    </tr>
</table>
<br>
<table>
    <thead>
        <tr>
            <th>No.</th>
            <th>Invoice No.</th>
            <th>Customer Name</th>
            <th>Mobile</th>
            <th>Doctor Name</th>
            <th>Subtotal</th>
            <th>GST</th>
            <th>Discount</th>
            <th>Grand Total</th>
            <th>Payment Method</th>
            <th>Transaction ID</th>
            <th>Date & Time</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($sales)): ?>
        <tr>
            <td colspan="12"class="no-records">
                No sales records found
                for the selected date range.
            </td>
        </tr>
    <?php else: ?>
        <?php foreach (
            $sales as $index => $sale
        ): ?>
            <tr>
                <td>
                    <?= $index + 1 ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['invoice_no']
                        ?? '-'
                    ) ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['customer_name']
                        ?? '-'
                    ) ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['customer_phone']
                        ?? '-'
                    ) ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['doctor_name']
                        ?? '-'
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
                <td class="amount total">
                    ₹<?= number_format(
                        (float)(
                            $sale['total_amount']
                            ?? 0
                        ),
                        2
                    ) ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['payment_method']
                        ?? 'Cash'
                    ) ?>
                </td>
                <td>
                    <?= excelEscape(
                        $sale['transaction_id']
                        ?: '-'
                    ) ?>
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
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>

    <?php if (!empty($sales)): ?>
        <tfoot>
            <tr>
                <td
                    colspan="5"
                    style="
                        font-weight:bold;
                        text-align:right;
                    "
                >
                    TOTAL
                </td>
                <td
                    class="amount"
                    style="font-weight:bold;"
                >
                    ₹<?= number_format(
                        $totalSubtotal,
                        2
                    ) ?>
                </td>
                <td
                    class="amount"
                    style="font-weight:bold;"
                >
                    ₹<?= number_format(
                        $totalGST,
                        2
                    ) ?>
                </td>
                <td
                    class="amount"
                    style="font-weight:bold;"
                >
                    ₹<?= number_format(
                        $totalDiscount,
                        2
                    ) ?>
                </td>
                <td
                    class="amount total"
                >
                    ₹<?= number_format(
                        $totalRevenue,
                        2
                    ) ?>
                </td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    <?php endif; ?>
</table>
</body>
</html>

<?php exit;?>