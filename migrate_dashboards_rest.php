<?php
// Script to help migrate the remaining admin dashboard sections

$adminFile = 'templates/dashboard/admin.html.twig';
$content = file_get_contents($adminFile);

// Convert the Admin Actions & Recent Activity grid
$content = str_replace(
    '    {# Admin Actions & Recent Activity #}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">',
    '    {# Admin Actions & Recent Activity #}
    <div class="row g-4">',
    $content
);

// The rest needs manual editing due to complexity
echo "Partial migration done. Manual editing required for complex nested structures.\n";
file_put_contents($adminFile, $content);
