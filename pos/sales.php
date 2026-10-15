<?php
session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($conn) && isset($pdo)) {
    $conn = $pdo;
}

include __DIR__ . '/../includes/exporthelper.php';

$stmt = $conn->query("PRAGMA table_info(sales)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasStatus = false;
$hasPayment = false;

foreach ($columns as $col) {
    if ($col['name'] === 'status') $hasStatus = true;
    if ($col['name'] === 'payment_method') $hasPayment = true;
}

if (!$hasStatus) {
    $conn->exec("ALTER TABLE sales ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Paid'");
}
if (!$hasPayment) {
    $conn->exec("ALTER TABLE sales ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
}

if (isset($_GET['export']) && ($_GET['export'] === 'excel' || $_GET['export'] === 'xlsx')) {
    if (function_exists('updateMasterExcelFile')) {
        updateMasterExcelFile($conn);
    }

    // Updated headers for .xlsx format
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=sales_report.xlsx");
    
    $export_query = "
        SELECT 
            s.id AS transaction_id, 
            'POS' AS source_type,
            s.customer_name, 
            DATE(s.sale_date) AS date_recorded, 
            GROUP_CONCAT(COALESCE(p.name, 'Day Pass') || ' (' || si.qty || ')', ', ') AS items_list,
            s.amount, 
            s.payment_method, 
            s.status
        FROM sales s 
        LEFT JOIN sale_items si ON s.id = si.sale_id 
        LEFT JOIN products p ON si.product_id = p.id
        GROUP BY s.id 
        UNION ALL
        SELECT 
            m.id AS transaction_id, 
            'MEMBERSHIP' AS source_type,
            COALESCE(m.name, 'Gym Member') AS customer_name, 
            DATE(m.created_at) AS date_recorded, 
            'Gym Membership (' || COALESCE(m.membership_type, 'Standard') || ')' AS items_list,
            m.final_rate AS amount, 
            'Cash' AS payment_method, 
            'Paid' AS status
        FROM members m
        ORDER BY date_recorded DESC";
        
    $res = $conn->query($export_query);

    echo "ID\tSource Type\tCustomer Name\tDate\tItems Bought / Description\tAmount\tPayment\tStatus\n";
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
        $typeTag = ($r['source_type'] === 'MEMBERSHIP') ? '[MEMBER]' : '[POS]';
        echo "#{$r['transaction_id']}\t{$typeTag}\t{$r['customer_name']}\t{$r['date_recorded']}\t{$r['items_list']}\t{$r['amount']}\t{$r['payment_method']}\t{$r['status']}\n";
    }
    exit();
}

if (isset($_POST['add_product'])) {
    $name = $_POST['pname'];
    $price = $_POST['pprice'];
    $stock = $_POST['pstock'];
    
    $image_path = ""; 

    if (isset($_FILES['pimg']) && $_FILES['pimg']['error'] === 0) {
        $userDataPath = getenv('USER_DATA_PATH');
        $target_dir = $userDataPath ? $userDataPath . '/assets/' : '../assets/';
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["pimg"]["name"], PATHINFO_EXTENSION);
        $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["pimg"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, stock, image_url) VALUES (?, ?, ?, ?)");
    if($stmt->execute([$name, $price, $stock, $image_path])) {
        header("Location: sales.php?success=1");
        exit();
    }
}

if (isset($_POST['update_product'])) {
    $prod_id = intval($_POST['prod_id']);
    $name = $_POST['edit_name'];
    $price = $_POST['edit_price'];
    $stock = $_POST['edit_stock'];

    if (isset($_FILES['edit_img']) && $_FILES['edit_img']['error'] === 0) {
        $userDataPath = getenv('USER_DATA_PATH');
        $target_dir = $userDataPath ? $userDataPath . '/assets/' : '../assets/';
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $stmt_img = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt_img->execute([$prod_id]);
        $old_img = $stmt_img->fetch(PDO::FETCH_ASSOC);

        $file_extension = pathinfo($_FILES["edit_img"]["name"], PATHINFO_EXTENSION);
        $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["edit_img"]["tmp_name"], $target_file)) {
            if ($old_img && !empty($old_img['image_url']) && file_exists($old_img['image_url'])) {
                @unlink($old_img['image_url']);
            }
            $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, stock = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $price, $stock, $target_file, $prod_id]);
        }
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->execute([$name, $price, $stock, $prod_id]);
    }

    header("Location: sales.php?updated_product=1");
    exit();
}

