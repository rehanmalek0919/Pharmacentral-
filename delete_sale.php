<?php
require_once __DIR__ . '/includes/auth.php';
requireRole(['admin', 'pharmacist']);
$db = getDBConnection();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sales.php");
    exit;
}
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: sales.php");
    exit;
}
try {
    $db->beginTransaction();
    $items = $db->prepare("SELECT medicine_id, quantity FROM sale_items WHERE sale_id = ?");
    $items->execute([$id]);
    $restore = $db->prepare("UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ?");
    foreach ($items->fetchAll() as $item) {
        $restore->execute([(int)$item['quantity'], (int)$item['medicine_id']]);
    }
    $stmt = $db->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$id]);
    $stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $db->commit();
    header("Location: sales.php?deleted=1");
    exit;
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Delete Failed: " . htmlspecialchars($e->getMessage()));
}