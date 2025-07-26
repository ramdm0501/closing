<?php
include 'db.php';

$sql = "SELECT ai.*, m.brand, m.model, m.serial_number, c.name AS client_name
        FROM approved_invoices ai
        LEFT JOIN machines m ON ai.machine_id = m.id
        LEFT JOIN clients c ON ai.client_id = c.id
        ORDER BY ai.approved_at DESC";
$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approved Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 1100px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .table th, .table td { vertical-align: middle !important; font-size: 0.97em; }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Approved Invoices</h2>
        <a href="invoices.php" class="btn btn-info">Back to Invoices</a>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <div class="card p-3">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Client</th>
                    <th>Machine</th>
                    <th>Serial</th>
                    <th>Period</th>
                    <th>Total</th>
                    <th>Approved At</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res && $res->num_rows): ?>
                <?php while($row = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['client_name']) ?></td>
                        <td><?= htmlspecialchars($row['brand']) ?> <?= htmlspecialchars($row['model']) ?></td>
                        <td><?= htmlspecialchars($row['serial_number']) ?></td>
                        <td><?= $row['date_from'] ?> → <?= $row['date_to'] ?></td>
                        <td><?= number_format($row['total'],2) ?></td>
                        <td><?= $row['approved_at'] ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No approved invoices yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
