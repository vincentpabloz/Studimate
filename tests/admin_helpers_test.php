<?php
require __DIR__ . '/../includes/admin_helpers.php';

$expected = [[5.00, 'Excellent'], [4.51, 'Excellent'], [4.50, 'Very Good'], [3.51, 'Very Good'], [3.50, 'Good'],
             [2.51, 'Good'], [2.50, 'Fair'], [1.51, 'Fair'], [1.50, 'Poor'], [1.00, 'Poor']];
foreach ($expected as [$mean, $label]) {
    if (adminEvalInterpretation($mean) !== $label) {
        throw new RuntimeException("Mean $mean should be $label (Table 4).");
    }
}
if (adminEvalInterpretation(4.505) !== 'Excellent') {
    throw new RuntimeException('A mean is rounded to 2 decimals before the Table 4 range is applied.');
}
if (adminMean(0, 0) !== null || abs(adminMean(9, 2) - 4.5) > 0.0001) {
    throw new RuntimeException('adminMean should return null for no ratings and sum/count otherwise.');
}
$catalog = adminEvalCatalog();
if (count($catalog) !== 8) {
    throw new RuntimeException('The catalog should list the 8 ISO/IEC 25010 characteristics.');
}
$keys = [];
foreach ($catalog as $c) {
    foreach ($c['items'] as $key => $statement) {
        if (isset($keys[$key])) { throw new RuntimeException("Duplicate item key $key."); }
        $keys[$key] = true;
    }
}
echo "Admin helper tests passed.\n";
