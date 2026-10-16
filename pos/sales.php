<?php
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_unlocked();

if (!isset($conn) && isset($pdo)) {
    $conn = $pdo;
}

include __DIR__ . '/../includes/exporthelper.php';
include __DIR__ . '/../includes/upload_paths.php';

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

if (isset($_GET['export'])) {
    updateMasterExcelFile($conn);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    write_sales_report_csv($conn, $out);
    fclose($out);
    exit();
}

// Save an uploaded product image. Returns [image_url or null, error message or ''].
// Only real images are accepted (an uploaded .php file must never land in a served folder).
function save_product_image($field) {
    $file = $_FILES[$field] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return [null, ''];
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) return [null, 'Image is too large (max 8 MB).'];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'Image upload failed. Please try again.'];

    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset($allowed[$info[2]])) return [null, 'Please choose a JPG, PNG, GIF or WEBP image.'];

    $target_dir = upload_dir();
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $new_filename = time() . '_' . uniqid() . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $target_dir . $new_filename)) {
        return [null, 'Could not save the image. Please try again.'];
    }
    return [upload_url($new_filename), ''];
}

// Validate product fields. Returns [name, price, stock, error].
function read_product_form($nameField, $priceField, $stockField) {
    $name = trim($_POST[$nameField] ?? '');
    $price = $_POST[$priceField] ?? '';
    $stock = $_POST[$stockField] ?? '';
    if ($name === '') return [null, null, null, 'Product name is required.'];
    if (!is_numeric($price) || $price < 0) return [null, null, null, 'Price must be 0 or more.'];
    if (filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) return [null, null, null, 'Stock must be a whole number, 0 or more.'];
    return [$name, round((float) $price, 2), (int) $stock, ''];
}

if (isset($_POST['add_product'])) {
    [$name, $price, $stock, $formError] = read_product_form('pname', 'pprice', 'pstock');
    if ($formError) {
        header("Location: sales.php?error=" . urlencode($formError));
        exit();
    }

    [$image_path, $imgError] = save_product_image('pimg');
    if ($imgError) {
        header("Location: sales.php?error=" . urlencode($imgError));
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, stock, image_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $price, $stock, $image_path ?? '']);
    header("Location: sales.php?success=1");
    exit();
}

