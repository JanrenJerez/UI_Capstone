<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Auto-create expenses table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        description TEXT,
        amount REAL NOT NULL,
        expense_date DATE DEFAULT (DATE('now'))
    )");
} catch (PDOException $e) {}

$message = "";
$error = "";

// Handle Form Submission for Expenses & Salaries
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_expense'])) {
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');

        if (!empty($category) && $amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO expenses (category, description, amount, expense_date) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$category, $description, $amount, $expense_date])) {
                $message = "Expense added successfully!";
            } else {
                $error = "Failed to add expense.";
            }
        } else {
            $error = "Please specify a category and a valid amount.";
        }
    } elseif (isset($_POST['add_salary'])) {
        $employee_name = trim($_POST['employee_name'] ?? '');
        $salary_amount = floatval($_POST['salary_amount'] ?? 0);
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');

        if (!empty($employee_name) && $salary_amount > 0) {
            $stmt = $pdo->prepare("INSERT INTO expenses (category, description, amount, expense_date) VALUES (?, ?, ?, ?)");
            if ($stmt->execute(['Salary', 'Salary payout for: ' . $employee_name, $salary_amount, $expense_date])) {
                $message = "Salary record for {$employee_name} added successfully!";
            } else {
                $error = "Failed to save salary entry.";
            }
        } else {
            $error = "Please select an employee and input a valid salary amount.";
        }
    } elseif (isset($_POST['delete_expense_id'])) {
        $delId = intval($_POST['delete_expense_id']);
        $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$delId]);
        $message = "Record deleted successfully.";
    }
}

