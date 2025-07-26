<?php
include 'db.php';

// إجمالي العملاء
$clients_count = $conn->query("SELECT COUNT(*) FROM clients")->fetch_row()[0];
// إجمالي الماكينات
$machines_count = $conn->query("SELECT COUNT(*) FROM machines")->fetch_row()[0];
// إجمالي عمليات التقفيل
$closings_count = $conn->query("SELECT COUNT(*) FROM closing_logs")->fetch_row()[0];
// إجمالي الفواتير (بنعد عمليات التقفيل كمصدر للفواتير)
$invoices_count = $closings_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fa; }
        .dashboard-header {
            color: #2261a0; font-size: 2.1em; font-weight: bold; margin-bottom: 25px; letter-spacing: .03em;
        }
        .card {
            border-radius: 18px; box-shadow: 0 2px 18px #d2e2fa;
            transition: transform .08s;
        }
        .card:hover { transform: translateY(-3px) scale(1.01);}
        .quick-link {
            font-size: 1.12em;
            color: #fff;
            background: #2874A6;
            border-radius: 12px;
            display: block;
            padding: 17px 0;
            margin-bottom: 20px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 1px 7px #b2c4e6;
            transition: background .16s;
        }
        .quick-link:hover { background: #1B4F72; color: #fff;}
        .report-card {
            background: #fff; border-radius: 15px; padding: 20px 25px; box-shadow: 0 1px 9px #e4edfc;
            text-align: center;
        }
        .report-number { font-size: 2.2em; font-weight: bold; color: #2261a0; }
        .report-title { color: #3a4e6b; font-size: 1.05em; letter-spacing: .01em; }
        @media (max-width:900px){
            .report-card {padding: 12px;}
            .dashboard-header {font-size: 1.45em;}
        }
    </style>
</head>
<body>
<div class="container" style="max-width:1000px; margin-top:38px;">
    <div class="dashboard-header d-flex align-items-center mb-4">
        <img src="https://cdn-icons-png.flaticon.com/512/1068/1068720.png" width="48" class="me-2">
        Welcome to Your Management Dashboard
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="report-card">
                <div class="report-number"><?= $clients_count ?></div>
                <div class="report-title">Clients</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="report-card">
                <div class="report-number"><?= $machines_count ?></div>
                <div class="report-title">Machines</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="report-card">
                <div class="report-number"><?= $closings_count ?></div>
                <div class="report-title">Closings</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="report-card">
                <div class="report-number"><?= $invoices_count ?></div>
                <div class="report-title">Invoices</div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <a href="clients.php" class="quick-link">
                <img src="https://cdn-icons-png.flaticon.com/512/6460/6460945.png" width="22" class="me-2 mb-1">
                Manage Clients
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="machines.php" class="quick-link">
                <img src="https://cdn-icons-png.flaticon.com/512/4042/4042368.png" width="22" class="me-2 mb-1">
                Manage Machines
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="closings.php" class="quick-link">
                <img src="https://cdn-icons-png.flaticon.com/512/1828/1828884.png" width="22" class="me-2 mb-1">
                Closings
            </a>
        </div>
        <div class="col-md-3 col-6">
            <a href="invoices.php" class="quick-link">
                <img src="https://cdn-icons-png.flaticon.com/512/201/201623.png" width="22" class="me-2 mb-1">
                Invoices
            </a>
        </div>
    </div>
    <!-- مكان لجرافيك مستقبلي أو تقارير إضافية -->
    <div class="card p-3 mb-3 mt-4 text-center">
        <span style="font-size:1.08em;color:#3c4c6c;">Dashboard reports & statistics coming soon.<br>
            <span style="color:#8088b6;font-size:.97em;">(You can add charts, graphs or recent activity here)</span>
        </span>
    </div>
</div>
</body>
</html>
