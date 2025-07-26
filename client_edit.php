<?php
include 'db.php';

// أنواع الورق والمقاسات من جدول paper_types في قاعدة البيانات
$paper_types = [];
$res_types = $conn->query("SELECT * FROM paper_types ORDER BY type, size");
while ($row = $res_types->fetch_assoc()) {
    $paper_types[] = $row;
}

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

// جلب أسعار الورق للعميل
$paper_prices = [];
$sql = "SELECT paper_type, paper_size, commitment, price 
                        FROM client_paper_settings
                        WHERE client_id = {$client['id']}
                    ";
$res = $conn->query($sql);
while ($pp = $res->fetch_assoc()) {
    $key = $pp['paper_type'].'_'.$pp['paper_size'];
    $paper_prices[$key] = $pp;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_client'])) {
    $name           = trim($_POST['name']);
    $address        = trim($_POST['address']);
    $area           = trim($_POST['area']);
    $branches_count = intval($_POST['branches_count']);
    $branches_names = $_POST['branch_name'];
    $branches_addrs = $_POST['branch_addr'];
    $commitments    = $_POST['commitment'];
    $prices         = $_POST['price'];

    if (!$name) $errors[] = "Client name is required.";
    if ($branches_count < 1) $errors[] = "At least one branch is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE clients SET name=?, address=?, area=?, branches_count=? WHERE id=?");
        $stmt->bind_param("sssii", $name, $address, $area, $branches_count, $client_id);
        $stmt->execute();
        $stmt->close();

        // حذف كل الفروع القديمة وإعادة إدخال الجديد
        $conn->query("DELETE FROM branches WHERE client_id = $client_id");
        for ($i = 0; $i < $branches_count; $i++) {
            $bname = trim($branches_names[$i]);
            $baddr = trim($branches_addrs[$i]);
            if ($bname) {
                $stmt = $conn->prepare("INSERT INTO branches (client_id, name, address) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $client_id, $bname, $baddr);
                $stmt->execute();
                $stmt->close();
            }
        }

        // تعديل أسعار الورق والالتزام
        foreach ($paper_types as $pt) {
            $key = $pt['type'].'_'.$pt['size'];
            $ptid = $pt['id'];
            $monthly_commitment = intval($commitments[$key] ?? 0);
            $price_per_copy = floatval($prices[$key] ?? 0);

            // هل للعميل سعر قديم لهذا النوع؟
            if (isset($paper_prices[$key]['price']) && $paper_prices[$key]['price']) {
                $price_id = $paper_prices[$key]['price'];
                $stmt = $conn->prepare("UPDATE client_paper_settings SET commitment=?, price=? WHERE id=?");
                $stmt->bind_param("idi", $monthly_commitment, $price_per_copy, $price_id);
                $stmt->execute();
                $stmt->close();
            } else {
                // لو سعر جديد
                if ($monthly_commitment > 0 || $price_per_copy > 0) {
                    $stmt = $conn->prepare("INSERT INTO client_paper_settings (client_id, paper_type, commitment, price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiid", $client_id, $ptid, $monthly_commitment, $price_per_copy);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        header("Location: client_edit.php?id=$client_id&success=1");
        exit;
    }
}

if (isset($_GET['success'])) {
    $success = "Client updated successfully!";
}

// إعادة تحميل الفروع بعد الحفظ
$branches = [];
$res_branches = $conn->query("SELECT * FROM branches WHERE client_id = $client_id");
while ($br = $res_branches->fetch_assoc()) $branches[] = $br;
$branchNames = array_column($branches, 'name');
$branchAddrs = array_column($branches, 'address');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 900px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .branch-box { background: #f6f7fa; border-radius: 10px; padding: 12px 15px; margin-bottom: 10px; }
        .btn-main { background: #2874A6; color: #fff; }
        .btn-main:hover { background: #1B4F72; color: #fff; }
    </style>
    <script>
        function updateBranches() {
            var count = document.getElementById('branches_count').value || 1;
            var branchesDiv = document.getElementById('branches_inputs');
            var branchNames = <?= json_encode($branchNames) ?>;
            var branchAddrs = <?= json_encode($branchAddrs) ?>;
            var html = '';
            for (var i = 0; i < count; i++) {
                var nameValue = branchNames[i] !== undefined ? branchNames[i].replace(/"/g, '&quot;') : '';
                var addrValue = branchAddrs[i] !== undefined ? branchAddrs[i].replace(/"/g, '&quot;') : '';
                html += `
                <div class="branch-box mb-2">
                    <label class="form-label mb-1">Branch #${i+1} Name</label>
                    <input type="text" class="form-control mb-1" name="branch_name[]" value="${nameValue}" required>
                    <label class="form-label mb-1">Branch #${i+1} Address</label>
                    <input type="text" class="form-control" name="branch_addr[]" value="${addrValue}" required>
                </div>
                `;
            }
            branchesDiv.innerHTML = html;
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateBranches();
            document.getElementById('branches_count').addEventListener('input', updateBranches);
        });
    </script>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-3 text-center">Edit Client</h3>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row mb-2">
                <div class="col-md-6">
                    <label class="form-label">Client Name</label>
                    <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($client['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Area</label>
                    <input type="text" class="form-control" name="area" value="<?= htmlspecialchars($client['area']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Branches Count</label>
                    <input type="number" class="form-control" id="branches_count" name="branches_count" value="<?= $client['branches_count'] ?>" min="1" max="10" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Client Address</label>
                <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($client['address']) ?>">
            </div>
            <div id="branches_inputs"></div>
            <div class="mb-3 mt-4">
                <label class="form-label">Monthly Commitment & Price</label>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Commitment</th>
                                <th>Price / Copy</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paper_types as $pt): 
                            $key = $pt['type'].'_'.$pt['size'];
                            $pp = $paper_prices[$key] ?? ['commitment'=>'', 'price'=>''];
                        ?>
                            <tr>
                                <td><?= ucfirst($pt['type']) ?></td>
                                <td><?= $pt['size'] ?></td>
                                <td>
                                    <input type="number" class="form-control" name="commitment[<?= $key ?>]" placeholder="0" value="<?= htmlspecialchars($pp['commitment']) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control" name="price[<?= $key ?>]" placeholder="0.00" value="<?= htmlspecialchars($pp['price']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="submit" name="edit_client" class="btn btn-main px-4 mt-2">Save Changes</button>
            <a href="clients.php" class="btn btn-secondary ms-2 mt-2">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
