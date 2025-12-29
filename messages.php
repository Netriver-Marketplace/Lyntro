<?php
// Nigerian Online Marketplace - Messaging System
// Secure buyer-seller communication

require_once 'config.php';

// Send message
function sendMessage($senderId, $receiverId, $message, $productId = null) {
    $pdo = getDBConnection();
    
    // Validate inputs
    $message = sanitizeInput($message);
    $message = trim($message);
    
    if (empty($message)) {
        return ['success' => false, 'message' => 'Message cannot be empty'];
    }
    
    if ($senderId == $receiverId) {
        return ['success' => false, 'message' => 'Cannot send message to yourself'];
    }
    
    // Verify receiver exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$receiverId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Receiver not found'];
    }
    
    // If product is specified, verify it exists
    if ($productId) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => 'Product not found'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, product_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$senderId, $receiverId, $productId, $message]);
        
        return ['success' => true, 'message' => 'Message sent successfully'];
    } catch (PDOException $e) {
        error_log("Send message error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send message'];
    }
}

// Get conversation between two users
function getConversation($userId, $otherUserId, $productId = null, $page = 1) {
    $pdo = getDBConnection();
    
    $offset = ($page - 1) * MESSAGES_PER_PAGE;
    $params = [$userId, $otherUserId];
    
    $sql = "
        SELECT m.*, 
               u_s.username as sender_name, u_s.profile_image as sender_image,
               u_r.username as receiver_name,
               p.title as product_title, p.images as product_images
        FROM messages m
        JOIN users u_s ON m.sender_id = u_s.id
        JOIN users u_r ON m.receiver_id = u_r.id
        LEFT JOIN products p ON m.product_id = p.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR 
               (m.sender_id = ? AND m.receiver_id = ?))
    ";
    
    if ($productId) {
        $sql .= " AND m.product_id = ?";
        $params[] = $productId;
    }
    
    $params = array_merge($params, [$userId, $otherUserId]);
    
    $sql .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
    $params[] = MESSAGES_PER_PAGE;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $markReadSql = "
        UPDATE messages 
        SET is_read = TRUE 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
    ";
    if ($productId) {
        $markReadSql .= " AND product_id = ?";
        $pdo->prepare($markReadSql)->execute([$userId, $otherUserId, $productId]);
    } else {
        $pdo->prepare($markReadSql)->execute([$userId, $otherUserId]);
    }
    
    // Reverse messages to show in chronological order
    $messages = array_reverse($messages);
    
    return $messages;
}

// Get user's conversations (list of people they've talked to)
function getConversations($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.username, u.full_name, u.profile_image,
            MAX(m.created_at) as last_message_time,
            (SELECT COUNT(*) FROM messages 
             WHERE ((sender_id = ? AND receiver_id = other_user_id) OR 
                    (sender_id = other_user_id AND receiver_id = ?)) 
             AND is_read = FALSE 
             AND sender_id != ?) as unread_count,
            p.title as last_product_title,
            p.id as last_product_id
        FROM messages m
        JOIN users u ON CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END = u.id
        LEFT JOIN products p ON m.product_id = p.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_user_id
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    
    return $stmt->fetchAll();
}

// Get unread message count
function getUnreadCount($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetch()['count'];
}

// Mark conversation as read
function markConversationRead($userId, $otherUserId) {
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId, $otherUserId]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("Mark as read error: " . $e->getMessage());
        return ['success' => false];
    }
}

// Delete message
function deleteMessage($messageId, $userId) {
    $pdo = getDBConnection();
    
    // Verify message belongs to user (either sender or receiver)
    $stmt = $pdo->prepare("
        SELECT id 
        FROM messages 
        WHERE id = ? AND (sender_id = ? OR receiver_id = ?)
    ");
    $stmt->execute([$messageId, $userId, $userId]);
    
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Message not found or access denied'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$messageId]);
        
        return ['success' => true, 'message' => 'Message deleted'];
    } catch (PDOException $e) {
        error_log("Delete message error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete message'];
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    
    switch ($action) {
        case 'conversations':
            $conversations = getConversations($_SESSION['user_id']);
            jsonResponse(['success' => true, 'conversations' => $conversations]);
            break;
            
        case 'conversation':
            $otherUserId = intval($_GET['user_id'] ?? 0);
            $productId = $_GET['product_id'] ?? null;
            $page = intval($_GET['page'] ?? 1);
            
            if (!$otherUserId) {
                jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
            }
            
            $messages = getConversation($_SESSION['user_id'], $otherUserId, $productId, $page);
            jsonResponse(['success' => true, 'messages' => $messages]);
            break;
            
        case 'unread_count':
            $count = getUnreadCount($_SESSION['user_id']);
            jsonResponse(['success' => true, 'count' => $count]);
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
        case 'send':
            $result = sendMessage(
                $_SESSION['user_id'],
                $_POST['receiver_id'],
                $_POST['message'],
                $_POST['product_id'] ?? null
            );
            jsonResponse($result);
            break;
            
        case 'mark_read':
            $result = markConversationRead(
                $_SESSION['user_id'],
                $_POST['other_user_id']
            );
            jsonResponse($result);
            break;
            
        case 'delete':
            $result = deleteMessage(
                $_POST['message_id'],
                $_SESSION['user_id']
            );
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}
?>