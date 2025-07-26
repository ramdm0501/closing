<?php
include 'db.php';

// جلب بيانات الفاتورة من الرابط
$log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
$paper_size = isset($_GET['size']) ? $_GET['size'] : '';
$paper_type = isset($_GET['type']) ? $_GET['type'] : '';

// جلب بيانات التقفيل
$sql = "SELECT cl.*, c.name AS client_name, c.address, 
               m.brand, m.model, m.serial_number
        FROM closing_logs cl
        LEFT JOIN clients c ON cl.client_id = c.id
        LEFT JOIN machines m ON cl.machine_id = m.id
        WHERE cl.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $log_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice_data = $result->fetch_assoc();

// جلب توزيع الورق للصف المحدد
function getPaperRow($conn, $log_id, $size, $type) {
    $sql = "SELECT * FROM closing_paper_distribution 
            WHERE closing_log_id = ? 
            AND paper_size = ? 
            AND paper_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $log_id, $size, $type);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$paper_row = getPaperRow($conn, $log_id, $paper_size, $paper_type);

// جلب سعر الورق للعميل
$sql = "SELECT price, commitment FROM client_paper_settings 
        WHERE client_id = ? 
        AND paper_size = ? 
        AND paper_type = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $invoice_data['client_id'], $paper_size, $paper_type);
$stmt->execute();
$price_data = $stmt->get_result()->fetch_assoc();

// حساب السعر الإجمالي
$qty = $paper_row['paper_count'] ?? 0;
$unit_price = $price_data['price'] ?? 0;
$commitment = $price_data['commitment'] ?? 0;

if ($commitment > 0 && $qty < $commitment) {
    $total = $commitment * $unit_price;
    $show_qty = "$qty / $commitment (التزام)";
} else {
    $total = $qty * $unit_price;
    $show_qty = $qty;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $log_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .container { width: 100% !important; }
        }
        .invoice-header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-logo {
            max-height: 100px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table th, 
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .invoice-table th {
            background-color: #f5f5f5;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- رأس الفاتورة مع اللوجو -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-6">
                    <img src="logo.png" alt="Company Logo" class="company-logo">
                    <h3>Radio Graphics For Printing Soluations</h3>
                    <p>ت: 01095232405 | البريد الإلكتروني: info@radiographics.co</p>
                </div>
                <div class="col-6 text-end">
                    <h1 class="text-primary">فاتورة</h1>
                    <p>رقم الفاتورة: <strong>INV-<?= $log_id ?></strong></p>
                    <p>التاريخ: <?= date('Y-m-d', strtotime($invoice_data['close_date'])) ?></p>
                </div>
            </div>
        </div>

        <!-- معلومات العميل -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>معلومات العميل:</h5>
                <p><strong><?= $invoice_data['client_name'] ?></strong></p>
                <p>العنوان: <?= $invoice_data['address'] ?></p>
            </div>
            <div class="col-md-6">
                <h5>معلومات الماكينة:</h5>
                <p>الماركة: <?= $invoice_data['brand'] ?></p>
                <p>الموديل: <?= $invoice_data['model'] ?></p>
                <p>الرقم التسلسلي: <?= $invoice_data['serial_number'] ?></p>
            </div>
        </div>

        <!-- تفاصيل الفاتورة -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>تاريخ الإغلاق</th>
                    <th>المقاس</th>
                    <th>نوع الورق</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>المجموع</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= date('Y-m-d', strtotime($invoice_data['close_date'])) ?></td>
                    <td><?= $paper_size ?></td>
                    <td><?= ucfirst($paper_type) ?></td>
                    <td><?= $show_qty ?></td>
                    <td><?= number_format($unit_price, 2) ?></td>
                    <td><?= number_format($total, 2) ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="5" class="text-end">المجموع الكلي:</td>
                    <td><?= number_format($total, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- ملاحظات -->
        <div class="mt-4">
            <h5>ملاحظات:</h5>
            <p>شكراً لتعاملكم معنا. يرجى السداد خلال 30 يوماً.</p>
        </div>

        <!-- زر الطباعة -->
        <div class="mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print()">طباعة الفاتورة</button>
        </div>
    </div>
</body>
</html>