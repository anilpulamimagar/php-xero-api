<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['xero_access_token'])) {
    header('Location: index.php');
    exit;
}

$results  = [];
$imported = 0;
$failed   = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jsonFile = __DIR__ . '/test_invoices.json';
    $invoices = json_decode(file_get_contents($jsonFile), true);

    // Xero accepts up to 50 invoices per batch request
    $batches = array_chunk($invoices, 50);

    foreach ($batches as $batchIndex => $batch) {
        $payload = ['Invoices' => []];

        foreach ($batch as $inv) {
            $payload['Invoices'][] = [
                'Type'          => 'ACCREC',
                'InvoiceNumber' => $inv['invoice_number'],
                'Contact'       => ['Name' => $inv['contact_name']],
                'Date'          => $inv['date'],
                'DueDate'       => $inv['due_date'],
                'Status'        => 'DRAFT',
                'LineItems'     => [
                    [
                        'Description' => $inv['description'],
                        'Quantity'    => (float) $inv['quantity'],
                        'UnitAmount'  => (float) $inv['unit_amount'],
                        'AccountCode' => '200',
                    ],
                ],
            ];
        }

        $ch = curl_init('https://api.xero.com/api.xro/2.0/Invoices');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $_SESSION['xero_access_token'],
                'Xero-Tenant-Id: ' . $_SESSION['xero_tenant_id'],
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $results[] = ['batch' => $batchIndex + 1, 'status' => 'error', 'message' => 'cURL: ' . $curlError];
            $failed += count($batch);
            continue;
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 || $httpCode === 201) {
            foreach ($data['Invoices'] as $created) {
                $hasError = !empty($created['ValidationErrors']) || ($created['HasErrors'] ?? false);
                if ($hasError) {
                    $errMsg = implode(', ', array_column($created['ValidationErrors'] ?? [], 'Message'));
                    $results[] = [
                        'batch'   => $batchIndex + 1,
                        'status'  => 'error',
                        'invoice' => $created['InvoiceNumber'] ?? '?',
                        'message' => $errMsg,
                    ];
                    $failed++;
                } else {
                    $results[] = [
                        'batch'   => $batchIndex + 1,
                        'status'  => 'ok',
                        'invoice' => $created['InvoiceNumber'],
                        'contact' => $created['Contact']['Name'] ?? '',
                        'total'   => $created['Total'] ?? 0,
                    ];
                    $imported++;
                }
            }
        } else {
            $detail = $data['Detail'] ?? $data['Message'] ?? $response;
            $results[] = ['batch' => $batchIndex + 1, 'status' => 'error', 'message' => 'HTTP ' . $httpCode . ': ' . $detail];
            $failed += count($batch);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Import – Xero Invoice App</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 860px; margin: 60px auto; }
        h1    { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th    { background: #13B5EA; color: #fff; }
        tr.ok   { background: #f0fff4; }
        tr.error { background: #fff0f0; color: #b71c1c; }
        .summary { padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 15px; }
        .summary.ok    { background: #e6f9ee; border: 1px solid #4CAF50; color: #2e7d32; }
        .summary.mixed { background: #fff8e1; border: 1px solid #FFC107; color: #7b5e00; }
        button { padding: 12px 32px; background: #13B5EA; color: #fff; border: none;
                 border-radius: 4px; font-size: 16px; cursor: pointer; display: block; margin: 0 auto; }
        button:hover { background: #0e9bc7; }
        .back { display: block; text-align: center; margin-top: 20px; color: #13B5EA; }
        .info { background: #e3f2fd; border: 1px solid #90caf9; padding: 12px;
                border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Bulk Invoice Import</h1>

    <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <div class="info">
            This will send all <strong>50 invoices</strong> from <code>test_invoices.json</code>
            to your Xero organisation in one batch request.
            Xero's documented limit is <strong>50 invoices per batch</strong>.
        </div>
        <form method="POST">
            <button type="submit">Run Bulk Import (50 invoices)</button>
        </form>

    <?php else: ?>
        <div class="summary <?= $failed === 0 ? 'ok' : 'mixed' ?>">
            Imported: <strong><?= $imported ?></strong> &nbsp;|&nbsp;
            Failed: <strong><?= $failed ?></strong> &nbsp;|&nbsp;
            Total: <strong><?= $imported + $failed ?></strong>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Invoice #</th>
                    <th>Contact</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr class="<?= $r['status'] ?>">
                    <td><?= $r['batch'] ?></td>
                    <td><?= htmlspecialchars($r['invoice'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($r['contact'] ?? '-') ?></td>
                    <td><?= isset($r['total']) ? '$' . number_format($r['total'], 2) : '-' ?></td>
                    <td><?= $r['status'] === 'ok' ? 'Created' : htmlspecialchars($r['message'] ?? 'Error') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a class="back" href="index.php">← Back to Home</a>
</body>
</html>
