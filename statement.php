<?php
require_once 'db.php';

if (!isset($_SESSION['user_name'])) {
    header('Location: login.php');
    exit();
}

$today = new DateTimeImmutable('today');
$currentPeriod = (int) $today->format('d') >= 8
    ? $today->format('Y-m')
    : $today->modify('-1 month')->format('Y-m');

$requestedPeriod = $_GET['month'] ?? '';
$periodKey = is_string($requestedPeriod) && preg_match('/^\d{4}-\d{2}$/', $requestedPeriod)
    ? $requestedPeriod
    : $currentPeriod;

$periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $periodKey . '-08');
if (!$periodStart) {
    $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $currentPeriod . '-08');
    $periodKey = $periodStart->format('Y-m');
}
$periodEnd = $periodStart->modify('+1 month');
$statementEnd = $periodEnd->modify('-1 day');
$periodStartValue = $periodStart->format('Y-m-d H:i:s');
$periodEndValue = $periodEnd->format('Y-m-d H:i:s');

$stmt = $conn->prepare('SELECT * FROM compras WHERE `datetime` >= ? AND `datetime` < ? ORDER BY `datetime` ASC, id ASC');
$stmt->bind_param('ss', $periodStartValue, $periodEndValue);
$stmt->execute();
$rows = $stmt->get_result();

require_once 'fpdf/fpdf.php';

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetTitle('Purchase Statement');
$pdf->SetAuthor($_SESSION['user_name']);

$pdfText = static function (string $value): string {
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted !== false) {
            return $converted;
        }
    }
    return $value;
};

$pdf->SetFillColor(0, 53, 99);
$pdf->Rect(0, 0, 297, 31, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 23);
$pdf->Cell(0, 13, 'RBC', 0, 1, 'L');
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->Cell(0, 8, 'ROYAL PURCHASE STATEMENT', 0, 1, 'L');
$pdf->SetFillColor(255, 204, 0);
$pdf->Rect(250, 0, 47, 31, 'F');
$pdf->SetTextColor(0, 53, 99);
$pdf->SetFont('Helvetica', 'B', 15);
$pdf->SetXY(250, 12);
$pdf->Cell(47, 8, 'STATEMENT', 0, 0, 'C');

$pdf->Ln(10);
$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(145, 6, $pdfText((string) $_SESSION['user_name']), 0, 0, 'L');
$pdf->Cell(132, 6, 'Statement period', 0, 1, 'R');
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(145, 6, 'Personal purchasing account', 0, 0, 'L');
$pdf->Cell(132, 6, $periodStart->format('F j, Y') . ' - ' . $statementEnd->format('F j, Y'), 0, 1, 'R');
$pdf->Ln(4);

$pdf->SetFillColor(225, 237, 246);
$pdf->SetDrawColor(185, 203, 218);
$pdf->SetTextColor(0, 53, 99);
$pdf->SetFont('Helvetica', 'B', 8);
$headers = ['DATE', 'MERCHANT', 'PRODUCT / CATEGORY', 'RESPONSIBLE', 'PAYMENT', 'AMOUNT (CAD)'];
$widths = [24, 48, 83, 32, 38, 52];
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, $i === 5 ? 'R' : 'L', true);
}
$pdf->Ln();

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(45, 45, 45);
$total = 0.0;
$count = 0;
while ($row = $rows->fetch_assoc()) {
    $count++;
    $amount = (float) $row['precio'] * (float) $row['exchangerate'];
    $total += $amount;
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row['datetime']);
    $dateText = $date ? $date->format('M j, Y') : substr((string) $row['datetime'], 0, 10);
    $category = (string) $row['category'];
    $product = (string) $row['producto'];
    $productCategory = trim($product . ($category !== '' ? ' / ' . $category : ''));
    $values = [
        $dateText,
        (string) $row['tienda'],
        $productCategory,
        (string) $row['responsable'],
        (string) $row['method'],
        '$' . number_format($amount, 2, '.', ','),
    ];
    $fill = $count % 2 === 0;
    if ($fill) {
        $pdf->SetFillColor(248, 250, 252);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    for ($i = 0; $i < count($values); $i++) {
        $pdf->Cell($widths[$i], 7, $pdfText($values[$i]), 1, 0, $i === 5 ? 'R' : 'L', $fill);
    }
    $pdf->Ln();
}
if ($count === 0) {
    $pdf->SetFillColor(248, 250, 252);
    $pdf->Cell(array_sum($widths), 10, 'No purchases were recorded for this statement period.', 1, 1, 'C', true);
}

$pdf->Ln(4);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetFillColor(255, 204, 0);
$pdf->Cell(225, 9, 'TOTAL PURCHASES', 1, 0, 'R', true);
$pdf->SetFillColor(225, 237, 246);
$pdf->Cell(52, 9, '$' . number_format($total, 2, '.', ','), 1, 1, 'R', true);
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Ln(3);
$pdf->Cell(277, 5, 'Generated ' . $today->format('F j, Y') . '  |  This statement contains ' . $count . ' purchase(s).', 0, 0, 'R');

$filename = 'purchase-statement-' . $periodKey . '.pdf';
$pdf->Output('D', $filename);