if (isset($_POST['delete_product'])) {
    $delete_id = intval($_POST['delete_id']);
    
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->execute([$delete_id]);
    $img_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($img_row && !empty($img_row['image_url']) && file_exists($img_row['image_url'])) {
        @unlink($img_row['image_url']);
    }

    $del = $conn->prepare("DELETE FROM products WHERE id = ?");
    $del->execute([$delete_id]);
    
    header("Location: sales.php?deleted=1");
    exit();
}

if (isset($_POST['delete_sale'])) {
    $sale_id = intval($_POST['sale_id']);
    
    $stmt1 = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmt1->execute([$sale_id]);
    
    $stmt2 = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $stmt2->execute([$sale_id]);
    
    if (function_exists('updateMasterExcelFile')) { updateMasterExcelFile($conn); }
    header("Location: sales.php?sale_deleted=1");
    exit();
}

if (isset($_POST['delete_all_sales'])) {
    $conn->exec("DELETE FROM sale_items");
    $conn->exec("DELETE FROM sales");
    if (function_exists('updateMasterExcelFile')) { updateMasterExcelFile($conn); }
    header("Location: sales.php?all_deleted=1");
    exit();
}

if (isset($_POST['update_status'])) {
    $sale_id = intval($_POST['sale_id']);
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE sales SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $sale_id]);
    
    if (function_exists('updateMasterExcelFile')) { updateMasterExcelFile($conn); }
    header("Location: sales.php?updated=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_sale'])) {
    $total = floatval($_POST['total_amount']);
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
    $customer_name = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in Guest';
    $status = 'Paid';
    $items = json_decode($_POST['items_json'], true);
    
    $stmt = $conn->prepare("INSERT INTO sales (customer_name, amount, status, payment_method, sale_date) VALUES (?, ?, ?, ?, datetime('now'))");
    $insert_sale = $stmt->execute([$customer_name, $total, $status, $payment_method]);
    
    if (!$insert_sale) {
        echo "DB Error (Sales)";
        exit;
    }
    
    $sale_id = $conn->lastInsertId();
    
    foreach ($items as $item) {
        $id = intval($item['id']);
        $qty = intval($item['qty']);
        $price = floatval($item['price']);
        
        if ($id > 0) {
            $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $upd->execute([$qty, $id]);
        }
        $ins = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price) VALUES (?, ?, ?, ?)");
        $ins->execute([$sale_id, $id, $qty, $price]);
    }

    if (function_exists('updateMasterExcelFile')) {
        updateMasterExcelFile($conn);
    }

    echo "success";
    exit;
}

$products_res = $conn->query("SELECT * FROM products ORDER BY id DESC");

$history_query = "
    SELECT 
        s.id AS transaction_id, 
        'POS' AS source_type,
        s.customer_name, 
        s.sale_date AS date_recorded, 
        GROUP_CONCAT(COALESCE(p.name, 'Day Pass') || ' (' || si.qty || ')', ', ') AS items_list,
        s.amount, 
        s.payment_method, 
        s.status
    FROM sales s 
    LEFT JOIN sale_items si ON s.id = si.sale_id 
    LEFT JOIN products p ON si.product_id = p.id
    GROUP BY s.id 

    UNION ALL

    SELECT 
        m.id AS transaction_id, 
        'MEMBERSHIP' AS source_type,
        COALESCE(m.name, 'Gym Member') AS customer_name, 
        m.created_at AS date_recorded, 
        'Gym Membership (' || COALESCE(m.membership_type, 'Standard') || ')' AS items_list,
        m.final_rate AS amount, 
        'Cash' AS payment_method, 
        'Paid' AS status
    FROM members m

    ORDER BY date_recorded DESC";

