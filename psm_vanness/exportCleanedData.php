<?php
require 'vendor/autoload.php'; // PhpSpreadsheet
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$mysqli = new mysqli("localhost", "root", "password", "dqms");
if ($mysqli->connect_error) {
    http_response_code(500);
    die("Database connection failed.");
}

$datasetID = $_GET['datasetID'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (empty($datasetID)) {
    http_response_code(400);
    die("Missing datasetID.");
}

// Get RecordIDs
$stmt = $mysqli->prepare("SELECT RecordID FROM RECORD WHERE DatasetID = ?");
$stmt->bind_param("s", $datasetID);
$stmt->execute();
$result = $stmt->get_result();

$recordIDs = [];
while ($row = $result->fetch_assoc()) {
    $recordIDs[] = $row['RecordID'];
}
$stmt->close();

if (empty($recordIDs)) {
    die("No records found.");
}

$placeholders = implode(',', array_fill(0, count($recordIDs), '?'));
$type = null;

// Determine if data is in PAYOUT or REFUND
$stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM PAYOUT WHERE RecordID IN ($placeholders)");
$stmt->bind_param(str_repeat('s', count($recordIDs)), ...$recordIDs);
$stmt->execute();
$payoutCount = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

if ($payoutCount > 0) {
    $type = 'payout';
} else {
    $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM REFUND WHERE RecordID IN ($placeholders)");
    $stmt->bind_param(str_repeat('s', count($recordIDs)), ...$recordIDs);
    $stmt->execute();
    $refundCount = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($refundCount > 0) {
        $type = 'refund';
    }
}

if (!$type) {
    die("No matching data found.");
}

$filename = ($type === 'refund') ? "Cleaned_Refund" : "Cleaned_Payout";

// Fetch data and headers
if ($type === 'payout') {
    $columns = ["OrderID", "TransactionDate", "GrossSalesAmount", "PlatformFees", "TransactionFees", "ShippingFees", "RefundsIssued", "NetPayoutAmount"];
    $headers = [
        "OrderID" => "Order ID",
        "TransactionDate" => "Transaction Date",
        "GrossSalesAmount" => "Gross Sales Amount",
        "PlatformFees" => "Platform Fees",
        "TransactionFees" => "Transaction Fees",
        "ShippingFees" => "Shipping Fees",
        "RefundsIssued" => "Refunds Issued",
        "NetPayoutAmount" => "Net Payout Amount"
    ];
    $query = "SELECT " . implode(",", $columns) . " FROM PAYOUT WHERE RecordID IN ($placeholders)";
} else {
    $columns = ["OrderID", "ProductName", "ReturnRequestDate", "RefundAmount", "ReasonForReturn", "ReturnStatus"];
    $headers = [
        "OrderID" => "Order ID",
        "ProductName" => "Product Name",
        "ReturnRequestDate" => "Return Request Date",
        "RefundAmount" => "Refund Amount",
        "ReasonForReturn" => "Reason For Return",
        "ReturnStatus" => "Return Status"
    ];
    $query = "SELECT " . implode(",", $columns) . " FROM REFUND WHERE RecordID IN ($placeholders)";
}


$stmt = $mysqli->prepare($query);
$stmt->bind_param(str_repeat('s', count($recordIDs)), ...$recordIDs);
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = array_values($row);
}
$stmt->close();

// Export
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename={$filename}.csv");
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    // date format to 2024-03-05
    $dateFields = ["TransactionDate", "ReturnRequestDate"];

    foreach ($data as $row) {
        foreach (array_keys($headers) as $i => $column) {
            if (in_array($column, $dateFields)) {
                $row[$i] = "\t" . $row[$i];  // Excel treats this as plain text
            }
        }
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
} elseif ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Write headers
    $sheet->fromArray(array_values($headers), NULL, 'A1');

    // Map columns to header keys
    $columnKeys = array_keys($headers);

    // Write data manually to preserve formatting
    $rowIndex = 2; // Start from second row (first row is header)
    foreach ($data as $row) {
        foreach ($row as $colIndex => $value) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $cell = $sheet->getCell("{$columnLetter}{$rowIndex}");

            // Force OrderID or large numbers as text
            if ($columnKeys[$colIndex] === 'OrderID') {
                $sheet->setCellValueExplicit("{$columnLetter}{$rowIndex}", $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue("{$columnLetter}{$rowIndex}", $value);
            }
        }
        $rowIndex++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename={$filename}.xlsx");
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} elseif ($format === 'pdf') {

    $dompdf = new Dompdf();

    // CSS styles mimicking your jsPDF autotable style
    $css = <<<CSS
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; }
            h2 { color: #F57C00; font-weight: bold; }
            table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 20px;
            }
            thead th {
                background-color: #FF9800;
                color: white;
                text-align: center;
                font-weight: bold;
                padding: 5px;
                border: 1px solid #ccc;
            }
            tbody td {
                background-color: #FFF3E0;
                padding: 5px;
                border: 1px solid #ccc;
                word-wrap: break-word;
                max-width: 150px;
            }
            tbody tr:nth-child(even) td {
                background-color: #FFECB3;
            }
            /* Header on every page */
            @page {
                margin: 40pt 30pt 40pt 30pt;
            }
            .header {
                position: fixed;
                top: -30pt;
                left: 0;
                right: 0;
                height: 30pt;
                text-align: left;
                font-size: 12pt;
                color: #282828;
                font-weight: bold;
            }
        </style>
    CSS;

    // Build HTML table with data
    $html = $css;
    $html .= "<div class='header'>Cleaned Data Export</div>";
    $html .= "<h2>{$filename}</h2><table><thead><tr>";

    foreach (array_values($headers) as $header) {
        $html .= "<th>" . htmlspecialchars($header) . "</th>";
    }
    $html .= "</tr></thead><tbody>";

    foreach ($data as $row) {
        $html .= "<tr>";
        foreach ($row as $cell) {
            $html .= "<td>" . htmlspecialchars($cell) . "</td>";
        }
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    // Stream as attachment for download
    $dompdf->stream("{$filename}.pdf", ["Attachment" => true]);
    exit;

} else {
    http_response_code(400);
    echo "Unsupported export format: $format";
}

$mysqli->close();
?>
