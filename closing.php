<?php
include 'db.php';

$paper_types = ['xerox', 'glossy', 'plastic'];
$paper_sizes = ['A3', 'A4', 'A5', 'A6'];

// جلب العملاء
$clients = [];
$res_clients = $conn->query("SELECT id, name FROM clients ORDER BY name");
while ($row = $res_clients->fetch_assoc()) $clients[] = $row;

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// جلب ماكينات العميل
$machines = [];
if ($client_id) {
    $sql = "SELECT m.*, b.name AS branch_name 
            FROM machines m 
            LEFT JOIN branches b ON m.branch_id = b.id 
            WHERE m.client_id = $client_id";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $machines[] = $row;
}

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_machines'])) {
    $client_id = intval($_POST['client_id']);
    $machine_ids = $_POST['machine_id'];

    foreach ($machine_ids as $i => $mid) {
        $mid = intval($mid);
        $consumeds = $wastes = $starts = $ends = [];
        foreach ($paper_sizes as $size) {
            $low = strtolower($size);
            $start = intval($_POST['start_counter_' . $low][$i]);
            $end   = intval($_POST['end_counter_' . $low][$i]);
            $waste = intval($_POST['waste_' . $low][$i]);
            $consumed = max(0, $end - $start - $waste);
            $consumeds[$low] = $consumed;
            $wastes[$low] = $waste;
            $starts[$low] = $start;
            $ends[$low] = $end;
        }

        // سجل تقفيل جديد
        $stmt = $conn->prepare("INSERT INTO closing_logs 
            (client_id, machine_id, start_a3, end_a3, consumed_a3, waste_a3,
             start_a4, end_a4, consumed_a4, waste_a4,
             start_a5, end_a5, consumed_a5, waste_a5,
             start_a6, end_a6, consumed_a6, waste_a6, close_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) die('SQL ERROR: ' . $conn->error);
        $stmt->bind_param(
            "iiiiiiiiiiiiiiiiii",
            $client_id, $mid,
            $starts['a3'], $ends['a3'], $consumeds['a3'], $wastes['a3'],
            $starts['a4'], $ends['a4'], $consumeds['a4'], $wastes['a4'],
            $starts['a5'], $ends['a5'], $consumeds['a5'], $wastes['a5'],
            $starts['a6'], $ends['a6'], $consumeds['a6'], $wastes['a6']
        );
        $stmt->execute();
        $closing_log_id = $conn->insert_id;
        $stmt->close();

        // تحديث العداد البداية للماكينة
        $stmt = $conn->prepare("UPDATE machines SET 
            start_counter_a3=?, start_counter_a4=?, start_counter_a5=?, start_counter_a6=?
            WHERE id=?");
        if (!$stmt) die('SQL ERROR: ' . $conn->error);
        $stmt->bind_param("iiiii", $ends['a3'], $ends['a4'], $ends['a5'], $ends['a6'], $mid);
        $stmt->execute();
        $stmt->close();

        // توزيع أنواع الورق
        foreach ($paper_sizes as $size) {
            $low = strtolower($size);
            $dist = $_POST['distribution_' . $low][$i] ?? [];
            foreach ($paper_types as $ptype) {
                $pcount = intval($dist[$ptype] ?? 0);
                if ($pcount > 0) {
                    $stmt = $conn->prepare("INSERT INTO closing_paper_distribution 
                        (closing_log_id, machine_id, paper_size, paper_type, paper_count)
                        VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt) die('SQL ERROR: ' . $conn->error);
                    $stmt->bind_param("iissi", $closing_log_id, $mid, $size, $ptype, $pcount);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    $success = "Closing completed and data saved!";
    header("Location: closing.php?client_id=$client_id&success=1");
    exit;
}
if (isset($_GET['success'])) $success = "Closing completed and data saved!";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Closing Machines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f8fd; }
        .container { margin-top: 45px; max-width: 1250px;}
        .card { border-radius: 20px; box-shadow: 0 2px 20px #0001; }
        .table th, .table td { vertical-align: middle !important; font-size: 0.97em; }
        .table thead th {
            background: #2874A6;
            color: #fff;
            text-align: center;
            font-weight: bold;
            border-bottom: 2px solid #204d6f;
        }
        .paper-table input[type=number] { min-width: 70px; text-align: center; }
        .branch-label { font-size: 0.97em; color: #246092; font-weight: 500; }
        .btn-main { background: #2874A6; color: #fff; border-radius: 10px; }
        .btn-main:hover { background: #185076; color: #fff; }
        .btn-secondary { border-radius: 10px; }
        .form-label { font-weight: 500; color: #204d6f; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f1f7fc; }
        input[readonly] { background: #e5e9f2 !important; color: #708090; }
        .table th, .table td { padding: 8px 6px !important; }
        @media (max-width:900px) {
            .table-responsive { font-size: .97em; }
            .container { padding: 0 2px;}
        }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-center flex-grow-1" style="color:#1761a0;letter-spacing:.04em;font-weight:700">
            <img src="https://cdn-icons-png.flaticon.com/512/1068/1068720.png" alt="" width="34" class="me-1 mb-1">
            Closing / End Of Period
        </h2>
        <a href="dashboard.php" class="btn btn-secondary ms-3">Back to Dashboard</a>
    </div>
    <div class="card p-4 mb-4">
        <form method="get" class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Choose Client</label>
                <select name="client_id" class="form-control form-select" onchange="this.form.submit()">
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success; ?></div>
    <?php endif; ?>
    <?php if ($client_id && $machines): ?>
    <form method="post">
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
        <div class="card p-3">
            <h5 class="mb-3" style="font-size:1.17em;">
                Machines Closing For Client: 
                <span class="text-primary"><?= htmlspecialchars($clients[array_search($client_id, array_column($clients, 'id'))]['name']); ?></span>
            </h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle bg-white paper-table">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Machine</th>
                            <th rowspan="2">Branch</th>
                            <?php foreach ($paper_sizes as $size): ?>
                                <th colspan="5"><?= $size ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($paper_sizes as $size): ?>
                                <th style="background:#1b4f72;">Start</th>
                                <th style="background:#1b4f72;">End</th>
                                <th style="background:#1b4f72;">Waste</th>
                                <th style="background:#1b4f72;">Consumption</th>
                                <th style="background:#154360;">Paper Types</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($machines as $i => $m): ?>
                            <tr>
                                <td style="background:#eaf1fb;"><?= $m['id'] ?></td>
                                <td>
                                    <span style="font-weight:600;"><?= htmlspecialchars($m['brand']) ?></span>
                                    <br><small><?= htmlspecialchars($m['model']) ?><br>
                                    <span class="text-muted">SN:<?= htmlspecialchars($m['serial_number']) ?></span></small>
                                </td>
                                <td><span class="branch-label"><?= $m['branch_name'] ? htmlspecialchars($m['branch_name']) : 'All Client' ?></span></td>
                                <?php foreach ($paper_sizes as $size): 
                                    $low = strtolower($size);
                                    $start = $m['start_counter_' . $low];
                                ?>
                                    <td>
                                        <input type="number" name="start_counter_<?= $low ?>[]" class="form-control" value="<?= $start ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="number" name="end_counter_<?= $low ?>[]" class="form-control" value="<?= $start ?>" min="<?= $start ?>" required oninput="calcConsumption(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="waste_<?= $low ?>[]" class="form-control" value="0" min="0" oninput="calcConsumption(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="consumption_<?= $low ?>[]" class="form-control" value="0" readonly tabindex="-1" style="background: #e9ecef;">
                                    </td>
                                    <td>
                                        <div class="row g-1">
                                        <?php foreach ($paper_types as $ptype): ?>
                                            <div class="col mb-1">
                                                <input type="number" 
                                                    name="distribution_<?= $low ?>[<?= $i ?>][<?= $ptype ?>]"
                                                    class="form-control"
                                                    min="0"
                                                    placeholder="<?= ucfirst($ptype) ?>"
                                                    oninput="validateDistribution(this)">
                                                <small style="font-size:.91em;color:#185076"><?= ucfirst($ptype) ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                                <input type="hidden" name="machine_id[]" value="<?= $m['id'] ?>">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="close_machines" class="btn btn-main px-4 mt-2">Save Closing</button>
            <a href="closing.php?client_id=<?= $client_id ?>" class="btn btn-secondary ms-2 mt-2">Cancel</a>
        </div>
    </form>
    <?php elseif ($client_id): ?>
        <div class="alert alert-warning">No machines found for this client.</div>
    <?php endif; ?>
</div>
<script>
function calcConsumption(input) {
    var row = input.closest('tr');
    var sizes = ['a3','a4','a5','a6'];
    sizes.forEach(function(sz) {
        var start = row.querySelector('input[name^="start_counter_'+sz+'"]').valueAsNumber || 0;
        var end   = row.querySelector('input[name^="end_counter_'+sz+'"]').valueAsNumber || 0;
        var waste = row.querySelector('input[name^="waste_'+sz+'"]').valueAsNumber || 0;
        var cons  = row.querySelector('input[name^="consumption_'+sz+'"]');
        var val = Math.max(0, end - start - waste);
        cons.value = val;
    });
}
function validateDistribution(input) {
    // إضافة تنبيه إذا التوزيع لا يساوي الاستهلاك ممكن لاحقًا
}
document.querySelectorAll('input[type=number]').forEach(function(inp){
    inp.addEventListener('input', function(){ calcConsumption(this); });
});
</script>
</body>
</html>
