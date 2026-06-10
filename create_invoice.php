<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['xero_access_token'])) {
    header('Location: index.php');
    exit;
}

$success = null;
$error   = null;
$invoiceNumber = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceNo   = trim($_POST['invoice_number'] ?? '');
    $contactName = trim($_POST['contact_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity    = (float) ($_POST['quantity'] ?? 1);
    $unitAmount  = (float) ($_POST['unit_amount'] ?? 0);
    $date        = $_POST['date'] ?? date('Y-m-d');
    $dueDate     = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

    $invoicePayload = [
        'Type'          => 'ACCREC',
        'InvoiceNumber' => $invoiceNo,
        'Contact'       => ['Name' => $contactName],
        'Date'          => $date,
        'DueDate'       => $dueDate,
        'Status'        => 'DRAFT',
        'LineItems'     => [
            [
                'Description' => $description,
                'Quantity'    => $quantity,
                'UnitAmount'  => $unitAmount,
                'AccountCode' => '200',
            ],
        ],
    ];

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
        CURLOPT_POSTFIELDS => json_encode($invoicePayload),
    ]);

    $response = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        $error = 'Connection error: ' . $curlError;
    } elseif ($httpCode === 200 || $httpCode === 201) {
        $data = json_decode($response, true);
        $invoiceNumber = $data['Invoices'][0]['InvoiceNumber'] ?? 'N/A';
        $success = 'Invoice created successfully! Invoice #: ' . $invoiceNumber;
    } else {
        $data  = json_decode($response, true);
        $error = 'Failed to create invoice (HTTP ' . $httpCode . '): '
               . ($data['Detail'] ?? $data['Message'] ?? $response);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Invoice – Xero Invoice App</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; }
        h1   { text-align: center; }
        label { display: block; margin-top: 16px; font-weight: bold; }
        input { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px;
                border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .row  { display: flex; gap: 16px; }
        .row > div { flex: 1; }
        button { margin-top: 24px; width: 100%; padding: 12px;
                 background: #13B5EA; color: #fff; border: none;
                 border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #0e9bc7; }
        .success { background: #e6f9ee; border: 1px solid #4CAF50; color: #2e7d32;
                   padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .error   { background: #fdecea; border: 1px solid #f44336; color: #b71c1c;
                   padding: 12px; border-radius: 4px; margin-bottom: 16px; }
        .back    { display: block; text-align: center; margin-top: 20px; color: #13B5EA; }
    </style>
</head>
<body>
    <h1>Create Invoice</h1>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Invoice Number</label>
        <input type="text" name="invoice_number" required placeholder="e.g. MY-INV-001"
               value="<?= htmlspecialchars($_POST['invoice_number'] ?? '') ?>">

        <label>Contact / Customer Name</label>
        <input type="text" name="contact_name" required placeholder="e.g. ABC Ltd"
               value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">

        <label>Description</label>
        <input type="text" name="description" required placeholder="e.g. Consulting services"
               value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">

        <div class="row">
            <div>
                <label>Quantity</label>
                <input type="number" name="quantity" min="0.01" step="0.01" required
                       value="<?= htmlspecialchars($_POST['quantity'] ?? '1') ?>">
            </div>
            <div>
                <label>Unit Amount ($)</label>
                <input type="number" name="unit_amount" min="0.01" step="0.01" required
                       value="<?= htmlspecialchars($_POST['unit_amount'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label>Invoice Date</label>
                <input type="date" name="date" required
                       value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>">
            </div>
            <div>
                <label>Due Date</label>
                <input type="date" name="due_date" required
                       value="<?= htmlspecialchars($_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'))) ?>">
            </div>
        </div>

        <button type="submit">Post Invoice to Xero</button>
    </form>

    <a class="back" href="index.php">← Back to Home</a>
</body>
</html>
