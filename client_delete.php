<?php
include 'db.php';

// التأكد من وجود ID صالح
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<div style='margin:40px'><h3>Client ID is missing!</h3></div>");
}

$client_id = intval($_GET['id']);

// جلب بيانات العميل (للتأكيد)
$stmt = $conn->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->bind_result($client_name);
$stmt->fetch();
$stmt->close();

if (!$client_name) {
    die("<div style='margin:40px'><h3>Client not found!</h3></div>");
}

// تنفيذ الحذف بعد التأكيد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_confirm'])) {
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->close();
    // إعادة التوجيه
    header("Location: clients.php?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f9eaea; }
        .container { margin-top: 80px; max-width: 420px; }
        .card { border-radius: 16px; box-shadow: 0 2px 12px #0001; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h4 class="mb-4 text-danger">Delete Client</h4>
        <p class="mb-4">Are you sure you want to <b>delete</b> client:<br>
            <span class="text-primary"><?= htmlspecialchars($client_name) ?></span> ?<br>
            This action <span class="text-danger">cannot be undone</span>!
        </p>
        <form method="post">
            <button type="submit" name="delete_confirm" class="btn btn-danger">Yes, Delete</button>
            <a href="clients.php" class="btn btn-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
