<?php
session_start();
require_once 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Validate form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $creator_id = $_SESSION['user_id'];
    
    // Validate channel name
    if (empty($name)) {
        header("Location: create_channel.html?error=Channel name cannot be empty");
        exit;
    }
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert into broadcast_channels table
        $stmt = $conn->prepare("INSERT INTO user_management.broadcast_channels (name, description, creator_id) VALUES (:name, :description, :creator_id) RETURNING id");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':creator_id' => $creator_id
        ]);
        
        // Get the new channel ID (PostgreSQL uses RETURNING)
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $channel_id = $result['id'];
        
        // Add creator as a channel member
        $stmt = $conn->prepare("INSERT INTO user_management.channel_members (channel_id, user_id) VALUES (:channel_id, :user_id)");
        $stmt->execute([
            ':channel_id' => $channel_id,
            ':user_id' => $creator_id
        ]);
        
        // Commit transaction
        $conn->commit();
        
        // Redirect with success message
        header("Location: create_channel.html?success=1");
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        header("Location: create_channel.html?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: create_channel.html");
    exit;
}
?>