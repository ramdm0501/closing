<?php
include 'db.php';

// جلب كل عمليات التقفيل (الأحدث أولاً)
$sql = "SELECT cl.*, c.name AS client_name, m.brand, m.model, m.serial_number
        FROM closing_logs cl
        LEFT JOIN clients c ON cl.client_id = c.id
        LEFT JOIN machines m ON cl.machine_id = m.id
        ORDER BY cl.close_date DESC";
$res = $conn->query($sql);

// جلب توزيع الأنواع
$paper_types = ['xerox', 'glossy', 'plastic'];
$paper_sizes = ['A3', 'A4', 'A5', 'A6'];

function getPaperDist($conn, $closing_log_id, $paper_size) {
    $out = [];
    $sql = "SELECT paper_type, paper_count FROM closing_paper_distribution 
            WHERE closing_log_id = ? AND paper_size = ?";
    $stmt = $conn->prepare($sql);
    if(!$stmt) return [];
    $stmt->bind_param("is", $closing_log_id, $paper_size);
    $stmt->execute();
    $result = $stmt->get_result();
    while($r = $result->fetch_assoc()) {
        $out[$r['paper_type']] = $r['paper_count'];
    }
    $stmt->close();
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Closing List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 1150px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        th, td { font-size: 0.96em; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Closing List</h2>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <div class="card p-4">
        <div class="table-responsive">
            <table class="table table-bordered align-middle bg-white">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Machine</th>
                        <th>Date</th>
                        <?php foreach($paper_sizes as $size): ?>
                            <th><?= $size ?><br>Usage / Waste / Types</th>
                        <?php endforeach; ?>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php if($res && $res->num_rows > 0): ?>
                    <?php while($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['client_name']) ?></td>
                            <td><?= htmlspecialchars($row['brand']) ?> <?= htmlspecialchars($row['model']) ?><br>
                                <span class="text-muted" style="font-size:0.93em;">SN:<?= htmlspecialchars($row['serial_number']) ?></span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($row['close_date'])) ?></td>
                            <?php foreach($paper_sizes as $size): 
                                $consumed = $row['consumed_' . strtolower($size)];
                                $waste    = $row['waste_' . strtolower($size)];
                                $dist     = getPaperDist($conn, $row['id'], $size);
                                ?>
                                <td>
                                    <b><?= $consumed ?></b>
                                    <span class="text-danger">/ <?= $waste ?></span>
                                    <div style="font-size:0.93em;">
                                        <?php foreach($paper_types as $ptype): ?>
                                            <?php if(isset($dist[$ptype]) && $dist[$ptype] > 0): ?>
                                                <span class="badge bg-info text-dark mb-1"><?= ucfirst($ptype) ?>: <?= $dist[$ptype] ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <a href="closing_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">No closing operations found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
