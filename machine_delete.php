<?php
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<div style='margin:40px'><h3>Machine ID is missing!</h3></div>");
}
$machine_id = intval($_GET['id']);

// جلب بيانات الماكينة للعرض
$stmt = $conn->prepare("SELECT m.*, c.name AS client_name, b.name AS branch_name 
    FROM machines m
    LEFT JOIN clients c ON m.client_id = c.id
    LEFT JOIN branches b ON m.branch_id = b.id
    WHERE m.id = ?");
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$result = $stmt->get_result();
$machine = $result->fetch_assoc();
$stmt->close();

if (!$machine) {
    die("<div style='margin:40px'><h3>Machine not found!</h3></div>");
}

// عند التأكيد، احذف وسجل الخروج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_confirm'])) {
    $stmt = $conn->prepare("DELETE FROM machines WHERE id = ?");
    $stmt->bind_param("i", $machine_id);
    $stmt->execute();
    $stmt->close();
    header("Location: machines.php?deleted=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Machine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f9eaea; }
        .container { margin-top: 80px; max-width: 470px; }
        .card { border-radius: 16px; box-shadow: 0 2px 12px #0001; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 text-danger">Delete Machine</h4>
        <a href="machines.php" class="btn btn-secondary">Back to Machines</a>
    </div>
    <div class="card p-4">
        <p class="mb-4">Are you sure you want to <b>delete</b> this machine?<br>
            <span class="text-primary">
                Client: <?= $machine['client_name'] ? htmlspecialchars($machine['client_name']) : '-' ?><br>
                Branch: <?= $machine['branch_name'] ? htmlspecialchars($machine['branch_name']) : 'All Client' ?><br>
                Brand: <?= htmlspecialchars($machine['brand']) ?><br>
                Model: <?= htmlspecialchars($machine['model']) ?><br>
                Serial Number: <?= htmlspecialchars($machine['serial_number']) ?>
            </span><br>
            <span class="text-danger">This action cannot be undone!</span>
        </p>
        <form method="post">
            <button type="submit" name="delete_confirm" class="btn btn-danger">Yes, Delete</button>
            <a href="machines.php" class="btn btn-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
