<?php
include 'db.php';

// أنواع الورق والمقاسات
$paper_types = ['xerox', 'glossy', 'plastic'];
$paper_sizes = ['A3', 'A4', 'A5', 'A6'];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_client'])) {
    $name           = trim($_POST['name']);
    $address        = trim($_POST['address']);
    $area           = trim($_POST['area']);
    $branches_count = intval($_POST['branches_count']);
    $branches_names = $_POST['branch_name'];
    $branches_addrs = $_POST['branch_addr'];
    $selected_types = $_POST['paper_type'] ?? [];
    $selected_sizes = $_POST['paper_size'] ?? [];
    $commitments    = $_POST['commitment'];
    $prices         = $_POST['price'];

    if (!$name) $errors[] = "Client name is required.";
    if ($branches_count < 1) $errors[] = "At least one branch is required.";

    if (empty($selected_types) || empty($selected_sizes)) {
        $errors[] = "Select at least one paper type and size.";
    }

    if (empty($errors)) {
        // إضافة العميل
        $stmt = $conn->prepare("INSERT INTO clients (name, address, area, branch_count) VALUES (?, ?, ?, ?)");
        if (!$stmt) die('SQL ERROR: ' . $conn->error);
        $stmt->bind_param("sssi", $name, $address, $area, $branches_count);
        $stmt->execute();
        $client_id = $stmt->insert_id;
        $stmt->close();

        // إضافة الفروع
        for ($i = 0; $i < $branches_count; $i++) {
            $bname = trim($branches_names[$i]);
            $baddr = trim($branches_addrs[$i]);
            if ($bname) {
                $stmt = $conn->prepare("INSERT INTO branches (client_id, name, address) VALUES (?, ?, ?)");
                if (!$stmt) die('SQL ERROR: ' . $conn->error);
                $stmt->bind_param("iss", $client_id, $bname, $baddr);
                $stmt->execute();
                $stmt->close();
            }
        }

        // إضافة أنواع الورق وأسعارها لهذا العميل في client_paper_settings
        foreach ($selected_types as $type) {
            foreach ($selected_sizes as $size) {
                $key = $type.'_'.$size;
                $monthly_commitment = intval($commitments[$key] ?? 0);
                $price_per_copy = floatval($prices[$key] ?? 0);
                $stmt = $conn->prepare("INSERT INTO client_paper_settings (client_id, paper_type, paper_size, commitment, price) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) die('SQL ERROR: ' . $conn->error);
                $stmt->bind_param("issid", $client_id, $type, $size, $monthly_commitment, $price_per_copy);
                $stmt->execute();
                $stmt->close();
            }
        }

        $success = "Client added successfully!";
    }
}

