<?php
include 'db.php';

// جلب العملاء
$clients = [];
$res_clients = $conn->query("SELECT id, name FROM clients ORDER BY name");
while ($row = $res_clients->fetch_assoc()) $clients[] = $row;

$paper_types = ['xerox', 'glossy', 'plastic'];
$paper_sizes = ['A3', 'A4', 'A5', 'A6'];

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = [];
if ($client_id) $where[] = "cl.client_id = $client_id";
if ($date_from) $where[] = "cl.close_date >= '" . $conn->real_escape_string($date_from) . " 00:00:00'";
if ($date_to) $where[] = "cl.close_date <= '" . $conn->real_escape_string($date_to) . " 23:59:59'";
$where_sql = $where ? "WHERE " . implode(' AND ', $where) : "";

// منع تكرار الفواتير اللي approved
$exclude_sql = "";
if($date_from && $date_to){
    $exclude_sql = " AND NOT EXISTS (
        SELECT 1 FROM approved_invoices ai 
        WHERE ai.machine_id = m.id 
          AND ai.client_id = c.id 
          AND ai.date_from = '" . $conn->real_escape_string($date_from) . "' 
          AND ai.date_to = '" . $conn->real_escape_string($date_to) . "'
    )";
}

$sql = "SELECT m.id as machine_id, m.brand, m.model, m.serial_number, c.name AS client_name, MIN(cl.close_date) as first_close, MAX(cl.close_date) as last_close
        FROM closing_logs cl
        LEFT JOIN clients c ON cl.client_id = c.id
        LEFT JOIN machines m ON cl.machine_id = m.id
        $where_sql
        $exclude_sql
        GROUP BY m.id
        ORDER BY last_close DESC";
$res = $conn->query($sql);

// جلب أسعار الورق
$prices = [];
if ($client_id) {
    $psql = "SELECT * FROM client_paper_settings WHERE client_id = $client_id";
    $pres = $conn->query($psql);
    if ($pres) while ($pr = $pres->fetch_assoc()) {
        $prices[$pr['paper_type']][$pr['paper_size']] = [
            'price' => $pr['price'],
            'commitment' => $pr['commitment']
        ];
    }
}

function getMachinePaper($conn, $machine_id, $paper_types, $paper_sizes, $date_from = '', $date_to = '') {
    $where = "WHERE cpd.machine_id = $machine_id";
    if ($date_from) $where .= " AND cl.close_date >= '" . $conn->real_escape_string($date_from) . " 00:00:00'";
    if ($date_to) $where .= " AND cl.close_date <= '" . $conn->real_escape_string($date_to) . " 23:59:59'";
    $sql = "SELECT cpd.paper_size, cpd.paper_type, SUM(cpd.paper_count) as qty
            FROM closing_paper_distribution cpd
            LEFT JOIN closing_logs cl ON cl.id = cpd.closing_log_id
            $where
            GROUP BY cpd.paper_size, cpd.paper_type";
    $res = $conn->query($sql);
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[$row['paper_type']][$row['paper_size']] = intval($row['qty']);
    }
    foreach ($paper_types as $ptype) foreach ($paper_sizes as $size) {
        if (!isset($out[$ptype][$size])) $out[$ptype][$size] = 0;
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 1200px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .invoice-box { background: #fff; border-radius: 16px; margin-bottom: 38px; box-shadow: 0 1px 8px #dadada;}
        .invoice-head { background: #2874A6; color: #fff; border-radius: 16px 16px 0 0; padding: 18px 30px; }
        .machine-info { font-size: 1.02em; font-weight: 500; }
        .table thead th { background: #e3ecfa; color: #205080; }
        .grand-total { font-size: 1.1em; font-weight: bold; color: #1761a0;}
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Invoices</h2>
        <a href="approved_invoices.php" class="btn btn-success">Approved Invoices</a>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <div class="card p-4 mb-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Choose Client</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $client_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-main w-100">Show</button>
            </div>
        </form>
    </div>
    <?php if ($client_id && $date_from && $date_to): ?>
        <?php
        if($res && $res->num_rows > 0) {
            while($row = $res->fetch_assoc()):
                $machine_id = $row['machine_id'];
                $machine_papers = getMachinePaper($conn, $machine_id, $paper_types, $paper_sizes, $date_from, $date_to);
                $machine_total = 0;
        ?>
        <div class="invoice-box mb-4">
            <div class="invoice-head">
                <span class="machine-info">
                    <strong>Client:</strong> <?= htmlspecialchars($row['client_name']) ?> &nbsp; | &nbsp;
                    <strong>Machine:</strong> <?= htmlspecialchars($row['brand']) ?> <?= htmlspecialchars($row['model']) ?> 
                    (<span class="text-warning">S/N:<?= htmlspecialchars($row['serial_number']) ?></span>)
                    &nbsp; | &nbsp; <strong>Period:</strong>
                    <?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>
                </span>
                <span class="float-end">
                    <a href="invoice_view.php?machine_id=<?= $machine_id ?>&client_id=<?= $client_id ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="btn btn-info btn-sm ms-3" style="font-weight:600">
                        View Invoice
                    </a>
                    <form method="post" action="approve_invoice.php" class="d-inline">
                        <input type="hidden" name="machine_id" value="<?= $machine_id ?>">
                        <input type="hidden" name="client_id" value="<?= $client_id ?>">
                        <input type="hidden" name="date_from" value="<?= $date_from ?>">
                        <input type="hidden" name="date_to" value="<?= $date_to ?>">
                        <input type="hidden" name="total" value="<?= $machine_total ?>">
                        <button type="submit" class="btn btn-success btn-sm ms-1" onclick="return confirm('Are you sure to approve this invoice?')">
                            Approved
                        </button>
                    </form>
                </span>
            </div>
            <div class="p-3">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle bg-white mb-0">
                        <thead>
                            <tr>
                                <th>Paper Size</th>
                                <th>Paper Type</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Commitment</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach($paper_sizes as $size){
                            foreach($paper_types as $ptype){
                                $qty = $machine_papers[$ptype][$size] ?? 0;
                                $unit = isset($prices[$ptype][$size]['price']) ? floatval($prices[$ptype][$size]['price']) : 0;
                                $commitment = isset($prices[$ptype][$size]['commitment']) ? intval($prices[$ptype][$size]['commitment']) : 0;
                                if($qty > 0 || $commitment > 0){
                                    if ($commitment > 0) {
                                        if ($qty < $commitment) {
                                            $total = $commitment * $unit;
                                            $show_qty = "<span class='text-danger'>$qty / $commitment</span>";
                                        } else {
                                            $total = $qty * $unit;
                                            $show_qty = $qty;
                                        }
                                    } else {
                                        $total = $qty * $unit;
                                        $show_qty = $qty;
                                    }
                                    $machine_total += $total;
                                    ?>
                                    <tr>
                                        <td><?= $size ?></td>
                                        <td><?= ucfirst($ptype) ?></td>
                                        <td><?= $show_qty ?></td>
                                        <td><?= $unit > 0 ? number_format($unit, 2) : '<span class="text-danger">0.00</span>' ?></td>
                                        <td><?= $commitment ? $commitment : '<span class="text-muted">-</span>' ?></td>
                                        <td><?= number_format($total,2) ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                        ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Total For This Machine</th>
                                <th class="grand-total"><?= number_format($machine_total,2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php } else { ?>
        <div class="alert alert-info text-center">No invoices for this client in selected period.</div>
    <?php }
    else: ?>
        <div class="alert alert-warning text-center">Please choose client and date period to show invoices.</div>
    <?php endif; ?>
</div>
</body>
</html>
