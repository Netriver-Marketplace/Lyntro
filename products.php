<?php
// Nigerian Online Marketplace - Products Management
// Secure product operations with SQL injection protection

require_once 'config.php';

// Add new product
function addProduct($sellerId, $categoryId, $title, $description, $price, $negotiable, $condition, $location, $images) {
    $pdo = getDBConnection();
    
    // Validate inputs
    $title = sanitizeInput($title);
    $description = sanitizeInput($description);
    $price = floatval($price);
    $negotiable = filter_var($negotiable, FILTER_VALIDATE_BOOLEAN);
    $condition = in_array($condition, ['new', 'used', 'refurbished']) ? $condition : 'used';
    $location = sanitizeInput($location);
    
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than 0'];
    }
    
    // Validate category exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Invalid category'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (seller_id, category_id, title, description, price, negotiable, condition, location, images) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sellerId, $categoryId, $title, $description, $price, 
            $negotiable, $condition, $location, json_encode($images)
        ]);
        
        return ['success' => true, 'message' => 'Product added successfully', 'product_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("Add product error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add product'];
    }
}

// Get all products with filters
function getProducts($page = 1, $categoryId = null, $search = null, $location = null, $sort = 'newest', $minPrice = null, $maxPrice = null) {
    $pdo = getDBConnection();
    
    $offset = ($page - 1) * PRODUCTS_PER_PAGE;
    $params = [];
    $whereConditions = ['p.status = "active"'];
    
    // Build WHERE clause
    if ($categoryId) {
        $whereConditions[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }
    
    if ($search) {
        $whereConditions[] = '(p.title LIKE ? OR p.description LIKE ?)';
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if ($location) {
        $whereConditions[] = 'p.location LIKE ?';
        $params[] = "%$location%";
    }
    
    if ($minPrice !== null) {
        $whereConditions[] = 'p.price >= ?';
        $params[] = $minPrice;
    }
    
    if ($maxPrice !== null) {
        $whereConditions[] = 'p.price <= ?';
        $params[] = $maxPrice;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Build ORDER BY clause
    $orderBy = 'p.created_at DESC';
    switch ($sort) {
        case 'price_low':
            $orderBy = 'p.price ASC';
            break;
        case 'price_high':
            $orderBy = 'p.price DESC';
            break;
        case 'popular':
            $orderBy = 'p.views DESC';
            break;
        case 'rating':
            $orderBy = 'u.rating DESC';
            break;
    }
    
    // Get products
    $sql = "
        SELECT p.*, u.username, u.full_name, u.rating as seller_rating, c.name as category_name,
               (SELECT COUNT(*) FROM favorites WHERE product_id = p.id) as favorite_count
        FROM products p
        JOIN users u ON p.seller_id = u.id
        JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";
    
    $params[] = PRODUCTS_PER_PAGE;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM products p WHERE $whereClause";
    $countParams = array_slice($params, 0, -2);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetch()['total'];
    
    return [
        'products' => $products,
        'total' => $total,
        'pages' => ceil($total / PRODUCTS_PER_PAGE),
        'current_page' => $page
    ];
}

// Get single product details
function getProduct($productId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.full_name, u.phone, u.location as seller_location, 
               u.rating as seller_rating, u.total_reviews, u.profile_image, 
               c.name as category_name
        FROM products p
        JOIN users u ON p.seller_id = u.id
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if ($product) {
        // Increment view count
        $updateStmt = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
        $updateStmt->execute([$productId]);
        
        // Get product reviews
        $reviewStmt = $pdo->prepare("
            SELECT r.*, u.username, u.profile_image 
            FROM reviews r
            JOIN users u ON r.reviewer_id = u.id
            WHERE r.product_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $reviewStmt->execute([$productId]);
        $product['reviews'] = $reviewStmt->fetchAll();
    }
    
    return $product;
}

// Get user's products
function getUserProducts($userId, $status = 'active') {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM orders WHERE product_id = p.id) as sales_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ? AND p.status = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$userId, $status]);
    
    return $stmt->fetchAll();
}

// Update product
function updateProduct($productId, $sellerId, $title, $description, $price, $negotiable, $condition, $location, $status) {
    $pdo = getDBConnection();
    
    // Verify product belongs to seller
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$productId, $sellerId]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Product not found or access denied'];
    }
    
    // Validate inputs
    $title = sanitizeInput($title);
    $description = sanitizeInput($description);
    $price = floatval($price);
    $negotiable = filter_var($negotiable, FILTER_VALIDATE_BOOLEAN);
    $condition = in_array($condition, ['new', 'used', 'refurbished']) ? $condition : 'used';
    $location = sanitizeInput($location);
    $status = in_array($status, ['active', 'sold', 'inactive']) ? $status : 'active';
    
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than 0'];
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET title = ?, description = ?, price = ?, negotiable = ?, condition = ?, location = ?, status = ?
            WHERE id = ? AND seller_id = ?
        ");
        $stmt->execute([$title, $description, $price, $negotiable, $condition, $location, $status, $productId, $sellerId]);
        
        return ['success' => true, 'message' => 'Product updated successfully'];
    } catch (PDOException $e) {
        error_log("Update product error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update product'];
    }
}

