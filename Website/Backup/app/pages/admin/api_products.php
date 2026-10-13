<?php
Auth::requireAdmin();
header('Content-Type: application/json');

$categoryId = (int)get('category_id');
$categoryIndex = get('category_index', '0');

if (!$categoryId) {
    echo json_encode(['success' => false, 'error' => 'Category ID required']);
    exit;
}

$db = Database::getInstance();
$products = $db->fetchAll(
    "SELECT * FROM products WHERE category_id = ? AND available = 1 ORDER BY name",
    [$categoryId]
);

if (empty($products)) {
    echo json_encode(['success' => true, 'html' => '<div class="text-warm text-sm col-span-full">No products available in this category</div>']);
    exit;
}

$html = '';
foreach ($products as $product) {
    $html .= '<label class="flex items-center gap-2 p-2 border rounded cursor-pointer hover:bg-gray-50">';
    $html .= '<input type="checkbox" name="products[' . $categoryId . '][]" value="' . $product['id'] . '" class="w-4 h-4 accent-brown">';
    $html .= '<span class="text-sm">' . e($product['name']) . '</span>';
    $html .= '</label>';
}

echo json_encode(['success' => true, 'html' => $html]);
