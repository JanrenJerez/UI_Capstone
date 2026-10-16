<?php
// Shared helpers. Loaded by includes/db.php.

// Unique QR id for a new member, e.g. PE-3FA91C07B2
function generate_qr_id(PDO $pdo) {
    return migrate_new_qr_id($pdo);
}

// Escape for HTML output
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Read a value from the settings table
function get_setting(PDO $pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function set_setting(PDO $pdo, $key, $value) {
    $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
        ->execute([$key, $value]);
}

// Current page number from ?page=, at least 1
function current_page() {
    return max(1, intval($_GET['page'] ?? 1));
}

// Prev / page numbers / next links that keep the other query parameters
function render_pagination($totalRows, $perPage, $page) {
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($totalPages <= 1) return '';

    $link = function ($p) {
        $params = $_GET;
        $params['page'] = $p;
        return '?' . http_build_query($params);
    };

    $html = '<div class="pagination">';
    if ($page > 1) $html .= '<a href="' . e($link($page - 1)) . '">&laquo; Prev</a>';
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($p = $start; $p <= $end; $p++) {
        $html .= $p === $page
            ? '<span class="current">' . $p . '</span>'
            : '<a href="' . e($link($p)) . '">' . $p . '</a>';
    }
    if ($page < $totalPages) $html .= '<a href="' . e($link($page + 1)) . '">Next &raquo;</a>';
    $html .= '<span class="page-info">Page ' . $page . ' of ' . $totalPages . ' (' . $totalRows . ' records)</span>';
    $html .= '</div>';
    return $html;
}

// SQL for the combined POS + membership payment history (used by dashboard, POS page and exports)
function sales_history_sql($salesWhere = '', $paymentsWhere = '') {
    return "
        SELECT
            s.id AS transaction_id,
            'POS' AS source_type,
            s.customer_name,
            s.sale_date AS date_recorded,
            GROUP_CONCAT(COALESCE(si.item_name, p.name, 'Item') || ' (' || si.qty || ')', ', ') AS items_list,
            s.amount,
            s.payment_method,
            s.status
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        WHERE 1=1 $salesWhere
        GROUP BY s.id
        UNION ALL
        SELECT
            mp.id AS transaction_id,
            'MEMBERSHIP' AS source_type,
            COALESCE(mp.member_name, 'Gym Member') AS customer_name,
            mp.paid_at AS date_recorded,
            'Gym Membership (' || COALESCE(NULLIF(mp.description, ''), 'Standard') || ')' AS items_list,
            mp.amount,
            'Cash' AS payment_method,
            'Paid' AS status
        FROM membership_payments mp
        WHERE 1=1 $paymentsWhere";
}

// Record money received for a membership (new registration or renewal)
function record_membership_payment(PDO $pdo, $memberId, $memberName, $amount, $description) {
    $pdo->prepare("INSERT INTO membership_payments (member_id, member_name, amount, description, paid_at)
                   VALUES (?, ?, ?, ?, datetime('now', 'localtime'))")
        ->execute([$memberId, $memberName, $amount, $description]);
}

// Monthly rates for each tier (keys match members.tier, which is stored uppercase)
const MEMBERSHIP_TIERS = [
    'GYM USE - REGULAR' => 900,
    'GYM USE - STUDENT' => 700,
    'SUPERVISION - REGULAR' => 1500,
    'SUPERVISION - STUDENT' => 1200,
    '1-ON-1 COACHING - REGULAR' => 3000,
    '1-ON-1 COACHING - STUDENT' => 2500,
];

// Delete a member together with their attendance rows (the FK has no ON DELETE CASCADE)
function delete_member(PDO $pdo, $memberId) {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM attendance WHERE member_id = ?")->execute([$memberId]);
    $pdo->prepare("DELETE FROM members WHERE id = ?")->execute([$memberId]);
    $pdo->commit();
}
?>
