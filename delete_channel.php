<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit;
}

// Check if channel_id is provided
if (!isset($_POST['channel_id']) || !is_numeric($_POST['channel_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid channel ID'
    ]);
    exit;
}

// Include database connection
require_once 'db.php';

// Get user ID and channel ID
$user_id = $_SESSION['user_id'];
$channel_id = (int)$_POST['channel_id'];

try {
    // First check if the user is the creator of the channel
    $check_query = "SELECT creator_id FROM user_management.broadcast_channels 
                   WHERE id = :channel_id";
    $stmt = $conn->prepare($check_query);
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Channel not found'
        ]);
        exit;
    }
    
    if ($channel['creator_id'] != $user_id) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'You do not have permission to delete this channel'
        ]);
        exit;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Delete channel subscribers first (to maintain referential integrity)
    $delete_subscribers_query = "DELETE FROM user_management.channel_subscribers 
                                WHERE channel_id = :channel_id";
    $stmt = $conn->prepare($delete_subscribers_query);
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Delete channel messages
    $delete_messages_query = "DELETE FROM user_management.channel_messages 
                             WHERE channel_id = :channel_id";
    $stmt = $conn->prepare($delete_messages_query);
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Finally, delete the channel itself
    $delete_channel_query = "DELETE FROM user_management.broadcast_channels 
                            WHERE id = :channel_id AND creator_id = :user_id";
    $stmt = $conn->prepare($delete_channel_query);
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Check if the channel was actually deleted
    if ($stmt->rowCount() === 0) {
        throw new Exception("Failed to delete the channel");
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Channel deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>