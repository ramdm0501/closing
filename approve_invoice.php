<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_id = intval($_POST['machine_id']);
    $client_id  = intval($_POST['client_id']);
    $date_from  = $_POST['date_from'];
    $date_to    = $_POST['date_to'];
    $total      = floatval($_POST['total']);

    // اتأكد أن الفاتورة مش approved قبل كده
    $check = $conn->query("SELECT id FROM approved_invoices WHERE machine_id=$machine_id AND client_id=$client_id AND date_from='$date_from' AND date_to='$date_to'");
    if ($check && $check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO approved_invoices (machine_id, client_id, date_from, date_to, total) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissd", $machine_id, $client_id, $date_from, $date_to, $total);
        $stmt->execute();
        $stmt->close();
    }

    // رجّع المستخدم لصفحة الانفويس
    header("Location: invoices.php?client_id=$client_id&date_from=$date_from&date_to=$date_to");
    exit;
} else {
    // لو اتفتح GET أو مباشرًا
    header("Location: invoices.php");
    exit;
}
?>
