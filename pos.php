<?php
require_once __DIR__ . '/includes/header.php';
$medicines = $db->query("
    SELECT *
    FROM medicines
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.pos-search-box {
    width: 100%;
    font-size: 0.9rem;
    padding: 0.65rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    outline: none;
    box-sizing: border-box;
}
.pos-search-box:focus {
    border-color: #0284c7;
}
.med-card {
    cursor: pointer;
}
.med-card.disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.pos-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-sizing: border-box;
    outline: none;
}
.pos-input:focus {
    border-color: #0284c7;
}
.payment-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-right: 20px;
    cursor: pointer;
}
#checkout-message {
    display: none;
    padding: 10px;
    margin-top: 12px;
    border-radius: 6px;
    font-size: 14px;
}
.receipt-container {
    display: none;
}
.receipt-container table {
    width: 100%;
    border-collapse: collapse;
}
.receipt-container th,
.receipt-container td {
    padding: 6px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}
.receipt-total {
    font-size: 18px;
    font-weight: bold;
}
@media(max-width:900px) {
    .pos-layout {
        grid-template-columns: 1fr !important;
    }

}
</style>
<div class="pos-layout">
    <div>
        <div
            class="card"
            style="
                padding:1rem;
                margin-bottom:1rem;
            "
        >
            <input
                type="text"
                id="pos-search"
                class="pos-search-box"
                placeholder="Search medicine by name..."
                autocomplete="off"
            >
        </div>
        <div
            style="
                font-size:0.8rem;
                font-weight:600;
                color:white;
                text-transform:uppercase;
                margin-bottom:0.6rem;
            "
        >
            Click a medicine to add to cart
        </div>
        <div
            class="medicine-catalog"
            id="pos-catalog"
        >
            <?php foreach ($medicines as $m): ?>
                <?php
                $medicineData = [
                    'id' => (int)$m['id'],
                    'name' => $m['name'],
                    'price' => (float)$m['price'],
                    'stock' => (int)$m['stock_quantity'],
                    'expiry' => $m['expiry_date'],
                    'category' => $m['category']
                ];
                $jsonMedicine = htmlspecialchars(
                    json_encode(
                        $medicineData,
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_AMP |
                        JSON_HEX_QUOT
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $isExpired =
                    $m['expiry_date'] < date('Y-m-d');
                $isOutOfStock =
                    (int)$m['stock_quantity'] <= 0;
                ?>
                <div
                    class="med-card <?= (
                        $isExpired || $isOutOfStock
                    ) ? 'disabled' : '' ?>"
                    data-name="<?= htmlspecialchars(
                        strtolower($m['name'])
                    ) ?>"
                    onclick="
                        addToCart(
                            <?= $jsonMedicine ?>,
                            this
                        )
                    "
                >
                    <div class="cat">
                        <?= htmlspecialchars(
                            $m['category']
                        ) ?>
                    </div>
                    <div class="name">
                        <?= htmlspecialchars(
                            $m['name']
                        ) ?>
                    </div>
                    <div class="foot">
                        <span class="price">
                            ₹<?= number_format(
                                (float)$m['price'],
                                2
                            ) ?>
                        </span>
                        <?php if ($isExpired): ?>
                            <span
                                class="badge badge-danger"
                            >
                                Expired
                            </span>
                        <?php elseif ($isOutOfStock): ?>
                            <span
                                class="badge badge-danger"
                            >
                                Out of Stock
                            </span>
                        <?php elseif (
                            $m['stock_quantity'] <= 15
                        ): ?>
                            <span
                                class="badge badge-danger"
                            >
                                Qty:
                                <?= (int)$m['stock_quantity'] ?>
                            </span>
                        <?php else: ?>
                            <span
                                class="badge badge-success"
                            >
                                Qty:
                                <?= (int)$m['stock_quantity'] ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cart-panel">
        <div class="cart-title">
            <i
                class="ri-shopping-cart-2-line"
                style="color:white;"
            ></i>
            Billing Cart
        </div>
        <div
            style="
                display:flex;
                flex-direction:column;
                gap:8px;
                margin-bottom:15px;
            "
        >
            <input
                id="inp-customer"
                type="text"
                class="pos-input"
                placeholder="Customer Name"
            >
            <input
                type="tel"
                id="inp-phone"
                class="pos-input"
                name="customer_phone"
                maxlength="10"
                inputmode="numeric"
                placeholder="Enter Mobile Number"
                oninput="
                    this.value =
                    this.value
                    .replace(/[^0-9]/g,'')
                    .slice(0,10);
                "
            >
            <input
                id="inp-doctor"
                type="text"
                class="pos-input"
                placeholder="Doctor Name"
                maxlength="100"
                oninput="this.value = this.value.replace(/[^A-Za-z. ]/g, '')"
            >
        </div>
        <div
            class="cart-list"
            id="cart-items"
        >
            <p
                style="
                    text-align:center;
                    color:#888;
                    padding:30px;
                "
            >
                No items added.
            </p>
        </div>
        <div class="cart-summary">
            <div class="sum-line">
                <span>Subtotal</span>
                <span id="disp-subtotal">
                    ₹0.00
                </span>
            </div>
            <div class="sum-line">
                <span>GST (12%)</span>
                <span id="disp-gst">
                    ₹0.00
                </span>
            </div>
            <div class="sum-line">
                <span>Discount</span>
                <input
                    type="number"
                    id="inp-discount"
                    value="0"
                    min="0"
                    step="0.01"
                    style="width:100px;padding:6px;"
                    oninput="updateTotals()"
                >
            </div>
            <div class="sum-total">
                <span>Grand Total</span>
                <span id="disp-total">
                    ₹0.00
                </span>
            </div>
            <hr style="margin:15px 0;">
            <div>
                <label
                    style="font-weight:bold;"
                >
                    Payment Method
                </label>
                <div
                    style="margin-top:10px;"
                >
                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="Cash"
                            checked
                        >
                        Cash
                    </label>
                    <label class="payment-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="UPI"
                        >
                        UPI / Online
                    </label>
                </div>
            </div>
            <div
                id="upi-box"
                style="
                    display:none;
                    margin-top:15px;
                "
            >
                <label
                    for="upi-id"
                    style="
                        display:block;
                        font-weight:600;
                        margin-bottom:6px;
                    "
                >
                    UPI ID
                </label>
                <input
                    id="upi-id"
                    type="text"
                    placeholder="example@upi"
                    autocomplete="off"
                    maxlength="255"
                    style="
                        width:100%;
                        padding:10px;
                        border:1px solid #ccc;
                        border-radius:5px;
                        box-sizing:border-box;
                    "
                >
                <small
                    style="
                        display:block;
                        margin-top:5px;
                        color:#64748b;
                    "
                >
                    Enter a valid UPI ID, e.g. name@bank
                </small>
            </div>
            <div id="checkout-message"></div>
            <button
                type="button"
                id="checkout-button"
                class="btn btn-success"
                style="
                    width:100%;
                    margin-top:20px;
               "
            >
                <i class="ri-printer-line"></i>
                Generate Bill & Print
            </button>
        </div>
    </div>
</div>

<div id="receipt-area" class="receipt-container"> </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>