if (isset($_POST['update_product'])) {
    $prod_id = intval($_POST['prod_id']);
    [$name, $price, $stock, $formError] = read_product_form('edit_name', 'edit_price', 'edit_stock');
    if ($formError) {
        header("Location: sales.php?error=" . urlencode($formError));
        exit();
    }

    [$new_image, $imgError] = save_product_image('edit_img');
    if ($imgError) {
        header("Location: sales.php?error=" . urlencode($imgError));
        exit();
    }

    if ($new_image) {
        $stmt_img = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt_img->execute([$prod_id]);
        $old_img = $stmt_img->fetch(PDO::FETCH_ASSOC);
        $old_path = $old_img ? upload_disk_path($old_img['image_url']) : null;
        if ($old_path && file_exists($old_path)) {
            @unlink($old_path);
        }
        $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, stock = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$name, $price, $stock, $new_image, $prod_id]);
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
    
    $img_path = $img_row ? upload_disk_path($img_row['image_url']) : null;
    if ($img_path && file_exists($img_path)) {
        @unlink($img_path);
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
    $conn->prepare("DELETE FROM walkin_visits WHERE sale_id = ?")->execute([$sale_id]);
    
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
    $new_status = in_array($_POST['status'] ?? '', ['Paid', 'Unpaid', 'Void'], true) ? $_POST['status'] : 'Paid';

    $cur = $conn->prepare("SELECT status FROM sales WHERE id = ?");
    $cur->execute([$sale_id]);
    $old_status = $cur->fetchColumn();

    try {
        $conn->beginTransaction();
        $items = $conn->prepare("SELECT si.product_id, si.qty, COALESCE(si.item_name, p.name) AS name, p.stock
                                 FROM sale_items si LEFT JOIN products p ON p.id = si.product_id
                                 WHERE si.sale_id = ? AND si.product_id > 0");
        $items->execute([$sale_id]);
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);

        if ($old_status !== 'Void' && $new_status === 'Void') {
            // Voided sale: put the items back on the shelf
            $back = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            foreach ($rows as $r) $back->execute([$r['qty'], $r['product_id']]);
        } elseif ($old_status === 'Void' && $new_status !== 'Void') {
            // Un-voiding: take the items out of stock again, if there is enough
            $take = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            foreach ($rows as $r) {
                $take->execute([$r['qty'], $r['product_id'], $r['qty']]);
                if ($take->rowCount() === 0 && $r['stock'] !== null) {
                    throw new RuntimeException("Not enough stock of {$r['name']} to restore this sale.");
                }
            }
        }

        $conn->prepare("UPDATE sales SET status = ? WHERE id = ?")->execute([$new_status, $sale_id]);
        $conn->commit();
    } catch (RuntimeException $ex) {
        $conn->rollBack();
        header("Location: sales.php?error=" . urlencode($ex->getMessage()));
        exit();
    }

    updateMasterExcelFile($conn);
    header("Location: sales.php?updated=1");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_sale'])) {
    $payment_method = ($_POST['payment_method'] ?? '') === 'GCash' ? 'GCash' : 'Cash';
    $customer_name = trim($_POST['customer_name'] ?? '') !== '' ? trim($_POST['customer_name']) : 'Walk-in Guest';
    $is_day_pass = !empty($_POST['day_pass']);
    $items = json_decode($_POST['items_json'] ?? '[]', true);

    if (!is_array($items) || count($items) === 0) {
        echo "Cart is empty.";
        exit;
    }

    try {
        $conn->beginTransaction();
        $lines = [];
        $total = 0;
        $getProduct = $conn->prepare("SELECT name, price, stock FROM products WHERE id = ?");
        $takeStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

        foreach ($items as $item) {
            $id = intval($item['id'] ?? 0);
            $qty = intval($item['qty'] ?? 0);
            if ($qty <= 0) continue;

            if ($id > 0) {
                // Products: use the database price and make sure there is enough stock
                $getProduct->execute([$id]);
                $product = $getProduct->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new RuntimeException("A product in the cart no longer exists. Please refresh the page.");
                }
                $takeStock->execute([$qty, $id, $qty]);
                if ($takeStock->rowCount() === 0) {
                    throw new RuntimeException("Not enough stock for {$product['name']} (only {$product['stock']} left).");
                }
                $price = (float) $product['price'];
                $itemName = $product['name'];
            } else {
                // Day pass: the price is entered at the counter
                $price = max(0, floatval($item['price'] ?? 0));
                $itemName = 'Day Pass';
            }

            $lines[] = [$id, $qty, $price, $itemName];
            $total += $price * $qty;
        }

        if (!$lines) {
            throw new RuntimeException("Cart is empty.");
        }

        $stmt = $conn->prepare("INSERT INTO sales (customer_name, amount, status, payment_method, sale_date) VALUES (?, ?, 'Paid', ?, datetime('now', 'localtime'))");
        $stmt->execute([$customer_name, round($total, 2), $payment_method]);
        $sale_id = $conn->lastInsertId();

        $ins = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, item_name) VALUES (?, ?, ?, ?, ?)");
        foreach ($lines as [$id, $qty, $price, $itemName]) {
            $ins->execute([$sale_id, $id, $qty, $price, $itemName]);
        }

        // Walk-in day passes count as gym visits
        if ($is_day_pass) {
            $conn->prepare("INSERT INTO walkin_visits (sale_id, customer_name, visit_time) VALUES (?, ?, datetime('now', 'localtime'))")
                ->execute([$sale_id, $customer_name]);
        }

        $conn->commit();
    } catch (RuntimeException $ex) {
        $conn->rollBack();
        echo $ex->getMessage();
        exit;
    } catch (PDOException $ex) {
        $conn->rollBack();
        error_log("Sale failed: " . $ex->getMessage());
        echo "Could not save the sale. Please try again.";
        exit;
    }

    updateMasterExcelFile($conn);
    echo "success";
    exit;
}

