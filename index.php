<?php
require_once __DIR__ . '/includes/header.php';
$todaySales = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
    WHERE DATE(created_at) = DATE('now')
")->fetchColumn();

$totalSales = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
")->fetchColumn();

$totalMeds = $db->query("
    SELECT COUNT(*)
    FROM medicines
")->fetchColumn();

$lowStock = $db->query("
    SELECT COUNT(*)
    FROM medicines
    WHERE stock_quantity <= 15
")->fetchColumn();

$yesterdaySales = $db->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM sales
    WHERE DATE(created_at) = DATE('now', '-1 day')
")->fetchColumn();

$recentSales = $db->query("
    SELECT *
    FROM sales
    ORDER BY id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$lowStockMeds = $db->query("
    SELECT *
    FROM medicines
    WHERE stock_quantity <= 15
    ORDER BY stock_quantity ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


$monthlyRevenue = $db->query("
    SELECT
        strftime('%Y-%m', created_at) AS month_key,
        COUNT(*) AS invoice_count,
        COALESCE(SUM(total_amount), 0) AS total
    FROM sales
    GROUP BY month_key
    ORDER BY month_key DESC
")->fetchAll(PDO::FETCH_ASSOC);

$chartLabels = [];
$chartValues = [];

foreach ($monthlyRevenue as $m) {
    $monthDate = DateTime::createFromFormat(
        'Y-m-d',
        $m['month_key'] . '-01'
    );
    if ($monthDate) {
        $chartLabels[] = $monthDate->format('M Y');
    } else {
        $chartLabels[] = $m['month_key'];
    }
    $chartValues[] = round((float)$m['total'], 2);
}
?>

<div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
">
    <div id="liveClock" style="
        background:white;
        color:black;
        padding:15px 20px;
        border-radius:12px;
        font-size:15px;
        text-align:center;
        font-weight:bold;
        box-shadow:0 10px 25px rgb(70, 69, 69);
        min-width:220px;
    ">
        Loading...
    </div>
</div>
<div class="grid-summary">
    <div class="summary-card" style="border-left-color:#16a34a;">
        <p>
            <i class="ri-line-chart-line"></i>
            Yesterday Sales
        </p>
        <h2 style="color:#16a34a;">
            ₹<?= number_format((float)$yesterdaySales, 2) ?>
        </h2>
    </div>
    <div class="summary-card" style="border-left-color:#0284c7;">
        <p>
            <i class="ri-money-rupee-circle-line"></i>
            Today's Sales
        </p>
        <h2>
            ₹<?= number_format((float)$todaySales, 2) ?>
        </h2>
    </div>
    <div class="summary-card" style="border-left-color:#0d9488;">
        <p>
            <i class="ri-wallet-3-line"></i>
            Total Revenue
        </p>
        <h2>
            ₹<?= number_format((float)$totalSales, 2) ?>
        </h2>
    </div>
    <div class="summary-card" style="border-left-color:#7c3aed;">
        <p>
            <i class="ri-capsule-line"></i>
            Medicines in Stock
        </p>
        <h2 style="color:#7c3aed;">
            <?= (int)$totalMeds ?>
        </h2>
    </div>
        <div class="summary-card" style="border-left-color:#d97706;">
        <p>
            <i class="ri-alert-line"></i>
            Low Stock Alerts
        </p>
        <h2 style="color:#d97706;">
            <?= (int)$lowStock ?>
        </h2>
    </div>
</div>

<div class="card" style="margin-top:30px;">
    <div class="card-title">
        <span>
            <i class="ri-calendar-line" style="color:#0d9488;"></i>
            Month-wise Revenue
        </span>
        <a href="sales.php" class="btn btn-secondary btn-sm">
            View Sales
        </a>
    </div>
    <?php if (empty($monthlyRevenue)): ?>
        <div style="
            text-align:center;
            padding:30px;
            color:#94a3b8;
        ">
            No revenue data available yet.
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Month</th>
                        <th>Total Invoices</th>
                        <th>Revenue (₹)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $monthNo = 1;
                foreach ($monthlyRevenue as $m):
                    $monthDate = DateTime::createFromFormat(
                        'Y-m-d',
                        $m['month_key'] . '-01'
                    );
                    $monthName = $monthDate
                        ? $monthDate->format('F Y')
                        : $m['month_key'];
                    $currentMonth = date('Y-m');
                    $isCurrentMonth = ($m['month_key'] === $currentMonth);
                ?>
                    <tr>
                        <td>
                            <?= $monthNo++ ?>
                        </td>
                        <td>
                            <strong>
                                <?= htmlspecialchars($monthName) ?>
                            </strong>
                            <?php if ($isCurrentMonth): ?>
                                <span style="
                                    display:inline-block;
                                    margin-left:8px;
                                    padding:3px 8px;
                                    border-radius:20px;
                                    background:#dcfce7;
                                    color:#15803d;
                                    font-size:11px;
                                    font-weight:bold;
                                ">
                                    CURRENT
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= number_format((int)$m['invoice_count']) ?>
                        </td>
                        <td style="
                            font-weight:700;
                            color:#0d9488;
                        ">
                            ₹<?= number_format((float)$m['total'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:30px;">
    <h2 style="margin-bottom:20px;">
        <i class="ri-bar-chart-box-fill"></i>
        Monthly Sales Report
    </h2>
    <canvas id="salesChart" height="110"></canvas>
</div>

<div style="
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1.5rem;
">
    <div class="card" style="margin-bottom:0;">
        <div class="card-title">
            <span>
                <i
                    class="ri-history-line"
                    style="color:#0284c7;"
                ></i>
                Recent Sales
            </span>
            <a
                href="sales.php"
                class="btn btn-secondary btn-sm"
            >
                View All
            </a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Total (₹)</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentSales)): ?>
                <tr>
                    <td
                        colspan="4"
                        style="
                            text-align:center;
                            color:#94a3b8;
                        "
                    >
                        No sales yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentSales as $s): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $s['invoice_no'] ?? ''
                                ) ?>
                            </strong>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $s['customer_name'] ?: 'Walk-in'
                            ) ?>
                        </td>
                        <td style="
                            font-weight:700;
                            color:#0284c7;
                        ">
                            ₹<?= number_format(
                                (float)$s['total_amount'],
                                2
                            ) ?>
                        </td>
                        <td style="
                            color:#64748b;
                            font-size:0.82rem;
                        ">
                            <?= !empty($s['created_at'])
                                ? date(
                                    'd-m-Y',
                                    strtotime($s['created_at'])
                                )
                                : ''
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-title">
            <span>
                <i
                  class="ri-error-warning-line"
                    style="color:#d97706;"
                ></i>
                Low Stock Medicines
            </span>
            <a
                href="inventory.php"
                class="btn btn-secondary btn-sm"
            >
                View All
            </a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Qty Left</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($lowStockMeds)): ?>
                <tr>
                    <td
                        colspan="3"
                        style="
                            text-align:center;
                            color:#94a3b8;
                        "
                    >
                        All stock levels are fine.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lowStockMeds as $m): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $m['name']
                                ) ?>
                            </strong>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $m['category']
                            ) ?>
                        </td>
                        <td>
                            <span class="badge badge-danger">
                                <?= (int)$m['stock_quantity'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(
            $chartLabels,
            JSON_UNESCAPED_UNICODE
        ) ?>,
        datasets: [
            {
                label: 'Monthly Revenue',
                data: <?= json_encode(
                    $chartValues,
                    JSON_UNESCAPED_UNICODE
                ) ?>,
                backgroundColor: [
                    '#263047',
                    '#10b981',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#06b6d4',
                    '#84cc16',
                    '#ec4899',
                    '#14b8a6',
                    '#6366f1',
                    '#fb923c',
                    '#96baeb'
                ],
                borderRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        animation: {
            duration: 2000,
            easing: 'easeOutBounce'
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' +
                            Number(value).toLocaleString('en-IN');

                    }
                }
            }
        }
    }
});
</script>
<script>

function updateClock()
{
    const now = new Date();
    const date = now.toLocaleDateString(
        'en-IN',
        {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        }
    );
    const time = now.toLocaleTimeString('en-IN');
    document.getElementById('liveClock').innerHTML =
        "📅 " + date +
        "<br>🕒 " + time;
}
updateClock();
setInterval(updateClock, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>