$history_result = $conn->query($history_query);
if (!$history_result) {
    die("SQL Error"); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS & Sales History | Project E</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            background: #0d0d0d; color: #ffffff; font-family: 'Inter', 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.3), rgba(15, 15, 15, 0.1)), url('../assets/gym.jpg');
            background-size: cover; background-attachment: fixed; min-height: 100vh;
        }
        .container-wide { max-width: 1280px; margin: 0 auto; padding: 40px 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .sales-grid { display: grid; grid-template-columns: 1fr 440px; gap: 25px; margin-bottom: 50px; }
        .glass-card { 
            background: rgba(18, 18, 18, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); 
            padding: 25px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6); display: flex; flex-direction: column;
        }
        .glass-card h3 { margin: 0 0 20px 0; font-size: 1rem; color: #a3a3a3; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; max-height: 520px; overflow-y: auto; padding-right: 5px; }
        .product-card { background: #141414; border: 1px solid #262626; border-radius: 12px; overflow: hidden; cursor: pointer; transition: all 0.2s; text-align: center; position: relative; }
        .product-card:hover { transform: translateY(-4px); border-color: #dc2626; box-shadow: 0 10px 20px rgba(220, 38, 38, 0.2); background: #1a1a1a; }
        .product-card img { width: 100%; height: 110px; object-fit: contain; padding: 10px; background: rgba(255, 255, 255, 0.02); }
        .delete-btn { position: absolute; top: 6px; right: 6px; background: rgba(220, 38, 38, 0.85); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .btn-edit-prod { background: #2563eb; color: white; border: none; border-radius: 6px; padding: 4px 10px; font-size: 0.75rem; font-weight: 600; cursor: pointer; margin-top: 6px; display: inline-block; }
        .btn-add { background: #dc2626; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn-clear { background: #374151; color: white; border: none; padding: 10px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 8px; width: 100%; }
        .btn-checkout { width: 100%; padding: 14px; background: #dc2626; border: none; border-radius: 10px; color: white; font-weight: 700; cursor: pointer; margin-top: 10px; text-transform: uppercase; }
        .btn-gcash { width: 100%; padding: 12px; background: #007dfe; border: none; border-radius: 10px; color: #ffffff; font-weight: 600; cursor: pointer; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        .cart-item-row { display: flex; align-items: center; justify-content: space-between; padding: 12px; margin-bottom: 8px; background: #141414; border: 1px solid #262626; border-radius: 10px; }
        .qty-controls { display: inline-flex; align-items: center; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; padding: 2px; }
        .qty-btn { background: none; border: none; color: #a3a3a3; width: 26px; height: 26px; cursor: pointer; border-radius: 6px; }
        .qty-display { font-weight: 700; min-width: 24px; text-align: center; color: #ffffff; }
        .history-table th { background: rgba(220, 38, 38, 0.1); color: #ef4444; padding: 14px; text-align: left; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
        .history-table td { padding: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #d4d4d4; font-size: 0.9rem; }
        .status-select { background: #141414; color: #ffffff; border: 1px solid #404040; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; }
        .badge-cash { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
        .badge-gcash { background: rgba(0, 125, 254, 0.15); color: #60a5fa; border: 1px solid rgba(0, 125, 254, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; }
        .badge-member { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; margin-top: 4px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(6px); }
        .modal-content { background: #141414; margin: 6% auto; padding: 25px; width: 90%; max-width: 420px; border-radius: 16px; color: #ffffff; border: 1px solid #262626; text-align: center; }
        .modal-content input { width: 100%; padding: 10px; margin: 6px 0 12px 0; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; color: white; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container-wide">
    <div class="page-header">
        <h2 style="border-left: 4px solid #dc2626; padding-left: 12px; margin:0;">Point of Sale</h2>
        <div style="display: flex; gap: 10px;">
            <a href="../pos/sales.php?export=excel" style="padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">📊 Export Financial Report</a>
            <button onclick="openModal('productModal')" class="btn-add">+ New Product</button>
        </div>
    </div>

    <div class="sales-grid">
        <div class="glass-card">
            <h3>Inventory</h3>
            <div class="product-grid">
                <?php while($p = $products_res->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="product-card" onclick='addToCart(<?php echo json_encode($p); ?>)'>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="delete_product" class="delete-btn" onclick="event.stopPropagation();">&times;</button>
                        </form>
                        <img src="<?php echo !empty($p['image_url']) ? $p['image_url'] : 'https://via.placeholder.com/150'; ?>" alt="product">
                        <div style="padding: 10px;">
                            <h4 style="margin: 0; font-size: 0.85rem; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $p['name']; ?></h4>
                            <p style="color: #ef4444; font-weight: 700; margin: 4px 0 2px 0;">₱<?php echo number_format($p['price'], 2); ?></p>
                            <small style="color: #737373;">Stock: <?php echo $p['stock']; ?></small>
                            <div>
                                <button type="button" class="btn-edit-prod" onclick="event.stopPropagation(); openEditProductModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['price']; ?>, <?php echo $p['stock']; ?>)">Edit Details</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="glass-card">
            <h3>Current Order</h3>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-size: 0.8rem; color: #a3a3a3; margin-bottom: 6px;">Customer Name</label>
                <input type="text" id="customerName" placeholder="Walk-in Guest" style="width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; color: white;">
            </div>
            <div id="cartContainer" style="max-height: 280px; overflow-y: auto; flex-grow: 1;"></div>
            <button type="button" onclick="clearCart()" class="btn-clear">🧹 Clear POS Cart</button>
            
            <div style="margin-top: 15px; border-top: 1px solid #262626; padding-top: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="color: #a3a3a3;">Grand Total</span>
                    <h2 style="margin: 0;">₱<span id="totalDisplay">0.00</span></h2>
                </div>
                <button class="btn-checkout" onclick="processSale('Cash')">Complete (Cash)</button>
                <button class="btn-gcash" onclick="openGcashModal()">Pay via GCash</button>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Transaction History</h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="openModal('walkInModal')" class="btn-add" style="background: #16a34a;">⚡ Walk-In Day Pass</button>
            <form method="POST" onsubmit="return confirm('WARNING: This will delete ALL retail POS history records. Proceed?');" style="margin:0;">
                <button type="submit" name="delete_all_sales" class="btn-add" style="background: #450a0a; color: #fca5a5; border: 1px solid #dc2626;">🗑 Delete POS History</button>
            </form>
        </div>
    </div>

    <div class="glass-card" style="padding: 0; overflow: hidden;">
        <table class="history-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Date</th>
                    <th>Items Bought / Description</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $history_rows = $history_result->fetchAll(PDO::FETCH_ASSOC);
                if (count($history_rows) > 0): 
                ?>
                    <?php foreach($history_rows as $row): ?>
                    <?php 
                        $current_status = $row['status'] ?? 'Paid'; 
                        $payment_method = $row['payment_method'] ?? 'Cash';
                        $is_member_rec = ($row['source_type'] === 'MEMBERSHIP');
                    ?>
                    <tr>
                        <td>
                            #<?php echo $row['transaction_id']; ?>
                            <?php if ($is_member_rec): ?>
                                <br><span class="badge-member">MEMBER</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['date_recorded'])); ?></td>
                        <td><?php echo htmlspecialchars($row['items_list']); ?></td>
                        <td style="font-weight:700;">₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td>
                            <span class="<?php echo strtolower($payment_method) === 'gcash' ? 'badge-gcash' : 'badge-cash'; ?>">
                                <?php echo strtolower($payment_method) === 'gcash' ? '📱 GCash' : '💵 Cash'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($current_status === 'Paid'): ?>
                                <span style="color: #22c55e; font-weight:600;">● Paid</span>
                            <?php elseif ($current_status === 'Void'): ?>
                                <span style="color: #f59e0b; font-weight:600;">● Void</span>
                            <?php else: ?>
                                <span style="color: #ef4444; font-weight:600;">● Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$is_member_rec): ?>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <form method="POST" style="display:inline-flex; gap:4px;">
                                        <input type="hidden" name="sale_id" value="<?php echo $row['transaction_id']; ?>">
                                        <select name="status" class="status-select">
                                            <option value="Paid" <?php echo ($current_status === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Unpaid" <?php echo ($current_status === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                            <option value="Void" <?php echo ($current_status === 'Void') ? 'selected' : ''; ?>>Void</option>
                                        </select>
                                        <button type="submit" name="update_status" style="background:#dc2626; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">Save</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this record permanently?');" style="display:inline;">
                                        <input type="hidden" name="sale_id" value="<?php echo $row['transaction_id']; ?>">
                                        <button type="submit" name="delete_sale" style="background:#450a0a; color:#fca5a5; border:1px solid #dc2626; padding:4px 8px; border-radius:4px; cursor:pointer;" title="Delete Sale">Delete</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <small style="color:#737373;">Managed in Members</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #737373;">No transactions recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <h3 style="color:#ef4444; margin-top:0;">Add New Product</h3>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Name</label>
            <input type="text" name="pname" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Price</label>
            <input type="number" name="pprice" step="0.01" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Stock</label>
            <input type="number" name="pstock" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Image</label>
            <input type="file" name="pimg" accept="image/*" style="border:none;">
            <button type="submit" name="add_product" class="btn-checkout" style="margin-top:15px;">Save</button>
            <button type="button" onclick="closeModal('productModal')" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<div id="editProductModal" class="modal">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <h3 style="color:#2563eb; margin-top:0;">Edit Product Details</h3>
            <input type="hidden" name="prod_id" id="editProdId">
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Product Name</label>
            <input type="text" name="edit_name" id="editProdName" required>
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Price (₱)</label>
            <input type="number" name="edit_price" id="editProdPrice" step="0.01" required>
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Stock Quantity</label>
            <input type="number" name="edit_stock" id="editProdStock" required min="0">
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Replace Image (Optional)</label>
            <input type="file" name="edit_img" accept="image/*" style="border:none;">
            
            <button type="submit" name="update_product" class="btn-checkout" style="background:#2563eb; margin-top:15px;">Save Changes</button>
            <button type="button" onclick="closeModal('editProductModal')" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

<div id="walkInModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#16a34a; text-align: left;">Walk-In Day Pass</h3>
        <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Customer Name</label>
        <input type="text" id="walkInCustomer" value="Walk-in Guest">
        <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Day Pass Price (₱)</label>
        <input type="number" id="walkInPrice" value="100.00" step="100.00">
        <button class="btn-checkout" style="background:#16a34a;" onclick="processWalkIn('Cash')">Pay Cash</button>
        <button class="btn-gcash" onclick="openWalkInGcash()">Pay via GCash</button>
        <button type="button" onclick="closeModal('walkInModal')" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
    </div>
</div>

<div id="gcashModal" class="modal">
    <div class="modal-content">
        <h3>Scan GCash QR</h3>
        <p>Amount: <b style="color:#ef4444;">₱<span id="gcashAmount">0.00</span></b></p>
        <div style="background: white; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 15px;">
            <img src="../assets/gcash.jpg" style="width: 200px; height: 200px; object-fit: contain; display: block;">
        </div>
        <button class="btn-checkout" style="background:#007dfe;" id="gcashConfirmBtn" onclick="confirmGcashPayment()">Confirm Payment</button>
        <button type="button" onclick="closeActiveGcashModal()" style="width:100%; background:none; border:none; margin-top:10px; color:#737373; cursor:pointer;">Cancel</button>
    </div>
</div>

<script>
    let cart = [];
    let isWalkInGcash = false;

    function openModal(id) { document.getElementById(id).style.display = 'block'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    function openEditProductModal(id, name, price, stock) {
        document.getElementById('editProdId').value = id;
        document.getElementById('editProdName').value = name;
        document.getElementById('editProdPrice').value = price;
        document.getElementById('editProdStock').value = stock;
        openModal('editProductModal');
    }

    function clearCart() {
        cart = [];
        document.getElementById('customerName').value = '';
        renderCart();
    }

    function openWalkInGcash() {
        let price = parseFloat(document.getElementById('walkInPrice').value) || 0;
        if (price <= 0) return alert("Enter valid price.");
        isWalkInGcash = true;
        document.getElementById('gcashAmount').innerText = price.toFixed(2);
        closeModal('walkInModal');
        openModal('gcashModal');
    }

    function openGcashModal() {
        if(cart.length === 0) return alert("Cart is empty");
        isWalkInGcash = false;
        document.getElementById('gcashAmount').innerText = cart.reduce((s,i)=>s+(i.price*i.qty),0).toFixed(2);
        openModal('gcashModal');
    }

    function closeActiveGcashModal() {
        closeModal('gcashModal');
        if (isWalkInGcash) openModal('walkInModal');
    }

    function confirmGcashPayment() {
        if (isWalkInGcash) {
            closeModal('gcashModal');
            processWalkIn('GCash');
        } else {
            closeModal('gcashModal');
            processSale('GCash');
        }
    }

    function processWalkIn(paymentMethod) {
        let customerName = document.getElementById('walkInCustomer').value.trim() || 'Walk-in Guest';
        let price = parseFloat(document.getElementById('walkInPrice').value) || 0;
        if (price <= 0) return alert("Enter valid amount.");

        let calculatedQty = Math.floor(price / 100) || 1;
        let dayPassItem = [{ id: 0, name: 'Day Pass', qty: calculatedQty, price: price / calculatedQty }];

        let formData = new FormData();
        formData.append('ajax_sale', '1');
        formData.append('total_amount', price.toFixed(2));
        formData.append('payment_method', paymentMethod);
        formData.append('customer_name', customerName + ' (Day Pass)');
        formData.append('items_json', JSON.stringify(dayPassItem));

        fetch('sales.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if(data.trim() === "success") {
                alert("Day Pass (" + calculatedQty + "x) issued successfully via " + paymentMethod + "!");
                setTimeout(() => location.reload(), 500);
            } else {
                alert("Transaction failed.");
            }
        });
    }

    function addToCart(p) {
        if (p.stock <= 0) return alert("Out of stock");
        let item = cart.find(x => x.id === p.id);
        if (item) { if(item.qty < p.stock) item.qty++; } 
        else { cart.push({...p, qty: 1}); }
        renderCart();
    }

    function renderCart() {
        let container = document.getElementById('cartContainer'), total = 0;
        if(cart.length === 0) { container.innerHTML = '<div style="text-align:center; color:#525252; padding:30px;">Cart is empty</div>'; document.getElementById('totalDisplay').innerText = "0.00"; return; }
        container.innerHTML = cart.map((item, idx) => {
            let sub = item.price * item.qty; total += sub;
            return `<div class="cart-item-row">
                <div><b>${item.name}</b><br><small style="color:#737373;">₱${item.price}</small></div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="cart[${idx}].qty > 1 ? cart[${idx}].qty-- : cart.splice(${idx},1); renderCart();">-</button>
                    <span class="qty-display">${item.qty}</span>
                    <button class="qty-btn" onclick="cart[${idx}].qty++; renderCart();">+</button>
                </div>
                <div>₱${sub.toFixed(2)}</div>
            </div>`;
        }).join('');
        document.getElementById('totalDisplay').innerText = total.toFixed(2);
    }

    function processSale(method) {
        let name = document.getElementById('customerName').value.trim() || 'Walk-in Guest';
        let total = cart.reduce((s,i)=>s+(i.price*i.qty),0);
        let fd = new FormData();
        fd.append('ajax_sale', '1');
        fd.append('total_amount', total);
        fd.append('payment_method', method);
        fd.append('customer_name', name);
        fd.append('items_json', JSON.stringify(cart));
        fetch('sales.php', {method:'POST', body:fd}).then(res=>res.text()).then(res => {
            if(res.res.trim() === 'success') { 
                alert("Sale completed!"); 
                setTimeout(() => location.reload(), 500);
            }
            else alert("Error processing sale.");
        });
    }
    renderCart();
</script>
</body>
</html>