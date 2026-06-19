<?php
// Create a 500x300 blank image
$im = imagecreate(500, 300);

// Colors
$bg = imagecolorallocate($im, 255, 255, 255); // White background
$black = imagecolorallocate($im, 0, 0, 0);
$blue = imagecolorallocate($im, 46, 44, 146);
$gray = imagecolorallocate($im, 128, 128, 128);

// Draw borders
imagerectangle($im, 0, 0, 499, 299, $black);

// Add text
imagestring($im, 5, 20, 20, "OKAY ERP - DEMO INVOICE", $blue);
imagestring($im, 4, 20, 50, "-----------------------------------", $gray);

// These are the exact patterns our OCR is looking for!
imagestring($im, 5, 20, 80, "Invoice No: DEMO-8849", $black);
imagestring($im, 5, 20, 110, "Date: 18/06/2026", $black);
imagestring($im, 5, 20, 140, "Supplier: Test Supplier Inc.", $black);

imagestring($im, 4, 20, 180, "Item 1         Qty: 2      Price: 250.00", $gray);
imagestring($im, 4, 20, 200, "Item 2         Qty: 1      Price: 500.00", $gray);
imagestring($im, 4, 20, 220, "-----------------------------------", $gray);

imagestring($im, 5, 20, 250, "Grand Total: 1000.00", $blue);

// Output to public folder
imagejpeg($im, 'public/demo_invoice.jpg', 100);
imagedestroy($im);

echo "Invoice generated at public/demo_invoice.jpg\n";
