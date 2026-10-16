<?php
// Sales report export (POS sales + membership payments), shared by the
// download button and the auto-saved master copy.

// Folder for auto-saved exports: AppData in the packaged app, exports/ in dev
function export_dir() {
    $base = getenv('USER_DATA_PATH');
    $dir = $base ? $base . DIRECTORY_SEPARATOR . 'exports' : __DIR__ . '/../exports';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    return $dir;
}

// Text typed by staff (e.g. a customer named "=SUM(...)") must not run as an Excel formula
function csv_safe($value) {
    $value = (string) $value;
    return ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) ? "'" . $value : $value;
}

// Write the full sales report as CSV (UTF-8 with BOM so Excel shows ₱ and names correctly)
function write_sales_report_csv($conn, $handle) {
    fputs($handle, "\xEF\xBB\xBF");
    fputcsv($handle, ['ID', 'Type', 'Customer Name', 'Date', 'Items Bought / Description', 'Amount', 'Payment', 'Status']);

    $res = $conn->query(sales_history_sql() . " ORDER BY date_recorded DESC");
    while ($r = $res->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($handle, [
            '#' . $r['transaction_id'],
            $r['source_type'] === 'MEMBERSHIP' ? 'Membership' : 'POS',
            csv_safe($r['customer_name']),
            $r['date_recorded'],
            csv_safe($r['items_list']),
            number_format((float) $r['amount'], 2, '.', ''),
            $r['payment_method'],
            $r['status'],
        ]);
    }
}

// Keep an always-current copy at exports/sales_report.csv
function updateMasterExcelFile($conn) {
    $file = @fopen(export_dir() . '/sales_report.csv', 'w');
    if (!$file) return; // e.g. the file is open in Excel; skip quietly
    write_sales_report_csv($conn, $file);
    fclose($file);
}
?>