$products_res = $conn->query("SELECT * FROM products ORDER BY id DESC");

$perPage = 25;
$page = current_page();
$historySql = sales_history_sql();
$historyTotal = (int) $conn->query("SELECT COUNT(*) FROM ($historySql)")->fetchColumn();
$offset = ($page - 1) * $perPage;
$history_rows = $conn->query("$historySql ORDER BY date_recorded DESC LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS & Sales History | Project E</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        * { box-sizing: border-box; }
        body { 
            background: #0d0d0d; color: #ffffff; font-family: 'Inter', 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.3), rgba(15, 15, 15, 0.1)), url('/assets/gym.jpg');
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
        .history-wrap { overflow-x: auto; }
        .flash { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 0.9rem; }
        .flash-success { background: rgba(34, 197, 94, 0.12); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.35); }
        .flash-error { background: rgba(220, 38, 38, 0.12); color: #f87171; border: 1px solid rgba(220, 38, 38, 0.35); }
        @media (max-width: 1000px) {
            .sales-grid { grid-template-columns: 1fr; }
            .page-header { flex-wrap: wrap; gap: 12px; }
        }
        @media (max-width: 600px) {
            .container-wide { padding: 24px 12px; }
            .history-table th, .history-table td { padding: 10px 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="container-wide">
    <?php
    $flash = [
        'success' => 'Product added.',
        'updated_product' => 'Product updated.',
        'deleted' => 'Product deleted.',
        'sale_deleted' => 'Sale deleted.',
        'all_deleted' => 'All POS history deleted.',
        'updated' => 'Sale status updated.',
    ];
    foreach ($flash as $key => $text):
        if (isset($_GET[$key])): ?>
            <div class="flash flash-success"><?php echo e($text); ?></div>
        <?php endif;
    endforeach;
    if (!empty($_GET['error'])): ?>
        <div class="flash flash-error"><?php echo e($_GET['error']); ?></div>
    <?php endif; ?>
    <div class="page-header">
        <h2 style="border-left: 4px solid #dc2626; padding-left: 12px; margin:0;">Point of Sale</h2>
        <div style="display: flex; gap: 10px;">
            <a href="/pos/sales.php?export=csv" style="padding: 10px 18px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 0.85rem;">📊 Export Financial Report</a>
            <button onclick="openModal('productModal')" class="btn-add">+ New Product</button>
        </div>
    </div>

    <div class="sales-grid">
        <div class="glass-card">
            <h3>Inventory</h3>
            <div class="product-grid">
                <?php while($p = $products_res->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="product-card" onclick="addToCart(<?php echo e(json_encode(['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (float) $p['price'], 'stock' => (int) $p['stock']])); ?>)">
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" name="delete_product" class="delete-btn" onclick="event.stopPropagation();">&times;</button>
                        </form>
                        <img src="<?php echo e(upload_src($p['image_url'] ?? '')); ?>" alt="product" onerror="this.onerror=null; this.src='/assets/logo.jpg';">
                        <div style="padding: 10px;">
                            <h4 style="margin: 0; font-size: 0.85rem; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo e($p['name']); ?></h4>
                            <p style="color: #ef4444; font-weight: 700; margin: 4px 0 2px 0;">₱<?php echo number_format($p['price'], 2); ?></p>
                            <small style="color: #737373;">Stock: <?php echo $p['stock']; ?></small>
                            <div>
                                <button type="button" class="btn-edit-prod" onclick="event.stopPropagation(); openEditProductModal(<?php echo e(json_encode(['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (float) $p['price'], 'stock' => (int) $p['stock']])); ?>)">Edit Details</button>
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

    <div class="glass-card history-wrap" style="padding: 0;">
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
                <?php if (count($history_rows) > 0): ?>
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
        <?php echo render_pagination($historyTotal, $perPage, $page); ?>
    </div>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <h3 style="color:#ef4444; margin-top:0;">Add New Product</h3>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Name</label>
            <input type="text" name="pname" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Price</label>
            <input type="number" name="pprice" step="0.01" min="0" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Stock</label>
            <input type="number" name="pstock" step="1" min="0" required>
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Image</label>
            <input type="file" name="pimg" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;">
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
            <input type="number" name="edit_price" id="editProdPrice" step="0.01" min="0" required>
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Stock Quantity</label>
            <input type="number" name="edit_stock" id="editProdStock" step="1" required min="0">
            
            <label style="text-align:left; display:block; color:#a3a3a3; font-size:0.8rem;">Replace Image (Optional)</label>
            <input type="file" name="edit_img" accept="image/jpeg,image/png,image/gif,image/webp" style="border:none;">
            
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
            <img src="/assets/gcash.jpg" style="width: 200px; height: 200px; object-fit: contain; display: block;">
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
    
    function openEditProductModal(p) {
        document.getElementById('editProdId').value = p.id;
        document.getElementById('editProdName').value = p.name;
        document.getElementById('editProdPrice').value = p.price;
        document.getElementById('editProdStock').value = p.stock;
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
        formData.append('day_pass', '1');
        fetch('sales.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if(data.trim() === "success") {
                alert("Day Pass (" + calculatedQty + "x) issued successfully via " + paymentMethod + "!");
                setTimeout(() => location.reload(), 500);
            } else {
                alert(data.trim() || "Transaction failed.");
            }
        })
        .catch(() => alert("Transaction failed. Please try again."));
    }

    function addToCart(p) {
        if (p.stock <= 0) return alert("Out of stock");
        let item = cart.find(x => x.id === p.id);
 if (item) {
            if (item.qty < item.stock) item.qty++;
            else alert("Only " + item.stock + " " + item.name + " in stock.");
        }
        else { cart.push({...p, qty: 1}); }
        renderCart();
    }

    function renderCart() {
        let container = document.getElementById('cartContainer'), total = 0;
        if(cart.length === 0) { container.innerHTML = '<div style="text-align:center; color:#525252; padding:30px;">Cart is empty</div>'; document.getElementById('totalDisplay').innerText = "0.00"; return; }
        container.innerHTML = cart.map((item, idx) => {
            let sub = item.price * item.qty; total += sub;
            return `<div class="cart-item-row">
                <div><b>${escapeHtml(item.name)}</b><br><small style="color:#737373;">₱${item.price.toFixed(2)}</small></div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="cart[${idx}].qty > 1 ? cart[${idx}].qty-- : cart.splice(${idx},1); renderCart();">-</button>
                    <span class="qty-display">${item.qty}</span>
                    <button class="qty-btn" onclick="increaseQty(${idx})">+</button>
                </div>
                <div>₱${sub.toFixed(2)}</div>
            </div>`;
        }).join('');
        document.getElementById('totalDisplay').innerText = total.toFixed(2);
    }

    function increaseQty(idx) {
        let item = cart[idx];
        if (item.qty < item.stock) item.qty++;
        else alert("Only " + item.stock + " " + item.name + " in stock.");
        renderCart();
    }
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    function processSale(method) {
        if (cart.length === 0) return alert("Cart is empty");
        let name = document.getElementById('customerName').value.trim() || 'Walk-in Guest';
        let total = cart.reduce((s,i)=>s+(i.price*i.qty),0);
        let fd = new FormData();
        fd.append('ajax_sale', '1');
        fd.append('total_amount', total);
        fd.append('payment_method', method);
        fd.append('customer_name', name);
        fd.append('items_json', JSON.stringify(cart));
        fetch('sales.php', {method:'POST', body:fd}).then(res=>res.text()).then(res => {
            if (res.trim() === 'success') {
                alert("Sale completed!");
                setTimeout(() => location.reload(), 500);
            }
            else alert(res.trim() || "Error processing sale.");
        })
        .catch(() => alert("Error processing sale. Please try again."));
    }
    renderCart();
</script>
</body>
</html>