<?php
function updateMasterExcelFile($conn) {
    $dir = __DIR__ . '/../exports';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $filePath = $dir . '/sales_report.csv';
    $file = fopen($filePath, 'w');
    
    fputs($file, "\xEF\xBB\xBF");

    fputcsv($file, ['ID', 'Customer Name', 'Date', 'Items Bought', 'Amount', 'Payment', 'Status']);
    
    // Updated query for SQLite syntax (using datetime() and group_concat without SEPARATOR)
    $query = "SELECT s.id, s.customer_name, 
                     datetime(s.sale_date, 'localtime') as formatted_date, 
                     group_concat(COALESCE(p.name, 'Day Pass') || ' (' || si.qty || ')', ', ') as items_list,
                     s.amount, s.payment_method, s.status
              FROM sales s 
              LEFT JOIN sale_items si ON s.id = si.sale_id 
              LEFT JOIN products p ON si.product_id = p.id
              GROUP BY s.id 
              ORDER BY s.id DESC";
             
    $res = $conn->query($query);
    
    // Fixed fetch method for PDO
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($file, [
            '#' . $r['id'],
            $r['customer_name'],
            $r['formatted_date'],
            $r['items_list'],
            $r['amount'],
            $r['payment_method'],
            $r['status']
        ]);
    }
    fclose($file);
}
?>