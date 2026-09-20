<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Access Denied | <?= e(APP_SHORT_NAME) ?></title>
<link rel="stylesheet" href="<?= e(vendorAsset('bootstrap/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>">
</head>
<body class="bg-light">
<div class="container py-5">
<div class="card shadow-sm border-0 mx-auto" style="max-width:680px">
<div class="card-body p-5">
<div class="text-danger fw-bold mb-2">403 · Access Denied</div>
<h1 class="h3">You do not have the required CEPFMS permission.</h1>
<p class="text-muted">Required permission: <code><?= e($requiredPermission??'restricted CEPFMS permission') ?></code></p>
<a class="btn btn-primary" href="<?= e(appUrl('dashboard.php')) ?>">Return to Dashboard</a>
</div>
</div>
</div>
</body>
</html>
