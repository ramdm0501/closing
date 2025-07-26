<?php
include 'db.php';

// جلب العملاء (وأفرعهم)
$clients = [];
$res_clients = $conn->query("SELECT * FROM clients ORDER BY name");
while ($row = $res_clients->fetch_assoc()) {
    $row['branches'] = [];
    $res_br = $conn->query("SELECT * FROM branches WHERE client_id = " . $row['id'] . " ORDER BY name");
    while ($br = $res_br->fetch_assoc()) {
        $row['branches'][] = $br;
    }
    $clients[] = $row;
}

$errors = [];
$success = '';

// إضافة ماكينة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_machine'])) {
    $client_id        = intval($_POST['client_id']);
    $branch_id        = isset($_POST['branch_id']) && $_POST['branch_id'] ? intval($_POST['branch_id']) : null;
    $brand            = trim($_POST['brand']);
    $model            = trim($_POST['model']);
    $serial_number    = trim($_POST['serial_number']);
    $start_counter_a3 = intval($_POST['start_counter_a3'] ?? 0);
    $start_counter_a4 = intval($_POST['start_counter_a4'] ?? 0);
    $start_counter_a5 = intval($_POST['start_counter_a5'] ?? 0);
    $start_counter_a6 = intval($_POST['start_counter_a6'] ?? 0);

    if (!$client_id) $errors[] = "Client is required.";
    if (!$brand) $errors[] = "Brand is required.";
    if (!$model) $errors[] = "Model is required.";
    if (!$serial_number) $errors[] = "Serial Number is required.";

    // Check for duplicate serial number
    $check_serial = $conn->prepare("SELECT id FROM machines WHERE serial_number=?");
    $check_serial->bind_param("s", $serial_number);
    $check_serial->execute();
    $check_serial->store_result();
    if ($check_serial->num_rows > 0) {
        $errors[] = "Serial Number already exists!";
    }
    $check_serial->close();

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO machines 
            (client_id, branch_id, brand, model, serial_number, start_counter_a3, start_counter_a4, start_counter_a5, start_counter_a6)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "iisssiiii",
            $client_id, $branch_id, $brand, $model, $serial_number,
            $start_counter_a3, $start_counter_a4, $start_counter_a5, $start_counter_a6
        );
        $stmt->execute();
        $stmt->close();
        $success = "Machine added successfully!";
    }
}

// جلب كل الماكينات
$sql = "SELECT m.*, c.name AS client_name, b.name AS branch_name
        FROM machines m
        LEFT JOIN clients c ON m.client_id = c.id
        LEFT JOIN branches b ON m.branch_id = b.id
        ORDER BY m.id DESC";
$machines = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Machines Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 1100px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .btn-main { background: #2874A6; color: #fff; }
        .btn-main:hover { background: #1B4F72; color: #fff; }
    </style>
    <script>
        // عند اختيار عميل، أظهر أفرعه في قائمة الفروع (أو None)
        let clientsData = <?= json_encode($clients) ?>;
        function updateBranches() {
            let clientSelect = document.getElementById('client_id');
            let branchSelect = document.getElementById('branch_id');
            let clientId = clientSelect.value;
            let found = clientsData.find(c => c.id == clientId);
            let options = '<option value="">-- None (Machine for all client) --</option>';
            if (found && found.branches.length) {
                found.branches.forEach(function(br) {
                    options += `<option value="${br.id}">${br.name}</option>`;
                });
            }
            branchSelect.innerHTML = options;
        }
    </script>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 text-center flex-grow-1">Machines Management</h2>
        <a href="dashboard.php" class="btn btn-secondary ms-3">Back to Dashboard</a>
    </div>
    <!-- إضافة ماكينة جديدة -->
    <div class="card p-4 mb-4">
        <h5 class="mb-3">Add New Machine</h5>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= implode('<br>', $errors); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= $success; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Client <span class="text-danger">*</span></label>
                    <select name="client_id" id="client_id" class="form-control" required onchange="updateBranches()">
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Branch (optional)</label>
                    <select name="branch_id" id="branch_id" class="form-control">
                        <option value="">-- None (Machine for all client) --</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                    <input type="text" name="brand" class="form-control" required>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Model <span class="text-danger">*</span></label>
                    <input type="text" name="model" class="form-control" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                    <input type="text" name="serial_number" class="form-control" required>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A3</label>
                    <input type="number" name="start_counter_a3" class="form-control" value="0">
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A4</label>
                    <input type="number" name="start_counter_a4" class="form-control" value="0">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A5</label>
                    <input type="number" name="start_counter_a5" class="form-control" value="0">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A6</label>
                    <input type="number" name="start_counter_a6" class="form-control" value="0">
                </div>
            </div>
            <button type="submit" name="add_machine" class="btn btn-main px-4 mt-2">Add Machine</button>
        </form>
    </div>
    <!-- عرض كل الماكينات -->
    <div class="card p-3">
        <h5 class="mb-3">All Machines</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle bg-white">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Branch</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Serial Number</th>
                        <th>A3</th>
                        <th>A4</th>
                        <th>A5</th>
                        <th>A6</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($machines && $machines->num_rows > 0): ?>
                        <?php while($m = $machines->fetch_assoc()): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['client_name']) ?></td>
                                <td><?= $m['branch_name'] ? htmlspecialchars($m['branch_name']) : '<span class="text-muted">All Client</span>' ?></td>
                                <td><?= htmlspecialchars($m['brand']) ?></td>
                                <td><?= htmlspecialchars($m['model']) ?></td>
                                <td><?= htmlspecialchars($m['serial_number']) ?></td>
                                <td><?= $m['start_counter_a3'] ?></td>
                                <td><?= $m['start_counter_a4'] ?></td>
                                <td><?= $m['start_counter_a5'] ?></td>
                                <td><?= $m['start_counter_a6'] ?></td>
                                <td>
                                    <a href="machine_view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-info mb-1">View</a>
                                    <a href="machine_edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                                    <a href="machine_delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-danger mb-1"
                                        onclick="return confirm('Are you sure you want to delete this machine?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No machines yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    // لتعبئة الفروع عند أول تحميل الصفحة إذا العميل تم اختياره
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('client_id').value) {
            updateBranches();
        }
    });
</script>
</body>
</html>
