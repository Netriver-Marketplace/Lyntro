<?php
// Nigerian Online Marketplace - Shopping Cart System
// Secure cart management with user authentication

require_once 'config.php';

// Add item to cart
function addToCart($userId, $productId, $quantity = 1) {
    $pdo = getDBConnection();
    
    // Validate product exists and is available
    $stmt = $pdo->prepare("
        SELECT p.id, p.seller_id, p.status, p.price, p.title
        FROM products p
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        return ['success' => false, 'message' => 'Product not available'];
    }
    
    // Check if user is trying to add their own product
    if ($product['seller_id'] == $userId) {
        return ['success' => false, 'message' => 'Cannot add your own product to cart'];
    }
    
    $quantity = max(1, intval($quantity));
    
    try {
        // Check if item already in cart
        $stmt = $pdo->prepare("
            SELECT id, quantity 
            FROM cart_items 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$userId, $productId]);
        $existingItem = $stmt->fetch();
        
        if ($existingItem) {
            // Update quantity
            $newQuantity = $existingItem['quantity'] + $quantity;
            $stmt = $pdo->prepare("
                UPDATE cart_items 
                SET quantity = ? 
                WHERE id = ?
            ");
            $stmt->execute([$newQuantity, $existingItem['id']]);
        } else {
            // Add new item
            $stmt = $pdo->prepare("
                INSERT INTO cart_items (user_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $productId, $quantity]);
        }
        
        return ['success' => true, 'message' => 'Item added to cart'];
    } catch (PDOException $e) {
        error_log("Add to cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add item to cart'];
    }
}

// Get cart items
function getCartItems($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT ci.id as cart_item_id, ci.quantity,
               p.id, p.title, p.price, p.images, p.condition,
               u.username as seller_name, u.location as seller_location,
               c.name as category_name
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN users u ON p.seller_id = u.id
        JOIN categories c ON p.category_id = c.id
        WHERE ci.user_id = ? AND p.status = 'active'
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    $items = $stmt->fetchAll();
    
    // Calculate totals
    $totalItems = 0;
    $totalPrice = 0;
    
    foreach ($items as $item) {
        $totalItems += $item['quantity'];
        $totalPrice += $item['price'] * $item['quantity'];
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $item['images'] = json_decode($item['images'], true);
    }
    
    return [
        'items' => $items,
        'total_items' => $totalItems,
        'total_price' => $totalPrice,
        'item_count' => count($items)
    ];
}

// Update cart item quantity
function updateCartItem($userId, $cartItemId, $quantity) {
    $pdo = getDBConnection();
    
    $quantity = max(1, intval($quantity));
    
    // Verify cart item belongs to user
    $stmt = $pdo->prepare("
        SELECT id 
        FROM cart_items 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$cartItemId, $userId]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Cart item not found'];
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE cart_items 
            SET quantity = ? 
            WHERE id = ?
        ");
        $stmt->execute([$quantity, $cartItemId]);
        
        return ['success' => true, 'message' => 'Cart updated'];
    } catch (PDOException $e) {
        error_log("Update cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update cart'];
    }
}

// Remove item from cart
function removeFromCart($userId, $cartItemId) {
    $pdo = getDBConnection();
    
    // Verify cart item belongs to user
    $stmt = $pdo->prepare("
        SELECT id 
        FROM cart_items 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$cartItemId, $userId]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Cart item not found'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$cartItemId]);
        
        return ['success' => true, 'message' => 'Item removed from cart'];
    } catch (PDOException $e) {
        error_log("Remove from cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to remove item'];
    }
}

// Clear cart
function clearCart($userId) {
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        return ['success' => true, 'message' => 'Cart cleared'];
    } catch (PDOException $e) {
        error_log("Clear cart error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to clear cart'];
    }
}

// Create order from cart
function createOrder($userId, $cartItemIds, $shippingAddress, $paymentMethod) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $totalPrice = 0;
        $orders = [];
        
        foreach ($cartItemIds as $cartItemId) {
            // Get cart item details
            $stmt = $pdo->prepare("
                SELECT ci.quantity, ci.product_id, p.price, p.seller_id, p.title
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.id = ? AND ci.user_id = ?
            ");
            $stmt->execute([$cartItemId, $userId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception('Cart item not found');
            }
            
            $itemTotal = $item['price'] * $item['quantity'];
            $totalPrice += $itemTotal;
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (buyer_id, seller_id, product_id, quantity, total_price, 
                                   shipping_address, payment_method, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')
            ");
            $stmt->execute([
                $userId, $item['seller_id'], $item['product_id'], 
                $item['quantity'], $itemTotal, $shippingAddress, $paymentMethod
            ]);
            
            $orders[] = [
                'order_id' => $pdo->lastInsertId(),
                'product_title' => $item['title'],
                'quantity' => $item['quantity'],
                'price' => $itemTotal
            ];
        }
        
        // Remove items from cart
        $placeholders = str_repeat('?,', count($cartItemIds) - 1) . '?';
        $stmt = $pdo->prepare("
            DELETE FROM cart_items 
            WHERE id IN ($placeholders) AND user_id = ?
        ");
        $stmt->execute(array_merge($cartItemIds, [$userId]));
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'Order created successfully',
            'orders' => $orders,
            'total_price' => $totalPrice
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create order error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create order'];
    }
}

// Get user orders
function getUserOrders($userId, $status = null) {
    $pdo = getDBConnection();
    
    $params = [$userId];
    $sql = "
        SELECT o.*, p.title as product_title, p.images, p.condition,
               u_s.username as seller_name, u_s.location as seller_location,
               c.name as category_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u_s ON o.seller_id = u_s.id
        JOIN categories c ON p.category_id = c.id
        WHERE o.buyer_id = ?
    ";
    
    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $orders = $stmt->fetchAll();
    
    foreach ($orders as &$order) {
        $order['images'] = json_decode($order['images'], true);
    }
    
    return $orders;
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    
    switch ($action) {
        case 'get':
            $cart = getCartItems($_SESSION['user_id']);
            jsonResponse(['success' => true, 'cart' => $cart]);
            break;
            
        case 'orders':
            $status = $_GET['status'] ?? null;
            $orders = getUserOrders($_SESSION['user_id'], $status);
            jsonResponse(['success' => true, 'orders' => $orders]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    switch ($action) {
        case 'add':
            $result = addToCart(
                $_SESSION['user_id'],
                $_POST['product_id'],
                $_POST['quantity'] ?? 1
            );
            jsonResponse($result);
            break;
            
        case 'update':
            $result = updateCartItem(
                $_SESSION['user_id'],
                $_POST['cart_item_id'],
                $_POST['quantity']
            );
            jsonResponse($result);
            break;
            
        case 'remove':
            $result = removeFromCart($_SESSION['user_id'], $_POST['cart_item_id']);
            jsonResponse($result);
            break;
            
        case 'clear':
            $result = clearCart($_SESSION['user_id']);
            jsonResponse($result);
            break;
            
        case 'checkout':
            $cartItemIds = $_POST['cart_item_ids'] ?? [];
            $shippingAddress = sanitizeInput($_POST['shipping_address'] ?? '');
            $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
            
            if (empty($cartItemIds) || empty($shippingAddress)) {
                jsonResponse(['success' => false, 'message' => 'Missing required information']);
            }
            
            $result = createOrder($_SESSION['user_id'], $cartItemIds, $shippingAddress, $paymentMethod);
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}
?>