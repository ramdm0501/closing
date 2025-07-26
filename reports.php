<?php
include 'db.php';
// الفلاتر الافتراضية
$date_from = isset($_GET['date_from']) && $_GET['date_from'] ? $_GET['date_from'] : date('Y-m-01');
$date_to   = isset($_GET['date_to']) && $_GET['date_to'] ? $_GET['date_to'] : date('Y-m-d');
$client_id = isset($_GET['client_id']) && $_GET['client_id'] ? intval($_GET['client_id']) : 0;

// جلب العملاء للفلتر
$clients = [];
$q = $conn->query("SELECT id, name FROM clients ORDER BY name");
while($row = $q->fetch_assoc()) $clients[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Smart Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f2f7fd; }
        .container { margin-top: 35px; max-width: 1260px;}
        .report-header { color: #1761a0; font-size: 2em; font-weight: bold; margin-bottom: 20px; }
        .filter-box {background:#e7f0fa; border-radius:12px; padding:18px 22px 6px 22px; margin-bottom:28px;}
        .section-title {font-weight:600; color:#205989; font-size:1.2em;}
        .tab-pane {margin-top:18px;}
        .nav-tabs .nav-link.active {background:#2874A6;color:#fff;}
        .nav-tabs .nav-link {color:#185076;font-weight:500;}
        .table thead th { background:#2874A6; color:#fff; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f4f8fd; }
        .highlight {background:#e9f4fa;}
        .stat-card {background:#fff; padding:14px 20px; border-radius:12px; box-shadow:0 1px 8px #e4edfc; text-align:center;}
        .stat-num {font-size:1.7em; font-weight:bold; color:#2261a0;}
        @media (max-width:900px){.container {padding: 0 2px;}}
    </style>
</head>
<body>
<div class="container">
    <div class="report-header mb-3">
        <img src="https://cdn-icons-png.flaticon.com/512/1068/1068720.png" width="35" class="me-2 mb-1">
        Reports & Analytics Dashboard
    </div>
    <!-- فلاتر عامة -->
    <form class="filter-box row gx-2 gy-2 align-items-end" id="filtersForm" method="get" onsubmit="return false;">
        <div class="col-md-3 col-6">
            <label class="form-label">Date From</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label">Date To</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="col-md-4 col-8">
            <label class="form-label">Client (optional)</label>
            <select class="form-control form-select" name="client_id" id="client_id">
                <option value="">All Clients</option>
                <?php foreach($clients as $cl): ?>
                <option value="<?= $cl['id'] ?>" <?= $cl['id']==$client_id ? 'selected':'' ?>>
                    <?= htmlspecialchars($cl['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 col-4">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-main w-100" style="background:#2874A6;color:#fff;font-weight:500;" onclick="loadReport()">
                Show Report
            </button>
        </div>
    </form>

    <!-- اختيار نوع التقرير -->
    <ul class="nav nav-tabs" id="reportTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#prod_by_size" id="tab_prod_by_size" role="tab">Production By Size & Type</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#machines_list" id="tab_machines_list" role="tab">Machines List</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#top_machine" id="tab_top_machine" role="tab">Top Output Machine</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#invoices" id="tab_invoices" role="tab">Invoices</a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="prod_by_size" role="tabpanel">
            <div id="prod_by_size_content" class="mt-4"></div>
        </div>
        <div class="tab-pane fade" id="machines_list" role="tabpanel">
            <div id="machines_list_content" class="mt-4"></div>
        </div>
        <div class="tab-pane fade" id="top_machine" role="tabpanel">
            <div id="top_machine_content" class="mt-4"></div>
        </div>
        <div class="tab-pane fade" id="invoices" role="tabpanel">
            <div id="invoices_content" class="mt-4"></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function getFilterParams() {
    return {
        date_from: document.getElementById('date_from').value,
        date_to: document.getElementById('date_to').value,
        client_id: document.getElementById('client_id').value
    };
}
function loadReport() {
    let active = document.querySelector('.nav-link.active').getAttribute('href').replace('#','');
    loadTabContent(active);
}
function loadTabContent(tab) {
    let params = getFilterParams();
    params['report'] = tab;
    let target = document.getElementById(tab+'_content');
    target.innerHTML = '<div class="text-center p-5"><span class="spinner-border text-info"></span></div>';
    fetch('reports_ajax.php?' + new URLSearchParams(params))
    .then(resp => resp.text())
    .then(html => { target.innerHTML = html; if(tab=='prod_by_size') {renderProdChart();}})
    .catch(() => {target.innerHTML='<div class="text-danger">Error loading report.</div>';});
}
document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(tab=>{
    tab.addEventListener('shown.bs.tab', function(){
        let tabid = this.getAttribute('href').replace('#','');
        loadTabContent(tabid);
    });
});
window.addEventListener('DOMContentLoaded', ()=>{loadTabContent('prod_by_size');});
document.getElementById('filtersForm').addEventListener('submit', function(e){e.preventDefault(); loadReport();});
</script>
<!-- رسم بياني إنتاج المكن -->
<script>
function renderProdChart() {
    let chartElem = document.getElementById('prod_chart');
    if (!chartElem) return;
    let data = JSON.parse(chartElem.getAttribute('data-chart'));
    if(window.prodChart) window.prodChart.destroy();
    window.prodChart = new Chart(chartElem.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: data.datasets
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: true } },
            scales: { x: { beginAtZero: true } }
        }
    });
}
</script>
</body>
</html>