// Fetch Staff List for the Dropdown
$staff_list = [];
try {
    $stmtStaff = $pdo->query("SELECT name, role FROM staff ORDER BY name ASC");
    if ($stmtStaff) $staff_list = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch Expenses Record List
$expenses = [];
try {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
    if ($stmt) $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not fetch expenses.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Expenses & Salaries | Project-E</title>
    <style>
        * { box-sizing: border-box; }
        body {
            background: #0a0a0a; color: #ffffff; font-family: 'Segoe UI', sans-serif; margin: 0;
            background-image: linear-gradient(rgba(10, 10, 10, 0.88), rgba(10, 10, 10, 0.1)), url('../assets/gym.jpg');
            background-attachment: fixed; background-size: cover; background-position: center;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 50px 20px 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .dashboard-title { color: #dc2626; margin: 0; font-size: 2rem; font-weight: 800; text-transform: uppercase; }
        .nav-links { display: flex; gap: 10px; }
        .back-btn { background: #262626; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 0.85rem; border: 1px solid #404040; }
        
        .alert-success { background: rgba(22, 163, 74, 0.2); border: 1px solid #16a34a; color: #4ade80; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626; color: #fca5a5; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }

        .forms-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 35px; }
        @media (max-width: 768px) { .forms-grid { grid-template-columns: 1fr; } }

        .glass-card { background: rgba(18, 18, 18, 0.95); padding: 25px; border-radius: 16px; border: 1px solid #262626; }
        .glass-card h3 { color: #dc2626; margin-top: 0; border-bottom: 1px solid #262626; padding-bottom: 10px; text-transform: uppercase; font-size: 1.1rem; }
        
        label { display: block; font-size: 0.8rem; color: #a3a3a3; margin-top: 12px; margin-bottom: 4px; text-transform: uppercase; font-weight: 700; }
        input, select, textarea { width: 100%; padding: 10px; background: #0a0a0a; border: 1px solid #262626; border-radius: 8px; color: white; font-family: inherit; font-size: 0.9rem; }
        
        .btn-submit { width: 100%; padding: 12px; background: #dc2626; border: none; border-radius: 8px; color: white; font-weight: 700; cursor: pointer; text-transform: uppercase; margin-top: 20px; transition: 0.2s; }
        .btn-submit:hover { background: #b91c1c; }
        .btn-submit-salary { background: #2563eb; }
        .btn-submit-salary:hover { background: #1d4ed8; }

        .table-container { background: rgba(18, 18, 18, 0.95); border: 1px solid #262626; border-radius: 16px; padding: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(220, 38, 38, 0.1); color: #ef4444; padding: 12px; text-align: left; font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
        td { padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); color: #d4d4d4; font-size: 0.9rem; }
        .badge-cat { background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3); padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-salary { background: rgba(37, 99, 235, 0.15); color: #60a5fa; border: 1px solid rgba(37, 99, 235, 0.3); }
        .btn-del { background: #7f1d1d; color: #fca5a5; border: 1px solid #991b1b; padding: 4px 10px; border-radius: 4px; font-weight: 700; font-size: 0.75rem; cursor: pointer; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>

<div class="container">
    <div class="page-header">
        <h1 class="dashboard-title">Expense & Salary Manager</h1>
        <div class="nav-links">
            <a href="/../admin/staff.php" class="back-btn" style="background:#2563eb; border-color:#3b82f6;">👥 Manage Staff</a>
            <a href="/../index.php" class="back-btn">← Dashboard</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="forms-grid">
        <!-- General Expense Form -->
        <div class="glass-card">
            <h3>Add Expense Item</h3>
            <form method="POST">
                <input type="hidden" name="add_expense" value="1">
                <label>Category</label>
                <select name="category" required>
                    <option value="Electricity">Electricity</option>
                    <option value="Water">Water</option>
                    <option value="Wifi">Wifi</option>
                    <option value="Rent">Rent</option>
                    <option value="Supplements">Supplements</option>
                    <option value="Tax">Tax</option>
                    <option value="Grocery">Grocery / Pantry</option>
                    <option value="Other">Other Expense</option>
                </select>

                <label>Description / Notes</label>
                <input type="text" name="description" placeholder="e.g., Monthly bill payment">

                <label>Amount (₱)</label>
                <input type="number" name="amount" step="100" min="0.00" required placeholder="0.00">

                <label>Date</label>
                <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>

                <button type="submit" class="btn-submit">+ Add Expense</button>
            </form>
        </div>

        <!-- Employee Salary Form (Dynamic Dropdown) -->
        <div class="glass-card">
            <h3>Add Employee Salary</h3>
            <form method="POST">
                <input type="hidden" name="add_salary" value="1">
                <label>Select Employee</label>
                <select name="employee_name" required>
                    <option value="">-- Choose Staff Member --</option>
                    <?php if (!empty($staff_list)): ?>
                        <?php foreach ($staff_list as $st): ?>
                            <option value="<?php echo htmlspecialchars($st['name'] . ' (' . $st['role'] . ')'); ?>">
                                <?php echo htmlspecialchars($st['name'] . ' - ' . $st['role']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No staff found. Add staff first!</option>
                    <?php endif; ?>
                </select>
                <?php if (empty($staff_list)): ?>
                    <p style="font-size:0.75rem; color:#f87171; margin-top:4px;">Please add staff members in the "Manage Staff" section first.</p>
                <?php endif; ?>

                <label>Salary Amount (₱)</label>
                <input type="number" name="salary_amount" step="100" min="0.00" required placeholder="0.00">

                <label>Payout Date</label>
                <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>

                <button type="submit" class="btn-submit btn-submit-salary">+ Add Salary Payout</button>
            </form>
        </div>
    </div>

    <!-- Expense Logs Table -->
    <div class="table-container">
        <h3 style="color: #dc2626; margin-top: 0; text-transform: uppercase; font-size: 1.1rem; border-bottom: 1px solid #262626; padding-bottom: 10px;">Expense History Log</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expenses)): ?>
                    <?php foreach ($expenses as $row): ?>
                        <?php $isSalary = ($row['category'] === 'Salary'); ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td>
                                <span class="badge-cat <?php echo $isSalary ? 'badge-salary' : ''; ?>">
                                    <?php echo htmlspecialchars($row['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td style="font-weight: 700; color: #ef4444;">₱<?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['expense_date'])); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this record?');" style="display:inline;">
                                    <input type="hidden" name="delete_expense_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn-del">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 30px; color: #737373;">No expenses recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>