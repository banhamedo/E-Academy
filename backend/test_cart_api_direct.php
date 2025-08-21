<?php
require 'config.php';

// Simulate a GET request to cart_api.php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['user_id'] = '4';

// Include the cart API
ob_start();
include 'cart_api.php';
$output = ob_get_clean();

echo "API Output:\n";
echo $output;

// Check if it's valid JSON
$decoded = json_decode($output, true);
if ($decoded === null) {
    echo "\nJSON Error: " . json_last_error_msg() . "\n";
} else {
    echo "\nJSON decoded successfully\n";
    echo "Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    echo "Cart items count: " . count($decoded['cart_items']) . "\n";
}
?>
