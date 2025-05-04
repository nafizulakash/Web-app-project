<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in']);
    exit;
}

// Include database connection
require_once 'db.php';

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// Check if action is set
if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit;
}

// Process different actions
switch ($_POST['action']) {
    case 'send_message':
        sendMessage($conn, $user_id);
        break;
    case 'edit_message':
        editMessage($conn, $user_id);
        break;
    case 'delete_message':
        deleteMessage($conn, $user_id);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

// Function to send a new message
function sendMessage($conn, $user_id) {
    // Validate input
    if (!isset($_POST['channel_id']) || !isset($_POST['message']) || empty(trim($_POST['message']))) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    $channel_id = (int)$_POST['channel_id'];
    $message = trim($_POST['message']);
    
    try {
        // Check if the user is the creator of the channel
        $stmt = $conn->prepare("SELECT creator_id FROM user_management.broadcast_channels WHERE id = :channel_id");
        $stmt->execute([':channel_id' => $channel_id]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$channel || $channel['creator_id'] != $user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Only the channel creator can send messages']);
            return;
        }
        
        // Insert the message
        $stmt = $conn->prepare("INSERT INTO user_management.broadcast_messages 
                               (channel_id, sender_id, message) 
                               VALUES (:channel_id, :sender_id, :message) 
                               RETURNING id");
        $stmt->execute([
            ':channel_id' => $channel_id,
            ':sender_id' => $user_id,
            ':message' => $message
        ]);
        
        // Get the inserted ID directly using PostgreSQL's RETURNING clause
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'message_id' => $result['id']
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to edit a message
function editMessage($conn, $user_id) {
    // Validate input
    if (!isset($_POST['message_id']) || !isset($_POST['message']) || empty(trim($_POST['message']))) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    $message_id = (int)$_POST['message_id'];
    $message = trim($_POST['message']);
    
    try {
        // Check if the message exists and belongs to the user
        $stmt = $conn->prepare("SELECT * FROM user_management.broadcast_messages 
                               WHERE id = :message_id AND sender_id = :sender_id");
        $stmt->execute([':message_id' => $message_id, ':sender_id' => $user_id]);
        $existingMessage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingMessage) {
            echo json_encode(['status' => 'error', 'message' => 'Message not found or you are not the sender']);
            return;
        }
        
        // Update the message
        $stmt = $conn->prepare("UPDATE user_management.broadcast_messages 
                               SET message = :message, updated_at = CURRENT_TIMESTAMP, is_edited = TRUE 
                               WHERE id = :message_id");
        $stmt->execute([':message' => $message, ':message_id' => $message_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Message updated successfully']);
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to delete a message
function deleteMessage($conn, $user_id) {
    // Validate input
    if (!isset($_POST['message_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing message ID']);
        return;
    }
    
    $message_id = (int)$_POST['message_id'];
    
    try {
        // Check if the message exists and belongs to the user
        $stmt = $conn->prepare("SELECT * FROM user_management.broadcast_messages 
                               WHERE id = :message_id AND sender_id = :sender_id");
        $stmt->execute([':message_id' => $message_id, ':sender_id' => $user_id]);
        $existingMessage = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingMessage) {
            echo json_encode(['status' => 'error', 'message' => 'Message not found or you are not the sender']);
            return;
        }
        
        // Delete the message
        $stmt = $conn->prepare("DELETE FROM user_management.broadcast_messages WHERE id = :message_id");
        $stmt->execute([':message_id' => $message_id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Message deleted successfully']);
        
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>