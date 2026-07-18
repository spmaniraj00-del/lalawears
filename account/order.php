<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$productId = (int) ($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
redirect('account/checkout.php' . ($productId ? ('?product_id=' . $productId) : ''));