// Delete product
function deleteProduct($productId, $sellerId) {
    $pdo = getDBConnection();
    
    // Verify product belongs to seller
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$productId, $sellerId]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Product not found or access denied'];
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$productId]);
        
        return ['success' => true, 'message' => 'Product deleted successfully'];
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete product'];
    }
}

// Get categories
function getCategories() {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count
        FROM categories c
        ORDER BY c.name
    ");
    
    return $stmt->fetchAll();
}

// Get featured products
function getFeaturedProducts($limit = 8) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.rating as seller_rating, c.name as category_name
        FROM products p
        JOIN users u ON p.seller_id = u.id
        JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND (p.featured = TRUE OR p.views > 100)
        ORDER BY p.views DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'list':
            $page = intval($_GET['page'] ?? 1);
            $categoryId = $_GET['category_id'] ?? null;
            $search = $_GET['search'] ?? null;
            $location = $_GET['location'] ?? null;
            $sort = $_GET['sort'] ?? 'newest';
            $minPrice = $_GET['min_price'] ?? null;
            $maxPrice = $_GET['max_price'] ?? null;
            
            $result = getProducts($page, $categoryId, $search, $location, $sort, $minPrice, $maxPrice);
            jsonResponse($result);
            break;
            
        case 'detail':
            $productId = intval($_GET['id'] ?? 0);
            $product = getProduct($productId);
            
            if (!$product) {
                jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
            }
            
            jsonResponse(['success' => true, 'product' => $product]);
            break;
            
        case 'my_products':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            $status = $_GET['status'] ?? 'active';
            $products = getUserProducts($_SESSION['user_id'], $status);
            jsonResponse(['success' => true, 'products' => $products]);
            break;
            
        case 'categories':
            $categories = getCategories();
            jsonResponse(['success' => true, 'categories' => $categories]);
            break;
            
        case 'featured':
            $limit = intval($_GET['limit'] ?? 8);
            $products = getFeaturedProducts($limit);
            jsonResponse(['success' => true, 'products' => $products]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    switch ($action) {
        case 'add':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            
            $user = getCurrentUser();
            if (!in_array($user['user_type'], ['seller', 'both'])) {
                jsonResponse(['success' => false, 'message' => 'Not authorized to add products'], 403);
            }
            
            // Handle image uploads
            $images = [];
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                foreach ($_FILES['images']['name'] as $key => $name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['images']['tmp_name'][$key];
                        $fileType = $_FILES['images']['type'][$key];
                        
                        if (in_array($fileType, ALLOWED_IMAGE_TYPES)) {
                            $extension = pathinfo($name, PATHINFO_EXTENSION);
                            $fileName = uniqid('product_', true) . '.' . $extension;
                            $filePath = UPLOAD_PATH . 'products/' . $fileName;
                            
                            if (move_uploaded_file($tmpName, $filePath)) {
                                $images[] = 'uploads/products/' . $fileName;
                            }
                        }
                    }
                }
            }
            
            $result = addProduct(
                $_SESSION['user_id'],
                $_POST['category_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['price'],
                $_POST['negotiable'] ?? true,
                $_POST['condition'] ?? 'used',
                $_POST['location'] ?? $user['location'],
                $images
            );
            jsonResponse($result);
            break;
            
        case 'update':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            
            $result = updateProduct(
                $_POST['product_id'],
                $_SESSION['user_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['price'],
                $_POST['negotiable'] ?? true,
                $_POST['condition'] ?? 'used',
                $_POST['location'],
                $_POST['status'] ?? 'active'
            );
            jsonResponse($result);
            break;
            
        case 'delete':
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
            }
            
            $result = deleteProduct($_POST['product_id'], $_SESSION['user_id']);
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}
?>