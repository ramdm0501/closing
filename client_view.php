<?php
include 'db.php';

// تأكد من وجود id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<div style='margin:40px'><h3>Client ID is missing!</h3></div>");
}

$client_id = intval($_GET['id']);

// جلب بيانات العميل
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$client) {
    die("<div style='margin:40px'><h3>Client not found!</h3></div>");
}

// جلب الفروع
$branches = [];
$res_branches = $conn->query("SELECT * FROM branches WHERE client_id = $client_id");
while ($br = $res_branches->fetch_assoc()) $branches[] = $br;

// جلب أسعار الورق
$sql_prices = "
                        SELECT paper_type, paper_size, commitment, price 
                        FROM client_paper_settings
                        WHERE client_id = {$client['id']}
                    ";
                    $prices = $conn->query($sql_prices);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Client #<?= $client['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 50px; max-width: 800px; }
        .card { border-radius: 18px; box-shadow: 0 2px 12px #0001; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #2874A6; }
        .badge { background: #2874A6; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-3 text-center">Client Details</h3>
        <div class="mb-3">
            <span class="section-title">Client Name:</span>
            <span><?= htmlspecialchars($client['name']) ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Area:</span>
            <span><?= htmlspecialchars($client['area']) ?></span>
        </div>
        <div class="mb-3">
            <span class="section-title">Address:</span>
            <span><?= htmlspecialchars($client['address']) ?></span>
        </div>
        <div class="mb-4">
            <span class="section-title">Number of Branches:</span>
            <span><?= $client['branches_count'] ?></span>
        </div>
        <div class="mb-4">
            <span class="section-title">Branches:</span>
            <?php if ($branches): ?>
                <ul class="list-group mt-2 mb-2">
                    <?php foreach ($branches as $br): ?>
                        <li class="list-group-item">
                            <b><?= htmlspecialchars($br['name']) ?></b> &mdash; <?= htmlspecialchars($br['address']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span class="text-muted">No branches found.</span>
            <?php endif; ?>
        </div>
        <div>
            <span class="section-title">Paper Prices & Commitments:</span>
            <?php if ($prices): ?>
                <div class="table-responsive mt-2">
                    <table class="table table-bordered align-middle bg-white">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Commitment</th>
                                <th>Price / Copy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prices as $pp): ?>
                                <tr>
                                    <td><span class="badge"><?= ucfirst($pp['paper_type']) ?></span></td>
                                    <td><?= $pp['paper_size'] ?></td>
                                    <td><?= $pp['commitment'] ? $pp['commitment'] : "<span class='text-muted'>No Commit</span>" ?></td>
                                    <td><span class="text-success fw-bold">$<?= number_format($pp['price'], 2) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <span class="text-muted">No paper price data.</span>
            <?php endif; ?>
        </div>
        <div class="mt-4 text-end">
            <a href="clients.php" class="btn btn-secondary">Back</a>
        </div>
    </div>
</div>
</body>
</html>
