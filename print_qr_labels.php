<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireAdmin();

$pageTitle = 'Print QR Labels';
$database = new Database();
$pdo = $database->getConnection();

$stmt = $pdo->query("SELECT * FROM items ORDER BY name ASC");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="fas fa-qrcode me-2"></i>Print QR Labels</h3>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
    </div>
    <div class="row" id="qr-labels">
        <?php foreach ($items as $item): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
            <div class="border rounded p-3 text-center qr-label" style="background: #fff;">
                <?php $qrUrl = generateQRCode($item['id'], $item['name']); ?>
                <img src="<?php echo $qrUrl; ?>" alt="QR Code" style="width:100px; height:100px; margin-bottom:10px;" />
                <div class="fw-bold mt-2"><?php echo htmlspecialchars($item['name']); ?></div>
                <div class="small text-muted">ID: <?php echo $item['id']; ?></div>
                <div class="small">Category: <?php echo htmlspecialchars($item['category']); ?></div>
                <div class="small">Location: <?php echo htmlspecialchars($item['location']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<style>
@media print {
    body * { visibility: hidden; }
    #qr-labels, #qr-labels * { visibility: visible; }
    #qr-labels { position: absolute; left: 0; top: 0; width: 100vw; }
    .qr-label { page-break-inside: avoid; }
    .btn, .navbar, .sidebar, .card-header, .alert { display: none !important; }
}
</style>
<?php include 'includes/footer.php'; ?> 