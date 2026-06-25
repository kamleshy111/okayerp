<?php

$units = [
    'Weight' => [
        'KG' => 'Kilogram (KG)',
        'GM' => 'Gram (GM)',
        'MG' => 'Milligram (MG)',
        'QTL' => 'Quintal (QTL)',
        'TON' => 'Ton (TON)',
    ],
    'Length' => [
        'M' => 'Meter (M)',
        'CM' => 'Centimeter (CM)',
        'MM' => 'Millimeter (MM)',
        'KM' => 'Kilometer (KM)',
        'IN' => 'Inch (IN)',
        'FT' => 'Feet (FT)',
        'YD' => 'Yard (YD)',
    ],
    'Area' => [
        'SQFT' => 'Square Feet (SQFT)',
        'SQM' => 'Square Meter (SQM)',
        'ACRE' => 'Acre (ACRE)',
        'HC' => 'Hectare (HC)',
    ],
    'Volume' => [
        'L' => 'Liter (L)',
        'ML' => 'Milliliter (ML)',
        'GAL' => 'Gallon (GAL)',
    ],
    'Quantity' => [
        'NOS' => 'Number (NOS)',
        'PCS' => 'Piece (PCS)',
        'UNT' => 'Unit (UNT)',
        'PR' => 'Pair (PR)',
        'SET' => 'Set (SET)',
    ],
    'Packaging' => [
        'BOX' => 'Box (BOX)',
        'PKT' => 'Packet (PKT)',
        'BDL' => 'Bundle (BDL)',
        'DZN' => 'Dozen (DZN)',
        'CTN' => 'Carton (CTN)',
        'BAG' => 'Bag (BAG)',
    ],
    'Transport' => [
        'DRM' => 'Drum (DRM)',
        'ROL' => 'Roll (ROL)',
        'COL' => 'Coil (COL)',
        'CONT' => 'Container (CONT)',
        'TNK' => 'Tank (TNK)',
    ],
    'Industrial' => [
        'SHT' => 'Sheet (SHT)',
        'PLT' => 'Plate (PLT)',
        'ROD' => 'Rod (ROD)',
        'PIP' => 'Pipe (PIP)',
    ],
    'Time' => [
        'HR' => 'Hour (HR)',
        'DAY' => 'Day (DAY)',
        'MON' => 'Month (MON)',
        'YR' => 'Year (YR)',
    ],
];

// Sort categories alphabetically (A-Z)
ksort($units);

// Sort units inside each category alphabetically by value (A-Z)
foreach ($units as $category => &$categoryUnits) {
    asort($categoryUnits);
}
unset($categoryUnits);

// Flatten the categorized array for legacy config('units.types') usage
$types = [];
foreach ($units as $category => $categoryUnits) {
    foreach ($categoryUnits as $key => $label) {
        $types[$key] = $label;
    }
}

// Sort the flat list alphabetically by value (A-Z)
asort($types);

return [
    'units' => $units,
    'types' => $types,
];
