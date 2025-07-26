<?php
include 'db.php';

$report = $_GET['report'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$client_id = intval($_GET['client_id'] ?? 0);

$where = [];
$where[] = "cl.close_date >= '$date_from 00:00:00'";
$where[] = "cl.close_date <= '$date_to 23:59:59'";
if($client_id) $where[] = "c.id = $client_id";
$where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";

// تقرير إنتاج المكنة بالمقاسات والأنواع (Production By Size & Type)
if($report == 'prod_by_size'){
    // جدول
    $details = [];
    $res = $conn->query("
        SELECT m.brand, m.model, m.serial_number, c.name AS client_name, 
               cpd.paper_size, cpd.paper_type, SUM(cpd.paper_count) as total_count
        FROM closing_paper_distribution cpd
        LEFT JOIN closing_logs cl ON cl.id = cpd.closing_log_id
        LEFT JOIN machines m ON m.id = cpd.machine_id
        LEFT JOIN clients c ON m.client_id = c.id
        $where_sql
        GROUP BY cpd.machine_id, cpd.paper_size, cpd.paper_type
        ORDER BY total_count DESC
    ");
    while($row = $res->fetch_assoc()) $details[] = $row;

    // تحضير الداتا لرسم بياني stacked bar لكل ماكينة حسب المقاس (كل مقاس بلون)
    $machines = [];
    $sizes = ['A3','A4','A5','A6'];
    foreach($details as $row) {
        $key = $row['serial_number'].' - '.$row['model'].' ('.$row['client_name'].')';
        if(!isset($machines[$key])) $machines[$key] = ['A3'=>0,'A4'=>0,'A5'=>0,'A6'=>0];
        $machines[$key][$row['paper_size']] += $row['total_count'];
    }
    $labels = array_keys($machines);
    $datasets = [];
    foreach($sizes as $i=>$sz){
        $datasets[] = [
            'label'=>$sz,
            'data'=>array_map(function($m)use($sz){return $m[$sz];}, $machines),
            'backgroundColor'=>['#175a8b','#5cc6e7','#f9b234','#e63c57'][$i]
        ];
    }
    ?>
    <div class="section-title mb-2">Machine Production Details (<?= htmlspecialchars($date_from) ?> to <?= htmlspecialchars($date_to) ?>)</div>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead>
                <tr>
                    <th>Serial Number</th>
                    <th>Model</th>
                    <th>Client</th>
                    <th>Paper Size</th>
                    <th>Paper Type</th>
                    <th>Total Output</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($details as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['serial_number']) ?></td>
                    <td><?= htmlspecialchars($r['brand']." ".$r['model']) ?></td>
                    <td><?= htmlspecialchars($r['client_name']) ?></td>
                    <td><?= htmlspecialchars($r['paper_size']) ?></td>
                    <td><?= ucfirst($r['paper_type']) ?></td>
                    <td><?= $r['total_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="chart-box">
        <canvas id="prod_chart" data-chart='<?= json_encode(['labels'=>$labels,'datasets'=>$datasets]) ?>' height="70"></canvas>
        <small style="color:#888;">* Each bar = copies per size (A3/A4/A5/A6) for every machine.</small>
    </div>
    <?php
    exit;
}

// قائمة الماكينات
if($report=='machines_list'){
    $machines = [];
    $res = $conn->query("
        SELECT m.serial_number, m.brand, m.model, c.name AS client_name
        FROM machines m
        LEFT JOIN clients c ON m.client_id = c.id
        ".($client_id ? "WHERE m.client_id = $client_id" : "")."
        ORDER BY c.name, m.brand
    ");
    while($row = $res->fetch_assoc()) $machines[] = $row;
    ?>
    <div class="section-title mb-2">Machines List</div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Serial Number</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Client</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($machines as $i=>$m): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($m['serial_number']) ?></td>
                    <td><?= htmlspecialchars($m['brand']) ?></td>
                    <td><?= htmlspecialchars($m['model']) ?></td>
                    <td><?= htmlspecialchars($m['client_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}

// أعلي ماكينة انتاج
if($report=='top_machine'){
    $res = $conn->query("
        SELECT m.brand, m.model, m.serial_number, c.name AS client_name,
        SUM(cl.consumed_a3 + cl.consumed_a4 + cl.consumed_a5 + cl.consumed_a6) as total_copies
        FROM closing_logs cl
        JOIN machines m ON cl.machine_id = m.id
        LEFT JOIN clients c ON m.client_id = c.id
        $where_sql
        GROUP BY cl.machine_id
        ORDER BY total_copies DESC
        LIMIT 1
    ");
    $row = $res->fetch_assoc();
    ?>
    <div class="section-title mb-2">Top Output Machine</div>
    <?php if($row): ?>
        <div class="stat-card">
            <strong><?= htmlspecialchars($row['brand']." ".$row['model']) ?></strong>
            <span class="ms-2 text-secondary">S/N:<?= htmlspecialchars($row['serial_number']) ?></span>
            <span class="ms-2">Client: <?= htmlspecialchars($row['client_name']) ?></span>
            <br>
            <span class="stat-num"><?= $row['total_copies'] ?></span>
            <div>Total Output</div>
        </div>
    <?php else: ?>
        <div class="text-muted">No data for this filter.</div>
    <?php endif; ?>
    <?php
    exit;
}

// تقرير الفواتير (اجمالي لكل عميل)
if($report=='invoices'){
    $res = $conn->query("
        SELECT c.id, c.name, 
        SUM(cpd.paper_count * IFNULL(cps.price,0)) as total_invoice
        FROM clients c
        LEFT JOIN machines m ON m.client_id = c.id
        LEFT JOIN closing_logs cl ON cl.machine_id = m.id
        LEFT JOIN closing_paper_distribution cpd ON cpd.closing_log_id = cl.id
        LEFT JOIN client_paper_settings cps 
            ON cps.client_id = c.id AND cps.paper_type = cpd.paper_type AND cps.paper_size = cpd.paper_size
        $where_sql
        GROUP BY c.id, c.name
        ORDER BY total_invoice DESC
    ");
    ?>
    <div class="section-title mb-2">Invoices Summary</div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle bg-white">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Total Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= number_format($row['total_invoice'],2) ?></td>
                </tr>
                <?php endwhile;?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}

?>
