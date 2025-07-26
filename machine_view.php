<?php
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<div style='margin:40px'><h3>Machine ID is missing!</h3></div>");
}
$machine_id = intval($_GET['id']);

// جلب بيانات الماكينة
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Machine #<?= $machine['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 50px; max-width: 700px; }
        .card { border-radius: 18px; box-shadow: 0 2px 12px #0001; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #2874A6; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Machine Details</h3>
        <a href="machines.php" class="btn btn-secondary">Back to Machines</a>
    </div>
    <div class="card p-4">
        <div class="mb-3">
            <span class="section-title">Client:</span>
            <span><?= $machine['client_name'] ? htmlspecialchars($machine['client_name']) : '<span class="text-muted">-</span>' ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Branch:</span>
            <span><?= $machine['branch_name'] ? htmlspecialchars($machine['branch_name']) : '<span class="text-muted">All Client</span>' ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Brand:</span>
            <span><?= htmlspecialchars($machine['brand']) ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Model:</span>
            <span><?= htmlspecialchars($machine['model']) ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Serial Number:</span>
            <span><?= htmlspecialchars($machine['serial_number']) ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Start Counter A3:</span>
            <span><?= $machine['start_counter_a3'] ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Start Counter A4:</span>
            <span><?= $machine['start_counter_a4'] ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Start Counter A5:</span>
            <span><?= $machine['start_counter_a5'] ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Start Counter A6:</span>
            <span><?= $machine['start_counter_a6'] ?></span>
        </div>
        <div class="mt-4 text-end">
            <a href="machines.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
</body>
</html>
