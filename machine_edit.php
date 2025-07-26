<?php
include 'db.php';

// جلب العملاء مع الفروع
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

// التأكد من وجود معرف الماكينة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<div style='margin:40px'><h3>Machine ID is missing!</h3></div>");
}
$machine_id = intval($_GET['id']);

// جلب بيانات الماكينة
$stmt = $conn->prepare("SELECT * FROM machines WHERE id = ?");
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$machine = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$machine) {
    die("<div style='margin:40px'><h3>Machine not found!</h3></div>");
}

$errors = [];
$success = '';

// عند حفظ التعديل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_machine'])) {
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

    // Check if serial number duplicated (except this machine)
    $check_serial = $conn->prepare("SELECT id FROM machines WHERE serial_number=? AND id<>?");
    $check_serial->bind_param("si", $serial_number, $machine_id);
    $check_serial->execute();
    $check_serial->store_result();
    if ($check_serial->num_rows > 0) {
        $errors[] = "Serial Number already exists!";
    }
    $check_serial->close();

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE machines SET 
            client_id=?, branch_id=?, brand=?, model=?, serial_number=?, 
            start_counter_a3=?, start_counter_a4=?, start_counter_a5=?, start_counter_a6=? 
            WHERE id=?");
        $stmt->bind_param(
            "iisssiiiii",
            $client_id, $branch_id, $brand, $model, $serial_number,
            $start_counter_a3, $start_counter_a4, $start_counter_a5, $start_counter_a6, $machine_id
        );
        $stmt->execute();
        $stmt->close();
        header("Location: machine_edit.php?id=$machine_id&success=1");
        exit;
    }
}

if (isset($_GET['success'])) {
    $success = "Machine updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Machine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e3eafc; }
        .container { margin-top: 40px; max-width: 800px;}
        .card { border-radius: 18px; box-shadow: 0 2px 15px #0001; }
        .btn-main { background: #2874A6; color: #fff; }
        .btn-main:hover { background: #1B4F72; color: #fff; }
    </style>
    <script>
        let clientsData = <?= json_encode($clients) ?>;
        function updateBranches(selectedBranchId = "") {
            let clientSelect = document.getElementById('client_id');
            let branchSelect = document.getElementById('branch_id');
            let clientId = clientSelect.value;
            let found = clientsData.find(c => c.id == clientId);
            let options = '<option value="">-- None (Machine for all client) --</option>';
            if (found && found.branches.length) {
                found.branches.forEach(function(br) {
                    let selected = selectedBranchId == br.id ? "selected" : "";
                    options += `<option value="${br.id}" ${selected}>${br.name}</option>`;
                });
            }
            branchSelect.innerHTML = options;
        }
        document.addEventListener('DOMContentLoaded', function () {
            // تحديث قائمة الفروع تلقائياً عند أول تحميل الصفحة
            updateBranches("<?= $machine['branch_id'] ?>");
            document.getElementById('client_id').addEventListener('change', function(){
                updateBranches();
            });
        });
    </script>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Edit Machine</h3>
        <a href="machines.php" class="btn btn-secondary">Back to Machines</a>
    </div>
    <div class="card p-4">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?= implode('<br>', $errors); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= $success; ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Client <span class="text-danger">*</span></label>
                    <select name="client_id" id="client_id" class="form-control" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $machine['client_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Branch (optional)</label>
                    <select name="branch_id" id="branch_id" class="form-control">
                        <!-- سيتم تعبئتها تلقائياً بالجافاسكريبت -->
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Brand <span class="text-danger">*</span></label>
                    <input type="text" name="brand" class="form-control" required value="<?= htmlspecialchars($machine['brand']) ?>">
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Model <span class="text-danger">*</span></label>
                    <input type="text" name="model" class="form-control" required value="<?= htmlspecialchars($machine['model']) ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Serial Number <span class="text-danger">*</span></label>
                    <input type="text" name="serial_number" class="form-control" required value="<?= htmlspecialchars($machine['serial_number']) ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A3</label>
                    <input type="number" name="start_counter_a3" class="form-control" value="<?= $machine['start_counter_a3'] ?>">
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A4</label>
                    <input type="number" name="start_counter_a4" class="form-control" value="<?= $machine['start_counter_a4'] ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A5</label>
                    <input type="number" name="start_counter_a5" class="form-control" value="<?= $machine['start_counter_a5'] ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Start Counter A6</label>
                    <input type="number" name="start_counter_a6" class="form-control" value="<?= $machine['start_counter_a6'] ?>">
                </div>
            </div>
            <button type="submit" name="edit_machine" class="btn btn-main px-4 mt-2">Save Changes</button>
            <a href="machines.php" class="btn btn-secondary ms-2 mt-2">Cancel</a>
        </form>
    </div>
</div>
</body>
</html>