// جلب العملاء مع الفروع
$clients = $conn->query("SELECT * FROM clients ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clients Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; }
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .form-section { background: #fff; border-radius: 15px; box-shadow: 0 1px 10px #0001; padding: 28px 30px 20px 30px; }
        .branch-box { background: #f6f7fa; border-radius: 10px; padding: 12px 15px; margin-bottom: 10px; }
        .table thead th { background: #2874A6; color: #fff; }
        .btn-main { background: #2874A6; color: #fff; }
        .btn-main:hover { background: #1B4F72; color: #fff; }
        .badge { background: #2874A6; }
    </style>
    <script>
        function updateBranches() {
            let count = document.getElementById('branches_count').value || 1;
            let branchesDiv = document.getElementById('branches_inputs');
            let html = '';
            for (let i = 0; i < count; i++) {
                html += `
                <div class="branch-box mb-2">
                    <label class="form-label mb-1">Branch #${i+1} Name</label>
                    <input type="text" class="form-control mb-1" name="branch_name[]" required>
                    <label class="form-label mb-1">Branch #${i+1} Address</label>
                    <input type="text" class="form-control" name="branch_addr[]" required>
                </div>
                `;
            }
            branchesDiv.innerHTML = html;
        }
        window.addEventListener('DOMContentLoaded', updateBranches);
    </script>
</head>
<body>
<div class="container">
    <h2 class="mb-4 text-center">Clients Management</h2>

    <!-- إضافة عميل جديد -->
    <div class="form-section mb-4">
        <h5 class="mb-3">Add New Client</h5>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row mb-2">
                <div class="col-md-6">
                    <label class="form-label">Client Name</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Area</label>
                    <input type="text" class="form-control" name="area">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Branches Count</label>
                    <input type="number" class="form-control" id="branches_count" name="branches_count" value="1" min="1" max="10" onchange="updateBranches()" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Client Address</label>
                <input type="text" class="form-control" name="address">
            </div>
            <div id="branches_inputs"></div>

            <div class="mb-3">
                <label class="form-label">Paper Types</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($paper_types as $type): ?>
                        <div>
                            <input type="checkbox" name="paper_type[]" value="<?= $type ?>" id="paper_<?= $type ?>">
                            <label for="paper_<?= $type ?>" class="me-3"><?= ucfirst($type) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Paper Sizes</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($paper_sizes as $size): ?>
                        <div>
                            <input type="checkbox" name="paper_size[]" value="<?= $size ?>" id="size_<?= $size ?>">
                            <label for="size_<?= $size ?>" class="me-3"><?= $size ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
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
                        <?php foreach ($paper_types as $type): ?>
                            <?php foreach ($paper_sizes as $size): ?>
                                <tr>
                                    <td><?= ucfirst($type) ?></td>
                                    <td><?= $size ?></td>
                                    <td>
                                        <input type="number" class="form-control" name="commitment[<?= $type ?>_<?= $size ?>]" placeholder="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control" name="price[<?= $type ?>_<?= $size ?>]" placeholder="0.00">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <button type="submit" name="add_client" class="btn btn-main px-4 mt-2">Add Client</button>
        </form>
    </div>

    <!-- عرض العملاء -->
    <div class="card p-3">
        <h5 class="mb-3">All Clients</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle bg-white">
<thead>
    <tr>
        <th>#</th>
        <th>Name</th>
        <th>Area</th>
        <th>Address</th>
        <th>Branches</th>
        <th>Paper Prices</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php if ($clients && $clients->num_rows > 0): ?>
        <?php while($client = $clients->fetch_assoc()): ?>
            <tr>
                <td><?= $client['id'] ?></td>
                <td><?= htmlspecialchars($client['name']) ?></td>
                <td><?= htmlspecialchars($client['area']) ?></td>
                <td><?= htmlspecialchars($client['address']) ?></td>
                <td>
                    <?php
                    $branches = $conn->query("SELECT * FROM branches WHERE client_id=" . $client['id']);
                    if ($branches && $branches->num_rows > 0) {
                        while ($br = $branches->fetch_assoc()) {
                            echo "<span class='d-block mb-1'><b>{$br['name']}</b>: {$br['address']}</span>";
                        }
                    } else {
                        echo "<span class='text-muted'>-</span>";
                    }
                    ?>
                </td>
                <td>
                    <?php
                    $sql_prices = "
                        SELECT paper_type, paper_size, commitment, price 
                        FROM client_paper_settings
                        WHERE client_id = {$client['id']}
                    ";
                    $prices = $conn->query($sql_prices);
                    if ($prices && $prices->num_rows > 0) {
                        while ($pp = $prices->fetch_assoc()) {
                            echo "<span class='badge me-1'>{$pp['paper_type']} {$pp['paper_size']}</span>
                            <span class='text-success'>\${$pp['price']}</span>
                            <span class='text-info ms-1'>" . ($pp['commitment'] ? "Commit: {$pp['commitment']}" : "No Commit") . "</span><br>";
                        }
                    } else {
                        echo "<span class='text-muted'>-</span>";
                    }
                    ?>
                </td>
                <td>
                    <a href="client_view.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-info mb-1">View</a>
                    <a href="client_edit.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                    <a href="client_delete.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-danger mb-1"
                       onclick="return confirm('Are you sure you want to delete this client?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" class="text-center">No clients yet.</td>
        </tr>
    <?php endif; ?>
</tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
